<?php

declare(strict_types=1);
require_once __DIR__.'/config/config.php';

$migrated = [];
$migrated['staff_count'] = 0;
$migrated['client_count'] = 0;
$matchingClientIds = [];

try {
    $otrsPDO = new PDO($sourceDSN, $sourceUser, $sourcePass);
    $otrsPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $osticketPDO = new PDO($destinationDSN, $destinationUser, $destinationPass);
    $osticketPDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if ($doMigrateStaff) {
        $staff = migrateStaff($otrsPDO, $osticketPDO, $migrated);
        echo "Done with staff's migration.".PHP_EOL;
    }
    if ($doMigrateCustomers) {
        $staff = migrateClients($otrsPDO, $osticketPDO, $migrated, $matchingClientIds);
        echo "Done with users' migration.".PHP_EOL;
    }
} catch (PDOException $e) {
    // If there is an error, catch and print it
    echo 'Database error: '.$e->getMessage().PHP_EOL;
} catch (Exception $e) {
    // Catch any other exceptions
    echo 'General error: '.$e->getMessage().PHP_EOL;
}
echo "Done. Migrated ".$migrated['staff_count']." staff users and ".$migrated['client_count']." customers (blocking ".$migrated['client_duplicates']." duplicates).".PHP_EOL;

/**
 * Migrate the staff from otrs.users to osticket.ost_staff
 * @param PDO $otrsPDO
 * @param PDO $osticketPDO
 * @param     $migrated
 * @return bool
 */
function migrateStaff(PDO $otrsPDO, PDO $osticketPDO, &$migrated): bool
{
    // Fetch users from the OTRS database
    // Take 2 assumptions:
    // 1) there exists a 'UserEmail' field in user_preferences
    // 2) admins are identified by group_user.group_id = 2
    $stmt = $otrsPDO->prepare(
        'SELECT DISTINCT u.id, u.login, u.pw, u.first_name, u.last_name, up.preferences_value email 
                    FROM users u
                    INNER JOIN user_preferences up ON u.id = up.user_id AND up.preferences_key = "UserEmail"
                    INNER JOIN group_user gu ON u.id = gu.user_id AND gu.group_id = 2
                    WHERE u.valid_id = 1'
    );
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare an insert statement to copy the user
    $insertStmt = $osticketPDO->prepare('
                INSERT INTO ost_staff (username, passwd, firstname, lastname, email, dept_id, role_id, isactive, isadmin, isvisible, default_signature_type, default_paper_size, extra, permissions, signature, created, lastlogin, passwdreset, updated)
                VALUES (:login, :passwd, :firstname, :lastname, :email, :dept_id, :role_id, :isactive, :isadmin, :isvisible, :signature, :papersize, :extra, :permissions, "", :now1, :now2, :now3, :now4)
                ');

    // Insert staff users into the OsTicket database
    // Other assumptions:
    // 1) we only need to migrate people to the "support" team, which has id 1 in ost_department
    // 2) we give them limited access by default, which is role_id 3 in ost_role
    foreach ($results as $otrsUser) {
        // Check if the user was found
        if (!empty($otrsUser)) {
            global $defaultStaffDepartmentId, $defaultStaffRoleId, $defaultStaffLanguage, $defaultStaffPermissions;
            $default = 1;
            $none = 'none';
            $letter = 'Letter';
            $now1 = $now2 = $now3 = $now4 = date('Y-m-d h:i:s');
            // Bind the user data to the insert statement parameters
            $insertStmt->bindParam(':login', $otrsUser['login'], PDO::PARAM_STR);
            $insertStmt->bindParam(':passwd', $otrsUser['pw'], PDO::PARAM_STR);
            $insertStmt->bindParam(':firstname', $otrsUser['first_name'], PDO::PARAM_STR);
            $insertStmt->bindParam(':lastname', $otrsUser['last_name'], PDO::PARAM_STR);
            $insertStmt->bindParam(':email', $otrsUser['email'], PDO::PARAM_STR);
            $insertStmt->bindParam(':dept_id', $defaultStaffDepartmentId, PDO::PARAM_INT);
            $insertStmt->bindParam(':role_id', $defaultStaffRoleId, PDO::PARAM_INT);
            $insertStmt->bindParam(':isactive', $default, PDO::PARAM_INT);
            $insertStmt->bindParam(':isadmin', $default, PDO::PARAM_INT);
            $insertStmt->bindParam(':isvisible', $default, PDO::PARAM_INT);
            $insertStmt->bindParam(':signature', $none, PDO::PARAM_STR);
            $insertStmt->bindParam(':papersize', $letter, PDO::PARAM_STR);
            $insertStmt->bindParam(':extra', $defaultStaffLanguage, PDO::PARAM_STR);
            $insertStmt->bindParam(':permissions', $defaultStaffPermissions, PDO::PARAM_STR);
            $insertStmt->bindParam(':now1', $now1, PDO::PARAM_STR);
            $insertStmt->bindParam(':now2', $now2, PDO::PARAM_STR);
            $insertStmt->bindParam(':now3', $now3, PDO::PARAM_STR);
            $insertStmt->bindParam(':now4', $now4, PDO::PARAM_STR);

            // Execute the insert statement
            $insertStmt->execute();

            // Fetch the last inserted ID
            $newUserId = $osticketPDO->lastInsertId();
            $migrated['staff_count']++;
        } else {
            echo "Empty user record in ".__FILE__." at line ".__LINE__.PHP_EOL;
        }
    }

    return true;
}

/**
 * Migrate the clients from otrs.customer_user to osticket.ost_user
 * @param PDO $otrsPDO
 * @param PDO $osticketPDO
 * @param array $migrated
 * @param array $matchingClientIds
 * @return bool
 */
function migrateClients(PDO $otrsPDO, PDO $osticketPDO, &$migrated, &$matchingClientIds): bool
{
    // Migrate clients from the "customer_user" and "customer_preferences" tables
    // We only need:
    // - title ('mr','mme', 'mlle', 'autre)
    // - firstname (customer_user.first_name)
    // - lastname (customer_user.last_name)
    // - client number (customer_user.customer_id)
    // - phone number (customer_user.telephonenumber or telephonenumber2)
    // - postcode (customer_user.postcode)
    // - city (customer_number.city)
    // - street/address (customer_number.street)
    // - tag
    // - recommendedby
    // Avoid duplicates by comparing lastname+firstname+phonenumber
    $stmt = $otrsPDO->prepare(
        'SELECT u.id, u.login, u.pw, u.title, u.first_name, u.last_name, u.email, u.customer_id, u.telephonenumber, u.telephonenumber2, u.postcode, u.city, u.street, p.preferences_value as language
                    FROM customer_user u LEFT JOIN customer_preferences p ON u.login = p.user_id AND p.preferences_key = "UserLanguage"
                    WHERE u.customer_id != ""
                    ORDER BY u.customer_id
                    '
    );
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $processedClients = [];

    // Prepare an insert statement to create the user
    // OsTicket (in class.user.php::User::fromVars() only requires name, created, updated and default_email_id fields. org_id can be 1. Status can be 0.
    // There are 3 destinations for user data from OTRS to put in OsTicket:
    // 1. ost_user: org_id(0), status (0), name (lastname or fullname), created (date), updated() and default_email_id which can be "2" initially
    // 2. ost_user__cdata has to be prepared by creating the necessary complementary fields in Admin>Manage>Forms>User details. It will then store:
    //    clientnum, firstname, mobilephone, landphone, address (street), postcode, city, lang (in format "[id],[language name translated]"), email2, tag, recommededby
    // 3. ost_user_email: user_id (ref to ost_user.id), flags (0) and address (email)
    $insertUserStmt = $osticketPDO->prepare('
        INSERT INTO ost_user (org_id, default_email_id, status, name, created, updated)
        VALUES (1, 1, 0, :name, :now1, :now2)
    ');
    $insertUserDataStmt = $osticketPDO->prepare('
        INSERT INTO ost_user__cdata(user_id, clientnum, firstname, mobilephone, landphone, address, postcode, city, lang, tag, recommendedby, title)
        VALUES (:userId, :clientId, :firstname, :mobilephone, :landphone, :address, :postcode, :city, :lang, :tag, :recommendedby, :title)
    ');
    $selectUserMailStmt = $osticketPDO->prepare('
        SELECT user_id FROM ost_user_email WHERE address = :email
    ');
    $insertUserMailStmt = $osticketPDO->prepare('
        INSERT INTO ost_user_email(user_id, flags, address)
        VALUES (:userId, 0, :email)
    ');
    // Update ost_user with the new e-mail
    $updateUserMailStmt = $osticketPDO->prepare('
        UPDATE ost_user SET default_email_id = :mailId WHERE id = :userId
    ');

    // Insert into ost_form_entry and ost_form_entry_values to fool OsTicket
    $insertFormEntryStmt = $osticketPDO->prepare('
        INSERT INTO ost_form_entry(form_id, object_id, object_type, sort, extra, created, updated)
        VALUES (1, :userId, "U", 1, NULL, :now1, :now2)
    ');
    $insertFormEntryValuesStmt = $osticketPDO->prepare('
        INSERT INTO ost_form_entry_values(entry_id, field_id, value, value_id)
        VALUES (:entryId, :fieldId, :fieldValue, NULL)
    ');
    global $fieldsList;
    $reversedFieldsList = array_flip($fieldsList);
    global $langTranslation;
    global $langTranslationTerms;
    global $titleTranslation;

    $mailDuplicates = [];
    $migrated['client_duplicates'] = 0;
    $defaultLanguage = 'fr';

    // Insert staff users into the OsTicket database
    // Other assumptions:
    // 1) we only need to migrate people to the "support" team, which has id 1 in ost_department
    // 2) we give them limited access by default, which is role_id 3 in ost_role
    foreach ($results as $otrsUser) {
        /*
        // Testing
        if ($migrated['client_count'] > 10) {
            break;
        }
        */
        // Check if the user was found
        if (!empty($otrsUser)) {
            // Sanitize
            $internalId = (int) $otrsUser['id'];
            $clientId = trim($otrsUser['customer_id'], "\t\n\r");
            //echo "Client ".$clientId.PHP_EOL;
            $firstName = trim($otrsUser['first_name']);
            $lastName = trim($otrsUser['last_name']);
            //$name = $firstName.' '.$lastName;
            $postCode = trim($otrsUser['postcode'], "\t\n\r");
            if (empty($postCode)) {
                $postCode = '0000';
            }
            $city = !empty($otrsUser['city']) ? trim($otrsUser['city']) : '';
            $street = !empty($otrsUser['street']) ? trim($otrsUser['street'], "\t\n\r") : '';
            $phone1 = !empty($otrsUser['telephonenumber']) ? trim($otrsUser['telephonenumber']) : '';
            $phone2 = !empty($otrsUser['telephonenumber2']) ? trim($otrsUser['telephonenumber2']) : '';
            $tag = '';
            $recommendedBy = '';
            $title = !empty($otrsUser['title']) ? trim($otrsUser['title']) : '-';
            switch ($title) {
                case 'M.':
                case 'M':
                case 'Monsieur':
                    $title = 'Mr';
                    break;
                case 'Madame':
                case 'Madame etMonsieur':
                    $title = 'Mme';
                    break;
                default:
                    $title = '-';
                    break;
            }
            if (empty($title) or empty($titleTranslation[$title])) {
                //echo "Found title ".$title." in dataset.".PHP_EOL;
                $titleId = $titleTranslation['-'];
            } else {
                $titleId = $titleTranslation[$title];
            }

            // Compose the email using the clientId and '@example.com'
            if (!empty($otrsUser['email'])) {
                $emailTemp = trim($otrsUser['email']);
                if ('personne@gazelec.info' === $emailTemp) {
                    $email = $clientId.'@example.com';
                } else {
                    $email = $emailTemp;
                }
            } else {
                $email = $clientId.'@example.com';
            }
            // Mail duplicates check
            $originalMail = true;
            if (!empty($mailDuplicates[$email])) {
                // This e-mail is already in use. Add a number to the list
                $num = $mailDuplicates[$email]++;
                $matches = preg_split('/@/', $email);
                $email = $matches[0].$num."@".$matches[1];
                $originalMail = false;
            } else {
                $mailDuplicates[$email] = 1;
            }
            //echo "Email will be ".$email.PHP_EOL;

            // Check for duplicates
            $matchLastName = strtolower($lastName);
            $matchFirstName = strtolower($firstName);
            $matchPhone1 = strtolower($phone1);
            if (!empty($processedClients[$matchLastName])
                && !empty($processedClients[$matchLastName][$matchFirstName]) && !empty($processedClients[$matchLastName][$matchFirstName][$matchPhone1])) {
                // This is a duplicate, write down and skip
                $matchingClientIds[$processedClients[$matchLastName][$matchFirstName][$matchPhone1]][] = $internalId;
                $migrated['client_duplicates']++;
                continue; //skip the rest of the loop for now
            } else {
                $processedClients[$matchLastName][$matchFirstName][$matchPhone1] = $internalId;
            }

            $now1 = $now2 = date('Y-m-d h:i:s');
            // Bind the user data to the insert statement parameters
            $insertUserStmt->bindParam(':name', $lastName, PDO::PARAM_STR);
            $insertUserStmt->bindParam(':now1', $now1, PDO::PARAM_STR);
            $insertUserStmt->bindParam(':now2', $now2, PDO::PARAM_STR);
            // Execute the insert statement
            $insertUserStmt->execute();
            //echo "ost_user OK".PHP_EOL;

            // Fetch the last inserted ID
            $newUserId = $osticketPDO->lastInsertId();

            if (empty($otrsUser['language'])) {
                $otrsUser['language'] = $defaultLanguage;
            }
            $lang = '';
            if (!empty($otrsUser['language']) && !empty($langTranslation[$otrsUser['language']])) {
                $lang = $langTranslation[$otrsUser['language']];
                $langTerm = $langTranslationTerms[$otrsUser['language']];
            }
            if (empty($lang)) {
                $lang = $langTranslation['-'];
                $langTerm = $langTranslationTerms['-'];
            }

            $userFields = [
                'clientnum' => $clientId,
                'title' => '{"'.$titleId.'":"'.$title.'"}',
                'firstname' => $firstName,
                'mobilephone' => $phone2,
                'landphone' => $phone1,
                'address' => $street,
                'postcode' => $postCode,
                'city' => $city,
                'lang' => '{"'.$lang.'":"'.$langTerm.'"}',
                'email2' => '',
                'tag' => $tag,
                'recommendedby' => $recommendedBy,
            ];

            // Insert new record into ost_user__cdata
            $insertUserDataStmt->bindParam(':userId', $newUserId, PDO::PARAM_INT);
            $insertUserDataStmt->bindParam(':clientId', $clientId, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':firstname', $firstName, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':mobilephone', $phone2, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':landphone', $phone1, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':address', $street, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':postcode', $postCode, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':city', $city, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':lang', $lang, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':tag', $tag, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':recommendedby', $recommendedBy, PDO::PARAM_STR);
            $insertUserDataStmt->bindParam(':title', $titleId, PDO::PARAM_STR);
            $insertUserDataStmt->execute();
            //echo "ost_user__cdata OK".PHP_EOL;

            // Check if the user with this e-mail doesn't exist already
            if ($originalMail) {
                $selectUserMailStmt->bindParam(':email', $email, PDO::PARAM_STR);
                $selectUserMailStmt->execute();
                $results = $selectUserMailStmt->rowCount();
                if ($results > 0) {
                    $num = $mailDuplicates[$email]++;
                    $matches = preg_split('/@/', $email);
                    $email = $matches[0].$num."@".$matches[1];
                }
            }
            // Insert record in ost_user_email
            $insertUserMailStmt->bindParam(':userId', $newUserId, PDO::PARAM_INT);
            $insertUserMailStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $insertUserMailStmt->execute();
            $newMailId = $osticketPDO->lastInsertId();
            //$insertUserMailStmt->debugDumpParams();
            //echo "ost_user_email OK".PHP_EOL;

            // Update ost_user with the new e-mail
            $updateUserMailStmt->bindParam(':mailId', $newMailId, PDO::PARAM_INT);
            $updateUserMailStmt->bindParam(':userId', $newUserId, PDO::PARAM_INT);
            $updateUserMailStmt->execute();
            //echo "ost_user update mail OK".PHP_EOL;

            // Insert dummy record in ost_form_entry and ost_form_entry_values to fool OsTicket
            $insertFormEntryStmt->bindParam(':userId', $newUserId, PDO::PARAM_INT);
            $insertFormEntryStmt->bindParam(':now1', $now1, PDO::PARAM_STR);
            $insertFormEntryStmt->bindParam(':now2', $now2, PDO::PARAM_STR);
            $insertFormEntryStmt->execute();
            $formEntryId = $osticketPDO->lastInsertId();
            foreach ($fieldsList as $field => $fieldId) {
                $insertFormEntryValuesStmt->bindParam(':entryId', $formEntryId, PDO::PARAM_INT);
                $insertFormEntryValuesStmt->bindParam(':fieldId', $fieldId, PDO::PARAM_INT);
                $insertFormEntryValuesStmt->bindParam(':fieldValue', $userFields[$reversedFieldsList[$fieldId]], PDO::PARAM_STR);
                $insertFormEntryValuesStmt->execute();
            }

            $migrated['client_count']++;
        } else {
            echo "Empty user record in ".__FILE__." at line ".__LINE__.PHP_EOL;
        }
    }

    return true;
}
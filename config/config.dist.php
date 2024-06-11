<?php
/**
 * Define database DSNs for OTRS (origin) and OsTicket (destination)
 */
$sourceDSN = 'mysql:host=localhost;dbname=otrs';
$sourceUser = 'otrs';
$sourcePass = 'otrs';
$destinationDSN = 'mysql:host=localhost;dbname=osticket';
$destinationUser = 'osticket';
$destinationPass = 'osticket';
$doMigrateStaff = true;
$doMigrateCustomers = true;

/**
 * Variables and arrays below have to be completed as needed for your
 * circumstances.
 * The values offered here are examples and you *MUST* change them
 * based on the values you will find in the corresponding "lists" in
 * OsTicket admin panel, under "Manage" > "Lists"
 */
$defaultStaffDepartmentId = 1;
$defaultStaffRoleId = 3;
$defaultStaffLanguage = '{"browser_lang":"fr"}';
$defaultStaffPermissions = '{"user.create":1,"user.delete":1,"user.edit":1,"user.manage":1,"user.dir":1,"org.create":1,"org.delete":1,"org.edit":1,"faq.manage":1,"visibility.agents":1,"emails.banlist":1,"visibility.departments":1}';

// @todo fill the following arrays via select queries
// Array of IDs of each user field in ost_user__cdata.
// These can be found in ost_form_field with form_id = 1 and label or name to get the translation
// The IDs come from the fields created (or pre-existing) in the User creation form in OsTicket
$fieldsList = [
    'clientnum' => 79,
    'title' => 73,
    'firstname' => 57,
    'mobilephone' => 3,
    'landphone' => 76,
    'address' => 59,
    'postcode' => 60,
    'city' => 61,
    'lang' => 62,
    'email2' => 72,
    'tag' => 75,
    'recommendedby' => 77,
];
// Translate languages in OTRS to language IDs in OsTicket
// The IDs come from a "list" of values created manually in OsTicket
$langTranslation = [
    'en' => '26',
    'fr' => '24',
    'nl' => '25',
    '-' => '27'
];
// Literal strings for language shortcodes
// "-" serves when no language was selected
// These come from a "list" of values created manually in OsTicket
$langTranslationTerms = [
    'en' => 'English',
    'fr' => 'Fran\u00e7ais',
    'nl' => 'Nederlands',
    '-' => 'Autre'
];
// IDs (in OsTicket) for "titles" obtained from OTRS
// The IDs come from the values of a list created manually in OsTicket
$titleTranslation = [
    'Mr' => 32,
    'Mme' => 31,
    'Mlle' => 42,
    '-' => 0,
];

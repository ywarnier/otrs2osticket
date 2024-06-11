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

// Array of IDs of each user field in ost_user__cdata.
// These can be found in ost_form_field with form_id = 1 and label or name to get the translation
// Todo: fill via a select query
// Prod
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
// Test
$fieldsList = [
    'clientnum' => 38,
    'title' => 39,
    'firstname' => 40,
    'mobilephone' => 41,
    'landphone' => 42,
    'address' => 43,
    'postcode' => 44,
    'city' => 45,
    'lang' => 46,
    'email2' => 47,
    'tag' => 48,
    'recommendedby' => 49,
];

// Prod
$langTranslation = [
    'en' => '26',
    'fr' => '24',
    'nl' => '25',
    '-' => '27'
];
// Test
$langTranslation = [
    'en' => '5',
    'fr' => '6',
    'nl' => '7',
    '-' => '4'
];

$langTranslationTerms = [
    'en' => 'English',
    'fr' => 'Fran\u00e7ais',
    'nl' => 'Nederlands',
    '-' => 'Autre'
];

// Prod
$titleTranslation = [
    'Mr' => 32,
    'Mme' => 31,
    'Mlle' => 42,
    '-' => 0,
];
// Test
$titleTranslation = [
    'Mr' => 36,
    'Mme' => 35,
    'Mlle' => 42,
    '-' => 0,
];

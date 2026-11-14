<?php
/**
 * Copy this file to includes/config.local.php on the server and fill it in. config.local.php is private: it is
 * git-ignored and must never be committed or shared. It overrides everything in config.php.
 */

// The real address of the live website, no slash at the end.
$ngo['site_url'] = 'https://aagaahifoundation.com';

// Database: in Hostinger hPanel > Databases > MySQL Databases create a database + user, then copy the three values here.
// (Both the name and the user start with "u" and digits. The host is almost always "localhost".)
$ngo['db'] = [
    'host' => 'localhost',
    'name' => 'u000000000_aagahi',
    'user' => 'u000000000_aagahi',
    'pass' => 'PASTE-THE-DATABASE-PASSWORD-HERE',
];

// Only needed while you run /install/ once: invent a long phrase and type the same phrase into the install form.
// Remove this line after installing.
$ngo['install_token'] = 'invent-a-long-random-phrase-here';

// Where messages from the contact form are emailed (the messages are also saved in the database).
$ngo['email'] = 'info@aagaahifoundation.com';

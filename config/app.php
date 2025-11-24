<?php
// Enable error reporting first
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Basic app configuration

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hagidy-website');

// App settings
define('APP_DEBUG', true);
define('APP_PORT', '8080'); // Default application port

// Set default timezone (adjust if needed)
date_default_timezone_set('UTC');
define('USER_BASEURL', 'http://localhost/hagidy%2011-11-2025/');

// Public assets base (used in header/footer and pages)
if (!defined('PUBLIC_ASSETS')) {
    define('PUBLIC_ASSETS', USER_BASEURL . 'public/');
}
// define('USER_BASEURL', 'https://hagidy.com/');
define('ADMIN_BASEURL', 'https://api.hagidy.com/api/');


$user_baseurl = USER_BASEURL; // simple variable alias if needed in pages
$admin_baseurl = ADMIN_BASEURL; // simple variable alias if needed in pages
$vendor_baseurl = $user_baseurl."public/";
$superadmin_baseurl =  $user_baseurl."public/";
?>


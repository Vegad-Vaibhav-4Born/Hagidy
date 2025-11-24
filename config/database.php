<?php
// Ensure app config is loaded
if (!defined('DB_HOST')) {
    $configPath = __DIR__ . '/app.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

$db_connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$con = $db_connection;
if (!$db_connection) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        die('Database connection failed: ' . mysqli_connect_error());
    } else {
        die('Database connection error.');
    }
}
mysqli_set_charset($db_connection, 'utf8mb4');
?>


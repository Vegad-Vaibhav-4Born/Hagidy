<?php
// Bridge include so legacy pages can `require 'includes/init.php'`
// require_once __DIR__ . '/../../Core/init.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Safety: if DB is still not initialized for any reason, load root config directly
if (!isset($db_connection)) {
    $appCfg = __DIR__ . '/../../../config/app.php';
    if (file_exists($appCfg)) { require_once $appCfg; }
    $dbCfg = __DIR__ . '/../../../config/database.php';
    if (file_exists($dbCfg)) { require_once $dbCfg; }
}
?>


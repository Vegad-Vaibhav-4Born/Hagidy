<?php
session_start();
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}

header('Content-Type: application/json');

// Check user session
if (!isset($_SESSION['mobile'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please login again."]);
    exit;
}

$mobile = $_SESSION['mobile'];

// Generate new OTP
$newOtp = rand(100000, 999999);

// Update OTP in DB
if (mysqli_query($db_connection, "UPDATE users SET otp='$newOtp' WHERE mobile='$mobile'")) {
    

    
    echo json_encode([
        "status" => "success", 
        "message" => "OTP has been resent successfully.",
        "otp" => $newOtp // ⚠️ For testing only, remove in production
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error. Try again."]);
}


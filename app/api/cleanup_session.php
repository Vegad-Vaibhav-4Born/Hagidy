<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'cleanup_password_success') {
    // Clean up session variables after successful password set/reset
    unset($_SESSION['otp_verified']);
    unset($_SESSION['pending_phone']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['vendor_registration']);
    unset($_SESSION['password_set_success']);
    unset($_SESSION['otp_stage']);
    
    // Clean up forgot password specific variables
    unset($_SESSION['forgot_password']);
    unset($_SESSION['vendor_id']);
    unset($_SESSION['business_name']);
    unset($_SESSION['otp']);
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>

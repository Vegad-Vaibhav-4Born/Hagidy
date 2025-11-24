<?php
require_once __DIR__ . '/../pages/includes/init.php';
session_start();

function redirectTo($url) { header('Location: ' . $url); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php'); 
}

// Check if this is a forgot password flow
if (!isset($_SESSION['forgot_password']) || $_SESSION['forgot_password'] !== true) {
    redirectTo('' . USER_BASEURL . 'vendor/login.php');
}

// Check if OTP was verified
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] != 1) {
    $_SESSION['reset_error'] = 'OTP verification required.';
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
}

$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';

if ($password === '' || $confirm === '') {
    $_SESSION['reset_error'] = 'Please fill in all fields.';
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}

if ($password !== $confirm) {
    $_SESSION['reset_error'] = 'Passwords do not match.';
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}

// Enforce minimum length
if (strlen($password) < 6) {
    $_SESSION['reset_error'] = 'Password must be at least 6 characters.';
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}

// Get vendor ID from session
if (!isset($_SESSION['vendor_id'])) {
    $_SESSION['reset_error'] = 'Session expired. Please start over.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}

$vendorId = $_SESSION['vendor_id'];
$phone = $_SESSION['pending_phone'];

// Hash the new password
$hash = password_hash($password, PASSWORD_BCRYPT);
$hashEsc = mysqli_real_escape_string($con, $hash);
$vendorIdEsc = mysqli_real_escape_string($con, $vendorId);

// Update password in database
$updateQuery = "UPDATE vendor_registration SET password = '{$hashEsc}', updated_at = NOW() WHERE id = '{$vendorIdEsc}'";
$updateResult = mysqli_query($con, $updateQuery);

if ($updateResult) {
    // Get vendor details for automatic login
    $vendorQuery = "SELECT id, business_name, status FROM vendor_registration WHERE id = '{$vendorIdEsc}'";
    $vendorResult = mysqli_query($con, $vendorQuery);
    
    if ($vendorResult && mysqli_num_rows($vendorResult) > 0) {
        $vendorData = mysqli_fetch_assoc($vendorResult);
        $businessName = $vendorData['business_name'];
        $status = $vendorData['status'];
        
        // Set session variables for automatic login
        $_SESSION['vendor_reg_id'] = $vendorId;
        $_SESSION['vendor_mobile'] = $phone;
        $_SESSION['vendor_business_name'] = $businessName;
        $_SESSION['vendor_status'] = $status;
        
        // Set success message
        $_SESSION['password_success'] = 'Password reset successfully!';
        
        // Decide target after reset
        $statusLower = strtolower($status);
        $targetPath = ($statusLower === 'pending' || $statusLower === 'hold')
            ? 'vendor/profileSetting.php'
            : 'vendor/index.php';
        
        // Clean up forgot password session variables
        unset($_SESSION['forgot_password']);
        unset($_SESSION['otp_verified']);
        unset($_SESSION['pending_phone']);
        unset($_SESSION['vendor_id']);
        unset($_SESSION['business_name']);
        unset($_SESSION['otp']);
        
        // Redirect directly to the target page
        redirectTo('' . USER_BASEURL . $targetPath);
    } else {
        $_SESSION['reset_error'] = 'Failed to retrieve vendor information.';
        redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
    }
} else {
    $_SESSION['reset_error'] = 'Failed to update password. Please try again.';
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}
?>

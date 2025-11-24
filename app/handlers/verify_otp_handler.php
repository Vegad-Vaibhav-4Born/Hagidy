<?php
require_once __DIR__ . '/../pages/includes/init.php';


function redirectTo($url) { header('Location: ' . $url); exit; }

// Resend flow
if (isset($_POST['resend']) && isset($_SESSION['pending_phone'])) {
    $phone = $_SESSION['pending_phone'];
    
    // Generate new OTP (use static for testing if requested, else random)
    $otp = '123456'; // Default static OTP for testing
    if (!isset($_GET['static']) || $_GET['static'] !== '1') {
        $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);
    $phoneEsc = mysqli_real_escape_string($con, $phone);
    
    // Clear existing OTP and insert new one
    mysqli_query($con, "DELETE FROM otp_verification WHERE phone_number = '{$phoneEsc}'");
    $insertResult = mysqli_query($con, "INSERT INTO otp_verification (phone_number, otp_code, is_verified, attempts, expires_at, created_at, updated_at) VALUES ('{$phoneEsc}', '{$otp}', 0, 0, '{$expiresAt}', NOW(), NOW())");
    
    if ($insertResult) {
        $_SESSION['otp'] = $otp; // Store OTP in session for testing
        $_SESSION['otp_success'] = 'A new OTP has been sent successfully!';
    } else {
        $_SESSION['otp_error'] = 'Failed to send new OTP. Please try again.';
    }
    
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
}

// Verify flow
$digits = [];
for ($i = 1; $i <= 6; $i++) {
    $key = 'd' . $i;
    $digits[] = isset($_POST[$key]) ? preg_replace('/\D/','', $_POST[$key]) : '';
}
$otpInput = implode('', $digits);

if (!isset($_SESSION['pending_phone'])) {
    $_SESSION['otp_error'] = 'Session expired. Please start over.';
    redirectTo('' . USER_BASEURL . 'vendor/registration.php');
}
$phone = $_SESSION['pending_phone'];

// Fetch OTP record
$phoneEsc = mysqli_real_escape_string($con, $phone);
$res = mysqli_query($con, "SELECT id, otp_code, is_verified, attempts, expires_at FROM otp_verification WHERE phone_number = '{$phoneEsc}' ORDER BY id DESC LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['otp_error'] = 'No OTP found. Please resend.';
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
}
$row = mysqli_fetch_assoc($res);
$id = (int)$row['id'];
$otp_code = $row['otp_code'];
$is_verified = (int)$row['is_verified'];
$attempts = (int)$row['attempts'];
$expires_at = $row['expires_at'];

// Check expiry
if (strtotime($expires_at) < time()) {
    mysqli_query($con, "UPDATE otp_verification SET attempts = 0, is_verified = 0 WHERE id = {$id}");
    $_SESSION['otp_error'] = 'OTP expired. Please resend a new one.';
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
}

// Increment attempts
$attempts++;
mysqli_query($con, "UPDATE otp_verification SET attempts = {$attempts} WHERE id = {$id}");

// Compare
if (!hash_equals($otp_code, $otpInput)) {
    $_SESSION['otp_error'] = 'Invalid OTP. Please try again.';
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
}

mysqli_query($con, "UPDATE otp_verification SET is_verified = 1, updated_at = NOW() WHERE id = {$id}");
$_SESSION['otp_verified'] = 1;

// Set session stage to prevent going back to registration
$_SESSION['otp_stage'] = 'verified';

// Check if this is a forgot password flow or registration flow
if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true) {
    // Forgot password flow - redirect to setNewPass.php with reset handler
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
} else {
    // Registration flow - redirect to setNewPass.php with set password handler
    redirectTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}



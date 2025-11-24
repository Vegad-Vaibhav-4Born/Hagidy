<?php
require_once __DIR__ . '/../pages/includes/init.php';

session_start();

function redirectTo($url) { header('Location: ' . $url); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php'); 
}

$mobileRaw = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
$mobile = preg_replace('/\D/', '', $mobileRaw);

// Clear any previous error messages
unset($_SESSION['forgot_error']);
unset($_SESSION['forgot_success']);

if (empty($mobileRaw)) {
    $_SESSION['forgot_error'] = 'Please enter your mobile number.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}

if (empty($mobile) || strlen($mobile) < 10) {
    $_SESSION['forgot_error'] = 'Please enter a valid 10-digit mobile number.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}

// Check if mobile number exists in vendor_registration
$mobileEsc = mysqli_real_escape_string($con, $mobile);
$checkQuery = "SELECT id, business_name, mobile_number FROM vendor_registration WHERE mobile_number = '{$mobileEsc}' ORDER BY id DESC LIMIT 1";
$checkResult = mysqli_query($con, $checkQuery);

if (!$checkResult) {
    $_SESSION['forgot_error'] = 'Database error occurred. Please try again.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}

if (mysqli_num_rows($checkResult) === 0) {
    $_SESSION['forgot_error'] = 'Mobile number ' . $mobile . ' not found. Please check your number or register first.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}

$vendorData = mysqli_fetch_assoc($checkResult);
$vendorId = $vendorData['id'];
$businessName = $vendorData['business_name'];

// Generate OTP (use static for testing if requested, else random)
$otp = '123456'; // Default static OTP for testing
if (!isset($_GET['static']) || $_GET['static'] !== '1') {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Expire in 10 minutes
$expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

// Clear any existing OTP for this phone number
mysqli_query($con, "DELETE FROM otp_verification WHERE phone_number = '{$mobileEsc}'");

// Insert new OTP
$insertResult = mysqli_query($con, "INSERT INTO otp_verification (phone_number, otp_code, is_verified, attempts, expires_at, created_at, updated_at) VALUES ('{$mobileEsc}', '{$otp}', 0, 0, '{$expiresAt}', NOW(), NOW())");

if ($insertResult) {
    // Store necessary data in session for forgot password flow
    $_SESSION['forgot_password'] = true;
    $_SESSION['pending_phone'] = $mobile;
    $_SESSION['vendor_id'] = $vendorId;
    $_SESSION['business_name'] = $businessName;
    $_SESSION['otp'] = $otp; // Store OTP in session for testing
     $_SESSION['otp_stage'] = 'forgot';
    $_SESSION['otp_verified'] = 0;
    
    $_SESSION['forgot_success'] = 'OTP sent successfully to your registered mobile number.';
    redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
} else {
    $_SESSION['forgot_error'] = 'Failed to send OTP. Please try again.';
    redirectTo('' . USER_BASEURL . 'vendor/forgotPassword.php');
}
?>

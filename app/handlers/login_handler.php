<?php
require_once __DIR__ . '/../pages/includes/init.php';

session_start();

function go($relPath) { header('Location: ' . $relPath); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { go(USER_BASEURL.'vendor/login.php'); }

$mobileRaw = isset($_POST['mobile']) ? $_POST['mobile'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

$mobile = preg_replace('/\D/', '', $mobileRaw);
if ($mobile === '' || $password === '') {
    $_SESSION['login_error'] = 'Invalid credentials.';
    go(USER_BASEURL.'vendor/login.php');
}

$mobileEsc = mysqli_real_escape_string($con, $mobile);
$sql = "SELECT id, password, status, business_name FROM vendor_registration WHERE mobile_number = '{$mobileEsc}' ORDER BY id DESC LIMIT 1";
$res = mysqli_query($con, $sql);
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['login_error'] = 'Account not found.';
    go(USER_BASEURL.'vendor/login.php');
}
$row = mysqli_fetch_assoc($res);
$regId = (int)$row['id'];
$hash = (string)$row['password'];
$status = (string)$row['status'];
$businessName = (string)$row['business_name'];

// if (strtolower($status) !== 'approved') {
//     $_SESSION['login_error'] = 'Account status: ' . $status . '. Contact support.';
//     go('../login.php');
// }

if (!password_verify($password, $hash)) {
    $_SESSION['login_error'] = 'Invalid credentials.';
    go(USER_BASEURL.'vendor/login.php');
}

// Success
$_SESSION['vendor_reg_id'] = $regId;
$_SESSION['vendor_mobile'] = $mobile;
$_SESSION['vendor_business_name'] = $businessName;
$_SESSION['vendor_status'] = $status;

// If status is pending or hold, send only to profile settings
$statusLower = strtolower($status);
if ($statusLower === 'pending' || $statusLower === 'hold') {
    go(USER_BASEURL.'vendor/profileSetting.php');
}

go(USER_BASEURL.'vendor/index.php');

<?php
require_once __DIR__ . '/../pages/includes/init.php';


function redirectTo($url) {
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('' . USER_BASEURL . 'vendor/registration.php');
}

// Collect posted fields without server-side validation (handled in JS on form page)
$data = [];
$fields = [
    'business_name','business_address','city','pincode','owner_name','mobile','whatsapp','email','gst_number',
    'account_name','account_type','account_number','account_number_confirm','bank_name','ifsc','signature_name','website'
];
foreach ($fields as $field) {
    $data[$field] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
}

// Normalize phone number to digits only
$phoneNumber = preg_replace('/\D/', '', $data['mobile'] ?? '');

// Check for duplicate phone number in vendor_registration table
$phoneEsc = mysqli_real_escape_string($con, $phoneNumber);
$checkPhone = mysqli_query($con, "SELECT id FROM vendor_registration WHERE mobile_number = '{$phoneEsc}' LIMIT 1");

if ($checkPhone && mysqli_num_rows($checkPhone) > 0) {
    // Phone number already exists, redirect back with error
    session_start();
    $_SESSION['registration_error'] = 'This phone number is already registered. Please use a different phone number.';
    redirectTo('' . USER_BASEURL . 'vendor/registration.php');
}

// Persist non-file fields into session for later insert after OTP verification
session_start();

// Set session stage to track registration flow
$_SESSION['otp_stage'] = 'registration';

$_SESSION['vendor_registration'] = [
    'business_name' => $data['business_name'],
    'business_address' => $data['business_address'],
    'business_type' => $_POST['business_type_id'] ?? null,
    'business_type_other' => $_POST['business_type_text'] ?? null,
    'state' => $_POST['state_id'] ?? null,
    'city' => $data['city'],
    'pincode' => $data['pincode'],
    'owner_name' => $data['owner_name'],
    'mobile_number' => $phoneNumber,
    'whatsapp_number' => preg_replace('/\D/', '', $data['whatsapp']),
    'email' => $data['email'],
    'website_social' => $data['website'],
    'gst_number' => $data['gst_number'],
    'account_name' => $data['account_name'],
    'account_type' => $data['account_type'],
    'account_number' => $data['account_number'],
    'confirm_account_number' => $data['account_number_confirm'],
    'bank_name' => $data['bank_name'],
    'ifsc_code' => $data['ifsc'],
    'product_categories' => isset($_POST['category_ids']) ? implode(',', (array)$_POST['category_ids']) : null,
    'avg_products' => $_POST['avg_products'] ?? null,
    'manufacture_products' => $_POST['is_manufacturer'] ?? null,
    'signature_name' => $data['signature_name'],
    'registration_date' => date('Y-m-d'),
];

// Handle file uploads (gst_certificate, bank_proof)
$vendorFolderName = $_SESSION['vendor_registration']['business_name'] ?? ('vendor_' . time());
$vendorFolderName = preg_replace('/[^a-zA-Z0-9-_]/', '_', $vendorFolderName);
// Filesystem base (absolute path) for writing files
$publicRoot = realpath(__DIR__ . '/../../public');
if ($publicRoot === false) { $publicRoot = __DIR__ . '/../../public'; }
$uploadDir = rtrim($publicRoot, '/\\') . '/uploads/vendors/' . $vendorFolderName;
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
// Public URL base for storing references
$uploadUrlBase = USER_BASEURL . 'public/uploads/vendors/' . $vendorFolderName;

// GST certificate
if (!empty($_FILES['gst_certificate']['name']) && is_uploaded_file($_FILES['gst_certificate']['tmp_name'])) {
    $ext = pathinfo($_FILES['gst_certificate']['name'], PATHINFO_EXTENSION);
    $fname = 'gst_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
    $dest = $uploadDir . '/' . $fname;
    if (@move_uploaded_file($_FILES['gst_certificate']['tmp_name'], $dest)) {
        $_SESSION['vendor_registration']['gst_certificate'] = $uploadUrlBase . '/' . $fname;
    }else{
        $_SESSION['registration_error'] = 'Failed to upload GST certificate.';
        header('Location: ' . USER_BASEURL . 'vendor/registration.php');
        exit;
    }
}else{
    $_SESSION['registration_error'] = 'Failed to upload GST certificate.';
    header('Location:' . USER_BASEURL . 'vendor/registration.php');
    exit;
}

// Cancelled cheque / bank proof
if (!empty($_FILES['bank_proof']['name']) && is_uploaded_file($_FILES['bank_proof']['tmp_name'])) {
    $ext = pathinfo($_FILES['bank_proof']['name'], PATHINFO_EXTENSION);
    $fname = 'bank_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
    $dest = $uploadDir . '/' . $fname;
    if (@move_uploaded_file($_FILES['bank_proof']['tmp_name'], $dest)) {
        $_SESSION['vendor_registration']['cancelled_cheque'] = $uploadUrlBase . '/' . $fname;
    }else{
        $_SESSION['registration_error'] = 'Failed to upload cancelled cheque.';
        header('Location: ' . USER_BASEURL . 'vendor/registration.php');
        exit;
    }
}else{
    $_SESSION['registration_error'] = 'Failed to upload cancelled cheque.';
    header('Location: ' . USER_BASEURL . 'vendor/registration.php');
    exit;
}

// Save the folder name for later use if needed
$_SESSION['vendor_registration']['vendor_folder'] = $vendorFolderName;

// Generate OTP (use static if requested, else random)
$otp = '123456';
if (!isset($_GET['static']) || $_GET['static'] !== '1') {
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Expire in 10 minutes
$expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

// Upsert into otp_verification
$stmt = mysqli_prepare($con, "INSERT INTO otp_verification (phone_number, otp_code, is_verified, attempts, expires_at, created_at, updated_at) VALUES (?, ?, 0, 0, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE otp_code=VALUES(otp_code), is_verified=0, attempts=0, expires_at=VALUES(expires_at), updated_at=NOW()");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'sss', $phoneNumber, $otp, $expiresAt);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Optionally send OTP via SMS provider here. For now, store in session for testing.
$_SESSION['pending_phone'] = $phoneNumber;
$_SESSION['pending_email'] = $data['email'] ?? null;
$_SESSION['otp'] = $otp; // Store OTP in session for testing
$_SESSION['otp_stage'] = 'registration';
$_SESSION['otp_verified'] = 0;

// Redirect to OTP page
redirectTo('' . USER_BASEURL . 'vendor/verifyOtp.php');
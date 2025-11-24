<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../pages/includes/init.php';

function json_response($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

function generateReferralCode($length = 6) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// Generate customer ID (B + 9 digits)
function generateCustomerId() {
    global $db_connection;
    
    // Get the highest existing customer_id
    $result = mysqli_query($db_connection, "SELECT customer_id FROM users WHERE customer_id IS NOT NULL ORDER BY customer_id DESC LIMIT 1");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_customer_id = $row['customer_id'];
        
        // Extract the numeric part (remove 'B' prefix)
        $numeric_part = substr($last_customer_id, 1);
        $next_number = intval($numeric_part) + 1;
    } else {
        // First customer
        $next_number = 1;
    }
    
    // Format as B + 9 digits (padded with zeros)
    $customer_id = 'B' . str_pad($next_number, 9, '0', STR_PAD_LEFT);
    
    return $customer_id;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Method not allowed');
}

$first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
$last_name  = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
$mobile     = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
$email      = isset($_POST['email']) ? trim($_POST['email']) : '';
$password_plain      = isset($_POST['password']) ? trim($_POST['password']) : '';
$refer_choice = isset($_POST['refer_choice']) ? $_POST['refer_choice'] : 'no';
$referral_code_input = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';

if ($first_name === '' || $last_name === '' || $mobile === '' ) {
    json_response('error', 'All fields are required.');
}

$first_name = mysqli_real_escape_string($db_connection, $first_name);
$last_name  = mysqli_real_escape_string($db_connection, $last_name);
$mobile     = mysqli_real_escape_string($db_connection, $mobile);
$email      = mysqli_real_escape_string($db_connection, $email);

// Check duplicates
$dup = mysqli_query($db_connection, "SELECT id FROM users WHERE mobile='$mobile'  LIMIT 1");
if (!$dup) {
    json_response('error', 'Database error');
}
if (mysqli_num_rows($dup) > 0) {
    json_response('error', 'Mobile already registered.');
}

$referred_by = 'NULL';
if ($refer_choice === 'yes' && $referral_code_input !== '') {
    $ref = mysqli_real_escape_string($db_connection, $referral_code_input);
    $checkRef = mysqli_query($db_connection, "SELECT id FROM users WHERE referral_code='$ref' OR mobile='$ref' LIMIT 1");
    if ($checkRef && mysqli_num_rows($checkRef) > 0) {
        $refRow = mysqli_fetch_assoc($checkRef);
        $referred_by = (string)$refRow['id'];
    } else {
        json_response('error', 'Oops! That referral code doesn’t exist.');
    }
}

// default password (as in login.php flow)
// $password_plain = '123';
$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);
$otp = rand(100000, 999999);
$my_referral_code = generateReferralCode();
$customer_id = generateCustomerId();

$sql = "INSERT INTO users (first_name, last_name, mobile, email, password, otp, referral_code, referred_by, customer_id)
        VALUES ('$first_name', '$last_name', '$mobile', '$email', '$password_hashed', '$otp', '$my_referral_code', $referred_by, '$customer_id')";

if (!mysqli_query($db_connection, $sql)) {
    json_response('error', 'Error creating user.');
}

$user_id = mysqli_insert_id($db_connection);
$_SESSION['mobile'] = $mobile;

// Debug: Log the redirect path
error_log("Registration successful for user ID: $user_id, redirecting to: otp.php");

json_response('success', 'Registration successful. Redirecting to OTP verification...', ['redirect' => 'otp.php', 'customer_id' => $customer_id]);
?>



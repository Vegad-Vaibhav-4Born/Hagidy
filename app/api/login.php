<?php
// Enable strict error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to prevent accidental output
ob_start();

// Set JSON header
header('Content-Type: application/json');

require_once __DIR__ . '/../pages/includes/init.php';

// Function to send JSON response and exit
function json_response($status, $message, $extra = []) {
    // Clear any previous output to avoid broken JSON
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Method not allowed');
}

// Get POST values
$mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$remember_me = isset($_POST['remember']) ? true : false;

if ($mobile === '' || $password === '') {
    json_response('error', 'Mobile and Password are required');
}

// Start session
session_start();

// Escape input
$mobile = mysqli_real_escape_string($db_connection, $mobile);

// Fetch user
$res = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile' LIMIT 1");
if (!$res) {
    json_response('error', 'Database error: ' . mysqli_error($db_connection));
}

if (mysqli_num_rows($res) === 0) {
    json_response('error', 'Mobile number not registered.');
}

$user = mysqli_fetch_assoc($res);

// Verify password
if (!password_verify($password, $user['password'])) {
    json_response('error', 'Incorrect password.');
}

// Check OTP verification
if (empty($user['customer_id'])) {
    $_SESSION['mobile'] = $user['mobile'];
    json_response('error', 'Please complete OTP verification before logging in.', ['redirect' => 'otp.php']);
}

// Generate OTP (if needed for login process)
$otp = rand(100000, 999999);
$update = mysqli_query($db_connection, "UPDATE users SET otp='$otp' WHERE id='" . $user['id'] . "'");
if (!$update) {
    json_response('error', 'Error updating OTP: ' . mysqli_error($db_connection));
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['mobile'] = $user['mobile'];

// Set session duration cookies
$session_duration = $remember_me ? 30 * 24 * 60 * 60 : 7 * 24 * 60 * 60;
setcookie(session_name(), session_id(), time() + $session_duration, '/');
$_SESSION['remember_me'] = $remember_me;
$_SESSION['session_expires'] = time() + $session_duration;

// Return success
json_response('success', 'Login Successfully.', ['redirect' => 'index.php']);
?>

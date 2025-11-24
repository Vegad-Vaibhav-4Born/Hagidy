<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../pages/includes/init.php';
function json_response($status, $message, $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Method not allowed');
}

$code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';
if ($code === '') {
    json_response('error', 'Referral code is required');
}

$code = mysqli_real_escape_string($db_connection, $code);

$res = mysqli_query($db_connection, "SELECT id, first_name, last_name FROM users WHERE referral_code='$code' OR mobile='$code' LIMIT 1");
if (!$res) {
    json_response('error', 'Database error');
}

if (mysqli_num_rows($res) === 0) {
    json_response('error', 'Referral not found');
}

$row = mysqli_fetch_assoc($res);
$name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
json_response('success', 'Referral valid', ['name' => $name !== '' ? $name : 'User']);

?>



<?php
include __DIR__ . '/../pages/includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exists' => false]);
    exit;
}

$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
if (empty($phone)) {
    echo json_encode(['exists' => false]);
    exit;
}

// Normalize phone number to digits only
$phoneNumber = preg_replace('/\D/', '', $phone);

if (strlen($phoneNumber) < 10) {
    echo json_encode(['exists' => false]);
    exit;
}

// Check for duplicate phone number in vendor_registration table
$phoneEsc = mysqli_real_escape_string($con, $phoneNumber);
$checkPhone = mysqli_query($con, "SELECT id FROM vendor_registration WHERE mobile_number = '{$phoneEsc}' LIMIT 1");

if ($checkPhone && mysqli_num_rows($checkPhone) > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}
?>

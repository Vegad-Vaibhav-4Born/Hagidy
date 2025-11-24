<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once __DIR__ . '/../pages/includes/init.php';
header('Content-Type: application/json');

if(!isset($_SESSION['vendor_reg_id'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$vendor_reg_id = $_SESSION['vendor_reg_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
    
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        exit;
    }
    
    // Mark notification as read
    $update_query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = '$notification_id' AND receiver_type = 'vendor' AND receiver_id = '$vendor_reg_id'";
    
    if (mysqli_query($con, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

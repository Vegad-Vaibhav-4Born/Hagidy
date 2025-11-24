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

// Get unread count
$unread_query = mysqli_query($con, "SELECT COUNT(*) as unread_count FROM notifications WHERE receiver_type = 'vendor' AND receiver_id = '$vendor_reg_id' AND is_read = 0");
$unread_data = mysqli_fetch_assoc($unread_query);
$unread_count = $unread_data['unread_count'] ?? 0;

// Get latest notifications (last 10)
$notifications_query = mysqli_query($con, "SELECT id, title, message, link_url, is_read, created_at, type FROM notifications WHERE receiver_type = 'vendor' AND receiver_id = '$vendor_reg_id' ORDER BY created_at DESC LIMIT 10");
$notifications = [];
while($notification = mysqli_fetch_assoc($notifications_query)) {
    $notifications[] = [
        'id' => $notification['id'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'link_url' => $notification['link_url'],
        'is_read' => (bool)$notification['is_read'],
        'created_at' => $notification['created_at'],
        'type' => $notification['type'],
        'time_ago' => timeAgo($notification['created_at'])
    ];
}

echo json_encode([
    'success' => true,
    'unread_count' => $unread_count,
    'notifications' => $notifications
]);

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}
?>

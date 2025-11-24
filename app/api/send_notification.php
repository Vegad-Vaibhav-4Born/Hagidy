<?php
// require_once '../../config/db.php';
require_once __DIR__ . '/../pages/includes/init.php';
// Helper function to send notifications
function sendNotification($con, $sender_type, $sender_id, $receiver_type, $receiver_id, $type, $title, $message, $link_url = null, $meta = null) {
    $sender_type_esc = mysqli_real_escape_string($con, $sender_type);
    $sender_id_esc = $sender_id ? mysqli_real_escape_string($con, $sender_id) : 'NULL';
    $receiver_type_esc = mysqli_real_escape_string($con, $receiver_type);
    $receiver_id_esc = mysqli_real_escape_string($con, $receiver_id);
    $type_esc = mysqli_real_escape_string($con, $type);
    $title_esc = mysqli_real_escape_string($con, $title);
    $message_esc = mysqli_real_escape_string($con, $message);
    $link_url_esc = $link_url ? "'" . mysqli_real_escape_string($con, $link_url) . "'" : 'NULL';
    $meta_esc = $meta ? "'" . mysqli_real_escape_string($con, json_encode($meta)) . "'" : 'NULL';
    
    $query = "INSERT INTO notifications (sender_type, sender_id, receiver_type, receiver_id, type, title, message, link_url, meta) 
              VALUES ('$sender_type_esc', $sender_id_esc, '$receiver_type_esc', '$receiver_id_esc', '$type_esc', '$title_esc', '$message_esc', $link_url_esc, $meta_esc)";
    
    return mysqli_query($con, $query);
}

// Example usage for order notifications
function sendOrderNotification($con, $order_id, $order_number, $vendor_id) {
    $title = "Your Received New Order Request";
    $message = "Order No: #$order_number, Please Accept Order Request";
    $link_url = "order-details.php?id=$order_id";
    $meta = json_encode(['order_id' => $order_id, 'order_number' => $order_number]);
    
    return sendNotification($con, 'user', 1, 'vendor', $vendor_id, 'order_new', $title, $message, $link_url, $meta);
}

// Example usage for registration status notifications
function sendRegistrationStatusNotification($con, $vendor_id, $old_status, $new_status) {
    $title = "Registration Status Updated";
    $message = "Your registration status has been changed from $old_status to $new_status by Superadmin.";
    $link_url = "profileSetting.php";
    $meta = json_encode(['old_status' => $old_status, 'new_status' => $new_status]);
    
    return sendNotification($con, 'superadmin', 1, 'vendor', $vendor_id, 'registration_status', $title, $message, $link_url, $meta);
}

// Send order status notification to customer
function sendOrderStatusNotification($con, $order_id, $user_id, $status, $message, $vendor_item_count = 0) {
    if ($user_id <= 0) return false; // No user ID, skip notification
    
    $status_titles = [
        'confirmed' => 'Order Confirmed',
        'cancelled' => 'Order Cancelled', 
        'shipped' => 'Order Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Order Delivered'
    ];
    
    $title = $status_titles[$status] ?? 'Order Status Updated';
    $link_url = "order-details.php?id=$order_id"; // Customer order details page
    
    // Create meta with proper JSON encoding (no double escaping)
    $meta_data = [
        'order_id' => $order_id,
        'status' => $status,
        'vendor_item_count' => $vendor_item_count
    ];
    $meta = json_encode($meta_data);
    
    // Add order ID to message if not already present
    if (strpos($message, $order_id) === false) {
        $message = $message . " Order ID: #$order_id";
    }
    
    // Add item count info if provided
    if ($vendor_item_count > 0) {
        $message = $message . " ($vendor_item_count item" . ($vendor_item_count > 1 ? 's' : '') . " from this vendor)";
    }
    
    return sendNotification($con, 'vendor', 1, 'user', $user_id, 'order_status', $title, $message, $link_url, $meta);
}
?>

<?php
require_once __DIR__ . '/../pages/includes/init.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';
$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

// For get_count action, we don't need product_id validation
if ($action === 'get_count') {
    if ($is_logged_in) {
      
        $count_query = "
            SELECT COUNT(*) as count 
            FROM wishlist w 
            INNER JOIN products p ON p.id = w.product_id 
            WHERE w.user_id = ? AND p.status = 'approved'";
        $stmt = mysqli_prepare($db_connection, $count_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count_data = mysqli_fetch_assoc($result);
        
        $response = [
            'success' => true,
            'count' => (int)$count_data['count'],
            'is_logged_in' => true,
            'user_id' => $user_id
        ];
        
        // Log for debugging
        error_log("Wishlist count for user $user_id: " . $count_data['count']);
        
        echo json_encode($response);
        mysqli_stmt_close($stmt);
        exit;
    } else {
        // For non-logged users, return 0 count (will be handled by localStorage)
        echo json_encode([
            'success' => true,
            'count' => 0,
            'is_logged_in' => false
        ]);
        exit;
    }
}

if (!$is_logged_in) {
    // For non-logged users, return success (will be handled by localStorage)
    echo json_encode([
        'success' => true, 
        'message' => 'Guest user - handle with localStorage',
        'is_logged_in' => false
    ]);
    exit;
}

// Validate product ID for other actions
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Check if product exists
$product_check = "SELECT id FROM products WHERE id = ? AND status = 'approved'";
$stmt = mysqli_prepare($db_connection, $product_check);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

switch ($action) {
    case 'add':
        // Check if already in wishlist
        $check_query = "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($db_connection, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            echo json_encode(['success' => false, 'message' => 'Product already in wishlist']);
            mysqli_stmt_close($stmt);
            exit;
        }
        mysqli_stmt_close($stmt);
        
        // Add to wishlist
        $insert_query = "INSERT INTO wishlist (user_id, product_id, created_date) VALUES (?, ?, NOW())";
        $stmt = mysqli_prepare($db_connection, $insert_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Added to wishlist',
                'is_logged_in' => true,
                'action' => 'added'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist']);
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'remove':
        // Remove from wishlist
        $delete_query = "DELETE FROM wishlist WHERE user_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($db_connection, $delete_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Removed from wishlist',
                    'is_logged_in' => true,
                    'action' => 'removed'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found in wishlist']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from wishlist']);
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'check':
        // Check if product is in wishlist
        $check_query = "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($db_connection, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $in_wishlist = mysqli_num_rows($result) > 0;
        
        echo json_encode([
            'success' => true,
            'in_wishlist' => $in_wishlist,
            'is_logged_in' => true
        ]);
        mysqli_stmt_close($stmt);
        break;
        
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

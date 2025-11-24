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
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Check for JSON decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'debug' => 'Raw input: ' . substr($raw_input, 0, 100)
    ]);
    exit;
}

$action = isset($input['action']) ? trim($input['action']) : '';
$product_id = isset($input['product_id']) ? (int)$input['product_id'] : 0;
$cart_id = isset($input['cart_id']) ? (int)$input['cart_id'] : 0;
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;
$selected_variants = isset($input['selected_variants']) ? $input['selected_variants'] : [];
$variant_prices = isset($input['variant_prices']) ? $input['variant_prices'] : [];

// Validate action
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

// Note: Guest users are handled by the cart manager in JavaScript (localStorage)
// This handler only processes logged-in users
if (!$is_logged_in) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please log in to add items to cart',
        'is_logged_in' => false
    ]);
    exit;
}

// Validate product ID for database operations
if ($product_id <= 0 && in_array($action, ['add', 'update', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Check if product exists (for add/update/remove operations)
if (in_array($action, ['add', 'update', 'remove'])) {
    $product_check = "SELECT id, product_name, selling_price, Inventory FROM products WHERE id = ? AND status = 'approved'";
    $stmt = mysqli_prepare($db_connection, $product_check);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . mysqli_error($db_connection)
        ]);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database execution error: ' . mysqli_stmt_error($stmt)
        ]);
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        mysqli_stmt_close($stmt);
        exit;
    }
    
    $product_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Check inventory for add/update operations
    if (in_array($action, ['add', 'update']) && $product_data['Inventory'] < $quantity) {
        echo json_encode([
            'success' => false, 
            'message' => 'Insufficient stock. Only ' . $product_data['Inventory'] . ' items available.'
        ]);
        exit;
    }
}

switch ($action) {
    case 'add':
        // Prepare variant data for storage
        $selected_variants_json = !empty($selected_variants) ? json_encode($selected_variants) : null;
        $variant_prices_json = !empty($variant_prices) ? json_encode($variant_prices) : null;
        
        // Calculate final price based on variant selection
        $final_price = $product_data['selling_price']; // Default to base price
        if (!empty($variant_prices) && isset($variant_prices['selling_price'])) {
            $final_price = floatval($variant_prices['selling_price']);
        }
        
        // Check if already in cart with same variants
        // For products without variants, check if there's any existing cart item for this product
        if (empty($selected_variants)) {
            // No variants - check for any existing cart item for this product
            $check_query = "SELECT id, quantity FROM user_cart WHERE user_id = ? AND product_id = ? AND (selected_variants IS NULL OR selected_variants = '' OR selected_variants = '{}')";
            $stmt = mysqli_prepare($db_connection, $check_query);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        } else {
            // Has variants - check for exact variant match
            $check_query = "SELECT id, quantity FROM user_cart WHERE user_id = ? AND product_id = ? AND selected_variants = ?";
            $stmt = mysqli_prepare($db_connection, $check_query);
            mysqli_stmt_bind_param($stmt, "iis", $user_id, $product_id, $selected_variants_json);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            // Update existing cart item
            $cart_item = mysqli_fetch_assoc($result);
            $new_quantity = $cart_item['quantity'] + $quantity;
            
            // Check inventory again
            if ($product_data['Inventory'] < $new_quantity) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot add more items. Only ' . $product_data['Inventory'] . ' items available, you already have ' . $cart_item['quantity'] . ' in cart.'
                ]);
                mysqli_stmt_close($stmt);
                exit;
            }
            
            mysqli_stmt_close($stmt);
            
            $update_query = "UPDATE user_cart SET quantity = ?, final_price = ?, variant_prices = ? WHERE user_id = ? AND product_id = ? AND selected_variants = ?";
            $stmt = mysqli_prepare($db_connection, $update_query);
            mysqli_stmt_bind_param($stmt, "idssis", $new_quantity, $final_price, $variant_prices_json, $user_id, $product_id, $selected_variants_json);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Cart updated successfully',
                    'is_logged_in' => true,
                    'action' => 'updated',
                    'new_quantity' => $new_quantity,
                    'final_price' => $final_price
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
            }
        } else {
            mysqli_stmt_close($stmt);
            
            // Add new item to cart with variant data
            $insert_query = "INSERT INTO user_cart (user_id, product_id, quantity, selected_variants, variant_prices, final_price, create_date) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($db_connection, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiisss", $user_id, $product_id, $quantity, $selected_variants_json, $variant_prices_json, $final_price);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Added to cart successfully',
                    'is_logged_in' => true,
                    'action' => 'added',
                    'quantity' => $quantity,
                    'final_price' => $final_price,
                    'selected_variants' => $selected_variants
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
            }
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'update':
        // Validate cart_id for update operations
        if ($cart_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
            exit;
        }
        
        // Check if cart item exists and belongs to user
        $cart_check = "SELECT id, product_id, quantity FROM user_cart WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($db_connection, $cart_check);
        
        if (!$stmt) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database error: ' . mysqli_error($db_connection)
            ]);
            exit;
        }
        
        mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Database execution error: ' . mysqli_stmt_error($stmt)
            ]);
            mysqli_stmt_close($stmt);
            exit;
        }
        
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 0) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            mysqli_stmt_close($stmt);
            exit;
        }
        
        $cart_item = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        // Get product inventory for stock check
        $product_check = "SELECT Inventory FROM products WHERE id = ? AND status = 'approved'";
        $stmt = mysqli_prepare($db_connection, $product_check);
        mysqli_stmt_bind_param($stmt, "i", $cart_item['product_id']);
        mysqli_stmt_execute($stmt);
        $product_result = mysqli_stmt_get_result($stmt);
        $product_data = mysqli_fetch_assoc($product_result);
        mysqli_stmt_close($stmt);
        
        // Check inventory for update operations
        if ($product_data['Inventory'] < $quantity) {
            echo json_encode([
                'success' => false, 
                'message' => 'Insufficient stock. Only ' . $product_data['Inventory'] . ' items available.'
            ]);
            exit;
        }
        
        // Update cart item quantity
        if ($quantity <= 0) {
            // If quantity is 0 or less, remove the item
            $delete_query = "DELETE FROM user_cart WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($db_connection, $delete_query);
            mysqli_stmt_bind_param($stmt, "ii", $cart_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item removed from cart',
                    'is_logged_in' => true,
                    'action' => 'removed'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
            }
        } else {
            $update_query = "UPDATE user_cart SET quantity = ? WHERE id = ? AND user_id = ?";
            $stmt = mysqli_prepare($db_connection, $update_query);
            mysqli_stmt_bind_param($stmt, "iii", $quantity, $cart_id, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $affected_rows = mysqli_stmt_affected_rows($stmt);
                if ($affected_rows > 0) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Cart updated successfully',
                        'is_logged_in' => true,
                        'action' => 'updated',
                        'new_quantity' => $quantity
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
            }
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'remove':
        // Remove specific variant from cart
        $selected_variants_json = !empty($selected_variants) ? json_encode($selected_variants) : null;
        
        if (empty($selected_variants)) {
            // No variants - remove any item for this product without variants
            $delete_query = "DELETE FROM user_cart WHERE user_id = ? AND product_id = ? AND (selected_variants IS NULL OR selected_variants = '' OR selected_variants = '{}')";
            $stmt = mysqli_prepare($db_connection, $delete_query);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        } else {
            // Has variants - remove exact variant match
            $delete_query = "DELETE FROM user_cart WHERE user_id = ? AND product_id = ? AND selected_variants = ?";
            $stmt = mysqli_prepare($db_connection, $delete_query);
            mysqli_stmt_bind_param($stmt, "iis", $user_id, $product_id, $selected_variants_json);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            if ($affected_rows > 0) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Removed from cart',
                    'is_logged_in' => true,
                    'action' => 'removed'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from cart']);
        }
        mysqli_stmt_close($stmt);
        break;
        
    case 'get_count':
        // Get cart count for user
        $count_query = "SELECT SUM(quantity) as count FROM user_cart WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $count_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count_data = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true,
            'count' => (int)($count_data['count'] ?? 0),
            'is_logged_in' => true
        ]);
        mysqli_stmt_close($stmt);
        break;
        
    case 'get_items':
        // Get cart items for header display with variant information
        $items_query = "
            SELECT c.id as cart_id, c.quantity, c.create_date, c.selected_variants, c.variant_prices, c.final_price,
                   p.id, p.product_name, p.selling_price, p.images, p.Inventory
            FROM user_cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND p.status = 'approved'
            ORDER BY c.create_date DESC
        ";
        
        $stmt = mysqli_prepare($db_connection, $items_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $items = [];
        $total = 0;
        $base_image_url = $vendor_baseurl;
        $default_image = $vendor_baseurl.'uploads/vendors/no-product.png';
        
        while ($row = mysqli_fetch_assoc($result)) {
            // Process images
            $product_images = [];
            if (!empty($row['images'])) {
                $images_data = json_decode($row['images'], true);
                if (is_array($images_data)) {
                    foreach ($images_data as $image_name) {
                        if (!empty($image_name)) {
                            // $product_images[] = $base_image_url . $image_name;
                            $product_images[] = $image_name;
                            break; // Just need the first image
                        }
                    }
                }
            }
            
            if (empty($product_images)) {
                $product_images[] = $default_image;
            }
            
            // Use final price if available (from variant selection), otherwise use base price
            $item_price = !empty($row['final_price']) ? floatval($row['final_price']) : $row['selling_price'];
            $subtotal = $item_price * $row['quantity'];
            $total += $subtotal;
            
            // Parse variant information
            $selected_variants = [];
            $variant_prices = [];
            if (!empty($row['selected_variants'])) {
                $selected_variants = json_decode($row['selected_variants'], true);
            }
            if (!empty($row['variant_prices'])) {
                $variant_prices = json_decode($row['variant_prices'], true);
            }
            
            $items[] = [
                'cart_id' => $row['cart_id'],
                'product_id' => $row['id'],
                'product_name' => $row['product_name'],
                'selling_price' => $item_price,
                'base_price' => $row['selling_price'],
                'quantity' => $row['quantity'],
                'subtotal' => $subtotal,
                'image' => $product_images[0],
                'inventory' => $row['Inventory'],
                'selected_variants' => $selected_variants,
                'variant_prices' => $variant_prices
            ];
        }
        
        echo json_encode([
            'success' => true,
            'items' => $items,
            'total' => $total,
            'count' => array_sum(array_column($items, 'quantity')),
            'is_logged_in' => true
        ]);
        mysqli_stmt_close($stmt);
        break;
        
    case 'clear':
        // Clear entire cart
        $clear_query = "DELETE FROM user_cart WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $clear_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true, 
                'message' => 'Cart cleared successfully',
                'is_logged_in' => true,
                'action' => 'cleared'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
        }
        mysqli_stmt_close($stmt);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

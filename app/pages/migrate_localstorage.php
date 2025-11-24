<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/init.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("Migration request received: " . json_encode($_SERVER));

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Migration failed: Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
error_log("Migration check - User ID: " . $user_id . ", Session data: " . json_encode($_SESSION));

if ($user_id <= 0) {
    error_log("Migration failed: User not logged in");
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'session_data' => $_SESSION]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$cart_data = isset($input['cart']) ? $input['cart'] : [];
$wishlist_data = isset($input['wishlist']) ? $input['wishlist'] : [];

error_log("Migration data received - Cart: " . json_encode($cart_data) . ", Wishlist: " . json_encode($wishlist_data));

$migrated_cart = 0;
$migrated_wishlist = 0;
$errors = [];

// Check database connection
if (!$db_connection) {
    error_log("Migration failed: Database connection not available");
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Start transaction
    mysqli_autocommit($db_connection, false);
    
    // Migrate cart data
    if (!empty($cart_data) && is_array($cart_data)) {
        foreach ($cart_data as $cart_item) {
            $product_id = (int)($cart_item['product_id'] ?? 0);
            $quantity = (int)($cart_item['quantity'] ?? 1);
            
            // Extract variant information
            $selected_variants = isset($cart_item['selected_variants']) ? $cart_item['selected_variants'] : [];
            $variant_prices = isset($cart_item['variant_prices']) ? $cart_item['variant_prices'] : [];
            
            if ($product_id <= 0) continue;
            
            // Check if product exists
            $product_check = "SELECT id, Inventory FROM products WHERE id = ? AND status = 'approved'";
            $stmt = mysqli_prepare($db_connection, $product_check);
            if (!$stmt) {
                $errors[] = 'Prepare failed (product_check cart): ' . mysqli_error($db_connection);
                error_log(end($errors));
                continue;
            }
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                $product = mysqli_fetch_assoc($result);
                $available_inventory = (int)$product['Inventory'];
                
                // Adjust quantity if it exceeds inventory
                if ($quantity > $available_inventory) {
                    $quantity = $available_inventory;
                }
                
                if ($quantity > 0) {
                    // Check if item already exists in cart
                    $check_cart = "SELECT id, quantity FROM user_cart WHERE user_id = ? AND product_id = ?";
                    $stmt = mysqli_prepare($db_connection, $check_cart);
                    if (!$stmt) {
                        $errors[] = 'Prepare failed (check_cart): ' . mysqli_error($db_connection);
                        error_log(end($errors));
                        continue;
                    }
                    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                    mysqli_stmt_execute($stmt);
                    $cart_result = mysqli_stmt_get_result($stmt);
                    
                    if (mysqli_num_rows($cart_result) > 0) {
                        // Update existing cart item
                        $existing_item = mysqli_fetch_assoc($cart_result);
                        $new_quantity = $existing_item['quantity'] + $quantity;
                        
                        // Adjust if exceeds inventory
                        if ($new_quantity > $available_inventory) {
                            $new_quantity = $available_inventory;
                        }
                        
                        $update_cart = "UPDATE user_cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                        $stmt = mysqli_prepare($db_connection, $update_cart);
                        if (!$stmt) {
                            $errors[] = 'Prepare failed (update_cart): ' . mysqli_error($db_connection);
                            error_log(end($errors));
                        } else {
                            mysqli_stmt_bind_param($stmt, "iii", $new_quantity, $user_id, $product_id);
                            mysqli_stmt_execute($stmt);
                        }
                    } else {
                        // Insert new cart item with variant data
                        $selected_variants_json = json_encode($selected_variants);
                        $variant_prices_json = json_encode($variant_prices);
                        
                        // Calculate final price if variant prices are available
                        $final_price = null;
                        if (!empty($variant_prices) && isset($variant_prices['selling_price'])) {
                            $final_price = (float)$variant_prices['selling_price'];
                        }
                        
                        $insert_cart = "INSERT INTO user_cart (user_id, product_id, quantity, selected_variants, variant_prices, final_price, create_date) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = mysqli_prepare($db_connection, $insert_cart);
                        if (!$stmt) {
                            $errors[] = 'Prepare failed (insert_cart): ' . mysqli_error($db_connection);
                            error_log(end($errors));
                        } else {
                            mysqli_stmt_bind_param($stmt, "iiisss", $user_id, $product_id, $quantity, $selected_variants_json, $variant_prices_json, $final_price);
                            mysqli_stmt_execute($stmt);
                        }
                    }
                    
                    $migrated_cart++;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Migrate wishlist data
    if (!empty($wishlist_data) && is_array($wishlist_data)) {
        foreach ($wishlist_data as $product_id) {
            $product_id = (int)$product_id;
            
            if ($product_id <= 0) continue;
            
            // Check if product exists
            $product_check = "SELECT id FROM products WHERE id = ? AND status = 'approved'";
            $stmt = mysqli_prepare($db_connection, $product_check);
            if (!$stmt) {
                $errors[] = 'Prepare failed (product_check wishlist): ' . mysqli_error($db_connection);
                error_log(end($errors));
                continue;
            }
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) > 0) {
                // Check if already in wishlist
                $check_wishlist = "SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?";
                $stmt = mysqli_prepare($db_connection, $check_wishlist);
                if (!$stmt) {
                    $errors[] = 'Prepare failed (check_wishlist): ' . mysqli_error($db_connection);
                    error_log(end($errors));
                    continue;
                }
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                mysqli_stmt_execute($stmt);
                $wishlist_result = mysqli_stmt_get_result($stmt);
                
                if (mysqli_num_rows($wishlist_result) == 0) {
                    // Insert new wishlist item
                    $insert_wishlist = "INSERT INTO wishlist (user_id, product_id, created_date) VALUES (?, ?, NOW())";
                    $stmt = mysqli_prepare($db_connection, $insert_wishlist);
                    if (!$stmt) {
                        $errors[] = 'Prepare failed (insert_wishlist): ' . mysqli_error($db_connection);
                        error_log(end($errors));
                    } else {
                        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                        mysqli_stmt_execute($stmt);
                    }
                    
                    $migrated_wishlist++;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    // Commit transaction
    mysqli_commit($db_connection);
    
    echo json_encode([
        'success' => true,
        'message' => 'Data migrated successfully',
        'migrated_cart' => $migrated_cart,
        'migrated_wishlist' => $migrated_wishlist,
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db_connection) {
        mysqli_rollback($db_connection);
    }
    
    $error_message = 'Migration failed: ' . $e->getMessage();
    error_log("Migration error for user $user_id: " . $e->getMessage());
    error_log("Migration error trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'errors' => $errors,
        'debug_info' => [
            'user_id' => $user_id,
            'cart_count' => count($cart_data),
            'wishlist_count' => count($wishlist_data),
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
} catch (Error $e) {
    // Handle PHP 7+ errors
    if ($db_connection) {
        mysqli_rollback($db_connection);
    }
    
    $error_message = 'Migration failed: ' . $e->getMessage();
    error_log("Migration PHP error for user $user_id: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $error_message,
        'error_type' => 'PHP Error',
        'debug_info' => [
            'user_id' => $user_id,
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile()
        ]
    ]);
} finally {
    // Restore autocommit
    if ($db_connection) {
        mysqli_autocommit($db_connection, true);
    }
}
?>

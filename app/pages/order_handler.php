<?php
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}   


define('RAZORPAY_KEY_ID', 'rzp_test_RNmDrl2AWS2wQF');       // replace with your test/live key
define('RAZORPAY_KEY_SECRET', 'eXKbGFujd3w2v1XAgOpXZn9V');
require_once __DIR__ . '/../../vendor/autoload.php';

use Razorpay\Api\Api;

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
        'message' => 'Invalid JSON data: ' . json_last_error_msg()
    ]);
    exit;
}

$action = isset($input['action']) ? trim($input['action']) : '';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

if (!$is_logged_in) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please login to place an order',
        'login_required' => true
    ]);
    exit;
}

switch ($action) {
    case 'place_order':
        placeOrder($input, $user_id, $db_connection);
        break;
        
    // New online order creation
    case 'create_online_order':
        createOnlineOrder($input, $user_id, $db_connection);
        break;

    // Verify Razorpay payment
    case 'verify_online_payment':
        verifyOnlinePayment($input, $user_id, $db_connection);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function createOnlineOrder($input, $user_id, $db_connection)
{
    try {
        // Validate required fields
        if (!isset($input['total_amount'], $input['address_id'], $input['products'])) {
            throw new Exception('Missing required fields for online order');
        }

        $total_amount = (float)$input['total_amount'];
        $address_id   = (int)$input['address_id'];
        $products     = $input['products'];
        $cart_amount  = isset($input['cart_amount']) ? (float)$input['cart_amount'] : 0;
        $shipping_charge = isset($input['shipping_charge']) ? (float)$input['shipping_charge'] : 0;
        $coupon_saving   = isset($input['coupon_saving']) ? (float)$input['coupon_saving'] : 0;
        $coupon_code     = isset($input['coupon_code']) ? trim($input['coupon_code']) : null;

        // Generate unique order ID
        $order_id = generateOrderId();

        // Calculate total coins
        $total_coins = 0;
        foreach ($products as $p) {
            $total_coins += (isset($p['coin']) ? $p['coin'] : 0) * (isset($p['quantity']) ? $p['quantity'] : 0);
        }

        // Enhance products with vendor_id and default product_status
        $productIds = array_map(fn($p) => (int)$p['product_id'], $products);
        $productIdToVendorId = [];
        if (!empty($productIds)) {
            $idsList = implode(',', array_unique($productIds));
            $vendorResult = mysqli_query($db_connection, "SELECT id, vendor_id FROM products WHERE id IN ($idsList) AND status='approved'");
            while ($row = mysqli_fetch_assoc($vendorResult)) {
                $productIdToVendorId[(int)$row['id']] = (int)$row['vendor_id'];
            }
        }

        foreach ($products as &$p) {
            $pid = (int)$p['product_id'];
            $p['vendor_id'] = $productIdToVendorId[$pid] ?? null;
            $p['product_status'] = 'pending';
        }
        unset($p);

        $products_json = mysqli_real_escape_string($db_connection, json_encode($products));

        // Insert order into DB with payment_status = 'Pending'
        $insert_order_sql = "INSERT INTO `order` 
            (user_id, products, order_id, cart_amount, shipping_charge, coupan_saving, total_amount, coin_earn, payment_method, payment_status, address_id, order_status, date, created_date) 
            VALUES 
            ($user_id, '$products_json', $order_id, $cart_amount, $shipping_charge, $coupon_saving, $total_amount, $total_coins, 'online', 'Pending', $address_id, 'Pending', CURDATE(), NOW())";

        if (!mysqli_query($db_connection, $insert_order_sql)) {
            throw new Exception('Failed to create online order: ' . mysqli_error($db_connection));
        }

        // Create Razorpay order
        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        $razorpayOrder = $api->order->create([
            'receipt' => 'rcpt_' . $order_id,
            'amount' => (int)($total_amount * 100), // amount in paise
            'currency' => 'INR',
            'payment_capture' => 1
        ]);

        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'razorpay_key' => RAZORPAY_KEY_ID,
            'coin_earn' => $total_coins,
            'amount' => (int)($total_amount * 100)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Verify Razorpay payment and update order details
function verifyOnlinePayment($input, $user_id, $db_connection)
{
    try {
        $required = ['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature', 'order_id'];
        foreach ($required as $field) {
            if (!isset($input[$field])) throw new Exception("Missing field: $field");
        }

        $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
        $api->utility->verifyPaymentSignature([
            'razorpay_order_id' => $input['razorpay_order_id'],
            'razorpay_payment_id' => $input['razorpay_payment_id'],
            'razorpay_signature' => $input['razorpay_signature']
        ]);

        $order_id = (int)$input['order_id'];

        // Update payment status and store Razorpay payment ID
        $update_order = "UPDATE `order` SET 
            payment_status='Paid', 
            order_status='Processing', 
            payment_id='" . mysqli_real_escape_string($db_connection, $input['razorpay_payment_id']) . "'
            WHERE order_id=$order_id AND user_id=$user_id";

        if (!mysqli_query($db_connection, $update_order)) {
            throw new Exception('Failed to update order payment: ' . mysqli_error($db_connection));
        }

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Payment verification failed: ' . $e->getMessage()]);
    }
}

function placeOrder($input, $user_id, $db_connection)
{
    try {
        // Start transaction
        mysqli_autocommit($db_connection, false);
        
        // Validate required fields
        $required_fields = ['products', 'address_id', 'payment_method', 'cart_amount', 'total_amount'];
        foreach ($required_fields as $field) {  
            if (!isset($input[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        $products = $input['products'];
        $address_id = (int)$input['address_id'];
        $payment_method = $input['payment_method'];
        $cart_amount = (float)$input['cart_amount'];
        $shipping_charge = isset($input['shipping_charge']) ? (float)$input['shipping_charge'] : 0;
        $coupon_saving = isset($input['coupon_saving']) ? (float)$input['coupon_saving'] : 0;
        $total_amount = (float)$input['total_amount'];
        $coupon_code = isset($input['coupon_code']) ? trim($input['coupon_code']) : null;
        
        // Generate unique order ID
        $order_id = generateOrderId();
        
        // Calculate total coins earned
        $total_coins = 0;
        foreach ($products as $product) {
            $total_coins += ($product['coin'] * $product['quantity']);
        }
        
        // Enrich products with vendor_id and default product_status = 'pending'
        $productIds = [];
        foreach ($products as $p) {
            if (isset($p['product_id'])) {
                $productIds[] = (int)$p['product_id'];
            }
        }

        $productIdToVendorId = [];
        if (!empty($productIds)) {
            $idsList = implode(',', array_unique($productIds));
            $vendorQuery = "SELECT id, vendor_id FROM products WHERE id IN ($idsList) AND status = 'approved'";
            $vendorResult = mysqli_query($db_connection, $vendorQuery);
            if ($vendorResult) {
                while ($row = mysqli_fetch_assoc($vendorResult)) {
                    $productIdToVendorId[(int)$row['id']] = isset($row['vendor_id']) ? (int)$row['vendor_id'] : null;
                }
                mysqli_free_result($vendorResult);
            }
        }

        $products_enhanced = [];
        foreach ($products as $p) {
            $pid = isset($p['product_id']) ? (int)$p['product_id'] : 0;
            $p['vendor_id'] = isset($productIdToVendorId[$pid]) ? $productIdToVendorId[$pid] : null;
            $p['product_status'] = 'pending';
            
            // Add variant information if available
            if (isset($p['selected_variants'])) {
                $p['selected_variants'] = $p['selected_variants'];
            }
            if (isset($p['variant_prices'])) {
                $p['variant_prices'] = $p['variant_prices'];
            }
            
            $products_enhanced[] = $p;
        }

        // Prepare products JSON
        $products_json = json_encode($products_enhanced);
        
        // Get current date
        $current_date = date('d-m-Y');
        
        // Insert order into database using direct query (avoiding prepared statement issues)
        $payment_status = 'Pending';
        $order_status = 'Pending';
        
        $products_json_escaped = mysqli_real_escape_string($db_connection, $products_json);
        $payment_method_escaped = mysqli_real_escape_string($db_connection, $payment_method);
        $payment_status_escaped = mysqli_real_escape_string($db_connection, $payment_status);
        $order_status_escaped = mysqli_real_escape_string($db_connection, $order_status);
        $current_date_escaped = mysqli_real_escape_string($db_connection, $current_date);
        
        $insert_order = "INSERT INTO `order` (
            user_id, products, order_id, cart_amount, shipping_charge, 
            coupan_saving, total_amount, coin_earn, payment_method, 
            payment_status, address_id, order_status, date, created_date
        ) VALUES (
            $user_id, '$products_json_escaped', $order_id, $cart_amount, $shipping_charge,
            $coupon_saving, $total_amount, $total_coins, '$payment_method_escaped',
            '$payment_status_escaped', $address_id, '$order_status_escaped', '$current_date_escaped', NOW()
        )";
        
        if (!mysqli_query($db_connection, $insert_order)) {
            throw new Exception('Failed to insert order: ' . mysqli_error($db_connection));
        }
        
        $order_db_id = mysqli_insert_id($db_connection);
        
        // Update product inventory
        foreach ($products as $product) {
            $product_id = (int)$product['product_id'];
            $quantity = (int)$product['quantity'];
            
            $update_inventory = "UPDATE products SET Inventory = Inventory - ? WHERE id = ? AND Inventory >= ?";
            $stmt = mysqli_prepare($db_connection, $update_inventory);
            
            if (!$stmt) {
                throw new Exception('Database error: ' . mysqli_error($db_connection));
            }
            
            mysqli_stmt_bind_param($stmt, "iii", $quantity, $product_id, $quantity);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Insufficient inventory for product ID: ' . $product_id);
            }
            
            if (mysqli_stmt_affected_rows($stmt) === 0) {
                throw new Exception('Insufficient inventory for product ID: ' . $product_id);
            }
            
            mysqli_stmt_close($stmt);
        }
        
        // Update coupon usage if coupon was applied
        if ($coupon_code) {
            $update_coupon = "UPDATE coupons SET usage_count = usage_count + 1 WHERE coupon_code = ?";
            $stmt = mysqli_prepare($db_connection, $update_coupon);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $coupon_code);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        // Clear user's cart
        $clear_cart = "DELETE FROM user_cart WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $clear_cart);
        
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to clear cart: ' . mysqli_stmt_error($stmt));
        }
        
        mysqli_stmt_close($stmt);
        
        // Insert coin transaction
        if ($total_coins > 0) {
            $insert_coin_transaction = "INSERT INTO coin_transactions (
                user_id, order_id, coint, type, method, comment, created_date
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = mysqli_prepare($db_connection, $insert_coin_transaction);
            
            if ($stmt) {
                $coin_type = 'earned';
                $coin_method = 'order_purchase';
                $coin_comment = "Earned {$total_coins} coins from order #{$order_id}";
                
                mysqli_stmt_bind_param($stmt, "isisss", 
                    $user_id, $order_id, $total_coins, $coin_type, $coin_method, $coin_comment
                );
                
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        
        // Commit transaction
        mysqli_commit($db_connection);
        
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $order_id,
            'total_amount' => $total_amount,
            'coins_earned' => $total_coins
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($db_connection);
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } finally {
        // Restore autocommit
        mysqli_autocommit($db_connection, true);
    }
}

function generateOrderId() {
    // Generate a random order ID starting from 23132
    $base_id = 23132;
    $random_part = mt_rand(100, 999);
    return $base_id + $random_part;
}
?>
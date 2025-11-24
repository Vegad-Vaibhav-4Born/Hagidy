<?php
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}

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
$coupon_code = isset($input['coupon_code']) ? trim($input['coupon_code']) : '';
$cart_total = isset($input['cart_total']) ? floatval($input['cart_total']) : 0;

// Check if user is logged in (optional for coupons)
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

switch ($action) {
    case 'validate':
        if (empty($coupon_code)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a coupon code']);
            exit;
        }

        if ($cart_total <= 0) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
            exit;
        }

        // Validate coupon from database
        $coupon_query = "
            SELECT id, coupan_code, coupan_limit, category, sub_category, type, 
                   minimum_order_value, start_date, end_date, status, created_date, discount
            FROM coupans 
            WHERE coupan_code = ? AND status = 'Active'
        ";
        
        $stmt = mysqli_prepare($db_connection, $coupon_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "s", $coupon_code);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid coupon code']);
            mysqli_stmt_close($stmt);
            exit;
        }

        $coupon = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Validate coupon dates
        $current_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime($coupon['start_date']));
        $end_date = date('Y-m-d', strtotime($coupon['end_date']));

        if ($current_date < $start_date) {
            echo json_encode(['success' => false, 'message' => 'Coupon is not yet active']);
            exit;
        }

        if ($current_date > $end_date) {
            echo json_encode(['success' => false, 'message' => 'Coupon has expired']);
            exit;
        }

        // Validate minimum order value
        $minimum_order = floatval($coupon['minimum_order_value']);
        if ($cart_total < $minimum_order) {
            echo json_encode([
                'success' => false, 
                'message' => 'Minimum order value of ₹' . number_format($minimum_order, 2) . ' required'
            ]);
            exit;
        }

        // Validate discount value
        $discount_value = floatval($coupon['discount']);
        if ($discount_value <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid coupon discount value']);
            exit;
        }

        // Additional validation based on coupon type
        switch (strtolower($coupon['type'])) {
            case 'percentage':
                if ($discount_value > 100) {
                    echo json_encode(['success' => false, 'message' => 'Invalid percentage discount']);
                    exit;
                }
                break;
            case 'fixed amount':
                // Fixed amount validation can be flexible, but should be reasonable
                if ($discount_value > 50000) { // Max discount of ₹50,000
                    echo json_encode(['success' => false, 'message' => 'Discount amount too high']);
                    exit;
                }
                break;
        }

        // Calculate discount based on coupon type using discount column
        $discount_amount = 0;
        $discount_type = '';
        $free_shipping = false;

        switch (strtolower($coupon['type'])) {
            case 'free shipping':
                $free_shipping = true;
                $discount_type = 'Free Shipping';
                $discount_amount = 0; // Free shipping doesn't reduce cart total
                break;

            case 'percentage':
                // Use discount column value as percentage
                $discount_amount = ($cart_total * $discount_value) / 100;
                $discount_type = $discount_value . '% Off';
                break;

            case 'fixed amount':
                // Use discount column value as fixed amount
                $discount_amount = $discount_value;
                // Don't let discount exceed cart total
                if ($discount_amount > $cart_total) {
                    $discount_amount = $cart_total;
                }
                $discount_type = '₹' . number_format($discount_amount, 2) . ' Off';
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid coupon type']);
                exit;
        }

        // Store coupon in session for later use
        $_SESSION['applied_coupon'] = [
            'id' => $coupon['id'],
            'code' => $coupon['coupan_code'],
            'type' => $coupon['type'],
            'limit' => $coupon['coupan_limit'],
            'discount_value' => $discount_value, // Store discount column value
            'discount_amount' => $discount_amount,
            'discount_type' => $discount_type,
            'free_shipping' => $free_shipping,
            'minimum_order_value' => $minimum_order,
            'applied_at' => time()
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Coupon applied successfully!',
            'coupon' => [
                'code' => $coupon['coupan_code'],
                'type' => $coupon['type'],
                'discount_amount' => $discount_amount,
                'discount_type' => $discount_type,
                'free_shipping' => $free_shipping,
                'new_total' => max(0, $cart_total - $discount_amount)
            ]
        ]);
        break;

    case 'remove':
        // Remove coupon from session
        if (isset($_SESSION['applied_coupon'])) {
            unset($_SESSION['applied_coupon']);
            echo json_encode([
                'success' => true,
                'message' => 'Coupon removed successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No coupon to remove'
            ]);
        }
        break;

    case 'get_applied':
        // Get currently applied coupon
        if (isset($_SESSION['applied_coupon'])) {
            $applied_coupon = $_SESSION['applied_coupon'];
            
            // Check if coupon is still valid (not expired in session)
            $applied_time = $applied_coupon['applied_at'];
            $current_time = time();
            
            // Coupon expires from session after 24 hours
            if (($current_time - $applied_time) > 86400) {
                unset($_SESSION['applied_coupon']);
                echo json_encode([
                    'success' => false,
                    'message' => 'Coupon session expired'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'coupon' => $applied_coupon
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No coupon applied'
            ]);
        }
        break;

    case 'recalculate':
        // Recalculate coupon discount for updated cart total
        if (!isset($_SESSION['applied_coupon'])) {
            echo json_encode([
                'success' => false,
                'message' => 'No coupon applied'
            ]);
            exit;
        }

        $applied_coupon = $_SESSION['applied_coupon'];
        
        // Validate minimum order value again
        $minimum_order = floatval($applied_coupon['minimum_order_value']);
        if ($cart_total < $minimum_order) {
            // Remove coupon if cart total falls below minimum
            unset($_SESSION['applied_coupon']);
            echo json_encode([
                'success' => false,
                'message' => 'Coupon removed: Cart total below minimum order value of ₹' . number_format($minimum_order, 2),
                'coupon_removed' => true
            ]);
            exit;
        }

        // Recalculate discount using stored discount value
        $discount_amount = 0;
        $free_shipping = false;
        $discount_value = floatval($applied_coupon['discount_value']); // Use stored discount value

        switch (strtolower($applied_coupon['type'])) {
            case 'free shipping':
                $free_shipping = true;
                $discount_amount = 0;
                break;

            case 'percentage':
                $discount_amount = ($cart_total * $discount_value) / 100;
                break;

            case 'fixed amount':
                $discount_amount = $discount_value;
                if ($discount_amount > $cart_total) {
                    $discount_amount = $cart_total;
                }
                break;
        }

        // Update session with new discount amount
        $_SESSION['applied_coupon']['discount_amount'] = $discount_amount;

        echo json_encode([
            'success' => true,
            'coupon' => [
                'code' => $applied_coupon['code'],
                'type' => $applied_coupon['type'],
                'discount_amount' => $discount_amount,
                'discount_type' => $applied_coupon['discount_type'],
                'free_shipping' => $free_shipping,
                'new_total' => max(0, $cart_total - $discount_amount)
            ]
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>

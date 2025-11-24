<?php
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
// Initialize variables
$cart_items = [];
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$base_image_url = $vendor_baseurl;
$default_image = $vendor_baseurl . 'uploads/vendors/no-product.png';
$cart_total = 0;
$cart_count = 0;
$user_details = null;

if ($is_logged_in) {
    $user_query = "SELECT id, first_name, last_name, email, mobile, customer_id FROM users WHERE id = ?";
    $stmt = mysqli_prepare($db_connection, $user_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) > 0) {
            $user_details = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}

if ($user_details) {
    $full_name = $user_details['first_name'] . ' ' . $user_details['last_name'];
    $email = $user_details['email'];
    $mobile = $user_details['mobile'];
    $customerId = $user_details['customer_id'];
} else {
    $full_name = $email = $mobile = $customerId = '';
}

if ($is_logged_in) {
    // Get cart items from database for logged-in users
    $cart_query = "
        SELECT c.id as cart_id, c.quantity, c.create_date, c.selected_variants, c.variant_prices, c.final_price,
               p.id, p.product_name, p.selling_price, p.mrp, p.images, p.Inventory, p.coin
        FROM user_cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? AND p.status = 'approved'
        ORDER BY c.create_date DESC
    ";

    $stmt = mysqli_prepare($db_connection, $cart_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
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
                                break; // Just need first image
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
                $cart_total += $subtotal;
                $cart_count += $row['quantity'];

                // Parse variant information
                $selected_variants = [];
                $variant_prices = [];
                if (!empty($row['selected_variants'])) {
                    $selected_variants = json_decode($row['selected_variants'], true);
                }
                if (!empty($row['variant_prices'])) {
                    $variant_prices = json_decode($row['variant_prices'], true);
                }

                $row['images_array'] = $product_images;
                $row['subtotal'] = $subtotal;
                $row['item_price'] = $item_price;
                $row['selected_variants'] = $selected_variants;
                $row['variant_prices'] = $variant_prices;
                $cart_items[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>CART | HAGIDY</title>

    <meta name="keywords" content="" />
    <meta name="description" content="">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">

    <!-- WebFont.js -->
    <script>
        WebFontConfig = {
            google: {
                families: ['Poppins:400,500,600,700']
            }
        };
        (function(d) {
            var wf = d.createElement('script'),
                s = d.scripts[0];
            wf.src = '<?php echo PUBLIC_ASSETS; ?>js/webfont.js';
            wf.async = true;
            s.parentNode.insertBefore(wf, s);
        })(document);
    </script>

    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-regular-400.woff" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-solid-900.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-brands-400.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>fonts/wolmart.ttf?png09e" as="font" type="font/ttf" crossorigin="anonymous">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/animate/animate.min.css">

    <!-- Plugin CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/default-skin/default-skin.min.css">

    <!-- Default CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/cart.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Dynamic Cart Styles -->

</head>

<body>
    <!-- Notification Container -->
    <div class="notification-container" id="notification-container"></div>

    <!-- Login Required Modal -->
    <div class="login-required-modal" id="login-required-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon">
                <i class="fas fa-user-lock"></i>
            </div>
            <div class="login-required-title">Login Required</div>
            <div class="login-required-message">
                Please login to your account to proceed with checkout, add addresses and manage your cart.
            </div>
            <div class="login-required-buttons">
                <a href="login.php" class="btn-login-required primary">Login Now</a>
            </div>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div class="login-required-modal" id="payment-method-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="login-required-title">Select Payment Method</div>
            <div class="login-required-message">
                Choose your preferred payment method to complete the order.
            </div>
            <div class="payment-methods">
                <!-- <div class="payment-option" data-method="cod">
                    <div class="payment-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-name">Cash on Delivery (COD)</div>
                        <div class="payment-desc">Pay when your order arrives</div>
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/Hagidy-QR-Code.jpg" alt="QR Code">
                        <div class="payment-name">Direct Payment</div>
                        <div class="payment-desc">After making payment to the qr code. please click proceed to order.</div>
                    </div>
                    <div class="payment-radio">
                        <input type="radio" name="payment_method" value="cod" id="cod-payment" checked>
                        <label for="cod-payment"></label>
                    </div>
                </div> -->
                <div class="payment-option" data-method="online">
                    <div class="payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="payment-details">
                        <div class="payment-name">Online Payment</div>
                        <div class="payment-desc">Pay securely online</div>
                    </div>
                    <div class="payment-radio">
                        <input type="radio" name="payment_method" value="online" id="online-payment">
                        <label for="online-payment"></label>
                    </div>
                </div>
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="proceedWithPayment()">Proceed to Order</button>
                <button class="btn-login-required secondary" onclick="closePaymentModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Order Success Modal -->
    <div class="login-required-modal" id="order-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title">Order Placed Successfully!</div>
            <div class="login-required-message">
                <div id="order-success-details">
                    Your order has been placed successfully.<br>
                    <strong>Order ID:</strong> <span id="order-id-display"></span><br>
                    <strong>Total Amount:</strong> <span id="order-total-display"></span>
                </div>
            </div>
            <div class="login-required-buttons">
                <a href="my-account.php#account-orders" class="btn-login-required primary">View My Orders</a>
                <a href="shop.php" class="btn-login-required secondary">Continue Shopping</a>
            </div>
        </div>
    </div>

    <div class="page-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <!-- Start of Main -->
        <main class="main cart">
            <!-- Start of Breadcrumb -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>

                            <li>Cart</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->

            <!-- Start of PageContent -->
            <div class="page-content">
                <div class="container">
                    <div class="row gutter-lg mb-10">
                        <div class="col-lg-8 pr-lg-4 mb-6">
                            <table class="shop-table cart-table">
                                <thead>
                                    <tr>
                                        <th class="product-name"><span><b>Product</b></span></th>
                                        <th></th>
                                        <th class="product-name"><span><b>Price</b></span></th>
                                        <th class="product-name"><span><b>Quantity</b></span></th>
                                        <th class="product-name"><span><b>Subtotal</b></span></th>
                                    </tr>
                                </thead>
                                <tbody id="cart-table-body">
                                    <?php if ($is_logged_in && !empty($cart_items)): ?>
                                        <?php foreach ($cart_items as $item): ?>
                                            <tr data-product-id="<?php echo $item['id']; ?>" data-cart-id="<?php echo $item['cart_id']; ?>">
                                                <td class="product-thumbnail">
                                                    <div class="p-relative">
                                                        <a href="product-detail.php?id=<?php echo $item['id']; ?>">
                                                            <figure>
                                                                <img src="<?php echo e($item['images_array'][0]); ?>"
                                                                    alt="<?php echo e($item['product_name']); ?>"
                                                                    width="300" height="338"
                                                                    onerror="this.src='<?php echo $default_image; ?>';">
                                                            </figure>
                                                        </a>
                                                        <button class="btn btn-link btn-close cart-remove-item"
                                                            data-product-id="<?php echo $item['id']; ?>"
                                                            data-cart-id="<?php echo $item['cart_id']; ?>"
                                                            data-selected-variants="<?php echo htmlspecialchars(json_encode($item['selected_variants']), ENT_QUOTES); ?>">
                                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="product-name">
                                                    <a href="product-detail.php?id=<?php echo $item['id']; ?>">
                                                        <?php echo e($item['product_name']); ?>
                                                    </a>
                                                    <br>

                                                    <!-- Display selected variants -->
                                                    <?php if (!empty($item['selected_variants'])): ?>
                                                        <div class="selected-variants mt-2">
                                                            <?php foreach ($item['selected_variants'] as $key => $value): ?>
                                                                <?php if (!empty($value) && !str_ends_with($key, '_variant_id')): ?>
                                                                    <span class="variant-item">
                                                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong>
                                                                        <?php if ($key === 'color'): ?>
                                                                            <span class="color-swatch" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo e($value); ?>; margin-left: 5px; vertical-align: middle;"></span>
                                                                        <?php endif; ?>
                                                                        <?php echo e($value); ?>
                                                                    </span>
                                                                    <br>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (isset($item['Inventory'])): ?>
                                                        <small class="stock-info <?php echo $item['Inventory'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $item['Inventory'] > 0 ? 'In Stock ' : 'Out of Stock'; ?>
                                                        </small>
                                                        <br>
                                                    <?php endif; ?>
                                                    <?php if (isset($item['coin']) && $item['coin'] > 0): ?>
                                                        <span class="d-block product-price1">You will earn
                                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid ml-1" alt="" style="width: 20px; height: 20px;">
                                                            <span class="coin-value"><?php echo number_format(((float)$item['coin']) * (float)$item['quantity'], 2); ?></span> coins
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="product-price text-center">
                                                    <span class="amount">₹<?php echo number_format($item['item_price'], 2); ?></span>
                                                    <?php if ($item['item_price'] != $item['selling_price']): ?>
                                                        <br><small class="text-muted">Base: ₹<?php echo number_format($item['selling_price'], 2); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="product-quantity text-center">
                                                    <div class="cart-position1">
                                                        <div class="cart-position">
                                                            <button class="quantity-plus w-icon-plus button2"
                                                                data-product-id="<?php echo $item['id']; ?>"
                                                                data-cart-id="<?php echo $item['cart_id']; ?>"
                                                                data-max-stock="<?php echo $item['Inventory']; ?>"></button>
                                                            <input class="quantity form-control text-center"
                                                                type="number"
                                                                min="0"
                                                                max="<?php echo $item['Inventory']; ?>"
                                                                value="<?php echo $item['quantity']; ?>"
                                                                data-initial-qty="<?php echo $item['quantity']; ?>"
                                                                data-product-id="<?php echo $item['id']; ?>"
                                                                data-cart-id="<?php echo $item['cart_id']; ?>"
                                                                data-price="<?php echo $item['item_price']; ?>"
                                                                data-coin="<?php echo $item['coin']; ?>"
                                                                data-selected-variants="<?php echo htmlspecialchars(json_encode($item['selected_variants']), ENT_QUOTES); ?>"
                                                                data-variant-prices="<?php echo htmlspecialchars(json_encode($item['variant_prices']), ENT_QUOTES); ?>">
                                                            <button class="quantity-minus w-icon-minus button1"
                                                                data-product-id="<?php echo $item['id']; ?>"
                                                                data-cart-id="<?php echo $item['cart_id']; ?>"></button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="product-subtotal text-center">
                                                    <span class="amount subtotal-amount">₹<?php echo number_format($item['subtotal'], 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif ($is_logged_in): ?>
                                        <tr id="empty-cart-row">
                                            <td colspan="5" class="text-center py-5">
                                                <div class="empty-cart">
                                                    <i class="w-icon-cart" style="font-size: 48px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                                                    <h4>Your cart is empty</h4>
                                                    <p>Add some products to your cart to see them here.</p>
                                                    <a href="shop.php" class="btn btn-primary btn-rounded">Continue Shopping</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- Guest user cart will be populated by JavaScript -->
                                        <tr id="loading-cart-row">
                                            <td colspan="5" class="text-center py-5">
                                                <div class="loading-cart">
                                                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                    <p>Loading your cart...</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                            <div class="cart-action mb-6">
                                <a href="./shop.php"
                                    class="btn btn-dark btn-rounded btn-icon-left btn-shopping mr-auto">Continue
                                    Shopping</a>
                                <button class="btn btn-rounded btn-default btn-clear" name="clear_cart"
                                    value="Clear Cart">Clear Cart</button>
                            </div>

                        </div>
                        <div class="col-lg-4 sticky-sidebar-wrapper">
                            <div class="cart-summary mb-4">
                                <h3 class="cart-title text-uppercase cart-title1">Coupon Discount</h3>

                                <!-- Coupon Input Form (shown when no coupon applied) -->
                                <div id="coupon-input-form">
                                    <div class="form-group mb-3">
                                        <input class="form-control form-control-md" type="text" id="coupon-input"
                                            placeholder="Enter coupon code here..." maxlength="50">
                                        <div id="coupon-message" class="mt-2" style="display: none;"></div>
                                    </div>
                                    <button type="button" id="apply-coupon-btn" class="btn btn-block1 btn-icon-right btn-rounded btn-checkout1">
                                        <span id="apply-coupon-text">Apply coupon</span>
                                        <i id="apply-coupon-loader" class="fas fa-spinner fa-spin" style="display: none; margin-left: 8px;"></i>
                                    </button>
                                </div>

                                <!-- Applied Coupon Display (shown when coupon is applied) -->
                                <div id="applied-coupon-display" style="display: none;">
                                    <div class="applied-coupon-info">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <strong>Coupon Applied: <span id="applied-coupon-code"></span></strong>
                                                <br>
                                                <small class="text-success" id="applied-coupon-type"></small>
                                            </div>
                                            <button type="button" id="remove-coupon-btn" class="btn btn-sm btn-outline-danger">
                                                Remove
                                            </button>
                                        </div>
                                        <div class="coupon-savings-display">
                                            <span class="text-success">
                                                <strong>You Save: <span id="coupon-discount-amount">₹0.00</span></strong>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button class="btn-earn btn mb-4" id="total-coins-earn">You will earn <img
                                        src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" alt="" style="width: 20px; height: 20px;">
                                    <span id="total-coins"><?php echo number_format((float)(array_sum(array_column($cart_items, 'coin')) * array_sum(array_column($cart_items, 'quantity'))), 2); ?></span> on this order</button>
                            </div>
                            <div class="cart-summary mb-4">
                                <h3 class="cart-title cart-title1 text-uppercase ">Payment Method
                                </h3>
                                <div class="form-group">
                                    <div class="select-box">
                                        <select name="country" class="form-control form-control-md">
                                            <option value="online" selected="selected">Online Payment</option>
                                            <option value="credit">Cash on Delivery</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="cart-summary mb-4">
                                <h3 class="cart-title cart-title1 text-uppercase ">Shipping to
                                </h3>

                                <div class="plans" id="address-list">
                                    <!-- Dynamic addresses will be loaded here -->
                                    <div id="address-loading" class="text-center py-3">
                                        <i class="fas fa-spinner fa-spin"></i> Loading addresses...
                                    </div>
                                </div>

                                <div class="btn btn-block1 btn-icon-right btn-rounded btn-checkout1" id="add-address-btn">
                                    + ADD ADDRESS
                                </div>
                            </div>
                            <div id="editModal" class="modal modal-lg"
                                style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); overflow-x: scroll;">
                                <div
                                    style="background:#fff; margin:5% auto; padding:20px 20px 20px 20px; border-radius:8px; width:90%; max-width:800px; position:relative; ">
                                    <div style="border-bottom: 1px solid #EBEBEB; margin: 10px 0; padding: 10px 0;"
                                        class="margin-bt-add-add">
                                        <span id="closeModal"
                                            style="position:absolute; right:25px; top:38px; font-size:14px; cursor:pointer; ">Close</span>
                                        <h4>ADD ADDRESS</h4>
                                    </div>
                                    <div class="row g-3">

                                        <!-- Address Fields -->
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="addressType" class="name-first">Address Type
                                                        <span>*</span></label>
                                                </div>
                                                <select id="addressType" name="addressType"
                                                    class="form-control form-control-md" required>
                                                    <option value="">Select</option>
                                                    <option value="home">Home</option>
                                                    <option value="business">Business</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="country" class="name-first">Country/Region</label>
                                                </div>
                                                <select id="country" name="country" class="form-control form-control-md">
                                                    <option value="">Select Country/Region</option>
                                                    <!-- Countries will be loaded dynamically -->
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="streetAddress" class="name-first">Street Address
                                                        <span>*</span></label>
                                                </div>
                                                <input type="text" id="streetAddress" name="streetAddress"
                                                    placeholder="House number and street name"
                                                    class="form-control form-control-md mb-2" required>
                                                <input type="text" id="streetAddress2" name="streetAddress2"
                                                    placeholder="Apartment, suite, unit, etc. (optional)"
                                                    class="form-control form-control-md">
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="town" class="name-first">Town/City
                                                        <span>*</span></label>
                                                </div>
                                                <input type="text" id="town" name="town" placeholder="Enter Town/City"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="state" class="name-first">State <span>*</span></label>
                                                </div>
                                                <select id="state" name="state" class="form-control form-control-md"
                                                    required>
                                                    <option value="">Select State</option>
                                                    <!-- States will be loaded dynamically based on country -->
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="pincode" class="name-first">PIN Code
                                                        <span>*</span></label>
                                                </div>
                                                <input type="number" id="pincode" name="pincode"
                                                    placeholder="Enter PIN Code" class="form-control form-control-md"
                                                    pattern="[0-9]*" inputmode="numeric" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="mobile" class="name-first">Mobile Number
                                                        <span>*</span></label>
                                                </div>
                                                <input type="number" id="mobile" name="mobile"
                                                    placeholder="Enter Mobile Number"
                                                    pattern="[0-9]*" inputmode="numeric" class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="email" class="name-first">Email Address</label>
                                                </div>
                                                <input type="email" id="email" name="email" placeholder="Enter Email"
                                                    class="form-control form-control-md">
                                            </div>
                                        </div>
                                        <div
                                            class="form-checkbox d-flex align-items-center justify-content-between mb-3">
                                            <input type="checkbox" class="custom-checkbox" id="remember" name="remember"
                                                required="">
                                            <label for="remember">Set as a primary Address</label>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" id="save-address-btn" class="btn btn-svae1">
                                                <span id="save-address-text">Add Address</span>
                                                <i id="save-address-loader" class="fas fa-spinner fa-spin ml-2" style="display: none;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Address Modal -->
                            <div id="editAddressModal" class="modal modal-lg"
                                style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); overflow-x: scroll;">
                                <div
                                    style="background:#fff; margin:5% auto; padding:20px 20px 20px 20px; border-radius:8px; width:90%; max-width:800px; position:relative; ">
                                    <div style="border-bottom: 1px solid #EBEBEB; margin: 10px 0; padding: 10px 0;"
                                        class="margin-bt-add-add">
                                        <span id="closeEditModal"
                                            style="position:absolute; right:25px; top:38px; font-size:14px; cursor:pointer; ">Close</span>
                                        <h4>EDIT ADDRESS</h4>
                                    </div>
                                    <div class="row g-3">
                                        <input type="hidden" id="editAddressId" name="editAddressId">

                                        <!-- Address Fields (same as add modal) -->
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editAddressType" class="name-first">Address Type
                                                        <span>*</span></label>
                                                </div>
                                                <select id="editAddressType" name="editAddressType"
                                                    class="form-control form-control-md" required>
                                                    <option value="">Select</option>
                                                    <option value="home">Home</option>
                                                    <option value="business">Business</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editCountry" class="name-first">Country/Region</label>
                                                </div>
                                                <select id="editCountry" name="editCountry" class="form-control form-control-md">
                                                    <option value="">Select Country/Region</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editStreetAddress" class="name-first">Street Address
                                                        <span>*</span></label>
                                                </div>
                                                <input type="text" id="editStreetAddress" name="editStreetAddress"
                                                    placeholder="House number and street name"
                                                    class="form-control form-control-md" required>
                                                <input type="text" id="editStreetAddress2" name="editStreetAddress2"
                                                    placeholder="Apartment, suite, unit, etc. (optional)"
                                                    class="form-control form-control-md">
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editTown" class="name-first">Town/City
                                                        <span>*</span></label>
                                                </div>
                                                <input type="text" id="editTown" name="editTown" placeholder="Enter Town/City"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editState" class="name-first">State <span>*</span></label>
                                                </div>
                                                <select id="editState" name="editState" class="form-control form-control-md"
                                                    required>
                                                    <option value="">Select State</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editPincode" class="name-first">PIN Code
                                                        <span>*</span></label>
                                                </div>
                                                <input type="number" id="editPincode" name="editPincode"
                                                    placeholder="Enter PIN Code" class="form-control form-control-md"
                                                    pattern="[0-9]*" inputmode="numeric" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editMobile" class="name-first">Mobile Number
                                                        <span>*</span></label>
                                                </div>
                                                <input type="number" type="tel" id="editMobile" name="editMobile"
                                                    placeholder="Enter Mobile Number"
                                                    pattern="[0-9]*" inputmode="numeric" class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <div class="mb-2">
                                                    <label for="editEmail" class="name-first">Email Address</label>
                                                </div>
                                                <input type="email" id="editEmail" name="editEmail" placeholder="Enter Email"
                                                    class="form-control form-control-md">
                                            </div>
                                        </div>
                                        <div
                                            class="form-checkbox d-flex align-items-center justify-content-between mb-3">
                                            <input type="checkbox" class="custom-checkbox" id="editRemember" name="editRemember">
                                            <label for="editRemember">Set as a primary Address</label>
                                        </div>
                                        <div class="col-12">
                                            <button type="button" id="update-address-btn" class="btn btn-svae1">
                                                <span id="update-address-text">Update Address</span>
                                                <i id="update-address-loader" class="fas fa-spinner fa-spin ml-2" style="display: none;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="cart-summary  mb-4">
                                <div class="d-md-flex justify-content-between align-items-center gap-3 relative">
                                    <div class="">
                                        <div class="order-total1">
                                            <span class="mb-2" id="final-total-amount">₹<?php echo number_format($cart_total, 2); ?></span>
                                            <label>Total Amount</label>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="#" class="btn  btn-primary  btn-rounded  btn-checkout" id="proceed-checkout-btn">
                                            Proceed to checkout</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End of PageContent -->
        </main>
        <!-- End of Main -->

        <!-- Modal for Clear Cart Confirmation -->
        <div id="clearCartModal" class="modal cart-clar-modal">
            <div class="cart-clar-modal1"
                style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:400px; text-align:center; position:relative;">
                <span id="closeModal"
                    style="position:absolute; right:15px; top:5px; font-size:18px; cursor:pointer; z-index: 1;">×</span>
                <div>
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/clearcart.png" style="width: 50px; height: 50px;" alt="">
                </div>
                <p style="font-size:18px; margin-bottom:20px;">Are you sure, you want to clear the cart?</p>
                <button class="btn btn-no"
                    style="background-color:transparent; color:#E6533C;  border:1px solid #E6533C; border-radius:5px; margin-right:10px; cursor:pointer;">No</button>
                <button class="btn btn-yes"
                    style="background-color:#3B4B6B; color:#fff;  border:1px solid #3B4B6B; border-radius:5px; cursor:pointer;">Yes</button>
            </div>
        </div>

        <!-- Modal for Remove Single Item Confirmation -->
        <div id="removeItemModal" class="modal cart-clar-modal" style="display:none;">
            <div class="cart-clar-modal1"
                style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:400px; text-align:center; position:relative;">
                <span id="closeRemoveItemModal"
                    style="position:absolute; right:15px; top:5px; font-size:18px; cursor:pointer; z-index: 1;">×</span>
                <div>
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" style="width: 40px; height: 40px;" alt="">
                </div>
                <p style="font-size:18px; margin-bottom:20px;">Are you sure you want to remove this item from your cart?</p>
                <button class="btn btn-remove-no"
                    style="background-color:transparent; color:#E6533C;  border:1px solid #E6533C; border-radius:5px; margin-right:10px; cursor:pointer;">No</button>
                <button class="btn btn-remove-yes"
                    style="background-color:#3B4B6B; color:#fff;  border:1px solid #3B4B6B; border-radius:5px; cursor:pointer;">Yes</button>
            </div>
        </div>

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/sticky/sticky.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>
    <script>
        // Open modal on any edit icon click
        document.querySelectorAll('.edit-address-btn').forEach(function(btn) {
            btn.onclick = function() {
                document.getElementById('editModal').style.display = 'block';
            }
        });
        document.getElementById('closeModal').onclick = function() {
            document.getElementById('editModal').style.display = 'none';
        };
        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

    <!-- Search Suggestions JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const searchSuggestions = document.getElementById('searchSuggestions');

            // Show suggestions when input is focused
            searchInput.addEventListener('focus', function() {
                searchSuggestions.classList.add('show');
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                    searchSuggestions.classList.remove('show');
                }
            });

            // Handle suggestion item clicks
            const suggestionItems = document.querySelectorAll('.suggestion-item');
            suggestionItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const title = this.querySelector('.suggestion-title').textContent;
                    searchInput.value = title;
                    searchSuggestions.classList.remove('show');
                });
            });

            // Hide suggestions on form submit
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    searchSuggestions.classList.remove('show');
                });
            }
        });
    </script>

    <!-- Address Management JavaScript -->
    <script>
        class AddressManager {
            constructor() {
                this.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
                this.init();
            }

            init() {
                document.addEventListener('DOMContentLoaded', () => {
                    this.initEventListeners();
                    this.loadCountries();
                    this.loadAddresses();
                });
            }

            initEventListeners() {
                // Add address button
                const addAddressBtn = document.getElementById('add-address-btn');
                if (addAddressBtn) {
                    addAddressBtn.addEventListener('click', () => {
                        this.openAddAddressModal();
                    });
                }

                // Close modals
                const closeModal = document.getElementById('closeModal');
                const closeEditModal = document.getElementById('closeEditModal');

                if (closeModal) {
                    closeModal.addEventListener('click', () => {
                        document.getElementById('editModal').style.display = 'none';
                    });
                }

                if (closeEditModal) {
                    closeEditModal.addEventListener('click', () => {
                        document.getElementById('editAddressModal').style.display = 'none';
                    });
                }

                // Country change for add modal
                const countrySelect = document.getElementById('country');
                if (countrySelect) {
                    countrySelect.addEventListener('change', (e) => {
                        this.loadStates(e.target.value, 'state');
                    });
                }

                // Country change for edit modal
                const editCountrySelect = document.getElementById('editCountry');
                if (editCountrySelect) {
                    editCountrySelect.addEventListener('change', (e) => {
                        this.loadStates(e.target.value, 'editState');
                    });
                }

                // Save address
                const saveAddressBtn = document.getElementById('save-address-btn');
                if (saveAddressBtn) {
                    saveAddressBtn.addEventListener('click', () => {
                        this.saveAddress();
                    });
                }

                // Update address
                const updateAddressBtn = document.getElementById('update-address-btn');
                if (updateAddressBtn) {
                    updateAddressBtn.addEventListener('click', () => {
                        this.updateAddress();
                    });
                }
            }

            openAddAddressModal() {
                if (!this.isLoggedIn) {
                    showLoginRequiredModal();
                    return;
                }

                // Reset form
                this.resetAddForm();
                document.getElementById('editModal').style.display = 'block';

                // Add real-time validation
                this.addAddressFormValidation();

                // Ensure India is selected after reset and modal open
                const addCountrySelect = document.getElementById('country');
                if (addCountrySelect) {
                    // Select only exact India match or ID 99
                    const indiaOpt = Array.from(addCountrySelect.options).find(o => {
                        const text = (o.textContent || '').trim().toLowerCase();
                        return o.value == '99' || text === 'india';
                    });
                    if (indiaOpt) {
                        addCountrySelect.value = indiaOpt.value;
                        this.loadStates(addCountrySelect.value, 'state');
                    }
                }
            }

            async loadCountries() {
                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_countries'
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    if (data.success) {
                        this.populateCountryDropdowns(data.countries);
                    } else if (data.login_required) {} else {
                        console.warn('Failed to load countries:', data.message);
                    }
                } catch (error) {
                    console.error('Error loading countries:', error);
                    // Don't show error to user for countries loading as it's not critical
                }
            }

            populateCountryDropdowns(countries) {
                const countrySelect = document.getElementById('country');
                const editCountrySelect = document.getElementById('editCountry');

                countries.forEach(country => {
                    const option = `<option value="${country.id}">${country.name}</option>`;
                    if (countrySelect) countrySelect.insertAdjacentHTML('beforeend', option);
                    if (editCountrySelect) editCountrySelect.insertAdjacentHTML('beforeend', option);
                });

                // Force-select India for both dropdowns
                if (countrySelect && !countrySelect.value) {
                    const indiaAdd = Array.from(countrySelect.options).find(o => (o.textContent || '').trim().toLowerCase() === 'india' || o.value === '99');
                    if (indiaAdd) {
                        countrySelect.value = indiaAdd.value;
                        this.loadStates(indiaAdd.value, 'state');
                    }
                }
                if (editCountrySelect && !editCountrySelect.value) {
                    const indiaEdit = Array.from(editCountrySelect.options).find(o => (o.textContent || '').trim().toLowerCase() === 'india' || o.value === '99');
                    if (indiaEdit) {
                        editCountrySelect.value = indiaEdit.value;
                        this.loadStates(indiaEdit.value, 'editState');
                    }
                }
            }


            async loadStates(countryId, targetSelectId) {
                if (!countryId) {
                    document.getElementById(targetSelectId).innerHTML = '<option value="">Select State</option>';
                    return;
                }

                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_states',
                            country_id: parseInt(countryId)
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        const stateSelect = document.getElementById(targetSelectId);
                        stateSelect.innerHTML = '<option value="">Select State</option>';

                        data.states.forEach(state => {
                            stateSelect.insertAdjacentHTML('beforeend',
                                `<option value="${state.id}">${state.name}</option>`
                            );
                        });
                    }
                } catch (error) {
                    console.error('Error loading states:', error);
                }
            }

            async loadAddresses() {
                if (!this.isLoggedIn) {
                    this.showNoAddresses();
                    return;
                }

                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_addresses'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.displayAddresses(data.addresses);
                    } else {
                        this.showNoAddresses();
                    }
                } catch (error) {
                    console.error('Error loading addresses:', error);
                    this.showNoAddresses();
                }
            }

            displayAddresses(addresses) {
                const addressList = document.getElementById('address-list');
                const addressLoading = document.getElementById('address-loading');

                if (addressLoading) {
                    addressLoading.remove();
                }

                if (addresses.length === 0) {
                    this.showNoAddresses();
                    return;
                }

                let html = '';
                addresses.forEach((address, index) => {
                    const isChecked = address.primary_address == 1 ? 'checked' : '';
                    const planClass = index === 0 ? 'basic-plan' : 'complete-plan';

                    html += `
                        <label class="plan ${planClass}" for="address-${address.id}">
                            <input type="radio" name="selected_address" id="address-${address.id}" 
                                   value="${address.id}" ${isChecked} />
                            <div class="plan-content">
                                <div class="plan-details">
                                    <span>${address.address_type.charAt(0).toUpperCase() + address.address_type.slice(1)}</span>
                                    <p>${address.street_address}, ${address.city}, ${address.state_name}, ${address.country_name} - ${address.pin_code}</p>
                                    <small>Mobile: ${address.mobile_number}</small>
                                    ${address.primary_address == 1 ? '<small class="text-success"><strong> (Primary)</strong></small>' : ''}
                                </div>
                                <div>
                                    <i class="fas fa-pen edit-address-btn" title="Edit" 
                                       style="cursor:pointer;" data-address-id="${address.id}"></i>
                                </div>
                            </div>
                        </label>
                    `;
                });

                addressList.innerHTML = html;

                // Add event listeners for edit buttons
                const editBtns = document.querySelectorAll('.edit-address-btn');
                editBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const addressId = btn.getAttribute('data-address-id');
                        this.editAddress(addressId);
                    });
                });

                // Add event listeners for address selection (use only for this order)
                // Do NOT persist as primary when selecting here
                const addressRadios = document.querySelectorAll('input[name="selected_address"]');
                addressRadios.forEach(radio => {
                    radio.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            // this.setPrimaryAddress(e.target.value);
                            // Store temporarily for this checkout flow if needed
                            this.currentOrderAddressId = e.target.value;
                        }
                    });
                });
            }

            showNoAddresses() {
                const addressList = document.getElementById('address-list');
                const addressLoading = document.getElementById('address-loading');

                if (addressLoading) {
                    addressLoading.remove();
                }

                if (!this.isLoggedIn) {
                    addressList.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                            <h5>Please Login to View Addresses</h5>
                            <p>You need to login to manage your delivery addresses.</p>
                        </div>
                    `;
                } else {
                    addressList.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                            <h5>No Addresses Found</h5>
                            <p>Add your first delivery address to continue.</p>
                        </div>
                    `;
                }
            }

            async saveAddress() {
                const saveBtn = document.getElementById('save-address-btn');
                const saveText = document.getElementById('save-address-text');
                const saveLoader = document.getElementById('save-address-loader');

                // Show loading
                saveBtn.disabled = true;
                saveText.textContent = 'Saving...';
                saveLoader.style.display = 'inline';

                // Get form data
                const formData = this.getAddFormData();

                if (!this.validateAddressForm(formData)) {
                    this.resetSaveButton();
                    return;
                }

                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'add_address',
                            ...formData
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccessNotification('Address Added!', 'Your new address has been added successfully.');
                        document.getElementById('editModal').style.display = 'none';
                        this.loadAddresses(); // Reload addresses
                        this.resetAddForm();
                    } else {
                        showErrorNotification('Address Error', data.message || 'Failed to add address. Please try again.');
                    }
                } catch (error) {
                    console.error('Error saving address:', error);
                    showErrorNotification('Network Error', 'An error occurred while saving. Please check your connection and try again.');
                } finally {
                    this.resetSaveButton();
                }
            }

            async editAddress(addressId) {
                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_address',
                            address_id: parseInt(addressId)
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.populateEditForm(data.address);
                        document.getElementById('editAddressModal').style.display = 'block';
                    } else {
                        alert(data.message || 'Failed to load address');
                    }
                } catch (error) {
                    console.error('Error loading address:', error);
                    alert('An error occurred. Please try again.');
                }
            }

            async updateAddress() {
                const updateBtn = document.getElementById('update-address-btn');
                const updateText = document.getElementById('update-address-text');
                const updateLoader = document.getElementById('update-address-loader');

                // Show loading
                updateBtn.disabled = true;
                updateText.textContent = 'Updating...';
                updateLoader.style.display = 'inline';

                // Get form data
                const formData = this.getEditFormData();

                if (!this.validateEditAddressForm(formData)) {
                    this.resetUpdateButton();
                    return;
                }

                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'update_address',
                            ...formData
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showSuccessNotification('Address Updated!', 'Your address has been updated successfully.');
                        document.getElementById('editAddressModal').style.display = 'none';
                        this.loadAddresses(); // Reload addresses
                    } else {
                        showErrorNotification('Update Error', data.message || 'Failed to update address. Please try again.');
                    }
                } catch (error) {
                    console.error('Error updating address:', error);
                    showErrorNotification('Network Error', 'An error occurred while updating. Please check your connection and try again.');
                } finally {
                    this.resetUpdateButton();
                }
            }

            async setPrimaryAddress(addressId) {
                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'set_primary',
                            address_id: parseInt(addressId)
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Reload addresses to update primary status
                        this.loadAddresses();
                    } else {
                        alert(data.message || 'Failed to set primary address');
                    }
                } catch (error) {
                    console.error('Error setting primary address:', error);
                }
            }

            getAddFormData() {
                return {
                    address_type: document.getElementById('addressType').value,
                    country_id: document.getElementById('country').value,
                    state_id: document.getElementById('state').value,
                    street_address: document.getElementById('streetAddress').value,
                    street_address2: document.getElementById('streetAddress2').value,
                    city: document.getElementById('town').value,
                    pin_code: document.getElementById('pincode').value,
                    mobile_number: document.getElementById('mobile').value,
                    email_address: document.getElementById('email').value,
                    is_primary: document.getElementById('remember').checked ? 1 : 0
                };
            }

            getEditFormData() {
                return {
                    address_id: document.getElementById('editAddressId').value,
                    address_type: document.getElementById('editAddressType').value,
                    country_id: document.getElementById('editCountry').value,
                    state_id: document.getElementById('editState').value,
                    street_address: document.getElementById('editStreetAddress').value,
                    street_address2: document.getElementById('editStreetAddress2').value,
                    city: document.getElementById('editTown').value,
                    pin_code: document.getElementById('editPincode').value,
                    mobile_number: document.getElementById('editMobile').value,
                    email_address: document.getElementById('editEmail').value,
                    is_primary: document.getElementById('editRemember').checked ? 1 : 0
                };
            }

            populateEditForm(address) {
                document.getElementById('editAddressId').value = address.id;
                document.getElementById('editAddressType').value = address.address_type;
                document.getElementById('editCountry').value = address.country_id;
                document.getElementById('editStreetAddress').value = address.street_address;
                document.getElementById('editStreetAddress2').value = address.street_address2 || '';
                document.getElementById('editTown').value = address.city;
                document.getElementById('editPincode').value = address.pin_code;
                document.getElementById('editMobile').value = address.mobile_number;
                document.getElementById('editEmail').value = address.email_address || '';
                document.getElementById('editRemember').checked = address.primary_address == 1;

                // Load states for the selected country
                this.loadStates(address.country_id, 'editState').then(() => {
                    document.getElementById('editState').value = address.state_id;

                    // Add validation to edit form after populating
                    this.addEditFormValidation();

                    // Validate pre-filled data
                    this.validateEditFormData();
                });
            }

            addEditFormValidation() {
                const requiredFields = [{
                        id: 'editAddressType',
                        name: 'Address Type'
                    },
                    {
                        id: 'editState',
                        name: 'State'
                    },
                    {
                        id: 'editStreetAddress',
                        name: 'Street Address'
                    },
                    {
                        id: 'editTown',
                        name: 'Town/City'
                    },
                    {
                        id: 'editPincode',
                        name: 'PIN Code'
                    },
                    {
                        id: 'editMobile',
                        name: 'Mobile Number'
                    }
                ];

                // Add validation to each required field
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element) {
                        // Add event listeners for real-time validation
                        element.addEventListener('blur', () => this.validateEditField(field.id, field.name));
                        element.addEventListener('input', () => {
                            const value = element.value.trim();
                            if (value) {
                                // Only show success if user has actually typed something
                                element.classList.remove('validation-error');
                                element.classList.add('validation-success');
                                this.clearEditFieldError(this.getEditFieldNameFromId(field.id));
                            } else {
                                // Remove all validation classes for empty fields
                                element.classList.remove('validation-error', 'validation-success');

                                // Find the form group container
                                let container = element.closest('.form-group');
                                if (!container) {
                                    container = element.parentNode;
                                }

                                const existingError = container.querySelector('.field-error-message');
                                if (existingError) {
                                    existingError.remove();
                                }
                            }
                        });
                    }
                });

                // Special validation for mobile number
                const mobileField = document.getElementById('editMobile');
                if (mobileField) {
                    mobileField.addEventListener('input', () => {
                        const value = mobileField.value.trim();
                        if (value) {
                            mobileField.classList.remove('validation-error');
                            mobileField.classList.add('validation-success');
                            this.clearEditFieldError('mobile_number');
                            if (value.length < 10) {
                                this.showEditFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                            }
                        } else {
                            // Remove all validation classes for empty fields
                            mobileField.classList.remove('validation-error', 'validation-success');

                            // Find the form group container
                            let container = mobileField.closest('.form-group');
                            if (!container) {
                                container = mobileField.parentNode;
                            }

                            const existingError = container.querySelector('.field-error-message');
                            if (existingError) {
                                existingError.remove();
                            }
                        }
                    });
                }

                // Email validation (optional field)
                const emailField = document.getElementById('editEmail');
                if (emailField) {
                    emailField.addEventListener('blur', () => {
                        const email = emailField.value.trim();
                        if (email) {
                            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailRegex.test(email)) {
                                this.showEditFieldError('email_address', 'Please enter a valid email address');
                            } else {
                                emailField.classList.remove('validation-error');
                                emailField.classList.add('validation-success');
                                this.clearEditFieldError('email_address');
                            }
                        } else {
                            // Remove all validation classes for empty fields
                            emailField.classList.remove('validation-error', 'validation-success');

                            // Find the form group container
                            let container = emailField.closest('.form-group');
                            if (!container) {
                                container = emailField.parentNode;
                            }

                            const existingError = container.querySelector('.field-error-message');
                            if (existingError) {
                                existingError.remove();
                            }
                        }
                    });
                }
            }

            validateEditFormData() {
                // Validate all pre-filled data
                const formData = this.getEditFormData();
                this.validateEditAddressForm(formData);
            }

            getEditFieldNameFromId(fieldId) {
                const fieldMap = {
                    'editAddressType': 'address_type',
                    'editCountry': 'country_id',
                    'editState': 'state_id',
                    'editStreetAddress': 'street_address',
                    'editStreetAddress2': 'street_address2',
                    'editTown': 'city',
                    'editPincode': 'pin_code',
                    'editMobile': 'mobile_number',
                    'editEmail': 'email_address'
                };
                return fieldMap[fieldId] || fieldId;
            }

            validateEditField(fieldId, fieldName) {
                const element = document.getElementById(fieldId);
                if (!element) return false;

                const value = element.value.trim();
                const fieldNameForError = this.getEditFieldNameFromId(fieldId);

                if (!value) {
                    this.showEditFieldError(fieldNameForError, `${fieldName} is required`);
                    return false;
                }

                // Special validation for mobile number
                if (fieldId === 'editMobile') {
                    if (value.length < 10) {
                        this.showEditFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                        return false;
                    }
                    if (!/^\d+$/.test(value)) {
                        this.showEditFieldError('mobile_number', 'Mobile number must contain only digits');
                        return false;
                    }
                }

                // Special validation for PIN code
                if (fieldId === 'editPincode') {
                    if (!/^\d+$/.test(value)) {
                        this.showEditFieldError('pin_code', 'PIN code must contain only digits');
                        return false;
                    }
                }

                // Only clear error if field has content and is valid
                if (value.trim()) {
                    this.clearEditFieldError(fieldNameForError);
                    return true;
                }
                return false;
            }

            showEditFieldError(field, message) {
                const element = this.getEditFieldElement(field);
                if (!element) return;

                // Add error class
                element.classList.add('validation-error');
                element.classList.remove('validation-success');

                // Find the form group container
                let container = element.closest('.form-group');
                if (!container) {
                    container = element.parentNode;
                }

                // Remove existing error message
                const existingError = container.querySelector('.field-error-message');
                if (existingError) {
                    existingError.remove();
                }

                // Add error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error-message';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '12px';
                errorDiv.style.marginTop = '5px';
                errorDiv.style.display = 'block';
                errorDiv.style.width = '100%';
                errorDiv.textContent = message;

                // Insert error message at the end of the container
                container.appendChild(errorDiv);
            }

            clearEditFieldError(field) {
                const element = this.getEditFieldElement(field);
                if (!element) return;

                element.classList.remove('validation-error');
                element.classList.remove('validation-success');

                // Find the form group container
                let container = element.closest('.form-group');
                if (!container) {
                    container = element.parentNode;
                }

                const existingError = container.querySelector('.field-error-message');
                if (existingError) {
                    existingError.remove();
                }
            }

            getEditFieldElement(field) {
                const fieldMap = {
                    'address_type': 'editAddressType',
                    'country_id': 'editCountry',
                    'state_id': 'editState',
                    'street_address': 'editStreetAddress',
                    'street_address2': 'editStreetAddress2',
                    'city': 'editTown',
                    'pin_code': 'editPincode',
                    'mobile_number': 'editMobile',
                    'email_address': 'editEmail'
                };
                const elementId = fieldMap[field] || field;
                return document.getElementById(elementId);
            }

            validateAddressForm(data) {
                const required = ['address_type', 'state_id', 'street_address', 'city', 'pin_code', 'mobile_number'];

                let isValid = true;
                let firstInvalidField = null;

                // Clear all previous validation errors
                this.clearAllValidationErrors();

                for (let field of required) {
                    if (!data[field] || data[field].toString().trim() === '') {
                        const fieldName = this.getFieldDisplayName(field);
                        this.showFieldError(field, `${fieldName} is required`);
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        isValid = false;
                    }
                }

                // Special validation for mobile number
                if (data.mobile_number && data.mobile_number.toString().trim() !== '') {
                    const mobile = data.mobile_number.toString().trim();
                    if (mobile.length < 10) {
                        this.showFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'mobile_number';
                        }
                        isValid = false;
                    } else if (!/^\d+$/.test(mobile)) {
                        this.showFieldError('mobile_number', 'Mobile number must contain only digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'mobile_number';
                        }
                        isValid = false;
                    }
                }

                // Special validation for PIN code
                if (data.pin_code && data.pin_code.toString().trim() !== '') {
                    const pincode = data.pin_code.toString().trim();
                    if (!/^\d+$/.test(pincode)) {
                        this.showFieldError('pin_code', 'PIN code must contain only digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'pin_code';
                        }
                        isValid = false;
                    }
                }

                // Email validation (optional field)
                if (data.email_address && data.email_address.toString().trim() !== '') {
                    const email = data.email_address.toString().trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        this.showFieldError('email_address', 'Please enter a valid email address');
                        if (!firstInvalidField) {
                            firstInvalidField = 'email_address';
                        }
                        isValid = false;
                    }
                }

                // Focus on first invalid field
                if (!isValid && firstInvalidField) {
                    const fieldElement = this.getFieldElement(firstInvalidField);
                    if (fieldElement) {
                        fieldElement.focus();
                    }
                }

                return isValid;
            }

            // Validation helper methods
            getFieldDisplayName(field) {
                const fieldNames = {
                    'address_type': 'Address Type',
                    'country_id': 'Country/Region',
                    'state_id': 'State',
                    'street_address': 'Street Address',
                    'city': 'Town/City',
                    'pin_code': 'PIN Code',
                    'mobile_number': 'Mobile Number',
                    'email_address': 'Email Address'
                };
                return fieldNames[field] || field.replace('_', ' ');
            }

            getFieldElement(field) {
                const fieldMap = {
                    'address_type': 'addressType',
                    'country_id': 'country',
                    'state_id': 'state',
                    'street_address': 'streetAddress',
                    'city': 'town',
                    'pin_code': 'pincode',
                    'mobile_number': 'mobile',
                    'email_address': 'email'
                };
                const elementId = fieldMap[field] || field;
                return document.getElementById(elementId);
            }

            showFieldError(field, message) {
                const element = this.getFieldElement(field);
                if (!element) return;

                // Add error class
                element.classList.add('validation-error');
                element.classList.remove('validation-success');

                // Find the form group container
                let container = element.closest('.form-group');
                if (!container) {
                    container = element.parentNode;
                }

                // Remove existing error message
                const existingError = container.querySelector('.field-error-message');
                if (existingError) {
                    existingError.remove();
                }

                // Add error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error-message';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '12px';
                errorDiv.style.marginTop = '5px';
                errorDiv.style.display = 'block';
                errorDiv.style.width = '100%';
                errorDiv.textContent = message;

                // Insert error message at the end of the container
                container.appendChild(errorDiv);
            }

            clearFieldError(field) {
                const element = this.getFieldElement(field);
                if (!element) return;

                element.classList.remove('validation-error');
                element.classList.remove('validation-success');

                // Find the form group container
                let container = element.closest('.form-group');
                if (!container) {
                    container = element.parentNode;
                }

                const existingError = container.querySelector('.field-error-message');
                if (existingError) {
                    existingError.remove();
                }
            }

            clearAllValidationErrors() {
                const fields = ['address_type', 'country_id', 'state_id', 'street_address', 'city', 'pin_code', 'mobile_number', 'email_address'];
                fields.forEach(field => {
                    this.clearFieldError(field);
                });
            }

            addAddressFormValidation() {
                const requiredFields = [{
                        id: 'addressType',
                        name: 'Address Type'
                    },
                    {
                        id: 'state',
                        name: 'State'
                    },
                    {
                        id: 'streetAddress',
                        name: 'Street Address'
                    },
                    {
                        id: 'town',
                        name: 'Town/City'
                    },
                    {
                        id: 'pincode',
                        name: 'PIN Code'
                    },
                    {
                        id: 'mobile',
                        name: 'Mobile Number'
                    }
                ];

                // Add validation to each required field
                requiredFields.forEach(field => {
                    const element = document.getElementById(field.id);
                    if (element) {
                        // Add event listeners for real-time validation
                        element.addEventListener('blur', () => this.validateField(field.id, field.name));
                        element.addEventListener('input', () => {
                            const value = element.value.trim();
                            if (value) {
                                // Only show success if user has actually typed something
                                element.classList.remove('validation-error');
                                element.classList.add('validation-success');
                                this.clearFieldError(this.getFieldNameFromId(field.id));
                            } else {
                                // Remove all validation classes for empty fields
                                element.classList.remove('validation-error', 'validation-success');

                                // Find the form group container
                                let container = element.closest('.form-group');
                                if (!container) {
                                    container = element.parentNode;
                                }

                                const existingError = container.querySelector('.field-error-message');
                                if (existingError) {
                                    existingError.remove();
                                }
                            }
                        });
                    }
                });

                // Special validation for mobile number
                const mobileField = document.getElementById('mobile');
                if (mobileField) {
                    mobileField.addEventListener('input', () => {
                        const value = mobileField.value.trim();
                        if (value) {
                            mobileField.classList.remove('validation-error');
                            mobileField.classList.add('validation-success');
                            this.clearFieldError('mobile_number');
                            if (value.length < 10) {
                                this.showFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                            }
                        } else {
                            // Remove all validation classes for empty fields
                            mobileField.classList.remove('validation-error', 'validation-success');

                            // Find the form group container
                            let container = mobileField.closest('.form-group');
                            if (!container) {
                                container = mobileField.parentNode;
                            }

                            const existingError = container.querySelector('.field-error-message');
                            if (existingError) {
                                existingError.remove();
                            }
                        }
                    });
                }

                // Email validation (optional field)
                const emailField = document.getElementById('email');
                if (emailField) {
                    emailField.addEventListener('blur', () => {
                        const email = emailField.value.trim();
                        if (email) {
                            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailRegex.test(email)) {
                                this.showFieldError('email_address', 'Please enter a valid email address');
                            } else {
                                emailField.classList.remove('validation-error');
                                emailField.classList.add('validation-success');
                                this.clearFieldError('email_address');
                            }
                        } else {
                            // Remove all validation classes for empty fields
                            emailField.classList.remove('validation-error', 'validation-success');

                            // Find the form group container
                            let container = emailField.closest('.form-group');
                            if (!container) {
                                container = emailField.parentNode;
                            }

                            const existingError = container.querySelector('.field-error-message');
                            if (existingError) {
                                existingError.remove();
                            }
                        }
                    });
                }
            }

            getFieldNameFromId(fieldId) {
                const fieldMap = {
                    'addressType': 'address_type',
                    'country': 'country_id',
                    'state': 'state_id',
                    'streetAddress': 'street_address',
                    'town': 'city',
                    'pincode': 'pin_code',
                    'mobile': 'mobile_number',
                    'email': 'email_address'
                };
                return fieldMap[fieldId] || fieldId;
            }

            validateField(fieldId, fieldName) {
                const element = document.getElementById(fieldId);
                if (!element) return false;

                const value = element.value.trim();
                const fieldNameForError = this.getFieldNameFromId(fieldId);

                if (!value) {
                    this.showFieldError(fieldNameForError, `${fieldName} is required`);
                    return false;
                }

                // Special validation for mobile number
                if (fieldId === 'mobile') {
                    if (value.length < 10) {
                        this.showFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                        return false;
                    }
                    if (!/^\d+$/.test(value)) {
                        this.showFieldError('mobile_number', 'Mobile number must contain only digits');
                        return false;
                    }
                }

                // Special validation for PIN code
                if (fieldId === 'pincode') {
                    if (!/^\d+$/.test(value)) {
                        this.showFieldError('pin_code', 'PIN code must contain only digits');
                        return false;
                    }
                }

                // Only clear error if field has content and is valid
                if (value.trim()) {
                    this.clearFieldError(fieldNameForError);
                    return true;
                }
                return false;
            }

            validateEditAddressForm(data) {
                const required = ['address_type', 'state_id', 'street_address', 'city', 'pin_code', 'mobile_number'];

                let isValid = true;
                let firstInvalidField = null;

                // Clear all previous validation errors
                this.clearAllValidationErrors();

                for (let field of required) {
                    if (!data[field] || data[field].toString().trim() === '') {
                        const fieldName = this.getFieldDisplayName(field);
                        this.showFieldError(field, `${fieldName} is required`);
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        isValid = false;
                    }
                }

                // Special validation for mobile number
                if (data.mobile_number && data.mobile_number.toString().trim() !== '') {
                    const mobile = data.mobile_number.toString().trim();
                    if (mobile.length < 10) {
                        this.showFieldError('mobile_number', 'Mobile number must have at least 10 digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'mobile_number';
                        }
                        isValid = false;
                    } else if (!/^\d+$/.test(mobile)) {
                        this.showFieldError('mobile_number', 'Mobile number must contain only digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'mobile_number';
                        }
                        isValid = false;
                    }
                }

                // Special validation for PIN code
                if (data.pin_code && data.pin_code.toString().trim() !== '') {
                    const pincode = data.pin_code.toString().trim();
                    if (!/^\d+$/.test(pincode)) {
                        this.showFieldError('pin_code', 'PIN code must contain only digits');
                        if (!firstInvalidField) {
                            firstInvalidField = 'pin_code';
                        }
                        isValid = false;
                    }
                }

                // Email validation (optional field)
                if (data.email_address && data.email_address.toString().trim() !== '') {
                    const email = data.email_address.toString().trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        this.showFieldError('email_address', 'Please enter a valid email address');
                        if (!firstInvalidField) {
                            firstInvalidField = 'email_address';
                        }
                        isValid = false;
                    }
                }

                // Focus on first invalid field
                if (!isValid && firstInvalidField) {
                    const fieldElement = this.getFieldElement(firstInvalidField);
                    if (fieldElement) {
                        fieldElement.focus();
                    }
                }

                return isValid;
            }

            resetAddForm() {
                document.getElementById('addressType').value = '';
                const countryEl = document.getElementById('country');
                if (countryEl) {
                    // Prefer keeping/setting India if available; otherwise clear
                    const indiaOpt = Array.from(countryEl.options).find(o => {
                        const text = (o.textContent || '').trim().toLowerCase();
                        return o.value == '99' || text.includes('india');
                    });
                    countryEl.value = indiaOpt ? indiaOpt.value : '';
                }

                // Clear all validation errors
                this.clearAllValidationErrors();
                document.getElementById('state').innerHTML = '<option value="">Select State</option>';
                document.getElementById('streetAddress').value = '';
                document.getElementById('town').value = '';
                document.getElementById('pincode').value = '';
                document.getElementById('mobile').value = '';
                document.getElementById('email').value = '';
                document.getElementById('remember').checked = false;
            }

            resetSaveButton() {
                const saveBtn = document.getElementById('save-address-btn');
                const saveText = document.getElementById('save-address-text');
                const saveLoader = document.getElementById('save-address-loader');

                saveBtn.disabled = false;
                saveText.textContent = 'Add Address';
                saveLoader.style.display = 'none';
            }

            resetUpdateButton() {
                const updateBtn = document.getElementById('update-address-btn');
                const updateText = document.getElementById('update-address-text');
                const updateLoader = document.getElementById('update-address-loader');

                updateBtn.disabled = false;
                updateText.textContent = 'Update Address';
                updateLoader.style.display = 'none';
            }
        }

        // Initialize address manager
        const addressManager = new AddressManager();
    </script>

    <!-- Cart Error Fix Script -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/cart_error_fix.js"></script>

    <!-- Dynamic Cart JavaScript -->
    <script>
        // Number formatting utility functions
        function formatCurrency(amount) {
            if (typeof amount === 'string') {
                amount = parseFloat(amount.replace(/[₹,]/g, ''));
            }
            if (isNaN(amount)) return '₹0.00';

            return '₹' + amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatNumber(number) {
            if (typeof number === 'string') {
                number = parseFloat(number.replace(/[₹,]/g, ''));
            }
            if (isNaN(number)) return '0';

            return number.toLocaleString('en-IN', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2
            });
        }

        function updateCurrencyDisplay(element, amount) {
            if (element) {
                element.textContent = formatCurrency(amount);
                element.classList.add('currency-amount');
            }
        }

        function updateNumberDisplay(element, number) {
            if (element) {
                element.textContent = formatNumber(number);
            }
        }

        class DynamicCartManager {
            constructor() {
                this.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
                this.baseImageUrl = '<?php echo $base_image_url; ?>';
                this.defaultImage = '<?php echo $default_image; ?>';
                this.updatingQuantity = false; // Flag to prevent double updates
                this.init();
            }

            init() {
                document.addEventListener('DOMContentLoaded', () => {
                    console.log('Is Logged In:', this.isLoggedIn);

                    if (!this.isLoggedIn) {
                        this.loadGuestCart();
                    } else {
                        // For logged-in users, ensure quantities are properly set from database
                        this.ensureQuantitiesFromDatabase();
                        this.updateCartTotals();
                    }

                    // Validate quantity states for all users
                    this.validateQuantityStates();
                    this.initEventListeners();
                    this.initCouponSystem();
                    this.loadAppliedCoupon();

                    // Additional check to ensure quantities are properly set after a short delay
                    setTimeout(() => {
                        if (!this.isLoggedIn) {
                            this.ensureGuestCartQuantities();
                        } else {
                            this.ensureQuantitiesFromDatabase();
                        }
                        this.updateCartTotals();
                    }, 500);
                });
            }

            // Force visual update by completely replacing the element
            forceVisualUpdate(element, newValue) {
                try {
                    if (!element || !newValue) return;
                    console.log('Force visual update - New value:', newValue);
                    // Check if there are multiple elements with same ID
                    const allElements = document.querySelectorAll('#cart-subtotal');
                    allElements.forEach((el, index) => {});

                    // Create new element with same attributes
                    const newElement = document.createElement('span');
                    newElement.id = element.id;
                    newElement.className = element.className;
                    newElement.textContent = newValue;

                    // Force all display styles with more aggressive approach
                    newElement.style.cssText = `
                        display: inline !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        color: #333 !important;
                        font-weight: bold !important;
                        background: transparent !important;
                        border: none !important;
                        margin: 0 !important;
                        padding: 0 !important;
                        position: static !important;
                        z-index: 9999 !important;
                        transform: translateZ(0) !important;
                        font-size: inherit !important;
                        line-height: inherit !important;
                        text-align: inherit !important;
                        vertical-align: inherit !important;
                    `;

                    // Replace the old element
                    element.parentNode.replaceChild(newElement, element);

                    // Force multiple reflows and repaints
                    newElement.offsetHeight;
                    newElement.style.transform = 'translateZ(0.1px)';
                    newElement.offsetHeight;
                    newElement.style.transform = 'translateZ(0)';
                    newElement.offsetHeight;

                    // Additional force methods
                    newElement.style.display = 'inline';
                    newElement.style.visibility = 'visible';
                    newElement.offsetHeight;
                    console.log('Force visual update - Final value:', newElement.textContent);
                    // Check if the element is actually visible
                    const rect = newElement.getBoundingClientRect();
                    console.log('Element position and size:', {
                        top: rect.top,
                        left: rect.left,
                        width: rect.width,
                        height: rect.height,
                        visible: rect.width > 0 && rect.height > 0
                    });

                } catch (e) {
                    console.error('Error in forceVisualUpdate:', e);
                }
            }

            // Completely rebuild the entire cart summary section
            rebuildEntireCartSummary() {
                try {
                    const finalTotalElement = document.getElementById('final-total-amount');
                    if (!finalTotalElement) {
                        return;
                    }

                    const finalValue = finalTotalElement.textContent || '';
                    // Find the cart summary container - specifically look for Order Detail section
                    const allCartSummaries = document.querySelectorAll('.cart-summary');
                    let cartSummary = null;

                    // Look for the Order Detail section specifically
                    for (let summary of allCartSummaries) {
                        const title = summary.querySelector('.cart-title1');
                        if (title && title.textContent.includes('Order Detail')) {
                            cartSummary = summary;
                            break;
                        }
                    }

                    // If not found, look for one that doesn't have coupon input
                    if (!cartSummary) {
                        for (let summary of allCartSummaries) {
                            if (!summary.querySelector('#coupon-input-form')) {
                                cartSummary = summary;
                                break;
                            }
                        }
                    }

                    if (!cartSummary) {
                        return;
                    }

                    // Double-check: don't rebuild if this is the coupon section
                    if (cartSummary.querySelector('#coupon-input-form')) {
                        return;
                    }

                    // Get current values
                    const cartTotalElement = document.getElementById('cart-total');
                    const itemCountElement = document.getElementById('cart-item-count');
                    const cartTotal = cartTotalElement ? cartTotalElement.textContent : '₹0.00';
                    const itemCount = itemCountElement ? itemCountElement.textContent : '(0 items)';
                    // Create completely new HTML
                    const newHTML = `
                        <h3 class="cart-title cart-title1 text-uppercase ">Order Detail <span id="cart-item-count">${itemCount}</span></h3>
                        <div class="cart-subtotal1 d-flex align-items-center justify-content-between">
                            <label class="ls-25">Cart Total</label>
                            <span id="cart-total">${cartTotal}</span>
                        </div>
                        <div class="cart-subtotal1 d-flex align-items-center justify-content-between">
                            <label class="ls-25">Shipping Charges</label>
                            <span id="shipping-charges">
                                <span id="shipping-amount">Free</span>
                                <span id="free-shipping-coupon" class="text-success" style="display: none;"> (Coupon Applied)</span>
                            </span>
                        </div>
                        <div class="cart-subtotal1 d-flex align-items-center justify-content-between" id="coupon-savings-row" style="display: none;">
                            <label class="ls-25">Coupon Savings</label>
                            <span class="cart-sbu-color" id="coupon-savings">- ₹0.00</span>
                        </div>
                        <hr class="divider mb-4">
                        <div class="cart-subtotal d-flex align-items-center justify-content-between">
                            <label class="ls-25">Sub Total</label>
                            <span id="cart-subtotal" style="display: inline !important; visibility: visible !important; opacity: 1 !important; color: #333 !important; font-weight: bold !important; background: transparent !important; border: none !important; margin: 0 !important; padding: 0 !important; position: static !important; z-index: 0 !important;">${finalValue}</span>
                        </div>
                        <hr class="divider mb-0">
                    `;
                    // Replace the entire content
                    cartSummary.innerHTML = newHTML;

                    // Force reflow on the new element
                    const newSubtotalElement = document.getElementById('cart-subtotal');
                    if (newSubtotalElement) {
                        newSubtotalElement.offsetHeight;
                        newSubtotalElement.style.transform = 'translateZ(0)';
                        newSubtotalElement.offsetHeight;
                        console.log('New subtotal value:', newSubtotalElement.textContent);

                        // Check visibility
                        const rect = newSubtotalElement.getBoundingClientRect();
                        console.log('New element visibility:', {
                            width: rect.width,
                            height: rect.height,
                            visible: rect.width > 0 && rect.height > 0
                        });
                    }
                } catch (e) {
                    console.error('Error in rebuildEntireCartSummary:', e);
                }
            }

            ensureQuantitiesFromDatabase() {
                // For logged-in users, ensure quantities match database values
                try {
                    const inputs = document.querySelectorAll('.quantity[data-initial-qty]');
                    inputs.forEach(input => {
                        const initial = parseInt(input.getAttribute('data-initial-qty'));
                        const max = parseInt(input.getAttribute('max')) || 999999;
                        const value = Math.min(Math.max(0, isNaN(initial) ? 0 : initial), max);

                        // Always set the value to match database
                        input.value = value;

                        // Update subtotal immediately
                        this.updateItemSubtotal(input);
                    });

                    // Update all totals after setting quantities
                    this.updateCartTotals();
                    this.checkAndRecalculateCoupon();
                } catch (e) {
                    console.warn('Quantity restore failed:', e);
                }
            }

            initEventListeners() {
                // Guard to bind only once per page load
                if (window.__DCM_BOUND__) {
                    return;
                }
                window.__DCM_BOUND__ = true;

                // Simple click lock mechanism
                const clickLockMs = 200;
                const lockedButtons = new Set();

                function isLocked(el) {
                    return lockedButtons.has(el);
                }

                function lock(el) {
                    lockedButtons.add(el);
                    setTimeout(() => {
                        lockedButtons.delete(el);
                    }, clickLockMs);
                }

                // Prevent theme's default qty handlers (which increment on mousedown) from running
                // so we control quantity changes exclusively in this cart logic.
                document.addEventListener('mousedown', (e) => {
                    const btn = e.target.closest('.quantity-plus, .quantity-minus');
                    if (!btn) return;
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }, true);

                document.addEventListener('touchstart', (e) => {
                    const btn = e.target.closest('.quantity-plus, .quantity-minus');
                    if (!btn) return;
                    if (e.cancelable) e.preventDefault();
                    e.stopImmediatePropagation();
                }, true);

                // Quantity plus/minus buttons
                document.addEventListener('click', (e) => {
                    const plusBtn = e.target.closest('.quantity-plus');
                    const minusBtn = e.target.closest('.quantity-minus');
                    if (!plusBtn && !minusBtn) return;
                    const button = plusBtn || minusBtn;
                    if (isLocked(button)) {
                        return;
                    }
                    lock(button);

                    const productId = button.getAttribute('data-product-id');
                    const cartId = button.getAttribute('data-cart-id');
                    const quantityInput = button.parentElement.querySelector('.quantity');

                    if (!quantityInput) {
                        return;
                    }

                    const maxStock = parseInt(quantityInput.getAttribute('max')) || 999999;
                    let currentQty;
                    let newQty;

                    // Always use the actual DOM value for validation to ensure accuracy
                    currentQty = parseInt(quantityInput.value);
                    if (isNaN(currentQty)) currentQty = 0;
                    if (this.isLoggedIn) {
                        // For logged-in users, also check data-initial-qty for consistency
                        const dataInitialQty = parseInt(quantityInput.getAttribute('data-initial-qty'));
                        if (isNaN(dataInitialQty)) {
                            quantityInput.setAttribute('data-initial-qty', '0');
                        }
                        // Use the higher of the two values to ensure we don't miss any updates
                        currentQty = Math.max(currentQty, isNaN(dataInitialQty) ? 0 : dataInitialQty);
                    }

                    if (plusBtn) {
                        // Check if already at maximum stock
                        // Double-check by also looking at the max attribute
                        const inputMax = parseInt(quantityInput.getAttribute('max')) || 999999;
                        const actualMaxStock = Math.min(maxStock, inputMax);

                        if (currentQty >= actualMaxStock) {
                            this.showMaxStockModal();
                            return;
                        }

                        newQty = Math.min(currentQty + 1, actualMaxStock);
                    } else if (minusBtn) {
                        newQty = Math.max(currentQty - 1, 0);
                    }

                    // Only proceed if quantity actually changed
                    if (newQty === currentQty) {
                        return;
                    }

                    // Get selected variants from the quantity input
                    const selectedVariants = JSON.parse(quantityInput.getAttribute('data-selected-variants') || '{}');

                    if (this.isLoggedIn) {
                        // For logged-in users, don't update DOM immediately - let database response handle it
                        this.updateQuantity(productId, cartId, newQty, selectedVariants);
                    } else {
                        // For guest users, update DOM immediately
                        quantityInput.value = newQty;
                        quantityInput.setAttribute('data-initial-qty', String(newQty));

                        // Update subtotal immediately
                        this.updateItemSubtotal(quantityInput);

                        // Update in localStorage
                        this.updateQuantity(productId, cartId, newQty, selectedVariants);
                    }
                });

                // Quantity input change
                document.addEventListener('change', (e) => {
                    if (!e.target.classList.contains('quantity')) return;

                    // Skip if quantity update is already in progress
                    if (this.updatingQuantity) {
                        return;
                    }

                    const input = e.target;
                    const productId = input.getAttribute('data-product-id');
                    const cartId = input.getAttribute('data-cart-id');
                    const selectedVariants = JSON.parse(input.getAttribute('data-selected-variants') || '{}');
                    const newQuantity = parseInt(input.value);
                    const maxStock = parseInt(input.max) || 999999;

                    if (isNaN(newQuantity) || newQuantity < 0) {
                        input.value = 0;
                        this.updateQuantity(productId, cartId, 0, selectedVariants);
                        this.showNotification('Quantity cannot be negative', 'warning');
                        return;
                    }

                    if (newQuantity > maxStock) {
                        input.value = maxStock;
                        this.updateQuantity(productId, cartId, maxStock, selectedVariants);
                        this.showNotification('Maximum available quantity is ' + maxStock, 'warning');
                        this.showMaxStockModal();
                        return;
                    }

                    // Update the data-initial-qty attribute to persist the change
                    input.setAttribute('data-initial-qty', String(newQuantity));
                    this.updateQuantity(productId, cartId, newQuantity, selectedVariants);
                });

                // Real-time input updates
                document.addEventListener('input', (e) => {
                    if (!e.target.classList.contains('quantity')) return;

                    const input = e.target;
                    const newQuantity = parseInt(input.value);

                    if (!isNaN(newQuantity) && newQuantity >= 0) {
                        // Update subtotal in real-time as user types
                        this.updateItemSubtotal(input);
                    }
                });

                // Remove item buttons
                document.addEventListener('click', (e) => {
                    const removeBtn = e.target.closest('.cart-remove-item');
                    if (!removeBtn) return;

                    e.preventDefault();
                    const productId = removeBtn.getAttribute('data-product-id');
                    const cartId = removeBtn.getAttribute('data-cart-id');
                    const selectedVariants = removeBtn.getAttribute('data-selected-variants');

                    let variants = {};
                    if (selectedVariants && selectedVariants !== 'null') {
                        try {
                            variants = JSON.parse(selectedVariants);
                        } catch (e) {
                            console.warn('Error parsing selected variants:', e);
                        }
                    }

                    this.openRemoveItemModal(productId, cartId, variants);
                });
            }

            async updateQuantity(productId, cartId, newQuantity, selectedVariants = {}) {
                // Prevent double updates
                if (this.updatingQuantity) {
                    return;
                }

                this.updatingQuantity = true;

                try {
                    // Quantity can be 0; keep the item in cart
                    if (this.isLoggedIn) {
                        // For logged-in users, only update database - no localStorage
                        const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/cart_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'update',
                                product_id: parseInt(productId),
                                cart_id: parseInt(cartId),
                                quantity: newQuantity,
                                selected_variants: selectedVariants
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            // Update DOM with the new quantity from database
                            this.updateCartDisplay(productId, newQuantity, selectedVariants);
                            this.updateCartTotals();
                            this.updateHeaderCart();
                            this.recalculateCouponOnCartChange();
                        } else {
                            // If database update failed, show error but don't change DOM
                            this.showNotification(data.message || 'Failed to update quantity', 'error');

                            // Show maximum stock reached modal if that's the issue
                            if (data.message && data.message.includes('Insufficient stock')) {
                                this.showMaxStockModal();
                            }
                        }
                    } else {
                        // Guest user - update localStorage only
                        let cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
                        const itemIndex = cart.findIndex(item =>
                            item.product_id === parseInt(productId) &&
                            JSON.stringify(item.selected_variants || {}) === JSON.stringify(selectedVariants)
                        );

                        if (itemIndex > -1) {
                            cart[itemIndex].quantity = newQuantity;
                            cart[itemIndex].last_updated = Date.now();
                            localStorage.setItem('guest_cart', JSON.stringify(cart));

                            // Update DOM display for the specific variant
                            this.updateCartDisplay(productId, newQuantity, selectedVariants);
                            this.updateCartTotals();
                            this.updateHeaderCart();
                            this.recalculateCouponOnCartChange();
                        }
                    }
                } catch (error) {
                    console.error('Error updating quantity:', error);
                    this.showNotification('An error occurred. Please try again.', 'error');
                } finally {
                    // Reset the flag after a short delay to allow for any pending operations
                    setTimeout(() => {
                        this.updatingQuantity = false;
                    }, 100);
                }
            }

            async removeItemFromCart(productId, cartId, selectedVariants = {}) {
                // Call the existing removeItem function
                await this.removeItem(productId, cartId, selectedVariants);
            }

            async removeItem(productId, cartId, selectedVariants = {}) {
                try {
                    if (this.isLoggedIn) {
                        const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/cart_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'remove',
                                product_id: parseInt(productId),
                                selected_variants: selectedVariants
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.removeItemFromDisplay(productId, selectedVariants);
                            this.updateCartTotals();
                            this.updateHeaderCart();
                            this.recalculateCouponOnCartChange();
                            this.showNotification('Item removed from cart', 'success');

                            // Refresh the page after successful removal
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            this.showNotification(data.message || 'Failed to remove item', 'error');
                        }
                    } else {
                        // Guest user - remove specific variant from localStorage
                        let cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');

                        if (Object.keys(selectedVariants).length === 0) {
                            // No variants - remove any item for this product without variants
                            cart = cart.filter(item =>
                                !(item.product_id === parseInt(productId) &&
                                    (!item.selected_variants || Object.keys(item.selected_variants).length === 0))
                            );
                        } else {
                            // Has variants - remove exact variant match
                            const variantKey = JSON.stringify(selectedVariants);
                            cart = cart.filter(item =>
                                !(item.product_id === parseInt(productId) &&
                                    JSON.stringify(item.selected_variants || {}) === variantKey)
                            );
                        }

                        localStorage.setItem('guest_cart', JSON.stringify(cart));
                        this.removeItemFromDisplay(productId, selectedVariants);
                        this.updateCartTotals();
                        this.updateHeaderCart();
                        this.recalculateCouponOnCartChange();
                        this.showNotification('Item removed from cart', 'success');

                        // Refresh the page after successful removal
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } catch (error) {
                    console.error('Error removing item:', error);
                    this.showNotification('An error occurred. Please try again.', 'error');
                }
            }

            openRemoveItemModal(productId, cartId, selectedVariants = {}) {
                const modal = document.getElementById('removeItemModal');
                if (!modal) {
                    this.removeItem(productId, cartId, selectedVariants);
                    return;
                }
                modal.style.display = 'block';

                const closeBtn = document.getElementById('closeRemoveItemModal');
                const noBtn = modal.querySelector('.btn-remove-no');
                const yesBtn = modal.querySelector('.btn-remove-yes');

                const cleanup = () => {
                    modal.style.display = 'none';
                    yesBtn.replaceWith(yesBtn.cloneNode(true));
                    noBtn.replaceWith(noBtn.cloneNode(true));
                    closeBtn.replaceWith(closeBtn.cloneNode(true));
                };

                // Bind once per open
                modal.querySelector('.btn-remove-yes').addEventListener('click', () => {
                    cleanup();
                    this.removeItem(productId, cartId, selectedVariants);
                }, {
                    once: true
                });
                modal.querySelector('.btn-remove-no').addEventListener('click', () => {
                    cleanup();
                }, {
                    once: true
                });
                document.getElementById('closeRemoveItemModal').addEventListener('click', () => {
                    cleanup();
                }, {
                    once: true
                });

                // Click outside to close
                window.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        cleanup();
                    }
                }, {
                    once: true
                });
            }

            updateCartDisplay(productId, newQuantity, selectedVariants = {}) {
                // For logged-in users, find row by cart_id, for guest users by product_id and variants
                let row;
                if (this.isLoggedIn) {
                    // For logged-in users, we need to find the row by cart_id
                    // Since we don't have cart_id in the function call, we'll use product_id and selectedVariants
                    const rows = document.querySelectorAll(`tr[data-product-id="${productId}"]`);
                    row = Array.from(rows).find(r => {
                        const rowVariants = JSON.parse(r.querySelector('.quantity').getAttribute('data-selected-variants') || '{}');
                        return JSON.stringify(rowVariants) === JSON.stringify(selectedVariants);
                    });
                } else {
                    // For guest users, find by product_id and variants
                    const rows = document.querySelectorAll(`tr[data-product-id="${productId}"]`);
                    row = Array.from(rows).find(r => {
                        const rowVariants = JSON.parse(r.querySelector('.quantity').getAttribute('data-selected-variants') || '{}');
                        return JSON.stringify(rowVariants) === JSON.stringify(selectedVariants);
                    });
                }

                if (!row) return;

                const quantityInput = row.querySelector('.quantity');
                const priceElement = row.querySelector('.product-price .amount');
                const subtotalElement = row.querySelector('.subtotal-amount');
                const coinElement = row.querySelector('.coin-value');

                // Always update the quantity display (allow 0 to remain visible)

                quantityInput.value = newQuantity;
                // Persist new quantity for any later in-page restores
                quantityInput.setAttribute('data-initial-qty', String(newQuantity));
                // Note: 0 quantity items are now removed entirely, so no special handling needed here

                // Update subtotal using the helper method
                this.updateItemSubtotal(quantityInput);

                // Update coins if applicable
                if (coinElement) {
                    const coinValue = parseFloat(quantityInput.getAttribute('data-coin'));
                    const coinsTotal = (coinValue * newQuantity);
                    coinElement.textContent = coinsTotal.toFixed(2);
                }
            }

            updateItemSubtotal(quantityInput) {
                const row = quantityInput.closest('tr');
                if (!row) return;

                const subtotalElement = row.querySelector('.subtotal-amount');
                if (!subtotalElement) return;

                const price = parseFloat(quantityInput.getAttribute('data-price'));
                const quantity = parseInt(quantityInput.value) || 0;
                const newSubtotal = price * quantity;

                updateCurrencyDisplay(subtotalElement, newSubtotal);
            }

            removeItemFromDisplay(productId, selectedVariants = {}) {
                // Find the specific row with matching variants
                const rows = document.querySelectorAll(`tr[data-product-id="${productId}"]`);
                let targetRow = null;

                if (Object.keys(selectedVariants).length === 0) {
                    // No variants - find any row for this product without variants
                    targetRow = Array.from(rows).find(row => {
                        const variantsAttr = row.getAttribute('data-selected-variants');
                        if (!variantsAttr || variantsAttr === 'null') return true;
                        try {
                            const variants = JSON.parse(variantsAttr);
                            return Object.keys(variants).length === 0;
                        } catch (e) {
                            return true;
                        }
                    });
                } else {
                    // Has variants - find exact variant match
                    const variantKey = JSON.stringify(selectedVariants);
                    targetRow = Array.from(rows).find(row => {
                        const variantsAttr = row.getAttribute('data-selected-variants');
                        if (!variantsAttr || variantsAttr === 'null') return false;
                        try {
                            const variants = JSON.parse(variantsAttr);
                            return JSON.stringify(variants) === variantKey;
                        } catch (e) {
                            return false;
                        }
                    });
                }

                if (targetRow) {
                    targetRow.remove();
                }

                // Do not auto-hide cart when items exist with 0 quantity
            }

            updateCartTotals() {
                let total = 0;
                let itemCount = 0;
                let totalCoins = 0;

                try {
                    const rows = document.querySelectorAll('tr[data-product-id]');
                    rows.forEach(row => {
                        const quantityInput = row.querySelector('.quantity');
                        if (!quantityInput) {
                            console.warn('Quantity input not found for row:', row);
                            return;
                        }

                        const quantity = parseInt(quantityInput.value) || 0;
                        const price = parseFloat(quantityInput.getAttribute('data-price')) || 0;
                        const coin = parseFloat(quantityInput.getAttribute('data-coin') || 0);

                        if (quantity > 0 && price > 0) {
                            total += price * quantity;
                            itemCount += quantity;
                            totalCoins += coin * quantity;
                        }
                    });
                } catch (error) {
                    console.error('Error updating cart totals:', error);
                }

                // Update totals display with error handling
                try {
                    const cartTotalElement = document.getElementById('cart-total');
                    const cartItemCountElement = document.getElementById('cart-item-count');
                    const totalCoinsElement = document.getElementById('total-coins');
                    const cartSubtotalElement = document.getElementById('cart-subtotal');
                    const finalTotalElement = document.getElementById('final-total-amount');

                    if (cartTotalElement) updateCurrencyDisplay(cartTotalElement, total);
                    if (cartItemCountElement) cartItemCountElement.textContent = '(' + itemCount + ' items)';
                    if (totalCoinsElement) updateNumberDisplay(totalCoinsElement, totalCoins);
                    if (cartSubtotalElement) updateCurrencyDisplay(cartSubtotalElement, total);
                    if (finalTotalElement) updateCurrencyDisplay(finalTotalElement, total);

                    // Force visual update for cart-subtotal
                    if (cartSubtotalElement) {
                        this.forceVisualUpdate(cartSubtotalElement, formatCurrency(total));
                        // Also try complete section rebuild as backup
                        setTimeout(() => this.rebuildEntireCartSummary(), 100);
                    }

                    // Print values to console for debugging
                    console.log('Calculated total:', total);
                    console.log('Cart Subtotal Value:', cartSubtotalElement ? cartSubtotalElement.textContent : 'NOT FOUND');
                    console.log('Final Total Value:', finalTotalElement ? finalTotalElement.textContent : 'NOT FOUND');
                } catch (error) {
                    console.error('Error updating total displays:', error);
                }

                // If there's an applied coupon, recalculate with coupon
                setTimeout(() => {
                    this.checkAndRecalculateCoupon();
                }, 100);

                // Enable/disable clear cart button based on cart content
                const clearCartBtn = document.querySelector('.btn-clear');
                if (clearCartBtn) {
                    if (itemCount > 0) {
                        clearCartBtn.disabled = false;
                        clearCartBtn.style.opacity = '1';
                        clearCartBtn.style.cursor = 'pointer';
                    } else {
                        clearCartBtn.disabled = true;
                        clearCartBtn.style.opacity = '0.5';
                        clearCartBtn.style.cursor = 'not-allowed';
                    }
                }

                // Update header cart if function exists
                this.updateHeaderCart();
            }

            async loadGuestCart() {
                const cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');

                if (cart.length === 0) {
                    this.showEmptyCart();
                    return;
                }

                try {
                    const productIds = cart.map(item => item.product_id).join(',');
                    const response = await fetch(`get_products.php?ids=${productIds}`);
                    const data = await response.json();

                    if (data.success && data.products.length > 0) {
                        this.renderGuestCart(cart, data.products);
                        // Ensure quantities are properly set after rendering
                        setTimeout(() => {
                            this.ensureGuestCartQuantities();
                        }, 100);
                    } else {
                        this.showEmptyCart();
                    }
                } catch (error) {
                    console.error('Error loading guest cart:', error);
                    this.showEmptyCart();
                }
            }

            ensureGuestCartQuantities() {
                // For guest users, ensure quantities match localStorage values
                try {
                    const cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
                    const inputs = document.querySelectorAll('.quantity[data-product-id]');

                    inputs.forEach(input => {
                        const productId = parseInt(input.getAttribute('data-product-id'));
                        const selectedVariants = JSON.parse(input.getAttribute('data-selected-variants') || '{}');

                        // Find the specific cart item by product_id AND selectedVariants
                        const cartItem = cart.find(item =>
                            item.product_id === productId &&
                            JSON.stringify(item.selected_variants || {}) === JSON.stringify(selectedVariants)
                        );

                        if (cartItem) {
                            const storedQuantity = cartItem.quantity;
                            const maxStock = parseInt(input.getAttribute('max')) || 999999;
                            const value = Math.min(Math.max(0, (storedQuantity ?? 0)), maxStock);

                            // Set the value to match localStorage
                            input.value = value;
                            input.setAttribute('data-initial-qty', String(value));

                            // Update subtotal
                            this.updateItemSubtotal(input);
                        }
                    });

                    // Update all totals after setting quantities
                    this.updateCartTotals();
                    this.checkAndRecalculateCoupon();
                } catch (e) {
                    console.warn('Guest cart quantity restore failed:', e);
                }
            }

            renderGuestCart(cart, products) {
                const tbody = document.getElementById('cart-table-body');
                let html = '';

                cart.forEach(cartItem => {
                    const product = products.find(p => p.id == cartItem.product_id);
                    if (!product) return;

                    const images = this.parseProductImages(product.images);
                    const mainImage = images.length > 0 ? images[0] : this.defaultImage;

                    // Use variant price if available, otherwise use base price
                    const itemPrice = cartItem.variant_prices && cartItem.variant_prices.selling_price ?
                        parseFloat(cartItem.variant_prices.selling_price) :
                        parseFloat(product.selling_price);
                    const subtotal = itemPrice * cartItem.quantity;

                    // Generate variant display HTML
                    let variantHtml = '';
                    if (cartItem.selected_variants) {
                        variantHtml = '<div class="selected-variants mt-2">';

                        // Loop through all selected variants
                        for (const [key, value] of Object.entries(cartItem.selected_variants)) {
                            // Skip variant_id fields and empty values
                            if (!value || key.endsWith('_variant_id')) continue;

                            const displayKey = key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');

                            variantHtml += `
                                <span class="variant-item">
                                    <strong>${displayKey}:</strong> 
                                    ${key === 'color' ? `<span class="color-swatch" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: ${value}; margin-left: 5px; vertical-align: middle;"></span>` : ''}
                                    ${value}
                                </span>
                                <br>
                            `;
                        }

                        variantHtml += '</div>';
                    }

                    html += `
                        <tr data-product-id="${product.id}" data-cart-id="guest-${product.id}">
                            <td class="product-thumbnail">
                                <div class="p-relative">
                                    <a href="product-detail.php?id=${product.id}">
                                        <figure>
                                            <img src="${mainImage}" 
                                                 alt="${this.escapeHtml(product.product_name)}" 
                                                 width="300" height="338"
                                                 onerror="this.src='${this.defaultImage}';">
                                        </figure>
                                    </a>
                                    <button class="btn btn-link btn-close cart-remove-item" 
                                            data-product-id="${product.id}" 
                                            data-cart-id="guest-${product.id}"
                                            data-selected-variants="${JSON.stringify(cartItem.selected_variants || {}).replace(/"/g, '&quot;')}">
                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                                    </button>
                                </div>
                            </td>
                            <td class="product-name">
                                <a href="product-detail.php?id=${product.id}">
                                    ${this.escapeHtml(product.product_name)}
                                </a>
                                <br>
                                ${variantHtml}
                                <small class="stock-info ${product.Inventory > 0 ? 'text-success' : 'text-danger'}">
                                    ${product.Inventory > 0 ? 'In Stock (' + product.Inventory + ')' : 'Out of Stock'}
                                </small>
                                <br>
                                ${product.coin > 0 ? `
                                    <span class="d-block product-price1">You will earn 
                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid ml-1" alt="" style="width: 20px; height: 20px;">
                                        <span class="coin-value">${product.coin * cartItem.quantity}</span> coins
                                    </span>
                                ` : ''}
                            </td>
                            <td class="product-price text-center">
                                <span class="amount">₹${itemPrice.toFixed(2)}</span>
                                ${itemPrice !== parseFloat(product.selling_price) ? `
                                    <br><small class="text-muted">Base: ₹${parseFloat(product.selling_price).toFixed(2)}</small>
                                ` : ''}
                            </td>
                            <td class="product-quantity text-center">
                                <div class="cart-position1">
                                    <div class="cart-position">
                                        <button class="quantity-plus w-icon-plus button2" 
                                                data-product-id="${product.id}" 
                                                data-cart-id="guest-${product.id}"
                                                data-max-stock="${product.Inventory}"></button>
                                        <input class="quantity form-control text-center" 
                                               type="number" 
                                               min="0" 
                                               max="${product.Inventory}"
                                               value="${cartItem.quantity}"
                                               data-initial-qty="${cartItem.quantity}"
                                               data-product-id="${product.id}"
                                               data-cart-id="guest-${product.id}"
                                               data-price="${itemPrice}"
                                               data-coin="${product.coin}"
                                               data-selected-variants="${JSON.stringify(cartItem.selected_variants || {}).replace(/"/g, '&quot;')}"
                                               data-variant-prices="${JSON.stringify(cartItem.variant_prices || {}).replace(/"/g, '&quot;')}">
                                        <button class="quantity-minus w-icon-minus button1" 
                                                data-product-id="${product.id}" 
                                                data-cart-id="guest-${product.id}"></button>
                                    </div>
                                </div>
                            </td>
                            <td class="product-subtotal text-center">
                                <span class="amount subtotal-amount">₹${subtotal.toFixed(2)}</span>
                            </td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
                this.updateCartTotals();
            }

            showEmptyCart() {
                const tbody = document.getElementById('cart-table-body');
                tbody.innerHTML = `
                    <tr id="empty-cart-row">
                        <td colspan="5" class="text-center py-5">
                            <div class="empty-cart">
                                <i class="w-icon-cart" style="font-size: 48px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                                <h4>Your cart is empty</h4>
                                <p>Add some products to your cart to see them here.</p>
                                <a href="shop.php" class="btn btn-primary btn-rounded">Continue Shopping</a>
                            </div>
                        </td>
                    </tr>
                `;

                // Reset totals
                document.getElementById('cart-total').textContent = '₹0.00';
                document.getElementById('cart-subtotal').textContent = '₹0.00';
                document.getElementById('cart-item-count').textContent = '(0 items)';
                document.getElementById('total-coins').textContent = '0';

                // Reset final total amount
                const finalTotalElement = document.getElementById('final-total-amount');
                if (finalTotalElement) {
                    finalTotalElement.textContent = '₹0.00';
                }

                // Disable clear cart button when empty
                const clearCartBtn = document.querySelector('.btn-clear');
                if (clearCartBtn) {
                    clearCartBtn.disabled = true;
                    clearCartBtn.style.opacity = '0.5';
                    clearCartBtn.style.cursor = 'not-allowed';
                }
            }

            parseProductImages(imagesData) {
                if (!imagesData) return [];

                try {
                    const parsed = JSON.parse(imagesData);
                    if (Array.isArray(parsed)) {
                        return parsed.map(img => this.baseImageUrl + img).filter(img => img);
                    }
                } catch (e) {
                    const images = imagesData.split(',').map(img => img.trim()).filter(img => img);
                    return images.map(img => this.baseImageUrl + img);
                }
                return [];
            }

            updateHeaderCart() {
                if (typeof window.updateHeaderCart === 'function') {
                    window.updateHeaderCart();
                }
            }

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `cart-notification cart-notification-${type}`;

                const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠' : 'ℹ';
                notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

                notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; color: white; padding: 16px 24px;
                    z-index: 10000; font-size: 14px; border-radius: 8px; max-width: 350px;
                    background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' : 
                                 type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' : 
                                 type === 'warning' ? 'linear-gradient(135deg, #f39c12, #e67e22)' :
                                 'linear-gradient(135deg, #3498db, #2980b9)'};
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease;
                `;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }, 4000);
            }

            showMaxStockModal() {
                // Remove any existing modal first
                const existingModal = document.getElementById('maxStockModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Create new modal
                const modal = document.createElement('div');
                modal.id = 'maxStockModal';
                modal.innerHTML = `
                    <div class="modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
                        <div class="modal-content" style="background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3); animation: scaleIn 0.3s ease;">
                            <div style="font-size: 48px; color: #e74c3c; margin-bottom: 20px;">⚠️</div>
                            <h3 style="color: #2c3e50; margin-bottom: 15px; font-size: 20px;">Maximum Stock Reached</h3>
                            <p style="color: #7f8c8d; margin-bottom: 25px; line-height: 1.5;">You have reached the maximum available quantity for this item. Please reduce the quantity or choose a different variant.</p>
                            <button onclick="this.closest('.modal-overlay').remove()" style="background: #3498db; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: background 0.2s ease;" onmouseover="this.style.background='#2980b9'" onmouseout="this.style.background='#3498db'">Got it</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);

                // Add click outside to close
                modal.querySelector('.modal-overlay').addEventListener('click', (e) => {
                    if (e.target === e.currentTarget) {
                        modal.remove();
                    }
                });
            }

            // Function to validate and sync quantity states
            validateQuantityStates() {
                const quantityInputs = document.querySelectorAll('.quantity');
                quantityInputs.forEach(input => {
                    const currentValue = parseInt(input.value) || 1;
                    const maxStock = parseInt(input.getAttribute('max')) || 999999;
                    const dataInitial = parseInt(input.getAttribute('data-initial-qty')) || 1;

                    // Ensure data-initial-qty matches the current value
                    if (dataInitial !== currentValue) {
                        input.setAttribute('data-initial-qty', String(currentValue));
                    }

                    // Ensure value doesn't exceed max stock
                    if (currentValue > maxStock) {
                        input.value = maxStock;
                        input.setAttribute('data-initial-qty', String(maxStock));
                    }
                });
            }

            initCouponSystem() {
                const applyBtn = document.getElementById('apply-coupon-btn');
                const removeBtn = document.getElementById('remove-coupon-btn');
                const couponInput = document.getElementById('coupon-input');

                if (applyBtn) {
                    applyBtn.addEventListener('click', () => {
                        this.applyCoupon();
                    });
                }

                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        this.removeCoupon();
                    });
                }

                if (couponInput) {
                    couponInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            this.applyCoupon();
                        }
                    });
                }
            }

            async loadAppliedCoupon() {
                try {
                    const response = await fetch('coupon_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'get_applied'
                        })
                    });

                    const data = await response.json();
                    if (data.success && data.coupon) {
                        this.displayAppliedCoupon(data.coupon);
                        this.updateCartTotalsWithCoupon(data.coupon);
                    }
                } catch (error) {
                    console.error('Error loading applied coupon:', error);
                }
            }

            async applyCoupon() {
                const couponInput = document.getElementById('coupon-input');
                const applyBtn = document.getElementById('apply-coupon-btn');
                const loader = document.getElementById('apply-coupon-loader');
                const applyText = document.getElementById('apply-coupon-text');

                const couponCode = couponInput.value.trim();
                if (!couponCode) {
                    this.showCouponMessage('Please enter a coupon code', 'error');
                    return;
                }

                // Get current cart total
                const cartTotal = this.getCurrentCartTotal();
                if (cartTotal <= 0) {
                    this.showCouponMessage('Your cart is empty', 'error');
                    return;
                }

                // Show loading state
                applyBtn.disabled = true;
                loader.style.display = 'inline';
                applyText.textContent = 'Applying...';

                try {
                    const response = await fetch('coupon_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'validate',
                            coupon_code: couponCode,
                            cart_total: cartTotal
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showCouponMessage(data.message, 'success');
                        this.displayAppliedCoupon(data.coupon);
                        this.updateCartTotalsWithCoupon(data.coupon);
                        couponInput.value = '';
                    } else {
                        this.showCouponMessage(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error applying coupon:', error);
                    this.showCouponMessage('An error occurred. Please try again.', 'error');
                } finally {
                    // Reset button state
                    applyBtn.disabled = false;
                    loader.style.display = 'none';
                    applyText.textContent = 'Apply coupon';
                }
            }

            async removeCoupon() {
                try {
                    const response = await fetch('coupon_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'remove'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.hideAppliedCoupon();
                        this.updateCartTotals();
                        this.showCouponMessage(data.message, 'success');
                    } else {
                        this.showCouponMessage(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error removing coupon:', error);
                    this.showCouponMessage('An error occurred. Please try again.', 'error');
                }
            }

            displayAppliedCoupon(coupon) {
                const appliedDisplay = document.getElementById('applied-coupon-display');
                const couponInputForm = document.getElementById('coupon-input-form');
                const couponCode = document.getElementById('applied-coupon-code');
                const couponType = document.getElementById('applied-coupon-type');
                const discountAmount = document.getElementById('coupon-discount-amount');

                if (appliedDisplay && couponInputForm && couponCode && couponType) {
                    couponCode.textContent = coupon.code;
                    couponType.textContent = coupon.discount_type;

                    // Update discount amount in the card
                    if (discountAmount) {
                        if (coupon.free_shipping) {
                            discountAmount.textContent = 'Free Shipping';
                        } else {
                            discountAmount.textContent = '₹' + coupon.discount_amount.toFixed(2);
                        }
                    }

                    // Show applied coupon card and hide input form
                    appliedDisplay.style.display = 'block';
                    couponInputForm.style.display = 'none';
                }
            }

            hideAppliedCoupon() {
                const appliedDisplay = document.getElementById('applied-coupon-display');
                const couponInputForm = document.getElementById('coupon-input-form');
                const couponSavingsRow = document.getElementById('coupon-savings-row');
                const freeShippingCoupon = document.getElementById('free-shipping-coupon');

                if (appliedDisplay && couponInputForm) {
                    appliedDisplay.style.display = 'none';
                    couponInputForm.style.display = 'block';
                }

                if (couponSavingsRow) {
                    couponSavingsRow.style.display = 'none';
                }

                if (freeShippingCoupon) {
                    freeShippingCoupon.style.display = 'none';
                }
            }

            updateCartTotalsWithCoupon(coupon) {
                // Update coupon savings display
                const couponSavingsRow = document.getElementById('coupon-savings-row');
                const couponSavings = document.getElementById('coupon-savings');
                const freeShippingCoupon = document.getElementById('free-shipping-coupon');
                const discountAmountInCard = document.getElementById('coupon-discount-amount');

                // Show coupon savings in order summary
                if (coupon.discount_amount > 0) {
                    if (couponSavingsRow && couponSavings) {
                        couponSavings.textContent = '- ' + formatCurrency(coupon.discount_amount);
                        couponSavingsRow.style.display = 'flex';
                    }
                } else if (coupon.free_shipping) {
                    // For free shipping, show different message
                    if (couponSavingsRow && couponSavings) {
                        couponSavings.textContent = 'Free Shipping';
                        couponSavingsRow.style.display = 'flex';
                    }
                }

                // Update free shipping indicator
                if (coupon.free_shipping) {
                    if (freeShippingCoupon) {
                        freeShippingCoupon.style.display = 'inline';
                    }
                }

                // Update discount amount in the coupon card
                if (discountAmountInCard) {
                    if (coupon.free_shipping) {
                        discountAmountInCard.textContent = 'Free Shipping';
                    } else {
                        discountAmountInCard.textContent = formatCurrency(coupon.discount_amount);
                    }
                }

                // Update all total fields consistently
                const cartTotal = document.getElementById('cart-total');
                const cartSubtotal = document.getElementById('cart-subtotal');
                const finalTotalAmount = document.getElementById('final-total-amount');

                // Prefer the already-rendered cart total to avoid race conditions with DOM updates
                let originalTotal = 0;
                if (cartTotal && cartTotal.textContent) {
                    originalTotal = parseFloat(cartTotal.textContent.replace('₹', '').replace(/,/g, '')) || 0;
                }
                if (!originalTotal) {
                    originalTotal = this.getCurrentCartTotal();
                }

                const discount = Math.max(0, parseFloat(coupon.discount_amount || 0));
                const newTotal = Math.max(0, originalTotal - discount);

                // Only update Sub Total / Final Total; keep Cart Total as-is (pre-discount)
                if (cartSubtotal) {
                    cartSubtotal.textContent = '₹' + newTotal.toFixed(2);
                    // Force visual update for cart-subtotal
                    this.forceVisualUpdate(cartSubtotal, '₹' + newTotal.toFixed(2));
                }
                if (finalTotalAmount) {
                    finalTotalAmount.textContent = '₹' + newTotal.toFixed(2);
                }

                // Print values to console for debugging
                console.log('Original Total:', originalTotal);
                console.log('New Total:', newTotal);
                console.log('Cart Subtotal Value:', cartSubtotal ? cartSubtotal.textContent : 'NOT FOUND');
                console.log('Final Total Value:', finalTotalAmount ? finalTotalAmount.textContent : 'NOT FOUND');
            }

            showCouponMessage(message, type) {
                const messageDiv = document.getElementById('coupon-message');
                if (!messageDiv) return;

                messageDiv.textContent = message;
                messageDiv.style.display = 'block';

                // Remove existing classes
                messageDiv.classList.remove('text-success', 'text-danger', 'text-info');

                // Add appropriate class based on type
                switch (type) {
                    case 'success':
                        messageDiv.classList.add('text-success');
                        break;
                    case 'error':
                        messageDiv.classList.add('text-danger');
                        break;
                    default:
                        messageDiv.classList.add('text-info');
                }

                // Hide message after 5 seconds
                setTimeout(() => {
                    if (type === 'success') {
                        messageDiv.style.display = 'none';
                    }
                }, 5000);
            }

            getCurrentCartTotal() {
                let total = 0;
                const rows = document.querySelectorAll('tr[data-product-id]');
                rows.forEach(row => {
                    const quantityInput = row.querySelector('.quantity');
                    if (quantityInput) {
                        const quantity = parseInt(quantityInput.value);
                        const price = parseFloat(quantityInput.getAttribute('data-price'));
                        total += price * quantity;
                    }
                });
                return total;
            }

            async recalculateCouponOnCartChange() {
                try {
                    const cartTotal = this.getCurrentCartTotal();
                    const response = await fetch('coupon_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'recalculate',
                            cart_total: cartTotal
                        })
                    });

                    const data = await response.json();
                    if (data.success && data.coupon) {
                        this.updateCartTotalsWithCoupon(data.coupon);
                    } else if (data.coupon_removed) {
                        this.hideAppliedCoupon();
                        this.showCouponMessage(data.message, 'warning');
                        // ensure totals show raw cart total when coupon removed
                        this.updateCartTotals();
                    }
                } catch (error) {
                    console.error('Error recalculating coupon:', error);
                }
            }

            async checkAndRecalculateCoupon() {
                try {
                    const cartTotal = this.getCurrentCartTotal();
                    const response = await fetch('coupon_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'recalculate',
                            cart_total: cartTotal
                        })
                    });

                    const data = await response.json();
                    if (data.success && data.coupon) {
                        this.updateCartTotalsWithCoupon(data.coupon);
                    } else if (data.coupon_removed) {
                        this.hideAppliedCoupon();
                        this.showCouponMessage(data.message, 'warning');
                        this.updateCartTotals();
                    }
                    // If no coupon applied, do nothing - Sub Total stays as cart total
                } catch (error) {
                    // If coupon API fails, just keep Sub Total as cart total
                }
            }

            async clearCart() {
                try {
                    if (this.isLoggedIn) {
                        // Clear cart from database for logged-in users
                        const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/cart_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'clear'
                            })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.showEmptyCart();
                            this.updateHeaderCart();
                            this.hideAppliedCoupon();
                            this.removeCoupon(); // Remove coupon from session
                            this.showNotification('Cart cleared successfully', 'success');
                        } else {
                            this.showNotification(data.message || 'Failed to clear cart', 'error');
                        }
                    } else {
                        // Clear cart from localStorage for guest users
                        localStorage.removeItem('guest_cart');
                        this.showEmptyCart();
                        this.updateHeaderCart();
                        this.hideAppliedCoupon();
                        this.showNotification('Cart cleared successfully', 'success');
                    }
                } catch (error) {
                    console.error('Error clearing cart:', error);
                    this.showNotification('An error occurred while clearing cart', 'error');
                }
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Debug function to check cart state
            debugCartState() {
                console.log('Is Logged In:', this.isLoggedIn);

                if (this.isLoggedIn) {
                    const rows = document.querySelectorAll('tr[data-product-id]');
                    rows.forEach((row, index) => {
                        const productId = row.getAttribute('data-product-id');
                        const quantityInput = row.querySelector('.quantity');
                        const quantity = quantityInput ? quantityInput.value : 'N/A';
                        const dataInitial = quantityInput ? quantityInput.getAttribute('data-initial-qty') : 'N/A';
                    });
                } else {}

                console.log('Cart totals:', {
                    cartTotal: document.getElementById('cart-total')?.textContent,
                    cartSubtotal: document.getElementById('cart-subtotal')?.textContent,
                    finalTotal: document.getElementById('final-total-amount')?.textContent
                });
            }
        }

        // Initialize dynamic cart manager
        const dynamicCartManager = new DynamicCartManager();

        // Add debug function to window for testing
        window.debugCart = () => dynamicCartManager.debugCartState();

        // Add function to check current subtotal and total values
        window.checkTotals = () => {
            const cartSubtotal = document.getElementById('cart-subtotal');
            const finalTotal = document.getElementById('final-total-amount');
            console.log('Cart Subtotal Element:', cartSubtotal);
            console.log('Final Total Element:', finalTotal);
            console.log('Are they the same?', cartSubtotal && finalTotal ? cartSubtotal.textContent === finalTotal.textContent : 'Cannot compare');
        };
    </script>

    <!-- Clear Cart Modal JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const clearCartBtn = document.querySelector('.btn-clear');
            const modal = document.getElementById('clearCartModal');
            const closeModal = document.getElementById('closeModal');
            const btnNo = document.querySelector('.btn-no');
            const btnYes = document.querySelector('.btn-yes');

            // Show modal when Clear Cart button is clicked
            clearCartBtn.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent any default form submission

                // Check if cart is empty
                const cartRows = document.querySelectorAll('tr[data-product-id]');
                if (cartRows.length === 0) {
                    // Show notification instead of modal if cart is empty
                    if (typeof dynamicCartManager !== 'undefined') {
                        dynamicCartManager.showNotification('Your cart is already empty', 'info');
                    }
                    return;
                }

                modal.style.display = 'block';
            });

            // Hide modal when Close button or No button is clicked
            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            btnNo.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Handle Yes button click (clear cart logic)
            btnYes.addEventListener('click', function() {
                if (typeof dynamicCartManager !== 'undefined') {
                    dynamicCartManager.clearCart();
                } else {
                    // Fallback for older implementation
                    const cartTable = document.querySelector('.cart-table tbody');
                    if (cartTable) {
                        cartTable.innerHTML = '';
                    }
                }
                modal.style.display = 'none';
                // You can add additional logic like updating the cart summary here
            });

            // Hide modal if clicked outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stickyElement = document.querySelector('.containt-sticy2');

            window.addEventListener('scroll', function() {
                if (window.scrollY >= 60) {
                    stickyElement.classList.add('containt-sticy');
                } else {
                    stickyElement.classList.remove('containt-sticy');
                }
            });
        });

        // Checkout Button Login Check
        document.addEventListener('DOMContentLoaded', function() {
            const checkoutBtn = document.getElementById('proceed-checkout-btn');
            if (checkoutBtn) {
                checkoutBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Check if user is logged in (PHP variable passed to JavaScript)
                    const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

                    if (!isLoggedIn) {
                        showLoginRequiredModal();
                        return false;
                    }

                    // Require at least one item with quantity > 0 for checkout
                    const cartRows = document.querySelectorAll('#cart-table-body tr[data-product-id]');
                    const hasPositiveQty = Array.from(cartRows).some(row => {
                        const qtyInput = row.querySelector('.quantity');
                        const qtyVal = qtyInput ? parseInt(qtyInput.value) : 0;
                        return !isNaN(qtyVal) && qtyVal > 0;
                    });
                    if (!hasPositiveQty) {
                        showWarningNotification('Empty Cart', 'Please set quantity above 0 for at least one item before checkout.');
                        return false;
                    }

                    // Check if address is selected
                    const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
                    if (!selectedAddress) {
                        showWarningNotification('Address Required', 'Please select a delivery address to proceed.');
                        return false;
                    }

                    // All checks passed, show payment method modal
                    showPaymentMethodModal();
                });
            }
        });

        // Beautiful Notification System
        function showNotification(type, title, message, duration = 5000) {
            const container = document.getElementById('notification-container');
            if (!container) return;

            const notification = document.createElement('div');
            notification.className = `notification ${type}`;

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            notification.innerHTML = `
        <div class="notification-icon">
            <i class="${icons[type] || icons.info}"></i>
        </div>
        <div class="notification-content">
            <div class="notification-title">${title}</div>
            <div class="notification-message">${message}</div>
        </div>
        <div class="notification-close">
            <i class="fas fa-times"></i>
        </div>
    `;

            // Add click to close
            notification.addEventListener('click', () => {
                removeNotification(notification);
            });

            container.appendChild(notification);

            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    removeNotification(notification);
                }, duration);
            }
        }

        function removeNotification(notification) {
            if (notification && notification.parentNode) {
                notification.classList.add('slide-out');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }
        }

        // Login Required Modal Functions
        function showLoginRequiredModal() {
            const modal = document.getElementById('login-required-modal');
            if (modal) {
                modal.style.display = 'flex';
                // Add click outside to close
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeLoginRequiredModal();
                    }
                });
            }
        }

        function closeLoginRequiredModal() {
            const modal = document.getElementById('login-required-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Helper functions for common notifications
        function showSuccessNotification(title, message) {
            showNotification('success', title, message);
        }

        function showErrorNotification(title, message) {
            showNotification('error', title, message);
        }

        function showWarningNotification(title, message) {
            showNotification('warning', title, message);
        }

        function showInfoNotification(title, message) {
            showNotification('info', title, message);
        }

        // Payment Method Modal Functions
        function showPaymentMethodModal() {
            const modal = document.getElementById('payment-method-modal');
            if (modal) {
                modal.style.display = 'flex';
                // Add click outside to close
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closePaymentModal();
                    }
                });

                // Add click handlers for payment options
                const paymentOptions = document.querySelectorAll('.payment-option');
                paymentOptions.forEach(option => {
                    option.addEventListener('click', () => {
                        // Remove selected class from all options
                        paymentOptions.forEach(opt => opt.classList.remove('selected'));
                        // Add selected class to clicked option
                        option.classList.add('selected');
                        // Check the radio button
                        const radio = option.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = true;
                        }
                    });
                });
            }
        }

        function closePaymentModal() {
            const modal = document.getElementById('payment-method-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Order Processing Functions
        async function proceedWithPayment() {
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedPayment) {
                showErrorNotification('Payment Error', 'Please select a payment method.');
                return;
            }

            const paymentMethod = selectedPayment.value;

            // Get cart items with variant information
            const cartItems = [];
            const cartRows = document.querySelectorAll('#cart-table-body tr[data-product-id]');

            cartRows.forEach(row => {
                const productId = row.getAttribute('data-product-id');
                const quantityInput = row.querySelector('.quantity');
                const quantity = parseInt(quantityInput.value) || 0;
                if (quantity === 0) return;

                const price = quantityInput.getAttribute('data-price');
                const coin = quantityInput.getAttribute('data-coin');

                let selectedVariants = {};
                let variantPrices = {};

                try {
                    const selectedVariantsJson = quantityInput.getAttribute('data-selected-variants');
                    const variantPricesJson = quantityInput.getAttribute('data-variant-prices');
                    if (selectedVariantsJson && selectedVariantsJson !== 'null') selectedVariants = JSON.parse(selectedVariantsJson);
                    if (variantPricesJson && variantPricesJson !== 'null') variantPrices = JSON.parse(variantPricesJson);
                } catch (e) {
                    console.warn('Error parsing variant data:', e);
                }

                cartItems.push({
                    product_id: parseInt(productId),
                    quantity,
                    price: parseFloat(price),
                    coin: parseInt(coin) || 0,
                    selected_variants: selectedVariants,
                    variant_prices: variantPrices
                });
            });

            if (cartItems.length === 0) {
                showErrorNotification('Cart Error', 'Your cart is empty.');
                return;
            }

            // Get selected address
            const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
            if (!selectedAddress) {
                showErrorNotification('Address Error', 'Please select a delivery address.');
                return;
            }

            // Get totals
            const cartTotal = parseFloat(document.getElementById('cart-total').textContent.replace('₹', '').replace(',', ''));
            const shippingCharge = parseFloat(document.getElementById('shipping-charges').textContent.replace('₹', '').replace(',', '')) || 0;
            const couponSaving = parseFloat(document.getElementById('coupon-savings').textContent.replace('- ₹', '').replace(',', '')) || 0;
            const finalTotal = parseFloat(document.getElementById('final-total-amount').textContent.replace('₹', '').replace(',', ''));
            const couponCode = document.getElementById('applied-coupon-code')?.textContent || null;

            // Proceed with online payment
            if (paymentMethod === 'online') {
                try {
                    // Create order on server
                    const orderResponse = await fetch('<?php echo USER_BASEURL; ?>app/pages/order_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create_online_order',
                            total_amount: finalTotal,
                            address_id: parseInt(selectedAddress.value),
                            cart_amount: cartTotal,
                            shipping_charge: shippingCharge,
                            coupon_saving: couponSaving,
                            coupon_code: couponCode,
                            products: cartItems
                        })
                    });

                    const orderData = await orderResponse.json();

                    if (!orderData.success) throw new Error(orderData.message || 'Failed to create order.');
                    // Open Razorpay checkout
                    const options = {
                        key: orderData.razorpay_key,
                        amount: orderData.amount,
                        currency: 'INR',
                        name: 'Hagidy',
                        description: 'Order #' + orderData.order_id,
                        order_id: orderData.razorpay_order_id,
                        handler: async function(response) {
                            // Verify payment on server
                            const verifyResp = await fetch('<?php echo USER_BASEURL; ?>app/pages/order_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: 'verify_online_payment',
                                    razorpay_payment_id: response.razorpay_payment_id,
                                    razorpay_order_id: response.razorpay_order_id,
                                    razorpay_signature: response.razorpay_signature,
                                    order_id: orderData.order_id
                                })
                            });

                            const verifyData = await verifyResp.json();
                            if (verifyData.success) {
                                const now = new Date();
                                const transactionDate = now.toLocaleDateString('en-GB').split('/').join('-');
                                const transactionTime = now.toLocaleTimeString('en-GB', {
                                    hour12: false
                                });

                                const formData = {
                                    TransactionAmount: finalTotal,
                                    MerchantUsername: 'V000002591',
                                    transactionDate: transactionDate,
                                    AssociateUsername: '<?php echo addslashes($customerId); ?>',
                                };

                                const billingResponse = await fetch('<?php echo ADMIN_BASEURL; ?>admins/billing', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify(formData)
                                });

                                const billingData = await billingResponse.json();
                                console.log('Billing API Response:', billingData);

                                // Close payment modal
                                closePaymentModal();

                                // Show success modal
                                showOrderSuccessModal(orderData.order_id, finalTotal);
                                // setTimeout(() => location.reload(), 3000);
                            } else {
                                showErrorNotification('Payment Verification Failed', verifyData.message || 'Please contact support.');
                            }
                        },
                        prefill: {
                            name: "<?php echo addslashes($full_name); ?>",
                            email: "<?php echo addslashes($email); ?>",
                            contact: "<?php echo addslashes($mobile); ?>"
                        },
                        theme: {
                            color: "#3399cc"
                        }
                    };

                    const rzp = new Razorpay(options);
                    rzp.on('payment.failed', function(resp) {
                        showErrorNotification('Payment Failed', resp.error.description || 'Payment failed.');
                    });
                    rzp.open();

                } catch (err) {
                    console.error(err);
                    showErrorNotification('Payment Error', err.message || 'Something went wrong during payment.');
                }

                return; // important: do NOT call placeOrder() for online
            }

            // COD / offline order
            // await placeOrder(paymentMethod);
        }

        async function placeOrder(paymentMethod) {
            try {
                // Get cart items with variant information
                const cartItems = [];
                const cartRows = document.querySelectorAll('#cart-table-body tr[data-product-id]');

                cartRows.forEach(row => {
                    const productId = row.getAttribute('data-product-id');
                    const quantityInput = row.querySelector('.quantity');
                    const quantity = parseInt(quantityInput.value) || 0;
                    const price = quantityInput.getAttribute('data-price');
                    const coin = quantityInput.getAttribute('data-coin');

                    // Skip items with 0 quantity
                    if (quantity === 0) {
                        return;
                    }

                    // Get variant information from data attributes
                    const selectedVariantsJson = quantityInput.getAttribute('data-selected-variants');
                    const variantPricesJson = quantityInput.getAttribute('data-variant-prices');

                    let selectedVariants = {};
                    let variantPrices = {};

                    try {
                        if (selectedVariantsJson && selectedVariantsJson !== 'null') {
                            selectedVariants = JSON.parse(selectedVariantsJson);
                        }
                        if (variantPricesJson && variantPricesJson !== 'null') {
                            variantPrices = JSON.parse(variantPricesJson);
                        }
                    } catch (e) {
                        console.warn('Error parsing variant data:', e);
                    }

                    cartItems.push({
                        product_id: parseInt(productId),
                        quantity: parseInt(quantity),
                        price: parseFloat(price),
                        coin: parseInt(coin) || 0,
                        selected_variants: selectedVariants,
                        variant_prices: variantPrices
                    });
                });

                // Get selected address
                const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
                if (!selectedAddress) {
                    showErrorNotification('Address Error', 'Please select a delivery address.');
                    return;
                }

                // Get order totals
                const cartTotal = parseFloat(document.getElementById('cart-total').textContent.replace('₹', '').replace(',', ''));
                const shippingCharge = parseFloat(document.getElementById('shipping-charges').textContent.replace('₹', '').replace(',', '')) || 0;
                const couponSaving = parseFloat(document.getElementById('coupon-savings').textContent.replace('- ₹', '').replace(',', '')) || 0;
                const finalTotal = parseFloat(document.getElementById('final-total-amount').textContent.replace('₹', '').replace(',', ''));

                // Get applied coupon
                const appliedCoupon = document.getElementById('applied-coupon-code');
                const couponCode = appliedCoupon ? appliedCoupon.textContent : null;

                // Prepare order data
                const orderData = {
                    action: 'place_order',
                    products: cartItems,
                    address_id: parseInt(selectedAddress.value),
                    payment_method: paymentMethod,
                    cart_amount: cartTotal,
                    shipping_charge: shippingCharge,
                    coupon_saving: couponSaving,
                    total_amount: finalTotal,
                    coupon_code: couponCode
                };

                // Show loading notification
                showInfoNotification('Processing Order', 'Please wait while we process your order...');

                // Send order to server
                const response = await fetch('<?php echo USER_BASEURL; ?>app/pages/order_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(orderData)
                });

                const result = await response.json();

                if (result.success) {
                    const now = new Date();
                    const transactionDate = now.toLocaleDateString('en-GB').split('/').join('-');
                    const transactionTime = now.toLocaleTimeString('en-GB', {
                        hour12: false
                    });
                    const formData = {
                        TransactionAmount: finalTotal,
                        MerchantUsername: 'V000002591',
                        // utrnumber: 'test',
                        transactionDate: transactionDate,
                        // transactionTime: transactionTime,
                        AssociateUsername: '<?php echo addslashes($customerId); ?>',
                    }
                    const response1 = await fetch('<?php echo ADMIN_BASEURL; ?>admins/billing', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    });

                    const result1 = await response1.json();
                    console.log('Billing API Response:', result1);

                    // Close payment modal
                    closePaymentModal();

                    // Show success modal
                    showOrderSuccessModal(result.order_id, result.total_amount);

                    // Clear cart and reload page after delay
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    showErrorNotification('Order Failed', result.message || 'Failed to place order. Please try again.');
                }

            } catch (error) {
                console.error('Order placement error:', error);
                showErrorNotification('Network Error', 'An error occurred while placing your order. Please try again.');
            }
        }

        function showOrderSuccessModal(orderId, totalAmount) {
            const modal = document.getElementById('order-success-modal');
            const orderIdDisplay = document.getElementById('order-id-display');
            const orderTotalDisplay = document.getElementById('order-total-display');

            if (modal && orderIdDisplay && orderTotalDisplay) {
                orderIdDisplay.textContent = orderId;
                orderTotalDisplay.textContent = '₹' + totalAmount.toFixed(2);
                modal.style.display = 'flex';

                // Add click outside to close
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
        }
    </script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</body>

</html>
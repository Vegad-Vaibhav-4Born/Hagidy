<?php 
session_start();
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

// Get order ID from URL parameter
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    // Redirect to my-account if no valid order ID
    header('Location: my-account.php');
    exit;
}

// Fetch order details
$order_query = "SELECT * FROM `order` WHERE order_id = $order_id AND user_id = $user_id";
$order_result = mysqli_query($db_connection, $order_query);

if (!$order_result || mysqli_num_rows($order_result) == 0) {
    // Order not found or doesn't belong to user
    header('Location: my-account.php');
    exit;
}

$order = mysqli_fetch_assoc($order_result);

// Parse order data
$products_data = json_decode($order['products'], true);
$order_track_data = !empty($order['order_track']) ? json_decode($order['order_track'], true) : [];

// Group products by vendor for better tracking display
$vendor_groups = [];
$vendor_tracks = [];

if (is_array($products_data)) {
    foreach ($products_data as $product_item) {
        $vendor_id = isset($product_item['vendor_id']) ? (string)$product_item['vendor_id'] : 'unknown';
        if (!isset($vendor_groups[$vendor_id])) {
            $vendor_groups[$vendor_id] = [];
        }
        $vendor_groups[$vendor_id][] = $product_item;
    }
}

// Load per-vendor tracking data from order_track table
if (!empty($order['order_id'])) {
    $oid_esc = mysqli_real_escape_string($db_connection, (string)$order['order_id']);
    $track_query = "SELECT vendor_id, track_json FROM `order_track` WHERE order_id='".$oid_esc."' AND (product_id IS NULL OR product_id='')";
    $track_result = mysqli_query($db_connection, $track_query);
    
    if ($track_result) {
        while ($track_row = mysqli_fetch_assoc($track_result)) {
            $vid = (string)($track_row['vendor_id'] ?? '');
            $track_array = [];
            $track_json = $track_row['track_json'] ?? '[]';
            $tmp = !empty($track_json) ? json_decode($track_json, true) : [];
            if (is_array($tmp)) { 
                $track_array = $tmp; 
            }
            $vendor_tracks[$vid] = $track_array;
        }
    }
}

// Normalize tracking to strict order: Pending -> Processing -> Shipped -> Delivered
// Keep only these statuses, at most one of each, and in the correct order
$allowed_statuses = array('pending', 'processing', 'shipped', 'delivered');
$normalized_track = array();
if (is_array($order_track_data)) {
    $status_to_data = array();
    foreach ($order_track_data as $track_item) {
        $status_key = isset($track_item['status']) ? strtolower(trim($track_item['status'])) : '';
        if (in_array($status_key, $allowed_statuses, true) && !isset($status_to_data[$status_key])) {
            $status_to_data[$status_key] = $track_item;
        }
    }
    foreach ($allowed_statuses as $status_key) {
        if (isset($status_to_data[$status_key])) {
            $normalized_track[] = $status_to_data[$status_key];
        }
    }
}
$order_track_data = $normalized_track;

// Function to get product details
function getProductDetails($product_id, $db_connection) {
    $product_query = "SELECT product_name, images FROM products WHERE id = $product_id AND status = 'approved'";
    $product_result = mysqli_query($db_connection, $product_query);
    if ($product_result && mysqli_num_rows($product_result) > 0) {
        return mysqli_fetch_assoc($product_result);
    }
    return null;
}

// Function to get vendor details
function getVendorDetails($vendor_id, $db_connection) {
    if (empty($vendor_id) || $vendor_id === 'unknown') {
        return ['business_name' => 'Hagidy Store', 'vendor_id' => 'HAG001'];
    }
    
    $vendor_query = "SELECT business_name, vendor_id FROM vendor_registration WHERE id = '".mysqli_real_escape_string($db_connection, (string)$vendor_id)."' LIMIT 1";
    $vendor_result = mysqli_query($db_connection, $vendor_query);
    if ($vendor_result && mysqli_num_rows($vendor_result) > 0) {
        return mysqli_fetch_assoc($vendor_result);
    }
    return ['business_name' => 'Vendor #'.$vendor_id, 'vendor_id' => 'V'.$vendor_id];
}

// Function to get first image from images JSON
function getFirstImage($images_json) {
    global $vendor_baseurl;
    if (empty($images_json)) {
        return $vendor_baseurl.'uploads/vendors/no-product.png';
    }
    
    $images = !empty($images_json) ? json_decode($images_json, true) : [];
    if (is_array($images) && !empty($images)) {
        return $vendor_baseurl. $images[0];
    }
    
    return $vendor_baseurl.'uploads/vendors/no-product.png';
}

// Function to format date
function formatDate($date) {
    return date('D, d M', strtotime($date)) . '<p>' . date('g:i A', strtotime($date)) . '</p>';
}

// Function to get status class for tracking
function getTrackingStatusClass($status, $index, $total) {
    $base_class = 'form-steps__item';
    $is_delivered = strtolower(trim($status)) === 'delivered';
    // Mark completed for all prior steps and also mark the last step if it is Delivered
    if ($index < $total - 1 || $is_delivered) {
        $base_class .= ' form-steps__item--completed';
    }
    return $base_class;
}
// Get address details with country and state names
$address_query = "SELECT ua.*, c.name as country_name, s.name as state_name 
                  FROM user_address ua 
                  LEFT JOIN country c ON ua.country_id = c.id 
                  LEFT JOIN state s ON ua.state_id = s.id 
                  WHERE ua.id = {$order['address_id']}";
$address_result = mysqli_query($db_connection, $address_query);
$address = $address_result && mysqli_num_rows($address_result) > 0 ? mysqli_fetch_assoc($address_result) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>Order Detail #<?php echo $order['order_id']; ?> | HAGIDY</title>

    <meta name="keywords" content="" />
    <meta name="description" content="">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">


    <!-- WebFont.js -->
    <script>
        WebFontConfig = {
            google: { families: ['Poppins:400,500,600,700'] }
        };
        (function (d) {
            var wf = d.createElement('script'), s = d.scripts[0];
            wf.src = '<?php echo PUBLIC_ASSETS; ?>js/webfont.js';
            wf.async = true;
            s.parentNode.insertBefore(wf, s);
        })(document);
    </script>


    <link rel="preload" href="./assetsvendor/fontawesome-free/webfonts/fa-regular-400.woff" as="font" type="font/woff2"
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
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/my-account.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


</head>

<body>
    <div class="page-wrapper">
      <?php include __DIR__ . '/../includes/header.php';?>    

        <!-- Start of Main -->
        <main class="main cart">
            <!-- Start of Breadcrumb -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li><a href="./my-account.php">Order History</a></li>
                            <li>Order Detail #<?php echo $order['order_id']; ?></li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->

            <!-- Start of PageContent -->
            <div class="page-content">
                <div class="container">
                    <div>
                        <nav class="form-steps">
                            <?php
                            // Aggregate per-vendor timeline dates (earliest date per status)
                            $overallTrackDates = [
                                'processing' => '',
                                'shipped' => '',
                                'out_for_delivery' => '',
                                'delivered' => '',
                            ];
                            if (!empty($vendor_tracks)) {
                                foreach ($vendor_tracks as $vTimeline) {
                                    if (!is_array($vTimeline)) { continue; }
                                    foreach ($vTimeline as $ev) {
                                        $st = strtolower(trim($ev['status'] ?? ''));
                                        $dt = (string)($ev['date'] ?? '');
                                        if ($dt === '') { continue; }
                                        if ($st === 'confirmed' || $st === 'processing') { $st = 'processing'; }
                                        if ($st === 'out for delivery') { $st = 'out_for_delivery'; }
                                        if (!isset($overallTrackDates[$st])) { continue; }
                                        if ($overallTrackDates[$st] === '' || strtotime($dt) < strtotime($overallTrackDates[$st])) {
                                            $overallTrackDates[$st] = $dt;
                                        }
                                    }
                                }
                            }

                            // Determine current overall order status from products
                            $currentOrderStatus = 'pending';
                            if (is_array($products_data)) {
                                $hasConfirmed = false; $hasShipped = false; $hasOfd = false; $hasDelivered = false;
                                $allCnt = 0; $cancelCnt = 0; $hasPending = false;
                                foreach ($products_data as $it) {
                                    $st = strtolower((string)($it['product_status'] ?? ''));
                                    $allCnt++;
                                    if ($st === 'pending') { $hasPending = true; }
                                    if ($st === 'confirmed') { $hasConfirmed = true; }
                                    if ($st === 'shipped') { $hasShipped = true; }
                                    if ($st === 'out_for_delivery') { $hasOfd = true; }
                                    if ($st === 'delivered') { $hasDelivered = true; }
                                    if ($st === 'cancelled') { $cancelCnt++; }
                                }
                                if ($allCnt > 0 && $cancelCnt === $allCnt) { $currentOrderStatus = 'cancelled'; }
                                elseif ($hasDelivered) { $currentOrderStatus = 'delivered'; }
                                elseif ($hasOfd) { $currentOrderStatus = 'out_for_delivery'; }
                                elseif ($hasShipped) { $currentOrderStatus = 'shipped'; }
                                elseif ($hasConfirmed || $hasPending) { $currentOrderStatus = $hasConfirmed ? 'confirmed' : 'pending'; }
                            }

                            // Build main steps for display
                            $mainSteps = [];
                            $mainSteps[] = [ 'status' => 'placed', 'label' => 'Order Placed', 'date' => $order['created_date'] ?? '' ];
                            if ($currentOrderStatus === 'cancelled') {
                                $mainSteps[] = [ 'status' => 'cancelled', 'label' => 'Order Cancelled', 'date' => $order['updated_date'] ?? '' ];
                            } else {
                                $mainSteps[] = [ 'status' => 'processing', 'label' => 'Order Confirmed', 'date' => $overallTrackDates['processing'] ];
                                $mainSteps[] = [ 'status' => 'shipped', 'label' => 'Shipped', 'date' => $overallTrackDates['shipped'] ];
                                $mainSteps[] = [ 'status' => 'out_for_delivery', 'label' => 'Out For Delivery', 'date' => $overallTrackDates['out_for_delivery'] ];
                                $mainSteps[] = [ 'status' => 'delivered', 'label' => 'Delivered', 'date' => $overallTrackDates['delivered'] ];
                            }

                            $totalMain = count($mainSteps);
                            foreach ($mainSteps as $i => $st) {
                                $statusKey = $st['status'];
                                $hasDate = !empty($st['date']);
                                $isCompleted = false;
                                if ($statusKey === 'placed') { $isCompleted = true; }
                                elseif ($statusKey === 'processing') { $isCompleted = $hasDate || in_array($currentOrderStatus, ['confirmed','shipped','out_for_delivery','delivered'], true); }
                                elseif ($statusKey === 'shipped') { $isCompleted = $hasDate || in_array($currentOrderStatus, ['shipped','out_for_delivery','delivered'], true); }
                                elseif ($statusKey === 'out_for_delivery') { $isCompleted = $hasDate || in_array($currentOrderStatus, ['out_for_delivery','delivered'], true); }
                                elseif ($statusKey === 'delivered') { $isCompleted = $hasDate || $currentOrderStatus === 'delivered'; }
                                elseif ($statusKey === 'cancelled') { $isCompleted = ($currentOrderStatus === 'cancelled'); }

                                $itemClass = 'form-steps__item' . ($isCompleted ? ' form-steps__item--completed' : '');
                                ?>
                                <div class="<?php echo $itemClass; ?>">
                                <div class="form-steps__item-content">
                                        <span class="form-steps__item-text">
                                            <?php echo !empty($st['date']) ? formatDate($st['date']) : ($statusKey === 'placed' ? formatDate($order['created_date']) : ''); ?>
                                        </span>
                                    <span class="form-steps__item-icon"></span>
                                        <?php if ($i <= $totalMain - 1): ?>
                                                <span class="form-steps__item-line"></span>
                                            <?php endif; ?>
                                        <span class="form-steps__item-text"><?php echo $st['label']; ?></span>
                                </div>
                            </div>
                            <?php } ?>
                        </nav>
                    </div>
                    <div class="row gutter-lg mb-10">
                        <div class="col-lg-8 pr-lg-4 mb-6">
                            <?php if (!empty($vendor_groups)): ?>
                                <?php foreach ($vendor_groups as $vendor_id => $vendor_products): ?>
                                    <?php 
                                    $vendor_details = getVendorDetails($vendor_id, $db_connection);
                                    $vendor_timeline = isset($vendor_tracks[$vendor_id]) ? $vendor_tracks[$vendor_id] : [];
                                    ?>
                                    
                                    <!-- Vendor Section -->
                                    <div class="vendor-section mb-4">
                                        <div class="vendor-header mb-3">
                                            <h4 class="mb-1">
                                                <i class="fas fa-store"></i> 
                                                <?php echo htmlspecialchars($vendor_details['business_name']); ?>
                                                <span class="vendor-id-badge">#<?php echo htmlspecialchars($vendor_details['vendor_id']); ?></span>
                                            </h4>
                                        </div>
                                        
                                        <!-- Products for this vendor -->
                                        <?php foreach ($vendor_products as $product_item): ?>
                                            <?php
                                            $product_details = getProductDetails($product_item['product_id'], $db_connection);
                                            $product_name = $product_details ? $product_details['product_name'] : 'Product Not Found';
                                            $product_image = $product_details ? getFirstImage($product_details['images']) : '<?php echo PUBLIC_ASSETS; ?>images/cart/product-1.jpg';
                                            $product_price = isset($product_item['price']) ? $product_item['price'] : 0;
                                            $product_quantity = isset($product_item['quantity']) ? $product_item['quantity'] : 1;
                                            $product_coin = isset($product_item['coin']) ? $product_item['coin'] : 0;
                                            $product_status = isset($product_item['product_status']) ? strtolower($product_item['product_status']) : 'pending';
                                            
                                            // Extract variant information
                                            $selected_variants = isset($product_item['selected_variants']) ? $product_item['selected_variants'] : [];
                                            $variant_prices = isset($product_item['variant_prices']) ? $product_item['variant_prices'] : [];
                                            $subtotal = $product_price * $product_quantity;
                                            ?>
                                            
                                            <div class="product-item mb-4">
                                                <div class="row align-items-center">
                                                    <div class="col-md-2">
                                                        <div class="product-thumbnail">
                                                        <a href="product-detail.php?id=<?php echo $product_item['product_id']; ?>">
                                                                <img src="<?php echo $vendor_baseurl . $product_image; ?>" alt="<?php echo htmlspecialchars($product_name); ?>" 
                                                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                                </a>
                                            </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="product-info">
                                                            <h5 class="mb-1">
                                                                <a href="product-detail.php?id=<?php echo $product_item['product_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($product_name); ?>
                                            </a>
                                                            </h5>
                                                            <div class="product-details">
                                                                <small class="text-muted">Qty: <?php echo $product_quantity; ?></small>
                                                                <span class="product-status-badge status-<?php echo $product_status; ?>">
                                                                    <?php echo ucfirst($product_status); ?>
                                                                </span>
                                                                
                                                                <!-- Display selected variants -->
                                                                <?php if (!empty($selected_variants)): ?>
                                                                    <div class="selected-variants flex-wrap mt-2">
                                                                        <?php foreach ($selected_variants as $key => $value): ?>
                                                                            <?php if (!empty($value) && !str_ends_with($key, '_variant_id')): ?>
                                                                                <span class="variant-item mb-1">
                                                                                    <small class="text-muted">
                                                                                        <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> 
                                                                                        <?php if ($key === 'color'): ?>
                                                                                            <span class="color-swatch" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo htmlspecialchars($value); ?>; margin-left: 5px; vertical-align: middle;"></span>
                                                                                        <?php endif; ?>
                                                                                        <?php echo htmlspecialchars($value); ?>
                                                                                    </small>
                                                                                </span>
                                                                            <?php endif; ?>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($product_coin > 0): ?>
                                                                <div class="mt-1">
                                                                    <small class="text-success">
                                                                        <i class="fas fa-coins"></i> <?php echo $product_coin; ?> coins earned
                                                                    </small>
                                                                </div>
                                                    <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-center">
                                                        <div class="product-price">
                                                            <strong>₹<?php echo number_format($product_price, 2); ?></strong>
                                                            <?php if (!empty($variant_prices)): ?>
                                                                <?php if (isset($variant_prices['mrp']) && $variant_prices['mrp'] != $product_price): ?>
                                                                    <br><small class="text-muted">Base: ₹<?php echo number_format($variant_prices['mrp'], 2); ?></small>
                                                                <?php endif; ?>
                                                                
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-center">
                                                        <div class="product-quantity">
                                                            <span class="badge"><?php echo $product_quantity; ?></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-center">
                                                        <div class="product-subtotal">
                                                            <strong>₹<?php echo number_format($subtotal, 2); ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Product Tracking Timeline -->
                                                <div class="product-tracking">
                                                    <div class="vendor-info">
                                                        <i class="fas fa-truck"></i>
                                                        <span class="vendor-name">Shipping Progress</span>
                                                    </div>
                                                    
                                                    <div class="product-timeline">
                                                        <?php
                                                        // Define tracking steps
                                                        $tracking_steps = [
                                                            'processing' => ['label' => 'Confirmed', 'icon' => 'fa-check'],
                                                            'shipped' => ['label' => 'Shipped', 'icon' => 'fa-shipping-fast'],
                                                            'out_for_delivery' => ['label' => 'Out for Delivery', 'icon' => 'fa-truck'],
                                                            'delivered' => ['label' => 'Delivered', 'icon' => 'fa-home']
                                                        ];
                                                        
                                                        $step_dates = [];
                                                        foreach ($vendor_timeline as $track_event) {
                                                            $status = strtolower(trim($track_event['status'] ?? ''));
                                                            $date = $track_event['date'] ?? '';
                                                            
                                                            // Map different status variations to our standard steps
                                                            $mapped_status = $status;
                                                            if ($status === 'processing' || $status === 'confirmed') {
                                                                $mapped_status = 'processing';
                                                            } elseif ($status === 'out for delivery') {
                                                                $mapped_status = 'out_for_delivery';
                                                            }
                                                            
                                                            if (isset($tracking_steps[$mapped_status])) {
                                                                $step_dates[$mapped_status] = $date;
                                                            }
                                                        }
                                                        
                                                        // If no tracking data but we have product status, show current status
                                                        if (empty($step_dates) && !empty($product_status)) {
                                                            $mapped_status = $product_status;
                                                            if ($product_status === 'confirmed') {
                                                                $mapped_status = 'processing';
                                                            } elseif ($product_status === 'out_for_delivery') {
                                                                $mapped_status = 'out_for_delivery';
                                                            }
                                                            // Don't set a date, just mark as current step
                                                        }
                                                        
                                                        $step_index = 0;
                                                        $total_steps = count($tracking_steps);
                                                        ?>
                                                        
                                                        <?php foreach ($tracking_steps as $step_key => $step_info): ?>
                                                            <?php
                                                            $is_completed = isset($step_dates[$step_key]);
                                                            $step_date = $is_completed ? $step_dates[$step_key] : '';
                                                            $is_current = false;
                                                            
                                                            // Determine if this is the current step
                                                            if (!$is_completed) {
                                                                if ($step_key === 'processing' && in_array($product_status, ['pending', 'confirmed'])) {
                                                                    $is_current = true;
                                                                } elseif ($step_key === 'shipped' && in_array($product_status, ['confirmed', 'shipped'])) {
                                                                    $is_current = true;
                                                                } elseif ($step_key === 'out_for_delivery' && in_array($product_status, ['shipped', 'out_for_delivery'])) {
                                                                    $is_current = true;
                                                                } elseif ($step_key === 'delivered' && in_array($product_status, ['out_for_delivery', 'delivered'])) {
                                                                    $is_current = true;
                                                                }
                                                            }
                                                            ?>
                                                            
                                                            <div class="timeline-step">
                                                                <div class="timeline-dot <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : ''); ?>">
                                                                    <?php if ($is_completed): ?>
                                                                        <i class="fas <?php echo $step_info['icon']; ?>"></i>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="timeline-title"><?php echo $step_info['label']; ?></div>
                                                                <?php if ($is_completed && $step_date): ?>
                                                                    <div class="timeline-date"><?php echo date('d M Y', strtotime($step_date)); ?></div>
                                                                    <div class="timeline-time"><?php echo date('h:i A', strtotime($step_date)); ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <?php if ($step_index < $total_steps - 1): ?>
                                                                <div class="timeline-connector <?php echo $is_completed ? 'completed' : ''; ?>"></div>
                                                            <?php endif; ?>
                                                            
                                                            <?php $step_index++; ?>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                <div class="text-center" style="padding: 40px;">
                                                <div style="color: #666;">
                                                    <i class="fa-solid fa-shopping-bag" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                                                    <h4>No products found</h4>
                                                    <p>This order doesn't contain any products.</p>
                                            </div>
                                </div>
                                    <?php endif; ?>

                            <div class="cart-action mb-6">
                                <a href="#" id="download-invoice-btn" class="btn btn-dark btn-rounded btn-icon-left btn-shopping mr-auto">Download
                                    Invoice</a>

                            </div>

                        </div>
                        <div class="col-lg-4 sticky-sidebar-wrapper">

                            <div class="cart-summary mb-4">
                                <?php
                                $total_items = 0;
                                if (is_array($products_data)) {
                                    foreach ($products_data as $product_item) {
                                        $total_items += isset($product_item['quantity']) ? $product_item['quantity'] : 1;
                                    }
                                }
                                ?>
                                <h3 class="cart-title cart-title1 text-uppercase ">Order Detail <span>(<?php echo $total_items; ?> items)</span>
                                </h3>
                                <div class="cart-subtotal1 d-flex align-items-center justify-content-between">
                                    <label class="ls-25">Cart Total</label>
                                    <span>₹<?php echo number_format($order['cart_amount'], 2); ?></span>
                                </div>
                                <div class="cart-subtotal1 d-flex align-items-center justify-content-between">
                                    <label class="ls-25">Shipping Charges</label>
                                    <span><?php echo $order['shipping_charge'] > 0 ? '₹' . number_format($order['shipping_charge'], 2) : 'Free'; ?></span>
                                </div>
                                <?php if ($order['coupan_saving'] > 0): ?>
                                <div class="cart-subtotal1 d-flex align-items-center justify-content-between">
                                    <label class="ls-25">Coupon Savings</label>
                                    <span class="cart-sbu-color">- ₹<?php echo number_format($order['coupan_saving'], 2); ?></span>
                                </div>
                                <?php endif; ?>
                                <hr class="divider mb-4">
                                <div class="cart-subtotal d-flex align-items-center justify-content-between">
                                    <label class="ls-25">Total Amount</label>
                                    <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <hr class="divider mb-0">
                            </div>
                            <div class="cart-summary mb-4">
                                <div class="mb-online d-flex align-items-center gap-3">
                                    <h3 class="cart-title cart-title1 text-uppercase mr-2 "> Payment Method
                                    </h3> : <Span class="ml-2"> <?php echo ucfirst($order['payment_method']); ?></Span>
                                </div>
                                <div class="mb-online d-flex align-items-center gap-3 mt-2">
                                    <h3 class="cart-title cart-title1 text-uppercase mr-2 "> Payment Status
                                    </h3> : <Span class="ml-2"> <?php echo ucfirst($order['payment_status']); ?></Span>
                                </div>
                            </div>
                            <div class="cart-summary mb-4">
                                <div class="mb-online d-flex align-items-center gap-3">
                                    <h3 class="cart-title cart-title1 text-uppercase mr-2 "> Shipping Address
                                    </h3>
                                </div>
                                <div class="order-home">
                                    <?php if ($address): ?>
                                        <h4><?php echo ucfirst($address['address_type']); ?></h4>
                                        <p>
                                            <?php echo htmlspecialchars($address['street_address']); ?><br>
                                            <?php echo htmlspecialchars($address['city']); ?>, 
                                            <?php echo htmlspecialchars($address['state_name'] ?: 'State not found'); ?>, 
                                            <?php echo htmlspecialchars($address['country_name'] ?: 'Country not found'); ?> - 
                                            <?php echo htmlspecialchars($address['pin_code']); ?><br>
                                            Mobile: <?php echo htmlspecialchars($address['mobile_number']); ?><br>
                                            <?php if (!empty($address['email_address'])): ?>
                                                Email: <?php echo htmlspecialchars($address['email_address']); ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php else: ?>
                                        <h4>Address Not Found</h4>
                                        <p>Shipping address information is not available for this order.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div id="editModal" class="modal modal-lg"
                                style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); overflow-x: scroll;">
                                <div
                                    style="background:#fff; margin:5% auto; padding:20px; border-radius:8px; width:90%; max-width:800px; position:relative;">
                                    <span id="closeModal"
                                        style="position:absolute; right:16px; top:16px; font-size:14px; cursor:pointer;">Close</span>
                                    <h4>Add / Edit Address </h4>
                                    <div class="row g-3">

                                        <!-- Address Fields -->
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="addressType" class="name-first">Address Type
                                                    <span>*</span></label>
                                                <select id="addressType" name="addressType"
                                                    class="form-control form-control-md" required>
                                                    <option value="">Select</option>
                                                    <option value="home">Home</option>
                                                    <option value="business">Business</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="country" class="name-first">Country/Region
                                                    <span>*</span></label>
                                                <select id="country" name="country" class="form-control form-control-md"
                                                    required>
                                                    <option value="">Select Country/Region</option>
                                                    <option value="it">Italy</option>
                                                    <option value="us">United States</option>
                                                    <option value="in">India</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="streetAddress" class="name-first">Street Address
                                                    <span>*</span></label>
                                                <input type="text" id="streetAddress" name="streetAddress"
                                                    placeholder="House number and street name"
                                                    class="form-control form-control-md mb-2" required>
                                             
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="town" class="name-first">Town/City <span>*</span></label>
                                                <input type="text" id="town" name="town" placeholder="Enter Town/City"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="state" class="name-first">State <span>*</span></label>
                                                <select id="state" name="state" class="form-control form-control-md"
                                                    required>
                                                    <option value="">Select State</option>
                                                    <option value="lombardia">Lombardia</option>
                                                    <option value="california">California</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="pincode" class="name-first">PIN Code <span>*</span></label>
                                                <input type="text" id="pincode" name="pincode"
                                                    placeholder="Enter PIN Code" class="form-control form-control-md"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                            <div class="form-group mb-3">
                                                <label for="mobile" class="name-first">Mobile Number
                                                    <span>*</span></label>
                                                <input type="tel" id="mobile" name="mobile"
                                                    placeholder="Enter Mobile Number"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="email" class="name-first">Email Address</label>
                                                <input type="email" id="email" name="email" placeholder="Enter Email"
                                                    class="form-control form-control-md">
                                            </div>
                                        </div>
                                        <div class="mb-2 form-group set-primary-add">
                                            <input type="checkbox" id="vehicle1" name="vehicle1" value="Bike">
                                            <label for="vehicle1" class="">Set as a primary Address</label>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-svae1">Add Address</button>
                                        </div>
                                    </div>
                                    </form>
                                </div>
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

    <!-- Start of Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/sticky/sticky.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>
    <!-- jsPDF + html2canvas for invoice PDF generation (will also lazily load if unavailable) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <div id="invoiceTemplate" style="position: fixed; left: -99999px; top: 0; z-index: -1;">
        <div class="invoice-a4" id="invoiceA4">
            <div class="invoice-head">
                <div class="invoice-title">Hagidy</div>
                <div class="invoice-tax">Tax Invoice</div>
            </div>

            <div class="row-2">
                <div>
                    <div class="label">Sold By :</div>
                    <div class="box" id="invSoldBy"></div>
                </div>
                <div>
                    <div class="label">Billing Address :</div>
                    <div class="box" id="invBilling"></div>
                </div>
            </div>

            <div class="split">
                <div>
                    <div class="label">GST No:</div>
                    <div class="box" id="invGst"></div>
                </div>
                <div>
                    <div class="label">Shipping Address :</div>
                    <div class="box" id="invShipping"></div>
                </div>
            </div>

            <div class="meta">
                <div><span class="k">Order Number:</span> <span id="invOrderNo"></span></div>
                <div><span class="k">Order Date:</span> <span id="invOrderDate"></span></div>
                <div><span class="k">Mode Of Payment:</span> <span id="invPayMode"></span></div>
                <div><span class="k">Invoice Number :</span> <span id="invNumber"></span></div>
                <div><span class="k">Invoice Date :</span> <span id="invDate"></span></div>
            </div>

            <div class="table-wrap">
                <table class="inv-table" id="invItems">
                    <thead>
                        <tr>
                            <th style="width:40px;">Sl. No</th>
                            <th>Description</th>
                            <th style="width:80px;">Unit Price</th>
                            <th style="width:40px;">Qty</th>
                            <th style="width:90px;">Net Amount</th>
                            <th style="width:60px;">Tax Rate</th>
                            <th style="width:70px;">Tax Type</th>
                            <th style="width:90px;">Tax Amount</th>
                            <th style="width:100px;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="invBody"></tbody>
                    <tfoot>
                        <tr class="tfoot">
                            <td colspan="8">TOTAL:</td>
                            <td id="invGrandTotal">0</td>
                        </tr>
                    </tfoot>
                </table>
                <div class="muted" style="margin-top:6px;">HSN: <span id="invHsn"></span></div>
            </div>

            <div class="sign">
                <div>
                    <div class="muted">&lt;Business Name&gt;</div>
                    <div style="font-weight:700;">Authorized Signatory</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Open modal on any edit icon click
        document.querySelectorAll('.edit-address-btn').forEach(function (btn) {
            btn.onclick = function () {
                document.getElementById('editModal').style.display = 'block';
            }
        });
        document.getElementById('closeModal').onclick = function () {
            document.getElementById('editModal').style.display = 'none';
        };
        window.onclick = function (event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
    <script>
        // Invoice PDF generation with robust lazy-loading of libs
        (function(){
            const downloadBtn = document.getElementById('download-invoice-btn');
            if(!downloadBtn){ return; }

            function loadScript(src){
                return new Promise(function(resolve, reject){
                    const s = document.createElement('script');
                    s.src = src;
                    s.onload = () => resolve();
                    s.onerror = () => reject(new Error('Failed to load '+src));
                    document.head.appendChild(s);
                });
            }

            async function ensureLibraries(){
                if(typeof window.html2canvas === 'undefined'){
                    // Try alternate CDNs
                    try{
                        await loadScript('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js');
                    }catch(e){
                        await loadScript('https://unpkg.com/html2canvas@1.4.1/dist/html2canvas.min.js');
                    }
                }
                if(typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined'){
                    try{
                        await loadScript('https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js');
                    }catch(e){
                        await loadScript('https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js');
                    }
                }
            }

            // PHP data into JS
            const order = {
                id: <?php echo (int)$order['order_id']; ?>,
                created: "<?php echo isset($order['created_date']) ? date('d M Y, h:i A', strtotime($order['created_date'])) : ''; ?>",
                payment_method: "<?php echo htmlspecialchars($order['payment_method']); ?>",
                total: <?php echo (float)$order['total_amount']; ?>
            };
            const products = <?php 
                $invoice_products = [];
                if (is_array($products_data)) {
                    foreach ($products_data as $pitem) {
                        $pname = 'Item';
                        $pid = isset($pitem['product_id']) ? (int)$pitem['product_id'] : 0;
                        if ($pid > 0) {
                            $pdet = getProductDetails($pid, $db_connection);
                            if ($pdet && isset($pdet['product_name'])) { $pname = $pdet['product_name']; }
                        }
                        $invoice_products[] = [
                            'product_name' => $pname,
                            'price' => isset($pitem['price']) ? (float)$pitem['price'] : 0,
                            'quantity' => isset($pitem['quantity']) ? (int)$pitem['quantity'] : 1
                        ];
                    }
                }
                echo json_encode($invoice_products);
            ?>;
            const address = <?php echo json_encode($address ?: []); ?>;

            function formatCurrency(n){ return '₹' + Number(n).toFixed(2); }

            function populateTemplate(){
                document.getElementById('invSoldBy').textContent = 'Hagidy';
                const bill = address && address.street_address ? (
                    (address.first_name? (address.first_name+' '):'') + (address.last_name||'') + '\n' +
                    address.street_address + '\n' +
                    (address.city||'') + ', ' + (address.state_name||'') + ', ' + (address.country_name||'') + ' - ' + (address.pin_code||'') + '\n' +
                    'Mobile: ' + (address.mobile_number||'') + (address.email_address? ('\nEmail: '+address.email_address):'')
                ) : '';
                document.getElementById('invBilling').textContent = bill;
                document.getElementById('invShipping').textContent = bill;
                document.getElementById('invGst').textContent = '';
                document.getElementById('invOrderNo').textContent = '#' + order.id;
                document.getElementById('invOrderDate').textContent = order.created;
                document.getElementById('invPayMode').textContent = (order.payment_method||'').toUpperCase();
                document.getElementById('invNumber').textContent = '#' + order.id;
                document.getElementById('invDate').textContent = new Date().toLocaleDateString();
                document.getElementById('invHsn').textContent = '';

                const tbody = document.getElementById('invBody');
                tbody.innerHTML = '';
                let grand = 0;
                const GST_RATE = 0.03;
                products.forEach((p, idx) => {
                    const name = (p && (p.product_name || p.name)) ? (p.product_name || p.name) : '';
                    const price = Number(p.price||0);
                    const qty = Number(p.quantity||1);
                    const net = price * qty;
                    const taxAmt = net * GST_RATE;
                    const total = net + taxAmt;
                    grand += total;
                    const tr = document.createElement('tr');
                    tr.innerHTML = '<td>'+ (idx+1) +'</td>'+
                                   '<td>'+ (name||'Item') +'</td>'+
                                   '<td>'+ formatCurrency(price) +'</td>'+
                                   '<td>'+ qty +'</td>'+
                                   '<td>'+ formatCurrency(net) +'</td>'+
                                   '<td>3%</td>'+
                                   '<td>IGST</td>'+
                                   '<td>'+ formatCurrency(taxAmt) +'</td>'+
                                   '<td>'+ formatCurrency(total) +'</td>';
                    tbody.appendChild(tr);
                });
                document.getElementById('invGrandTotal').textContent = formatCurrency(grand || order.total);
            }

            async function downloadPDF(){
                await ensureLibraries();
                populateTemplate();
                const a4 = document.getElementById('invoiceA4');
                const canvas = await html2canvas(a4, {scale: 2, backgroundColor: '#ffffff'});
                const imgData = canvas.toDataURL('image/jpeg', 1.0);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF('p', 'pt', 'a4');
                pdf.addImage(imgData, 'JPEG', 0, 0, 595.28, 841.89);
                pdf.save('invoice_'+order.id+'.pdf');
            }

            downloadBtn.addEventListener('click', function(e){
                e.preventDefault();
                downloadPDF();
            });
        })();
    </script>
    <!-- Search Suggestions JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search');
            const searchSuggestions = document.getElementById('searchSuggestions');

            if (searchInput && searchSuggestions) {
                // Show suggestions when input is focused
                searchInput.addEventListener('focus', function () {
                    searchSuggestions.classList.add('show');
                });

                // Hide suggestions when clicking outside
                document.addEventListener('click', function (event) {
                    if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                        searchSuggestions.classList.remove('show');
                    }
                });

                // Handle suggestion item clicks
                const suggestionItems = document.querySelectorAll('.suggestion-item');
                suggestionItems.forEach(function (item) {
                    item.addEventListener('click', function () {
                        const title = this.querySelector('.suggestion-title').textContent;
                        searchInput.value = title;
                        searchSuggestions.classList.remove('show');
                    });
                });

                // Handle "Show more" click
                const showMoreLink = document.querySelector('.show-more');
                if (showMoreLink) {
                    showMoreLink.addEventListener('click', function (event) {
                        event.preventDefault();
                        window.location.href = 'shop.php?search=' + encodeURIComponent(searchInput.value);
                    });
                }

                // Hide suggestions on form submit
                const searchForm = searchInput.closest('form');
                if (searchForm) {
                    searchForm.addEventListener('submit', function () {
                        searchSuggestions.classList.remove('show');
                    });
                }
            }
        });
    </script>
   <script>
    document.addEventListener('DOMContentLoaded', function () {
    const stickyElement = document.querySelector('.containt-sticy2');
    if (!stickyElement) { return; }

    window.addEventListener('scroll', function () {
        if (window.scrollY >= 60) {
            stickyElement.classList.add('containt-sticy');
        } else {
            stickyElement.classList.remove('containt-sticy');
        }
    });
});
</script>
</body>

</html>

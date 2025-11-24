<?php
include __DIR__ . '/../includes/init.php';  
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
}
$superadmin_id = $_SESSION['superadmin_id'];

// Load order for details page
$orderIdParam = isset($_GET['id']) ? trim($_GET['id']) : '';

$orderRow = null;
$vendorItems = [];
$orderSubTotal = 0.0;
$orderItemsCount = 0;
$orderShipping = 0.0;
$orderTotal = 0.0;
$orderTotalCoins = 0;
$orderPaymentMethod = '';
$orderCreatedAt = '';
$orderDisplayNo = '';

// Helper: store one tracking timeline JSON per (order_id + vendor_id)
function addOrderTrackEvent($con, $orderId, $vendorId, $statusLabel)
{
    $orderId = mysqli_real_escape_string($con, (string) $orderId);
    $vendorId = mysqli_real_escape_string($con, (string) $vendorId);
    $selSql = "SELECT id, track_json FROM `order_track` WHERE order_id='" . $orderId . "' AND vendor_id='" . $vendorId . "' AND (product_id IS NULL OR product_id='') LIMIT 1";
    $sel = mysqli_query($con, $selSql);

    $now = date('Y-m-d H:i:s');
    $entry = ['status' => (string) $statusLabel, 'date' => $now];

    if ($sel && mysqli_num_rows($sel) > 0) {
        $row = mysqli_fetch_assoc($sel);
        $arr = [];
        if (!empty($row['track_json'])) {
            $tmp = json_decode($row['track_json'], true);
            if (is_array($tmp)) {
                $arr = $tmp;
            }
        }
        $arr[] = $entry;
        $json = mysqli_real_escape_string($con, json_encode($arr, JSON_UNESCAPED_SLASHES));
        mysqli_query($con, "UPDATE `order_track` SET track_json='" . $json . "', updated_at='" . $now . "' WHERE id='" . mysqli_real_escape_string($con, (string) $row['id']) . "' LIMIT 1");
    } else {
        $json = mysqli_real_escape_string($con, json_encode([$entry], JSON_UNESCAPED_SLASHES));
        mysqli_query($con, "INSERT INTO `order_track` (order_id, vendor_id, product_id, track_json, updated_at) VALUES ('" . $orderId . "', '" . $vendorId . "', NULL, '" . $json . "', '" . $now . "')");
    }
}

// Handle confirm / cancel / shipping flow actions for all vendor items (superadmin can manage all)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_action']) && $orderIdParam !== '') {
    $action = $_POST['vendor_action'];
    $reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
    $targetProductId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    $oid = mysqli_real_escape_string($con, $orderIdParam);
    $rsO = mysqli_query($con, "SELECT products FROM `order` WHERE order_id='" . $oid . "' LIMIT 1");
    if ($rsO && mysqli_num_rows($rsO) > 0) {
        $rowO = mysqli_fetch_assoc($rsO);
        $items = json_decode($rowO['products'] ?? '[]', true);
        if (is_array($items)) {
            $updatedVendors = [];
            foreach ($items as &$it) {
                // Update all items regardless of vendor for superadmin
                $statusLower = strtolower((string) ($it['product_status'] ?? ''));
                if ($action === 'confirm' && $statusLower === 'pending') {
                    $it['product_status'] = 'confirmed';
                    if (isset($it['vendor_id'])) {
                        $updatedVendors[] = $it['vendor_id'];
                    }
                }
                if ($action === 'cancel' && in_array($statusLower, ['pending', 'confirmed'], true)) {
                    $it['product_status'] = 'cancelled';
                    if ($reason !== '') {
                        // append/mutate cancel reason
                        if (!empty($it['cancel_reason'])) {
                            $it['cancel_reason'] = trim((string)$it['cancel_reason']) . ', ' . $reason;
                        } else {
                            $it['cancel_reason'] = $reason;
                        }
                    }
                    if (isset($it['vendor_id'])) {
                        $updatedVendors[] = $it['vendor_id'];
                    }
                }
                // Per-item cancel when specifically requested
                if ($action === 'cancel_item' && $targetProductId > 0) {
                    $pidIt = isset($it['product_id']) ? (int)$it['product_id'] : 0;
                    if ($pidIt === $targetProductId && $statusLower === 'pending') {
                        $it['product_status'] = 'cancelled';
                        if ($reason !== '') {
                            if (!empty($it['cancel_reason'])) {
                                $it['cancel_reason'] = trim((string)$it['cancel_reason']) . ', ' . $reason;
                            } else {
                                $it['cancel_reason'] = $reason;
                            }
                        }
                        if (isset($it['vendor_id'])) {
                            $updatedVendors[] = $it['vendor_id'];
                        }
                    }
                }
                if ($action === 'ship' && $statusLower === 'confirmed') {
                    $it['product_status'] = 'shipped';
                    if (isset($it['vendor_id'])) {
                        $updatedVendors[] = $it['vendor_id'];
                    }
                }
                if ($action === 'out_for_delivery' && $statusLower === 'shipped') {
                    $it['product_status'] = 'out_for_delivery';
                    if (isset($it['vendor_id'])) {
                        $updatedVendors[] = $it['vendor_id'];
                    }
                }
                if ($action === 'delivered' && in_array($statusLower, ['out_for_delivery', 'shipped'], true)) {
                    $it['product_status'] = 'delivered';
                    if (isset($it['vendor_id'])) {
                        $updatedVendors[] = $it['vendor_id'];
                    }
                }
            }
            unset($it);
            $newJson = mysqli_real_escape_string($con, json_encode($items, JSON_UNESCAPED_SLASHES));
            mysqli_query($con, "UPDATE `order` SET products='" . $newJson . "' WHERE order_id='" . $oid . "' LIMIT 1");

            // Record timeline events for all affected vendors
            $uniqueVendors = array_unique($updatedVendors);
            foreach ($uniqueVendors as $vendorId) {
                if ($action === 'confirm') {
                    addOrderTrackEvent($con, $orderIdParam, $vendorId, 'Processing');
                }
                if ($action === 'cancel') {
                    addOrderTrackEvent($con, $orderIdParam, $vendorId, 'Cancelled');
                }
                if ($action === 'ship') {
                    addOrderTrackEvent($con, $orderIdParam, $vendorId, 'Shipped');
                }
                if ($action === 'out_for_delivery') {
                    addOrderTrackEvent($con, $orderIdParam, $vendorId, 'Out For Delivery');
                }
                if ($action === 'delivered') {
                    addOrderTrackEvent($con, $orderIdParam, $vendorId, 'Delivered');
                }
            }

            // If all products have same status, set order_status
            $allStatuses = [];
            foreach ($items as $tmpIt) {
                $allStatuses[] = strtolower((string) ($tmpIt['product_status'] ?? ''));
            }
            $allStatuses = array_filter($allStatuses, function ($s) {
                return $s !== '';
            });
            if (!empty($allStatuses) && count(array_unique($allStatuses)) === 1) {
                $common = mysqli_real_escape_string($con, (string) reset($allStatuses));
                mysqli_query($con, "UPDATE `order` SET order_status='" . $common . "' WHERE order_id='" . $oid . "' LIMIT 1");
            }
        }
    }
    // Redirect to avoid resubmission
    header('Location: order-details.php?id=' . urlencode($orderIdParam));
    exit;
}

if ($orderIdParam !== '') {
    $oid = mysqli_real_escape_string($con, $orderIdParam);
    $rs = mysqli_query($con, "SELECT * FROM `order` WHERE order_id='" . $oid . "' LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $orderRow = mysqli_fetch_assoc($rs);
        $orderDisplayNo = $orderRow['order_id'] ?? $orderIdParam;
        $orderCreatedAt = $orderRow['created_date'] ?? '';
        $orderPaymentMethod = $orderRow['payment_method'] ?? '';
        $orderShipping = isset($orderRow['shipping_charge']) ? (float) $orderRow['shipping_charge'] : 0.0;

        $items = [];
        if (!empty($orderRow['products'])) {
            $decoded = json_decode($orderRow['products'], true);
            if (is_array($decoded)) {
                $items = $decoded;
            }
        }

        // Show all vendor items for superadmin
        foreach ($items as $it) {
            // Remove vendor ID filtering to show all products for superadmin
            $pid = isset($it['product_id']) ? (int) $it['product_id'] : 0;
            $qty = isset($it['quantity']) ? (int) $it['quantity'] : 0;
            $price = isset($it['price']) ? (float) $it['price'] : 0.0;
            $title = '';
            $image = 'assets/images/default.png';

            if ($pid > 0) {
                $prs = mysqli_query($con, "SELECT product_name, images, sku_id FROM products WHERE id='" . mysqli_real_escape_string($con, (string) $pid) . "' LIMIT 1");
                if ($prs && mysqli_num_rows($prs) > 0) {
                    $prow = mysqli_fetch_assoc($prs);
                    $title = $prow['product_name'] ?? '';
                    if (!empty($prow['images'])) {
                        $imgs = @json_decode($prow['images'], true);
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $image = './' . ltrim($imgs[0], './');
                        }
                    }
                    $sku = $prow['sku_id'] ?? '';
                } else {
                    $sku = '';
                }
            } else {
                $sku = '';
            }

            $lineTotal = $qty * $price;
            $orderSubTotal += $lineTotal;
            $orderItemsCount += $qty;

            // Extract coin value (per item, multiply by quantity for total coins for this line)
            $coinPerItem = isset($it['coin']) ? (int) $it['coin'] : 0;
            $coinForThisLine = $coinPerItem * $qty;
            $orderTotalCoins += $coinForThisLine;

            // Get selected variants for display
            $selectedVariants = isset($it['selected_variants']) ? $it['selected_variants'] : [];
            $variantText = '';
            $variantHtml = '';
            if (!empty($selectedVariants) && is_array($selectedVariants)) {
                $variantParts = [];
                $variantHtmlParts = [];
                foreach ($selectedVariants as $variantKey => $variantValue) {
                    $variantParts[] = ucfirst($variantKey) . ': ' . $variantValue;

                    // Special handling for color variants
                    if (strtolower($variantKey) === 'color') {
                        $colorValue = strtolower(trim($variantValue));
                        $colorClass = '';
                        $colorStyle = '';

                        // Map common color names to CSS classes or styles
                        switch ($colorValue) {
                            case 'red':
                                $colorClass = 'bg-danger';
                                break;
                            case 'blue':
                                $colorClass = 'bg-primary';
                                break;
                            case 'green':
                                $colorClass = 'bg-success';
                                break;
                            case 'yellow':
                                $colorClass = 'bg-warning';
                                break;
                            case 'black':
                                $colorClass = 'bg-dark';
                                break;
                            case 'white':
                                $colorClass = 'bg-light border';
                                break;
                            case 'orange':
                                $colorStyle = 'background-color: #ff8c00;';
                                break;
                            case 'purple':
                                $colorStyle = 'background-color: #800080;';
                                break;
                            case 'pink':
                                $colorStyle = 'background-color: #ffc0cb;';
                                break;
                            case 'brown':
                                $colorStyle = 'background-color: #8b4513;';
                                break;
                            case 'gray':
                            case 'grey':
                                $colorStyle = 'background-color: #808080;';
                                break;
                            default:
                                // For custom colors, try to use the color name directly
                                $colorStyle = 'background-color: ' . $colorValue . ';';
                        }

                        $colorIndicator = '';
                        if ($colorClass) {
                            $colorIndicator = '<span class="badge ' . $colorClass . '" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block;"></span>';
                        } elseif ($colorStyle) {
                            $colorIndicator = '<span class="badge" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; ' . $colorStyle . '"></span>';
                        }

                        $variantHtmlParts[] = $colorIndicator . ucfirst($variantKey) . ': ' . $variantValue;
                    } else {
                        $variantHtmlParts[] = ucfirst($variantKey) . ': ' . $variantValue;
                    }
                }
                $variantText = ' (' . implode(', ', $variantParts) . ')';
                $variantHtml = ' (' . implode(', ', $variantHtmlParts) . ')';
            }

            $vendorItems[] = [
                'title' => $title ?: ('#' . $pid),
                'variant_text' => $variantText,
                'variant_html' => $variantHtml,
                'selected_variants' => $selectedVariants,
                'image' => $image,
                'sku' => $sku,
                'price' => $price,
                'qty' => $qty,
                'total' => $lineTotal,
                'coin' => $coinPerItem,
                'coin_total' => $coinForThisLine,
                'vendor_id' => isset($it['vendor_id']) ? $it['vendor_id'] : '',
                'product_status' => isset($it['product_status']) ? $it['product_status'] : 'pending',
                'cancel_reason' => isset($it['cancel_reason']) ? trim($it['cancel_reason']) : '',
                'product_id' => $pid,
            ];
        }
        $orderTotal = $orderSubTotal + $orderShipping;

        // Group products by vendor for compact per-vendor tracking
        $vendorGroups = [];
        foreach ($vendorItems as $it) {
            $vid = (string) ($it['vendor_id'] ?? '');
            if ($vid === '') {
                $vid = 'unknown';
            }
            if (!isset($vendorGroups[$vid])) {
                $vendorGroups[$vid] = [];
            }
            $vendorGroups[$vid][] = $it;
        }

        // Load per-vendor timeline for this order
        $vendorTracks = [];
        if (!empty($orderRow['order_id'])) {
            $oidEsc = mysqli_real_escape_string($con, (string) $orderRow['order_id']);
            $qT = mysqli_query($con, "SELECT vendor_id, track_json FROM `order_track` WHERE order_id='" . $oidEsc . "' AND (product_id IS NULL OR product_id='')");
            if ($qT) {
                while ($rT = mysqli_fetch_assoc($qT)) {
                    $vid = (string) ($rT['vendor_id'] ?? '');
                    $arr = [];
                    $tmp = json_decode($rT['track_json'] ?? '[]', true);
                    if (is_array($tmp)) {
                        $arr = $tmp;
                    }
                    $vendorTracks[$vid] = $arr;
                }
            }
        }
    }
}

// Build Buyer details (safe fallbacks)
$buyerName = 'User #' . ($orderRow['user_id'] ?? '');
$deliveryAddress = '';
if (!empty($orderRow['address_id'])) {
    $aid = mysqli_real_escape_string($con, (string) $orderRow['address_id']);
    $addr_rs = mysqli_query($con, "SELECT * FROM user_address WHERE id='" . $aid . "' LIMIT 1");
    if ($addr_rs && mysqli_num_rows($addr_rs) > 0) {
        $addr = mysqli_fetch_assoc($addr_rs);
        // Resolve state name
        $stateName = '';
        if (!empty($addr['state_id'])) {
            $sid = mysqli_real_escape_string($con, (string) $addr['state_id']);
            $st_rs = mysqli_query($con, "SELECT name FROM state WHERE id='" . $sid . "' LIMIT 1");
            if ($st_rs && mysqli_num_rows($st_rs) > 0) {
                $stateName = (mysqli_fetch_assoc($st_rs)['name'] ?? '');
            }
        }
        $parts = [];
        if (!empty($addr['street_address'])) {
            $parts[] = $addr['street_address'];
        }
        if (!empty($addr['city'])) {
            $parts[] = $addr['city'];
        }
        if (!empty($stateName)) {
            $parts[] = $stateName;
        } elseif (!empty($addr['state_id'])) {
            $parts[] = $addr['state_id'];
        }
        if (!empty($addr['pin_code'])) {
            $parts[] = $addr['pin_code'];
        }
        $deliveryAddress = implode(', ', $parts);
    }
}

// Overall order status flags from all items
$trackDates = [
    'processing' => '',
    'cancelled' => '',
    'shipped' => '',
    'out_for_delivery' => '',
    'delivered' => '',
];
$orderHasPending = false;
$orderHasConfirmed = false;
$orderHasRejected = false;
$orderHasShipped = false;
$orderHasOutForDelivery = false;
$orderHasDelivered = false;
$orderHasShippedEligible = false;
$orderAllCancelled = false;
$currentOrderStatus = 'pending';
$countPending = 0;
$countConfirmed = 0;
$countCancelled = 0;
$countShipped = 0;
$countOutForDelivery = 0;
$countDelivered = 0;
$totalLineItems = 0;
foreach ($vendorItems as $viTmp) {
    // We need original status; recompute from orderRow products
}
if (!empty($orderRow['products'])) {
    $all = json_decode($orderRow['products'], true);
    if (is_array($all)) {
        $orderItemCount = 0;
        $cancelCount = 0;
        foreach ($all as $it) {
            // Check all items regardless of vendor for superadmin
            $st = strtolower((string) ($it['product_status'] ?? ''));
            $qtyForCount = isset($it['quantity']) ? (int) $it['quantity'] : 1;
            if ($qtyForCount < 1) {
                $qtyForCount = 1;
            }
            $orderItemCount += $qtyForCount;
            if ($st === 'pending') {
                $orderHasPending = true;
            }
            if ($st === 'confirmed') {
                $orderHasConfirmed = true;
                $orderHasShippedEligible = true;
            }
            if ($st === 'cancelled') {
                $orderHasRejected = true;
                $cancelCount += $qtyForCount;
            }
            if ($st === 'shipped') {
                $orderHasShipped = true;
            }
            if ($st === 'out_for_delivery') {
                $orderHasOutForDelivery = true;
            }
            if ($st === 'delivered') {
                $orderHasDelivered = true;
            }

            // counts for superadmin summary
            $totalLineItems += $qtyForCount;
            if ($st === 'pending') {
                $countPending += $qtyForCount;
            } elseif ($st === 'confirmed') {
                $countConfirmed += $qtyForCount;
            } elseif ($st === 'cancelled') {
                $countCancelled += $qtyForCount;
            } elseif ($st === 'shipped') {
                $countShipped += $qtyForCount;
            } elseif ($st === 'out_for_delivery') {
                $countOutForDelivery += $qtyForCount;
            } elseif ($st === 'delivered') {
                $countDelivered += $qtyForCount;
            }
        }
        if ($orderItemCount > 0 && $cancelCount === $orderItemCount) {
            $orderAllCancelled = true;
        }
        // Determine a single current status for gating the UI
        if ($orderAllCancelled) {
            $currentOrderStatus = 'cancelled';
        } elseif ($orderHasDelivered) {
            $currentOrderStatus = 'delivered';
        } elseif ($orderHasOutForDelivery) {
            $currentOrderStatus = 'out_for_delivery';
        } elseif ($orderHasShipped) {
            $currentOrderStatus = 'shipped';
        }
        // new partially confirmed when mix of confirmed and pending exists
        elseif ($orderHasConfirmed && $orderHasPending) {
            $currentOrderStatus = 'partially_confirmed';
        } elseif ($orderHasConfirmed) {
            $currentOrderStatus = 'confirmed';
        } else {
            $currentOrderStatus = 'pending';
        }

        // Build cumulative stage counts so stages are monotonic
        $activeItems = max(0, (int) $totalLineItems - (int) $countCancelled);
        $confirmedStageCount = (int) $countConfirmed + (int) $countShipped + (int) $countOutForDelivery + (int) $countDelivered;
        $shippedStageCount = (int) $countShipped + (int) $countOutForDelivery + (int) $countDelivered;
        $outForDeliveryStageCount = (int) $countOutForDelivery + (int) $countDelivered;
        $deliveredStageCount = (int) $countDelivered;
    }
}

// Aggregate tracking dates across all vendor timelines for the main track
if (!empty($vendorTracks)) {
    $minDates = [
        'processing' => null,
        'cancelled' => null,
        'shipped' => null,
        'out_for_delivery' => null,
        'delivered' => null,
    ];
    foreach ($vendorTracks as $vid => $events) {
        foreach ($events as $ev) {
            $st = strtolower((string) ($ev['status'] ?? ''));
            $dt = (string) ($ev['date'] ?? '');
            if ($dt === '') {
                continue;
            }
            if ($st === 'out for delivery') {
                $st = 'out_for_delivery';
            }
            if (array_key_exists($st, $minDates)) {
                $ts = strtotime($dt) ?: null;
                if ($ts !== null) {
                    if ($minDates[$st] === null || $ts < strtotime((string) $minDates[$st])) {
                        $minDates[$st] = $dt;
                    }
                }
            }
        }
    }
    foreach ($minDates as $k => $v) {
        $trackDates[$k] = $v ?: '';
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>ORDER DETAILS | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords" content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/admin/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/styles.min.css" rel="stylesheet">
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/order-detail.css" rel="stylesheet">
    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/icons.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">
    <!-- FlatPickr CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body>
   

    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php'; ?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php'; ?>
        <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap gap-2">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Order Details</h1>
                    <div class="ms-md-1 ms-0">
                        <nav>
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="./order-management.php">Orders</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Order Details</li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row g-3">
                    <div class="col-12 col-xl-6 col-lg-12 col-md-12 col-sm-12" id="print-cart">
                        <div class="print-header" style="display:none">
                            <h2>Order #<?php echo htmlspecialchars($orderDisplayNo); ?></h2>
                            <div class="print-meta">
                                <div>Date:
                                    <?php date_default_timezone_set('Asia/Kolkata');
                                    echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?>
                                </div>
                                <div>Customer:
                                    <?php $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string) $orderRow['user_id']) . "' LIMIT 1");
                                    if ($getname && mysqli_num_rows($getname) > 0) {
                                        $getname_row = mysqli_fetch_assoc($getname);
                                        echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                    } ?>
                                </div>
                                <div>Address: <?php echo htmlspecialchars($deliveryAddress); ?></div>
                            </div>
                        </div>
                        <div class="card custom-card screen-only">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea"> Order No - <span
                                        class="spk-color">#<?php echo htmlspecialchars($orderDisplayNo); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table text-nowrap">
                                        <thead>
                                            <tr>
                                                <th scope="col">Item</th>
                                                <th scope="col">Vendor</th>
                                                <th scope="col">SKU</th>
                                                <th scope="col">Price</th>
                                                <th scope="col">Quantity</th>
                                                <th scope="col">Coin</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Total Price</th>
                                            <th scope="col">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($vendorItems)): ?>
                                                <?php foreach ($vendorItems as $vi): ?>
                                                    <tr>
                                                        <th>
                                                            <div class="d-flex align-items-center gap-3">
                                                                <div class="detali-img1">
                                                                    <img src="<?php if ($vi['image'] !== '' || $vi['image'] !== null) {
                                                                        echo $vendor_baseurl . $vi['image'];
                                                                    } else {
                                                                        echo $vendor_baseurl . 'assets/images/default.png';
                                                                    } ?>" alt="">
                                                                </div>
                                                                <div class="detail-tab-1">
                                                                    <h4 class="mb-2">
                                                                        <?php echo htmlspecialchars($vi['title']); ?>
                                                                    </h4>
                                                                    <?php if (!empty($vi['selected_variants']) && is_array($vi['selected_variants'])): ?>
                                                                        <div class="variant-specs">
                                                                            <div class="d-flex flex-wrap">
                                                                                <?php foreach ($vi['selected_variants'] as $variantKey => $variantValue): ?>
                                                                                    <div class="variant-item d-flex align-items-center">
                                                                                        <?php if (strtolower($variantKey) === 'color'): ?>
                                                                                            <?php
                                                                                            $colorValue = strtolower(trim($variantValue));
                                                                                            $colorClass = '';
                                                                                            $colorStyle = '';

                                                                                            switch ($colorValue) {
                                                                                                case 'red':
                                                                                                    $colorClass = 'bg-danger';
                                                                                                    break;
                                                                                                case 'blue':
                                                                                                    $colorClass = 'bg-primary';
                                                                                                    break;
                                                                                                case 'green':
                                                                                                    $colorClass = 'bg-success';
                                                                                                    break;
                                                                                                case 'yellow':
                                                                                                    $colorClass = 'bg-warning';
                                                                                                    break;
                                                                                                case 'black':
                                                                                                    $colorClass = 'bg-dark';
                                                                                                    break;
                                                                                                case 'white':
                                                                                                    $colorClass = 'bg-light border';
                                                                                                    break;
                                                                                                case 'orange':
                                                                                                    $colorStyle = 'background-color: #ff8c00;';
                                                                                                    break;
                                                                                                case 'purple':
                                                                                                    $colorStyle = 'background-color: #800080;';
                                                                                                    break;
                                                                                                case 'pink':
                                                                                                    $colorStyle = 'background-color: #ffc0cb;';
                                                                                                    break;
                                                                                                case 'brown':
                                                                                                    $colorStyle = 'background-color: #8b4513;';
                                                                                                    break;
                                                                                                case 'gray':
                                                                                                case 'grey':
                                                                                                    $colorStyle = 'background-color: #808080;';
                                                                                                    break;
                                                                                                default:
                                                                                                    $colorStyle = 'background-color: ' . $colorValue . ';';
                                                                                            }

                                                                                            if ($colorClass) {
                                                                                                echo '<span class="color-indicator ' . $colorClass . '"></span>';
                                                                                            } elseif ($colorStyle) {
                                                                                                echo '<span class="color-indicator" style="' . $colorStyle . '"></span>';
                                                                                            }
                                                                                            ?>
                                                                                        <?php endif; ?>
                                                                                        <span class="variant-label">
                                                                                            <strong><?php echo ucfirst($variantKey); ?>:</strong>
                                                                                            <span
                                                                                                class="variant-value"><?php echo htmlspecialchars($variantValue); ?></span>
                                                                                        </span>
                                                                                    </div>
                                                                                <?php endforeach; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </th>
                                                        <td>
                                                            <?php
                                                            $vendorName = 'Unknown Vendor';
                                                            $vendorId = '';
                                                            $v_id = '';
                                                            if (!empty($vi['vendor_id'])) {
                                                                $vendorId = $vi['vendor_id'];
                                                                $vendorQuery = mysqli_query($con, "SELECT business_name,vendor_id FROM vendor_registration WHERE id='" . mysqli_real_escape_string($con, (string) $vi['vendor_id']) . "' LIMIT 1");
                                                                if ($vendorQuery && mysqli_num_rows($vendorQuery) > 0) {
                                                                    $vendorRow = mysqli_fetch_assoc($vendorQuery);
                                                                    $v_id = $vendorRow['vendor_id'];
                                                                    $vendorName = $vendorRow['business_name'] ?? 'Vendor #' . $vi['vendor_id'];
                                                                } else {
                                                                    $vendorName = 'Vendor #' . $vi['vendor_id'];
                                                                }
                                                            }
                                                            ?>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($vendorName); ?></strong>
                                                                <br>
                                                                <small class="text-muted">Seller ID:
                                                                    #<?php echo htmlspecialchars($v_id); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <b><?php echo htmlspecialchars($vi['sku'] ?: '-'); ?></b>
                                                        </td>
                                                        <td>
                                                            <b>₹<?php echo number_format((float) $vi['price'], 2); ?></b>
                                                        </td>
                                                        <td>
                                                            <?php echo (int) $vi['qty']; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info text-white">
                                                                <?php echo (int) ($vi['coin'] ?? 0); ?> Coins
                                                                <?php if (isset($vi['coin_total']) && (int) $vi['coin_total'] > 0 && (int) $vi['qty'] > 1): ?>
                                                                    <br><small>(Total: <?php echo (int) $vi['coin_total']; ?>)</small>
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php
                                                            $status = strtolower($vi['product_status'] ?? 'pending');
                                                            switch ($status) {
                                                                case 'pending':
                                                                    echo 'warning';
                                                                    break;
                                                                case 'confirmed':
                                                                    echo 'info';
                                                                    break;
                                                                case 'shipped':
                                                                    echo 'primary';
                                                                    break;
                                                                case 'out_for_delivery':
                                                                    echo 'secondary';
                                                                    break;
                                                                case 'delivered':
                                                                    echo 'success';
                                                                    break;
                                                                case 'cancelled':
                                                                    echo 'danger';
                                                                    break;
                                                                default:
                                                                    echo 'secondary';
                                                            }
                                                            ?>">
                                                                <?php echo ucfirst($vi['product_status'] ?? 'Pending'); ?>
                                                            </span>
                                                            <?php if ($status === 'cancelled' && !empty($vi['cancel_reason'])): ?>
                                                                <br><small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">
                                                                    <strong>Reason:</strong> <?php echo htmlspecialchars($vi['cancel_reason']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>

                                                        <td>
                                                            ₹<?php echo number_format((float) $vi['total'], 2); ?>
                                                        </td>
                                                        <td>
                                                            <?php $status = strtolower($vi['product_status'] ?? ''); ?>
                                                            <?php if ($status === 'pending'): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-danger js-cancel-item" data-product-id="<?php echo isset($vi['product_id']) ? (int)$vi['product_id'] : 0; ?>">Cancel</button>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td colspan="9">
                                                            <?php
                                                            $rowVendorId = isset($vi['vendor_id']) ? (string) $vi['vendor_id'] : '';
                                                            $timelineRow = $rowVendorId !== '' ? ($vendorTracks[$rowVendorId] ?? []) : [];
                                                            $dateProcessingR = '';
                                                            $dateShippedR = '';
                                                            $dateOfdR = '';
                                                            $dateDeliveredR = '';
                                                            foreach ($timelineRow as $evR) {
                                                                $stlR = strtolower((string) ($evR['status'] ?? ''));
                                                                $dteR = (string) ($evR['date'] ?? '');
                                                                if ($stlR === 'processing') {
                                                                    $dateProcessingR = $dteR;
                                                                }
                                                                if ($stlR === 'shipped') {
                                                                    $dateShippedR = $dteR;
                                                                }
                                                                if ($stlR === 'out for delivery' || $stlR === 'out_for_delivery') {
                                                                    $dateOfdR = $dteR;
                                                                }
                                                                if ($stlR === 'delivered') {
                                                                    $dateDeliveredR = $dteR;
                                                                }
                                                            }
                                                            $d1 = $dateProcessingR !== '';
                                                            $d2 = $dateShippedR !== '';
                                                            $d3 = $dateOfdR !== '';
                                                            $d4 = $dateDeliveredR !== '';
                                                            ?>
                                                            <div class="vt-line sm">
                                                                <div class="vt-step">
                                                                    <span
                                                                        class="vt-when"><?php echo $d1 ? date('d M Y', strtotime($dateProcessingR)) : ''; ?></span>
                                                                    <span
                                                                        class="vt-when"><?php echo $d1 ? date('h:i A', strtotime($dateProcessingR)) : ''; ?></span>
                                                                    <div
                                                                        class="<?php echo $d1 ? 'done vt-dot-none' : 'vt-dot'; ?>">
                                                                        <?php if ($d1) { ?><img
                                                                                src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png"
                                                                                class="img-fluid img-thumbnail p-0"
                                                                                alt=""><?php } ?>
                                                                    </div>
                                                                    <div class="vt-title">Confirmed</div>
                                                                </div>
                                                                <div class="vt-connector <?php echo $d2 ? 'done' : ''; ?>">
                                                                </div>
                                                                <div class="vt-step">
                                                                    <span
                                                                        class="vt-when"><?php echo $d2 ? date('d M Y', strtotime($dateShippedR)) : ''; ?></span>
                                                                    <span
                                                                        class="vt-when"><?php echo $d2 ? date('h:i A', strtotime($dateShippedR)) : ''; ?></span>
                                                                    <div
                                                                        class=" <?php echo $d2 ? 'done vt-dot-none' : 'vt-dot '; ?>">
                                                                        <?php if ($d2) { ?><img
                                                                                src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png"
                                                                                class="img-fluid img-thumbnail p-0"
                                                                                alt=""><?php } ?>
                                                                    </div>
                                                                    <div class="vt-title">Shipped</div>
                                                                </div>
                                                                <div class="vt-connector <?php echo $d3 ? 'done' : ''; ?>">
                                                                </div>
                                                                <div class="vt-step">
                                                                    <span
                                                                        class="vt-when"><?php echo $d3 ? date('d M Y', strtotime($dateOfdR)) : ''; ?></span>
                                                                    <span
                                                                        class="vt-when"><?php echo $d3 ? date('h:i A', strtotime($dateOfdR)) : ''; ?></span>
                                                                    <div
                                                                        class=" <?php echo $d3 ? 'done vt-dot-none' : 'vt-dot'; ?>">
                                                                        <?php if ($d3) { ?><img
                                                                                src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png"
                                                                                class="img-fluid img-thumbnail p-0"
                                                                                alt=""><?php } ?>
                                                                    </div>
                                                                    <div class="vt-title">Out For Delivery</div>
                                                                </div>
                                                                <div class="vt-connector <?php echo $d4 ? 'done' : ''; ?>">
                                                                </div>
                                                                <div class="vt-step">
                                                                    <span
                                                                        class="vt-when"><?php echo $d4 ? date('d M Y', strtotime($dateDeliveredR)) : ''; ?></span>
                                                                    <span
                                                                        class="vt-when"><?php echo $d4 ? date('h:i A', strtotime($dateDeliveredR)) : ''; ?></span>
                                                                    <div
                                                                        class=" <?php echo $d4 ? 'done vt-dot-none' : 'vt-dot'; ?>">
                                                                        <?php if ($d4) { ?><img
                                                                                src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png"
                                                                                class="img-fluid img-thumbnail p-0"
                                                                                alt=""><?php } ?>
                                                                    </div>
                                                                    <div class="vt-title">Delivered</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted">No items found in this
                                                        order.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-end">
                                    <div class="detail-total-text">
                                        <h4>
                                            Sub Total :
                                        </h4>
                                        <p>
                                            ₹<?php echo number_format((float) $orderSubTotal, 2); ?>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Shipping Charges :
                                        </h4>
                                        <p class="text-danger">
                                            +₹<?php echo number_format((float) $orderShipping, 2); ?>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Total Price :
                                        </h4>
                                        <p>
                                            <b> ₹<?php echo number_format((float) $orderTotal, 2); ?></b>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Total Items :
                                        </h4>
                                        <p>
                                            <?php echo (int) $orderItemsCount; ?>
                                        </p>
                                    </div>
                                    <div class="detail-total-text">
                                        <h4>
                                            Total Coins Earned :
                                        </h4>
                                        <p class="text-info">
                                            <b><?php echo (int) $orderTotalCoins; ?> Coins</b>
                                        </p>
                                    </div>
                                </div>

                                <div class="text-end mt-5">
                                    <button class="btn btn-print" id="btnPrintCart"><i class="fa-solid fa-print"></i>
                                        Print</button>
                                </div>
                            </div>
                        </div>
                        <!-- Print Invoice Only -->
                        <div class="print-invoice">
                            <div class="pi-brand">
                                <div class="print-title"><strong>Hagidy</strong></div>

                            </div>
                            <div class="print-header" style="display:block">
                                <div class="print-meta">
                                    <div><strong>Order #<?php echo htmlspecialchars($orderDisplayNo); ?></strong></div>
                                    <div>Date:
                                        <?php date_default_timezone_set('Asia/Kolkata');
                                        echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?>
                                    </div>
                                    <div>Customer:
                                        <?php $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string) $orderRow['user_id']) . "' LIMIT 1");
                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                            $getname_row = mysqli_fetch_assoc($getname);
                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                        } ?>
                                    </div>
                                    <div>Address: <?php echo htmlspecialchars($deliveryAddress); ?></div>
                                </div>
                            </div>
                            <div class="pi-title">Items</div>
                            <table class="table pi-table avoid-break">
                                <thead>
                                    <tr>
                                        <th style="width:35%">Item</th>
                                        <th style="width:10%">SKU</th>
                                        <th style="width:8%" class="text-end">Price</th>
                                        <th style="width:6%" class="text-center">Qty</th>
                                        <th style="width:8%" class="text-center">Coin</th>
                                        <th style="width:10%" class="text-end">Total</th>
                                        <th style="width:13%" class="text-start">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($vendorItems)): ?>
                                        <?php foreach ($vendorItems as $vi): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-start">
                                                        <img class="img-fluid"
                                                            style="width:25px;height:25px;border-radius:6px;margin-right:8px;"
                                                            src="<?php if ($vi['image'] !== '' || $vi['image'] !== null) {
                                                                echo $vendor_baseurl . $vi['image'];
                                                            } else {
                                                                echo $vendor_baseurl . 'assets/images/default.png';
                                                            } ?>">
                                                        <div>
                                                            <div class="fw-semibold">
                                                                <?php echo htmlspecialchars($vi['title']); ?>
                                                            </div>
                                                            <?php if (!empty($vi['selected_variants']) && is_array($vi['selected_variants'])): ?>
                                                                <div class="mt-1">
                                                                    <?php foreach ($vi['selected_variants'] as $variantKey => $variantValue): ?>
                                                                        <div class="d-flex align-items-center mb-1">
                                                                            <?php if (strtolower($variantKey) === 'color'): ?>
                                                                                <?php
                                                                                $colorValue = strtolower(trim($variantValue));
                                                                                $colorClass = '';
                                                                                $colorStyle = '';

                                                                                switch ($colorValue) {
                                                                                    case 'red':
                                                                                        $colorClass = 'bg-danger';
                                                                                        break;
                                                                                    case 'blue':
                                                                                        $colorClass = 'bg-primary';
                                                                                        break;
                                                                                    case 'green':
                                                                                        $colorClass = 'bg-success';
                                                                                        break;
                                                                                    case 'yellow':
                                                                                        $colorClass = 'bg-warning';
                                                                                        break;
                                                                                    case 'black':
                                                                                        $colorClass = 'bg-dark';
                                                                                        break;
                                                                                    case 'white':
                                                                                        $colorClass = 'bg-light border';
                                                                                        break;
                                                                                    case 'orange':
                                                                                        $colorStyle = 'background-color: #ff8c00;';
                                                                                        break;
                                                                                    case 'purple':
                                                                                        $colorStyle = 'background-color: #800080;';
                                                                                        break;
                                                                                    case 'pink':
                                                                                        $colorStyle = 'background-color: #ffc0cb;';
                                                                                        break;
                                                                                    case 'brown':
                                                                                        $colorStyle = 'background-color: #8b4513;';
                                                                                        break;
                                                                                    case 'gray':
                                                                                    case 'grey':
                                                                                        $colorStyle = 'background-color: #808080;';
                                                                                        break;
                                                                                    default:
                                                                                        $colorStyle = 'background-color: ' . $colorValue . ';';
                                                                                }

                                                                                if ($colorClass) {
                                                                                    echo '<span class="color-indicator ' . $colorClass . ' me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; border: 1px solid #ddd;"></span>';
                                                                                } elseif ($colorStyle) {
                                                                                    echo '<span class="color-indicator me-2" style="width: 12px; height: 12px; border-radius: 50%; display: inline-block; border: 1px solid #ddd; ' . $colorStyle . '"></span>';
                                                                                }
                                                                                ?>
                                                                            <?php endif; ?>
                                                                            <span class="text-muted" style="font-size: 0.8rem;">
                                                                                <strong><?php echo ucfirst($variantKey); ?>:</strong>
                                                                                <?php echo htmlspecialchars($variantValue); ?>
                                                                            </span>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="w-25"><?php echo htmlspecialchars($vi['sku'] ?: '-'); ?></td>
                                                <td class="text-end">₹<?php echo number_format((float) $vi['price'], 2); ?></td>
                                                <td class="text-center"><?php echo (int) $vi['qty']; ?></td>
                                                <td class="text-center"><?php echo (int) ($vi['coin'] ?? 0); ?></td>
                                                <td class="text-end">₹<?php echo number_format((float) $vi['total'], 2); ?></td>
                                                <td class="text-start">
                                                    <?php echo ucfirst($vi['product_status'] ?? 'Pending'); ?>
                                                    <?php if (strtolower($vi['product_status'] ?? '') === 'cancelled' && !empty($vi['cancel_reason'])): ?>
                                                        <br><small style="font-size: 0.7rem; color: #666;">
                                                            <strong>Reason:</strong> <?php echo htmlspecialchars($vi['cancel_reason']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No items</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div class="pi-summary avoid-break">
                                <table>
                                    <tr>
                                        <td>Sub Total</td>
                                        <td class="text-end">₹<?php echo number_format((float) $orderSubTotal, 2); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Shipping Charges</td>
                                        <td class="text-end">₹<?php echo number_format((float) $orderShipping, 2); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Total</td>
                                        <td class="text-end">₹<?php echo number_format((float) $orderTotal, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Coins Earned</strong></td>
                                        <td class="text-end"><strong><?php echo (int) $orderTotalCoins; ?> Coins</strong></td>
                                    </tr>
                                </table>
                            </div>
                            <div style="margin-top:12px;font-size:12px;color:#555">Payment Method:
                                <?php if ($orderPaymentMethod == 'cod') {
                                    echo 'Cash on Delivery';
                                } else {
                                    echo htmlspecialchars($orderPaymentMethod ?: '-');
                                } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-3 col-lg-6 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea">Buyer Details </div>
                            </div>
                            <div class="d-flex align-items-center gap-3 border-bottom p-3">
                                <div class="address-text">
                                    <h3>
                                        Customer Name :
                                    </h3>
                                    <h6>

                                        <?php //echo htmlspecialchars($buyerName);
                                        $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string) $orderRow['user_id']) . "' LIMIT 1");
                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                            $getname_row = mysqli_fetch_assoc($getname);
                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                        }
                                        ?>
                                    </h6>
                                </div>
                            </div>
                            <div class="card-body border-bottom">
                                <div class="address-text">
                                    <h3>
                                        Delivery address :
                                    </h3>
                                    <p>
                                        <?php echo htmlspecialchars($deliveryAddress); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="card-body border-bottom">
                                <div class="order-summary">
                                    <!-- <h4>
                                        Order Summary
                                    </h4> -->
                                    <h5>Order Time Stamp :
                                        <span><?php date_default_timezone_set('Asia/Kolkata');
                                        echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?></span>
                                    </h5>
                                    <h5>Payment Method :
                                        <span><?php if ($orderPaymentMethod == 'cod') {
                                            echo 'Cash on Delivery';
                                        } else {
                                            echo htmlspecialchars($orderPaymentMethod ?: '-');
                                        } ?></span>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-3 col-lg-6 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1 mb-0 card-order-details-hea"> Order Tracking </div>
                                <div class="text-sky-blue">
                                    <!-- #SPK1218153635 -->
                                </div>
                            </div>
                            <div class="card-body ">
                                <div id="one">
                                    <div class="order-detail-rela">
                                        <div class="d-flex align-items-start gap-2">
                                            <div class="order-shipping-img">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping1.png" alt="">
                                            </div>
                                            <div class="order-placed-text">
                                                <h3>Order Placed</h3>
                                                <h4>
                                                    Order placed successfully by <span>

                                                        <?php //echo htmlspecialchars($buyerName);
                                                        $getname = mysqli_query($con, "SELECT * FROM users WHERE id='" . mysqli_real_escape_string($con, (string) $orderRow['user_id']) . "' LIMIT 1");
                                                        if ($getname && mysqli_num_rows($getname) > 0) {
                                                            $getname_row = mysqli_fetch_assoc($getname);
                                                            echo htmlspecialchars($getname_row['first_name']) . ' ' . htmlspecialchars($getname_row['last_name']);
                                                        }
                                                        ?>
                                                    </span>
                                                </h4>
                                                <p>
                                                    <?php date_default_timezone_set('Asia/Kolkata');
                                                    echo date('d M Y h:i A', strtotime(htmlspecialchars($orderCreatedAt ?: '-'))); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="border-order-plas"></div>
                                    </div>
                                    <?php if ($currentOrderStatus === 'cancelled'): ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/cancel.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Order Cancelled</h3>
                                                    <h4>Order has been cancelled</h4>
                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3><?php
                                                    if ($currentOrderStatus === 'pending') {
                                                        echo 'Order Need To Confirmed';
                                                    } elseif ($currentOrderStatus === 'partially_confirmed') {
                                                        echo 'Order Partially Confirmed';
                                                    } else {
                                                        echo 'Order Confirmed';
                                                    }
                                                    ?></h3>
                                                    <h4>
                                                        <?php if ($currentOrderStatus === 'pending') { ?>
                                                            Please review the order summary and confirm your order, otherwise
                                                            the order will be cancelled.
                                                        <?php } elseif ($currentOrderStatus === 'partially_confirmed') { ?>
                                                            Confirmed:
                                                            <?php echo (int) $confirmedStageCount; ?>/<?php echo (int) $activeItems; ?>
                                                            items, Pending:
                                                            <?php echo (int) max(0, $activeItems - $confirmedStageCount); ?>
                                                            <?php echo ($countCancelled > 0 ? ' • Cancelled: ' . $countCancelled : ''); ?>
                                                        <?php } else { ?>
                                                            Order has been confirmed
                                                            (<?php echo (int) $confirmedStageCount; ?>/<?php echo (int) $activeItems; ?>
                                                            items<?php echo ($countCancelled > 0 ? ' • Cancelled: ' . $countCancelled : ''); ?>)
                                                        <?php } ?>
                                                        <?php if (!empty($trackDates['processing'])) { ?>
                                                            <br><small
                                                                class="text-muted"><?php echo date('d M Y h:i A', strtotime($trackDates['processing'])); ?></small>
                                                        <?php } ?>
                                                    </h4>
                                                    <?php if ($currentOrderStatus === 'pending'): ?>
                                                        <div class="mt-2">

                                                            <div class="modal fade" id="addAttributeModal" tabindex="-1"
                                                                aria-labelledby="addAttributeModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog modal-sm modal-dialog-centered">
                                                                    <div class="modal-content add-attribute-modal-content">
                                                                        <div class="modal-header border-0 pb-0">
                                                                            <h4 class="modal-title w-100 text-center fw-bold"
                                                                                id="addAttributeModalLabel">Add Reason</h4>
                                                                            <button type="button" class="btn-close"
                                                                                data-bs-dismiss="modal"
                                                                                aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body pt-3">

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div id="two">
                                    <div class="order-detail-rela">

                                        <div class="border-order-plas"></div>
                                    </div>
                                    <?php if ($currentOrderStatus === 'confirmed'): ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping6.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Ready to Ship</h3>
                                                    <h4>
                                                        Please update the status once the order has been Shipped.
                                                    </h4>

                                                </div>
                                                <div class="border-order-plas"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (in_array($currentOrderStatus, ['shipped', 'out_for_delivery', 'delivered'], true)): ?>
                                    <div id="three">
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping6.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Shipped</h3>
                                                    <h4>
                                                        Shipped:
                                                        <?php echo (int) $shippedStageCount; ?>/<?php echo (int) $activeItems; ?>
                                                        items<?php echo ($countCancelled > 0 ? ' • Cancelled: ' . $countCancelled : ''); ?>
                                                        <?php if (!empty($trackDates['shipped'])) { ?>
                                                            <br><small
                                                                class="text-muted"><?php echo date('d M Y h:i A', strtotime($trackDates['shipped'])); ?></small>
                                                        <?php } ?>
                                                    </h4>
                                                    <div>
                                                        <?php if ($currentOrderStatus === 'shipped'): ?>

                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (in_array($currentOrderStatus, ['out_for_delivery', 'delivered'], true)): ?>
                                        <div class="order-detail-rela">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping4.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Out For Delivery</h3>
                                                    <h4>
                                                        Out For Delivery:
                                                        <?php echo (int) $outForDeliveryStageCount; ?>/<?php echo (int) $activeItems; ?>
                                                        items<?php echo ($countCancelled > 0 ? ' • Cancelled: ' . $countCancelled : ''); ?>
                                                        <?php if (!empty($trackDates['out_for_delivery'])) { ?>
                                                            <br><small
                                                                class="text-muted"><?php echo date('d M Y h:i A', strtotime($trackDates['out_for_delivery'])); ?></small>
                                                        <?php } ?>
                                                    </h4>

                                                </div>
                                            </div>
                                            <div class="border-order-plas"></div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($currentOrderStatus === 'delivered'): ?>
                                        <div class="order-detail-rela mb-0">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="order-shipping-img">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping4.png" alt="">
                                                </div>
                                                <div class="order-placed-text">
                                                    <h3>Delivered</h3>
                                                    <h4>
                                                        Delivered:
                                                        <?php echo (int) $deliveredStageCount; ?>/<?php echo (int) $activeItems; ?>
                                                        items<?php echo ($countCancelled > 0 ? ' • Cancelled: ' . $countCancelled : ''); ?>
                                                        <?php if (!empty($trackDates['delivered'])) { ?>
                                                            <br><small
                                                                class="text-muted"><?php echo date('d M Y h:i A', strtotime($trackDates['delivered'])); ?></small>
                                                        <?php } ?>
                                                    </h4>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End:: row-2 -->
        </div>
        <!-- End::app-content -->


        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                        class="text-primary fw-semibold"> Hagidy </a>.
                    Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                        <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology
                        </span>
                    </a>
                </span>
            </div>
        </footer>
        <!-- Footer End -->
    </div>

    <!-- Scroll To Top -->
    <div class="scrollToTop">
        <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
    </div>
    <div id="responsive-overlay"></div>
    <!-- Scroll To Top -->

    <!-- Popper JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Defaultmenu JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>

    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/ecommerce-dashboard.js"></script>

    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom-switcher.min.js"></script>

    <!-- Date & Time Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/date&time_pickers.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>

    <!-- Per-product Cancel Reason Modal -->
    <div class="modal fade" id="adminCancelReasonModal" tabindex="-1" aria-labelledby="adminCancelReasonLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content add-attribute-modal-content">
                <div class="modal-header border-0 pb-0">
                    <h4 class="modal-title w-100 text-center fw-bold" id="adminCancelReasonLabel">Cancel Reason</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <form id="adminCancelItemForm" method="post">
                        <input type="hidden" name="vendor_action" value="cancel_item">
                        <input type="hidden" name="product_id" id="adminCancelProductId" value="0">
                        <div class="mb-3">
                            <label for="adminCancelReason" class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control form-control-lg" id="adminCancelReason" name="cancel_reason" placeholder=" Enter Reason" required></textarea>
                        </div>
                        <button type="submit" class="btn save-attribute-btn w-100">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Print only the cart section
            var btnPrintCart = document.getElementById('btnPrintCart');
            if (btnPrintCart) {
                btnPrintCart.addEventListener('click', function () {
                    window.print();
                });
            }
            // Get the cancel button and modal elements
            const cancelBtn = document.getElementById('cancelOrderBtn');
            const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
            const cancelNoBtn = document.getElementById('cancelNoBtn');
            const cancelYesBtn = document.getElementById('cancelYesBtn');

            // Show modal when cancel button is clicked
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    modal.show();
                });
            }

            // Handle No button click
            if (cancelNoBtn) {
                cancelNoBtn.addEventListener('click', function () {
                    modal.hide();
                });
            }

            // Handle Yes button click
            if (cancelYesBtn) {
                cancelYesBtn.addEventListener('click', function () {
                    // Here you can add the actual cancel order logic
                    alert('Order has been cancelled successfully!');
                    modal.hide();


                });
            }
        });
    </script>

    <script>
        // Per-product cancel trigger
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.js-cancel-item').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var pid = parseInt(this.getAttribute('data-product-id') || '0', 10);
                    if (!pid || isNaN(pid) || pid <= 0) return;
                    var pidInput = document.getElementById('adminCancelProductId');
                    if (pidInput) pidInput.value = String(pid);
                    var reasonEl = document.getElementById('adminCancelReason');
                    if (reasonEl) reasonEl.value = '';
                    var modalEl = document.getElementById('adminCancelReasonModal');
                    if (modalEl && window.bootstrap) {
                        var m = new bootstrap.Modal(modalEl);
                        m.show();
                    }
                });
            });
        });
    </script>

</body>

</html>
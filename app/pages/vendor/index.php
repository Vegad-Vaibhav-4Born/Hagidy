<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/init.php';
if (!isset($_SESSION['vendor_reg_id'])) {
    // Construct absolute path to vendor login page
    $baseUrl = rtrim(USER_BASEURL, '/');
    header('Location: ' . $baseUrl . '/vendor/login.php');
    exit;
}


$vendor_reg_id = $_SESSION['vendor_reg_id'];

// Access guard: block dashboard when vendor status is pending/hold/rejected
require_once __DIR__ . '/../../handlers/acess_guard.php';


// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get date range filter (default to current month: 1st day of current month to today)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // 1st day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Dashboard Statistics from JSON items in `order.products`
$total_sales = 0;
$total_orders = 0;
$units_sold = 0;
$new_orders = 0;

$start_date_esc = mysqli_real_escape_string($con, $start_date);
$end_date_esc = mysqli_real_escape_string($con, $end_date);

// Fetch orders in range
$orders_rs = mysqli_query($con, "SELECT id, order_id, created_date, order_status, products FROM `order` WHERE DATE(created_date) BETWEEN '$start_date_esc' AND '$end_date_esc'");
$order_ids_with_vendor_items = [];
if ($orders_rs) {
    while ($row = mysqli_fetch_assoc($orders_rs)) {
        if (strtolower($row['order_status']) === 'cancelled') {
            continue;
        }
        $items = json_decode($row['products'], true);
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (isset($item['vendor_id']) && (string) $item['vendor_id'] === (string) $vendor_reg_id) {
                // Only count delivered items for Total Sales and Units Sold
                $product_status = strtolower(trim($item['product_status'] ?? ''));
                if ($product_status === 'delivered') {
                    $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                    $price = isset($item['price']) ? (float) $item['price'] : 0;
                    $units_sold += $qty;
                    $total_sales += ($qty * $price);
                }
                $order_ids_with_vendor_items[$row['id']] = true;
            }
        }
    }
}
$total_orders = count($order_ids_with_vendor_items);

// New Orders (last 7 days) independent of selected range
$seven_days_ago = date('Y-m-d', strtotime('-7 days'));
$new_orders_rs = mysqli_query($con, "SELECT id, created_date, order_status, products FROM `order` WHERE DATE(created_date) >= '$seven_days_ago'");
$new_order_ids = [];
if ($new_orders_rs) {
    while ($row = mysqli_fetch_assoc($new_orders_rs)) {
        if (strtolower($row['order_status']) === 'cancelled') {
            continue;
        }
        $items = json_decode($row['products'], true);
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (isset($item['vendor_id']) && (string) $item['vendor_id'] === (string) $vendor_reg_id) {
                $new_order_ids[$row['id']] = true;
            }
        }
    }
}
$new_orders = count($new_order_ids);

// Payments (same as total sales - only delivered items)
$payments = $total_sales;

// Last 5 Orders for this vendor
$recent_orders = [];
$recent_rs = mysqli_query($con, "SELECT id, order_id, created_date, order_status, products FROM `order` WHERE order_status != 'Cancelled' ORDER BY created_date DESC LIMIT 50");
if ($recent_rs) {
    while ($row = mysqli_fetch_assoc($recent_rs)) {
        $items = json_decode($row['products'], true);
        if (!is_array($items)) {
            continue;
        }
        $total_amount = 0;
        $vendor_statuses = [];
        foreach ($items as $item) {
            if (isset($item['vendor_id']) && (string) $item['vendor_id'] === (string) $vendor_reg_id) {
                $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                $price = isset($item['price']) ? (float) $item['price'] : 0;
                $total_amount += ($qty * $price);
                if (isset($item['product_status'])) {
                    $vendor_statuses[strtolower((string) $item['product_status'])] = true;
                }
            }
        }
        if ($total_amount > 0) {
            // Derive vendor status label from only this vendor's items
            $vs_list = array_keys($vendor_statuses);
            $derived_status = 'pending';
            if (count($vs_list) === 1) {
                $derived_status = $vs_list[0];
            } else if (!empty($vs_list)) {
                $has_cancelled = isset($vendor_statuses['cancelled']);
                $has_ofd = isset($vendor_statuses['out for delivery']) || isset($vendor_statuses['out_for_delivery']);
                $has_shipped = isset($vendor_statuses['shipped']);
                $has_confirmed = isset($vendor_statuses['confirmed']) || isset($vendor_statuses['processing']);
                $has_pending = isset($vendor_statuses['pending']);
                $all_delivered = (count($vs_list) === 1 && isset($vendor_statuses['delivered'])) ? true : false;
                if ($all_delivered) {
                    $derived_status = 'delivered';
                } else if ($has_cancelled) {
                    $derived_status = 'cancelled';
                } else if ($has_ofd) {
                    $derived_status = 'shipped'; // Map out_for_delivery to shipped for display
                } else if ($has_shipped) {
                    $derived_status = 'shipped';
                } else if ($has_confirmed) {
                    $derived_status = 'confirmed';
                } else if ($has_pending) {
                    $derived_status = 'pending';
                } else {
                    $derived_status = $vs_list[0];
                }
            }
            $recent_orders[] = [
                'id' => $row['id'],
                'order_id' => $row['order_id'],
                'created_date' => $row['created_date'],
                'order_status' => $row['order_status'],
                'total_amount' => $total_amount,
                '__vendor_status' => $derived_status,
            ];
        }
        if (count($recent_orders) >= 5) {
            break;
        }
    }
}

// Product Stock Statistics
// All Stock Count
$all_stock_query = mysqli_query($con, "SELECT COUNT(*) as count FROM products WHERE vendor_id = '$vendor_reg_id'");
$all_stock_data = mysqli_fetch_assoc($all_stock_query);
$all_stock_count = $all_stock_data['count'] ?? 0;

// Low Stock Count (inventory < 10 and > 0)
$low_stock_query = mysqli_query($con, "SELECT COUNT(*) as count FROM products WHERE vendor_id = '$vendor_reg_id' AND inventory < 10 AND inventory > 0");
$low_stock_data = mysqli_fetch_assoc($low_stock_query);
$low_stock_count = $low_stock_data['count'] ?? 0;

// Out Of Stock Count (inventory = 0)
$out_stock_query = mysqli_query($con, "SELECT COUNT(*) as count FROM products WHERE vendor_id = '$vendor_reg_id' AND inventory = 0");
$out_stock_data = mysqli_fetch_assoc($out_stock_query);
$out_stock_count = $out_stock_data['count'] ?? 0;

// All Stock Products (last 5)
$all_stock_products_query = mysqli_query($con, "SELECT p.*, sc.name as sub_category_name 
    FROM products p 
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
    WHERE p.vendor_id = '$vendor_reg_id' 
    ORDER BY p.updated_date DESC 
    LIMIT 5");
$all_stock_products = [];
while ($product = mysqli_fetch_assoc($all_stock_products_query)) {
    $all_stock_products[] = $product;
}

// Low Stock Products (last 5)
$low_stock_products_query = mysqli_query($con, "SELECT p.*, sc.name as sub_category_name 
    FROM products p 
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
    WHERE p.vendor_id = '$vendor_reg_id' AND p.inventory < 10 AND p.inventory > 0 
    ORDER BY p.inventory ASC 
    LIMIT 5");
$low_stock_products = [];
while ($product = mysqli_fetch_assoc($low_stock_products_query)) {
    $low_stock_products[] = $product;
}

// Out Of Stock Products (last 5)
$out_stock_products_query = mysqli_query($con, "SELECT p.*, sc.name as sub_category_name 
    FROM products p 
    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
    WHERE p.vendor_id = '$vendor_reg_id' AND p.inventory = 0 
    ORDER BY p.updated_date DESC 
    LIMIT 5");
$out_stock_products = [];
while ($product = mysqli_fetch_assoc($out_stock_products_query)) {
    $out_stock_products[] = $product;
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
    <title>DASHBOARD | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords" content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

   
    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.css" rel="stylesheet">

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
        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/media/loader.svg" alt="">
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
                    class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb g-3">
                    <div>
                        <h1 class="page-title fw-semibold fs-18 dashborder-heading">Dashboard</h1>
                    </div>
                    <div>
                        <div class="input-group">
                            <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i> </div> <input
                                type="text" class="form-control flatpickr-input" id="daterange"
                                placeholder="Date range picker" readonly="readonly">
                        </div>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card-flex">
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/TotalSales.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Total Sales</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b>₹<?php echo number_format($total_sales, 2); ?></b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/TotalOrders.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Total Orders</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b><?php echo number_format($total_orders); ?></b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Unitssold.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Units Sold</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b><?php echo number_format($units_sold); ?></b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/NewOrders.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2 mr-2">New Orders <span
                                                    class="badge bg-success-transparent text-success px-1 py-1 fs-10">New</span>
                                            </div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <?php echo number_format($new_orders); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Payments.svg" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2 mr-2">Payments</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    ₹ 0.00<?php //echo number_format($payments, 2); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--End::row-1 -->
                <!-- Page Header -->
                <div
                    class="d-md-flex d-block align-items-center justify-content-between my-4 mb-4 page-header-breadcrumb">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Management</h1>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12 col-xxl-6 col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                        <div class="card custom-card custom-card-index mb-0">
                            <div class="card-header justify-content-between">
                                <div class="card-title1">
                                    Order Request
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table text-nowrap">
                                    <tbody class="table-group-divider">
                                        <?php if (empty($recent_orders)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No recent orders found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_orders as $order):


                                                ?>
                                                <tr>
                                                    <td class="rqu-id">#<?php echo $order['order_id']; ?></td>
                                                    <td>
                                                        <span class="req-date">Order Date & Time</span> <br>
                                                        <span
                                                            class="req-time"><?php echo date('d M Y H:i', strtotime($order['created_date'])); ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="req-date">Selling Price</span> <br>
                                                        <span
                                                            class="req-time"><b>₹<?php echo number_format($order['total_amount'], 2); ?></b></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $vs = strtolower((string) ($order['__vendor_status'] ?? 'pending'));
                                                        $label = ucfirst(str_replace('_', ' ', $vs));
                                                        $class = 'bg-outline-warning';
                                                        if ($vs === 'confirmed')
                                                            $class = 'bg-outline-primary';
                                                        if ($vs === 'shipped')
                                                            $class = 'bg-outline-info';
                                                        if ($vs === 'out_for_delivery')
                                                            $class = 'bg-outline-success';
                                                        if ($vs === 'delivered')
                                                            $class = 'bg-success';
                                                        if ($vs === 'cancelled')
                                                            $class = 'bg-outline-danger';
                                                        ?>
                                                        <span
                                                            class="badge rounded-pill <?php echo $class; ?>"><?php echo htmlspecialchars($label); ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="./order-details.php?id=<?php echo $order['order_id']; ?>"><i
                                                                class="fa-solid fa-chevron-right"></i></a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="view-all p-4">
                                <a href="./order-management.php" class="text-sky-blue"> View All Entries <i
                                        class="fa-solid fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xxl-6 col-xl-12 col-lg-12 col-md-12 col-sm-12 mb-3">
                        <div class="card custom-card custom-card-index mb-0">
                            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                                <div class="card-title1 ">
                                    Products Stock
                                </div>
                                <div class="nav-index">
                                    <ul class="nav nav-pills " id="pills-tab" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active nav-linkindex" id="pills-home-tab"
                                                data-bs-toggle="pill" data-bs-target="#pills-home" type="button"
                                                role="tab" aria-controls="pills-home" aria-selected="true">All
                                                Stock <span
                                                    class="badge ms-2 bg-primary"><?php echo $all_stock_count; ?></span></button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link nav-linkindex" id="pills-profile-tab"
                                                data-bs-toggle="pill" data-bs-target="#pills-profile" type="button"
                                                role="tab" aria-controls="pills-profile" aria-selected="false">Low
                                                Stock <span
                                                    class="badge ms-2 bg-warning"><?php echo $low_stock_count; ?></span></button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link nav-linkindex d-flex align-items-center"
                                                id="pills-contact-tab" data-bs-toggle="pill"
                                                data-bs-target="#pills-contact" type="button" role="tab"
                                                aria-controls="pills-contact" aria-selected="false">Out Of Stock <span
                                                    class="badge ms-2 bg-danger"><?php echo $out_stock_count; ?></span></button>
                                        </li>
                                    </ul>

                                </div>
                            </div>
                            <div class="tab-content index-teb" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                                    aria-labelledby="pills-home-tab" tabindex="0">
                                    <div class="table-responsive index-secon-table">
                                        <table class="table text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><b>SKU</b></th>
                                                    <th scope="col"><b>Product Name</b></th>
                                                    <th scope="col"><b>Sub Category</b></th>
                                                    <th scope="col"><b>Stock</b></th>
                                                    <th scope="col"><b>Avbl Stock</b></th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if (empty($all_stock_products)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No products
                                                            found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($all_stock_products as $product): ?>
                                                        <tr>
                                                            <td>#<?php echo $product['sku_id']; ?></td>
                                                            <td
                                                                class="<?php echo strlen($product['product_name']) > 30 ? 'cut-text' : ''; ?>">
                                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                if ($product['Inventory'] == 0) {
                                                                    echo '<span class="badge bg-danger-transparent">Out Of Stock</span>';
                                                                } elseif ($product['Inventory'] < 10) {
                                                                    echo '<span class="badge bg-warning-transparent">Low Stock</span>';
                                                                } else {
                                                                    echo '<span class="badge bg-success-transparent">In Stock</span>';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php echo number_format($product['Inventory']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="pills-profile" role="tabpanel"
                                    aria-labelledby="pills-profile-tab" tabindex="0">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><b>SKU</b></th>
                                                    <th scope="col"><b>Product Name</b></th>
                                                    <th scope="col"><b>Sub Category</b></th>
                                                    <th scope="col"><b>Stock</b></th>
                                                    <th scope="col"><b>Avbl Stock</b></th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if (empty($low_stock_products)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No low stock
                                                            products found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($low_stock_products as $product): ?>
                                                        <tr>
                                                            <td>#<?php echo $product['sku_id']; ?></td>
                                                            <td
                                                                class="<?php echo strlen($product['product_name']) > 30 ? 'cut-text' : ''; ?>">
                                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-warning-transparent">Low Stock</span>
                                                            </td>
                                                            <td>
                                                                <?php echo number_format($product['Inventory']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="pills-contact" role="tabpanel"
                                    aria-labelledby="pills-contact-tab" tabindex="0">
                                    <div class="table-responsive">
                                        <table class="table text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><b>SKU</b></th>
                                                    <th scope="col"><b>Product Name</b></th>
                                                    <th scope="col"><b>Sub Category</b></th>
                                                    <th scope="col"><b>Stock</b></th>
                                                    <th scope="col"><b>Avbl Stock</b></th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider">
                                                <?php if (empty($out_stock_products)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No out of stock
                                                            products found</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($out_stock_products as $product): ?>
                                                        <tr>
                                                            <td>#<?php echo $product['sku_id']; ?></td>
                                                            <td
                                                                class="<?php echo strlen($product['product_name']) > 30 ? 'cut-text' : ''; ?>">
                                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-danger-transparent">Out Of Stock</span>
                                                            </td>
                                                            <td>
                                                                0
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="view-all p-4">
                                <a href="./inventoryManagement.php" class="text-sky-blue"> View All Entries <i
                                        class="fa-solid fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-2 -->

            </div>
        </div>
        <!-- End::app-content -->

        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                        class="text-primary fw-semibold"> Hagidy </a>.
                    Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                        <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology </span>
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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>


    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/ecommerce-dashboard.js"></script>


    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>

    <!-- Date & Time Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/date&time_pickers.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>

    <script>
        // Date range picker functionality
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize flatpickr for date range
            flatpickr("#daterange", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: ["<?php echo $start_date; ?>", "<?php echo $end_date; ?>"],
                onChange: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        const startDate = selectedDates[0].toISOString().split('T')[0];
                        const endDate = selectedDates[1].toISOString().split('T')[0];

                        // Redirect with new date range
                        const url = new URL(window.location);
                        url.searchParams.set('start_date', startDate);
                        url.searchParams.set('end_date', endDate);
                        window.location.href = url.toString();
                    }
                }
            });
        });
    </script>

</body>

</html>
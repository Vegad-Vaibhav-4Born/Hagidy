<?php
include __DIR__ . '/../includes/init.php';  

if (!isset($_SESSION['superadmin_id'])) {
    header('Location: admin/login.php');
    exit;
}

// Handle AJAX request for dashboard data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'dashboard_data') {
    header('Content-Type: application/json');
    // Ensure browsers do not cache the dashboard JSON
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    http_response_code(200);
    // Ensure no PHP warnings/notices break JSON output
    @ini_set('display_errors', '0');
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    }

    // Capture PHP warnings/notices and log them (avoid throwing to keep 200 JSON)
    $ajax_errors = [];
    set_error_handler(function ($errno, $errstr, $errfile = '', $errline = 0) use (&$ajax_errors) {
        $msg = "[$errno] $errstr in $errfile:$errline";
        $ajax_errors[] = $msg;
        error_log('Dashboard AJAX error: ' . $msg);
        return true; // handled
    });

    try {
        // Get date filter parameters
        $date_start = isset($_GET['date_start']) ? $_GET['date_start'] : null;
        $date_end = isset($_GET['date_end']) ? $_GET['date_end'] : null;

        // Validate dates (YYYY-MM-DD). If invalid, ignore filtering
        $date_regex = '/^\d{4}-\d{2}-\d{2}$/';
        if ($date_start && !preg_match($date_regex, $date_start)) { $date_start = null; }
        if ($date_end && !preg_match($date_regex, $date_end)) { $date_end = null; }

    // Build date filter condition
    $date_filter = '';
    if ($date_start && $date_end) {
        $date_start = mysqli_real_escape_string($con, $date_start);
        $date_end = mysqli_real_escape_string($con, $date_end);
        $date_filter = " AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'";
    }

    // Debug: Check if transactions table exists and has data
    $debug_transactions = mysqli_query($con, "SELECT COUNT(*) as count FROM transactions");
    $debug_result = $debug_transactions ? mysqli_fetch_assoc($debug_transactions) : null;
    $transaction_count = $debug_result ? $debug_result['count'] : 0;

    // Get commission + GST from settled transactions
    $commission = mysqli_query($con, "SELECT sum(platform_fee + COALESCE(gst, 0)) as total FROM transactions WHERE status = 'settled'");
    if ($commission === false) { $ajax_errors[] = 'SQL error (commission): ' . mysqli_error($con); }
    $commission_result = $commission ? mysqli_fetch_assoc($commission) : null;
    $stats['total_commission'] = ($commission_result && $commission_result['total'] !== null)
        ? (float) $commission_result['total']
        : 0.0;

    // Debug: Check what statuses exist in transactions
    $status_debug = mysqli_query($con, "SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
    $statuses = [];
    if ($status_debug) {
        while ($row = mysqli_fetch_assoc($status_debug)) {
            $statuses[] = $row['status'] . ': ' . $row['count'];
        }
    }

    // Debug: Log commission calculation
    error_log("AJAX Commission Debug - Transaction count: " . $transaction_count . ", Commission: " . $stats['total_commission'] . ", Statuses: " . implode(', ', $statuses));

    // Total Orders
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM `order` WHERE 1=1" . $date_filter);
    if ($result === false) { $ajax_errors[] = 'SQL error (total_orders): ' . mysqli_error($con); }
    $stats['total_orders'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Sales (sum of order amounts)
    $result = mysqli_query($con, "SELECT SUM(total_amount) as total FROM `order` WHERE order_status = 'completed'" . $date_filter);
    if ($result === false) { $ajax_errors[] = 'SQL error (total_sales): ' . mysqli_error($con); }
    $stats['total_sales'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Products
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM products WHERE status = 'approved'");
    $stats['total_products'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Vendors
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM vendor_registration WHERE status = 'approved'");
    $stats['total_vendors'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Pending Vendor Requests
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM vendor_registration WHERE status = 'pending'");
    $stats['pending_vendors'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Users (filtered by date range)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (total_users filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users");
        if ($result === false) { $ajax_errors[] = 'SQL error (total_users): ' . mysqli_error($con); }
    }
    $stats['total_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Active Users (filtered by date range)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Active' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (active_users filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Active'");
        if ($result === false) { $ajax_errors[] = 'SQL error (active_users): ' . mysqli_error($con); }
    }
    $stats['active_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Inactive Users (filtered by date range)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Inactive' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (inactive_users filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Inactive'");
        if ($result === false) { $ajax_errors[] = 'SQL error (inactive_users): ' . mysqli_error($con); }
    }
    $stats['inactive_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Blocked Users (filtered by date range)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Blocked' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (blocked_users filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Blocked'");
        if ($result === false) { $ajax_errors[] = 'SQL error (blocked_users): ' . mysqli_error($con); }
    }
    $stats['blocked_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // New Users (filtered by date range or last 7 days)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (new_users filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        if ($result === false) { $ajax_errors[] = 'SQL error (new_users): ' . mysqli_error($con); }
    }
    $stats['new_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Referrals (filtered by date range)
    if ($date_start && $date_end) {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE referred_by IS NOT NULL AND referred_by != '' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
        if ($result === false) { $ajax_errors[] = 'SQL error (total_referrals filtered): ' . mysqli_error($con); }
    } else {
        $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE referred_by IS NOT NULL AND referred_by != ''");
        if ($result === false) { $ajax_errors[] = 'SQL error (total_referrals): ' . mysqli_error($con); }
    }
    $stats['total_referrals'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

    // Total Commission (re-affirm as float and non-null)
    $stats['total_commission'] = ($commission_result && $commission_result['total'] !== null)
        ? (float) $commission_result['total']
        : 0.0;

    // Top Vendors (by order count) - Simplified for MariaDB compatibility
    $top_vendors_query = "SELECT v.id, v.business_name, v.owner_name, 
        COALESCE((
            SELECT COUNT(*)
            FROM `order` o
            WHERE o.products LIKE CONCAT('%\"vendor_id\":', v.id, '%')
            OR o.products LIKE CONCAT('%\"vendor_id\":\"', v.id, '\"%')
        ), 0) as order_count
    FROM vendor_registration v
    WHERE v.status = 'approved'
    ORDER BY order_count DESC
    LIMIT 5";
    $top_vendors_result = mysqli_query($con, $top_vendors_query);
    if ($top_vendors_result === false) { $ajax_errors[] = 'SQL error (top_vendors): ' . mysqli_error($con); }
    $top_vendors = [];
    if ($top_vendors_result) {
        while ($row = mysqli_fetch_assoc($top_vendors_result)) {
            $top_vendors[] = $row;
        }
    }

    // Top Referrals (users with most referrals)
    $top_referrals_query = "
    SELECT 
        u.id, 
        CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
        u.referral_code, 
        COUNT(r.id) AS referral_count
    FROM users u
    LEFT JOIN users r ON r.referred_by = u.id
    GROUP BY u.id, u.first_name, u.last_name, u.referral_code
    HAVING referral_count > 0
    ORDER BY referral_count DESC
    LIMIT 5
";

    $top_referrals_result = mysqli_query($con, $top_referrals_query);
    if ($top_referrals_result === false) { $ajax_errors[] = 'SQL error (top_referrals): ' . mysqli_error($con); }
    $top_referrals = [];
    if ($top_referrals_result) {
        while ($row = mysqli_fetch_assoc($top_referrals_result)) {
            $top_referrals[] = $row;
        }
    }

    // Best Selling Products - Simplified for MariaDB compatibility with pagination
    $products_page = isset($_GET['products_page']) ? (int) $_GET['products_page'] : 1;
    $products_per_page = 5;
    $products_offset = ($products_page - 1) * $products_per_page;

    $best_products_query = "SELECT p.id, p.product_name, p.images, p.selling_price, v.id as vendor_id, v.business_name, p.sku_id,
        COALESCE((
            SELECT COUNT(*)
            FROM `order` o
            WHERE o.products LIKE CONCAT('%\"product_id\":', p.id, '%')
            OR o.products LIKE CONCAT('%\"product_id\":\"', p.id, '\"%')
        ), 0) as total_sold
    FROM products p
    LEFT JOIN vendor_registration v ON p.vendor_id = v.id
    WHERE p.status = 'approved'
    ORDER BY total_sold DESC
    LIMIT $products_per_page OFFSET $products_offset";

    // Get total count for pagination
    $total_products_query = "SELECT COUNT(*) as total FROM products WHERE status = 'approved'";
    $total_products_result = mysqli_query($con, $total_products_query);
    if ($total_products_result === false) { $ajax_errors[] = 'SQL error (products_count): ' . mysqli_error($con); }
    $total_products_count = $total_products_result ? mysqli_fetch_assoc($total_products_result)['total'] : 0;
    $total_products_pages = ceil($total_products_count / $products_per_page);
    $best_products_result = mysqli_query($con, $best_products_query);
    if ($best_products_result === false) { $ajax_errors[] = 'SQL error (best_products): ' . mysqli_error($con); }
    $best_products = [];
    if ($best_products_result) {
        while ($row = mysqli_fetch_assoc($best_products_result)) {
            $best_products[] = $row;
        }
    }

        $response = [
            'success' => empty($ajax_errors),
            'stats' => $stats,
            'top_vendors' => $top_vendors,
            'top_referrals' => $top_referrals,
            'best_products' => $best_products,
            'pagination' => [
                'current_page' => $products_page,
                'total_pages' => $total_products_pages,
                'total_count' => $total_products_count
            ]
        ];
        if (!empty($ajax_errors)) { $response['message'] = 'One or more queries failed.'; $response['errors'] = $ajax_errors; }
        echo json_encode($response);
    } catch (Throwable $e) {
        error_log('Dashboard AJAX exception: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Server exception',
            'error' => $e->getMessage()
        ]);
    } finally {
        restore_error_handler();
    }
    exit;
}

// Fetch dashboard statistics
$stats = [];

// Total Orders
$result = mysqli_query($con, "SELECT COUNT(*) as total FROM `order`");
$stats['total_orders'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Total Sales (sum of order amounts)
$result = mysqli_query($con, "SELECT SUM(total_amount) as total FROM `order` WHERE order_status = 'completed'");
$stats['total_sales'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Total Products
$result = mysqli_query($con, "SELECT COUNT(*) as total FROM products WHERE status = 'approved'");
$stats['total_products'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Total Vendors
$result = mysqli_query($con, "SELECT COUNT(*) as total FROM vendor_registration WHERE status = 'approved'");
$stats['total_vendors'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Pending Vendor Requests
$result = mysqli_query($con, "SELECT COUNT(*) as total FROM vendor_registration WHERE status = 'pending'");
$stats['pending_vendors'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Get date filter parameters for main dashboard
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : null;
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : null;

// Total Users (filtered by date range)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users");
}
$stats['total_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Active Users (filtered by date range)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Active' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Active'");
}
$stats['active_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Inactive Users (filtered by date range)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Inactive' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Inactive'");
}
$stats['inactive_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Blocked Users (filtered by date range)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Blocked' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE status = 'Blocked'");
}
$stats['blocked_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// New Users (filtered by date range or last 7 days)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
}
$stats['new_users'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Total Referrals (filtered by date range)
if ($date_start && $date_end) {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE referred_by IS NOT NULL AND referred_by != '' AND DATE(created_date) BETWEEN '$date_start' AND '$date_end'");
} else {
    $result = mysqli_query($con, "SELECT COUNT(*) as total FROM users WHERE referred_by IS NOT NULL AND referred_by != ''");
}
$stats['total_referrals'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Debug: Check if transactions table exists and has data
$debug_transactions = mysqli_query($con, "SELECT COUNT(*) as count FROM transactions");
$debug_result = $debug_transactions ? mysqli_fetch_assoc($debug_transactions) : null;
$transaction_count = $debug_result ? $debug_result['count'] : 0;

// Get commission + GST from settled transactions
$commission = mysqli_query($con, "SELECT sum(platform_fee + COALESCE(gst, 0)) as total FROM transactions WHERE status = 'settled'");
$commission_result = $commission ? mysqli_fetch_assoc($commission) : null;
$stats['total_commission'] = ($commission_result && $commission_result['total'] !== null)
    ? (float) $commission_result['total']
    : 0.0;

// Debug: Check what statuses exist in transactions
$status_debug = mysqli_query($con, "SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
$statuses = [];
if ($status_debug) {
    while ($row = mysqli_fetch_assoc($status_debug)) {
        $statuses[] = $row['status'] . ': ' . $row['count'];
    }
}

// Debug: Log commission calculation
error_log("Commission Debug - Transaction count: " . $transaction_count . ", Commission: " . $stats['total_commission'] . ", Statuses: " . implode(', ', $statuses));
$result = mysqli_query(
    $con,
    "SELECT COUNT(*) as total FROM `order` WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);
$stats['recent_orders'] = $result ? mysqli_fetch_assoc($result)['total'] : 0;

// Top Vendors (by order count) - Simplified for MariaDB compatibility
$top_vendors_query = "SELECT v.id, v.business_name, v.owner_name, 
    COALESCE((
        SELECT COUNT(*)
        FROM `order` o
        WHERE o.products LIKE CONCAT('%\"vendor_id\":', v.id, '%')
        OR o.products LIKE CONCAT('%\"vendor_id\":\"', v.id, '\"%')
    ), 0) as order_count
FROM vendor_registration v
WHERE v.status = 'approved'
ORDER BY order_count DESC
LIMIT 5";
$top_vendors_result = mysqli_query($con, $top_vendors_query);
$top_vendors = [];
if ($top_vendors_result) {
    while ($row = mysqli_fetch_assoc($top_vendors_result)) {
        $top_vendors[] = $row;
    }
}

// Top Referrals (users with most referrals)
$top_referrals_query = "
    SELECT 
        u.id, 
        CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
        u.referral_code, 
        COUNT(r.id) AS referral_count
    FROM users u
    LEFT JOIN users r ON r.referred_by = u.id
    GROUP BY u.id, u.first_name, u.last_name, u.referral_code
    HAVING referral_count > 0
    ORDER BY referral_count DESC
    LIMIT 5
";

$top_referrals_result = mysqli_query($con, $top_referrals_query);
$top_referrals = [];
if ($top_referrals_result) {
    while ($row = mysqli_fetch_assoc($top_referrals_result)) {
        $top_referrals[] = $row;
    }
}
// Best Selling Products - Simplified for MariaDB compatibility with pagination
$products_page = isset($_GET['products_page']) ? (int) $_GET['products_page'] : 1;
$products_per_page = 5;
$products_offset = ($products_page - 1) * $products_per_page;

$best_products_query = "SELECT p.id, p.product_name,p.images, p.selling_price, v.vendor_id as vendor_id, v.business_name, p.sku_id,
    COALESCE((
        SELECT COUNT(*)
        FROM `order` o
        WHERE o.products LIKE CONCAT('%\"product_id\":', p.id, '%')
        OR o.products LIKE CONCAT('%\"product_id\":\"', p.id, '\"%')
    ), 0) as total_sold
FROM products p
LEFT JOIN vendor_registration v ON p.vendor_id = v.id
WHERE p.status = 'approved'
ORDER BY total_sold DESC
LIMIT $products_per_page OFFSET $products_offset";

// Get total count for pagination
$total_products_query = "SELECT COUNT(*) as total FROM products WHERE status = 'approved'";
$total_products_result = mysqli_query($con, $total_products_query);
$total_products_count = $total_products_result ? mysqli_fetch_assoc($total_products_result)['total'] : 0;
$total_products_pages = ceil($total_products_count / $products_per_page);
$best_products_result = mysqli_query($con, $best_products_query);
$best_products = [];
if ($best_products_result) {
    while ($row = mysqli_fetch_assoc($best_products_result)) {
        $best_products[] = $row;
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
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>DASHBOARD | HADIDY</title>
    <meta name="Description" content="Hagidy-Super-Admin">
    <meta name="Author" content="Hagidy-Super-Admin">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template dashboard, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, dashboard, dashboard template bootstrap, admin dashboard bootstrap.">

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
                    class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb g-3">
                    <div>
                        <h1 class="page-title fw-semibold fs-18 dashborder-heading">Dashboard</h1>
                    </div>
                    <div class="d-flex gap-2">

                        <div class="input-group">
                            <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i> </div> <input
                                type="text" class="form-control flatpickr-input" id="daterange"
                                placeholder="Date range picker" readonly="readonly">
                        </div>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="card-flex card-flex1">
                            <div class="card custom-card custom-card-index mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-3  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/currency-rupee.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Active Users</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom"
                                                    id="active-users">
                                                    <b><?php echo number_format($stats['active_users']); ?></b>
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
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/TotalOrders.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Inactive Users</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom"
                                                    id="inactive-users">
                                                    <b><?php echo number_format($stats['inactive_users']); ?></b>
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
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/vendor.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">New Users</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom"
                                                    id="new-users">
                                                    <b><?php echo number_format($stats['new_users']); ?></b>
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
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/customer.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2 mr-2">Total Commission + GST
                                                <!-- <span class="badge bg-success-transparent text-success px-1 py-1 fs-10">New</span> -->
                                            </div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom"
                                                    id="total-commission">
                                                    ₹<?php echo number_format($stats['total_commission']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="row">

                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1"> Highest Referrals</div>
                                <div class="dropdown">

                                    <ul class="dropdown-menu" role="menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Download</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Import</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Export</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body" id="top-referrals-list">
                                <ul class="list-unstyled mb-0">
                                    <?php if (!empty($top_referrals)): ?>
                                        <?php foreach ($top_referrals as $index => $referral): ?>
                                            <li class="<?php echo $index < count($top_referrals) - 1 ? 'mb-3' : 'mb-0'; ?>">
                                                <a href="javascript:void(0);">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-top justify-content-center">
                                                            <div class="me-2">
                                                                <span
                                                                    class="avatar avatar-md avatar-rounded bg-primary text-white d-flex align-items-center justify-content-center">
                                                                    <?php
                                                                    $name = $referral['full_name'];
                                                                    $words = explode(' ', trim($name));
                                                                    $initials = '';
                                                                    foreach ($words as $word) {
                                                                        if (!empty($word)) {
                                                                            $initials .= strtoupper(substr($word, 0, 1));
                                                                        }
                                                                    }
                                                                    echo substr($initials, 0, 2);
                                                                    ?>
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <p class="mb-0 fw-semibold">
                                                                    <?php echo htmlspecialchars($referral['full_name']); ?>
                                                                </p>
                                                                <p class="mb-0 text-muted fs-12">
                                                                    <?php echo $referral['referral_count']; ?> Referrals
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="mb-0">
                                            <div class="text-center text-muted py-3">
                                                <p class="mb-0">No referrals found</p>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title1"> Top Vendors </div>
                                <div class="dropdown">
                                    <a href="./allVendors.php" class="view-all">View All </a>
                                    <ul class="dropdown-menu" role="menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Download</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Import</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Export</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body" id="top-vendors-list">
                                <ul class="list-unstyled mb-0">
                                    <?php if (!empty($top_vendors)): ?>
                                        <?php foreach ($top_vendors as $index => $vendor): ?>
                                            <li class="<?php echo $index < count($top_vendors) - 1 ? 'mb-3' : 'mb-0'; ?>">
                                                <a href="javascript:void(0);">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <div class="d-flex align-items-top justify-content-center">
                                                            <div class="me-2">
                                                                <span
                                                                    class="avatar avatar-md avatar-rounded bg-primary text-white d-flex align-items-center justify-content-center">
                                                                    <?php
                                                                    $name = $vendor['business_name'];
                                                                    $words = explode(' ', trim($name));
                                                                    $initials = '';
                                                                    foreach ($words as $word) {
                                                                        if (!empty($word)) {
                                                                            $initials .= strtoupper(substr($word, 0, 1));
                                                                        }
                                                                    }
                                                                    echo substr($initials, 0, 2);
                                                                    ?>
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <p class="mb-0 fw-semibold">
                                                                    <?php echo htmlspecialchars($vendor['business_name']); ?>
                                                                </p>
                                                                <p class="mb-0 text-muted fs-12">
                                                                    <?php echo $vendor['order_count']; ?> Orders
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <span
                                                                class="fs-14">₹<?php echo number_format($vendor['order_count'] * 1000); ?></span>
                                                        </div>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="mb-0">
                                            <div class="text-center text-muted py-3">
                                                <p class="mb-0">No vendors found</p>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12">

                        <div class="card custom-card">
                            <div class="card-header justify-content-between d-sm-flex d-block">
                                <div class="card-title1"> Vendors Request <span class="badge ms-2 bg-danger"
                                        id="vendor-requests-count"><?php echo $stats['pending_vendors']; ?></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="tab-content">
                                    <div class="tab-pane show active text-muted border-0 p-0" id="active-orders"
                                        role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table card-table table-vcenter text-nowrap mb-0">
                                                <tbody class="active-tab">
                                                    <?php
                                                    $pendingVendorsQuery = "SELECT * FROM vendor_registration order by id desc LIMIT 5";
                                                    $pendingVendorsResult = mysqli_query($con, $pendingVendorsQuery);
                                                    if (mysqli_num_rows($pendingVendorsResult) > 0) {
                                                        while ($row = mysqli_fetch_assoc($pendingVendorsResult)) {
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="d-flex align-items-center">
                                                                        <p class="mb-0 fw-semibold">
                                                                            <?php echo $row['business_name']; ?>
                                                                        </p>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="align-items-center">
                                                                        <span
                                                                            class="fs-12 "><?php echo $row['mobile_number']; ?></span>

                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <div class="align-items-center">
                                                                        <span class="fs-12 "><?php echo $row['city']; ?></span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    if ($row['status'] == 'pending') {
                                                                        $status_class = 'bg-outline-secondary';
                                                                    } elseif ($row['status'] == 'approved') {
                                                                        $status_class = 'bg-outline-success';
                                                                    } elseif ($row['status'] == 'rejected') {
                                                                        $status_class = 'bg-outline-danger';
                                                                    } elseif ($row['status'] == 'hold') {
                                                                        $status_class = 'bg-outline-info';
                                                                    }
                                                                    ?>
                                                                    <span
                                                                        class="badge rounded-pill <?php echo $status_class; ?>"><?php echo $row['status']; ?></span>
                                                                </td>
                                                                <td> <a aria-label="anchor"
                                                                        href="./sellerDetail.php?vendor_id=<?php echo $row['id']; ?>">
                                                                        <span class="orders-arrow"><i
                                                                                class="ri-arrow-right-s-line fs-18"></i></span>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                            <?php
                                                        }
                                                    } else {
                                                        ?>

                                                        <tr>
                                                            <td colspan="9" class="text-center py-4">
                                                                <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                                <div class="text-muted">No vendors found.</div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    ?>

                                                </tbody>

                                            </table>
                                            <div class="p-3 "><a href="./allVendors.php" class="view-all">View
                                                    All Entries <i class="bi bi-arrow-right ms-2 fw-semibold"></i></a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="col-xl-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <div class="card-title1">Best Selling Products </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table text-nowrap mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">SKU ID</th>
                                                <th scope="col">Seller ID</th>
                                                <th scope="col" class="text-start p-2">Name</th>
                                                <th scope="col">Price</th>
                                                <th scope="col">Units Sold</th>
                                                <th scope="col">Revenue</th>
                                                <th scope="col">Status</th>

                                            </tr>
                                        </thead>
                                        <tbody class="top-selling" id="best-products-list">
                                            <?php if (!empty($best_products)): ?>
                                                <?php foreach ($best_products as $index => $product): 

                                                // print_r($product);
                                                    $productImages = [];
                                                    if (!empty($product['images'])) {
                                                        $productImages = json_decode($product['images'], true);
                                                    }
                                                    $firstImage = !empty($productImages) && isset($productImages[0]) ? $productImages[0] : 'uploads/vendors/no-product.png';
                                                    ?>
                                                    <tr>
                                                        <td>#<?php echo $product['sku_id']; ?></td>
                                                        <td>#<?php echo $product['vendor_id']; ?></td>
                                                        <td>
                                                            <img src="<?php echo $vendor_baseurl . $firstImage; ?>"
                                                                class="p-2 rounded-pill bg-light" alt=""
                                                                style="width:40px;height:40px;border-radius:6px;margin-right:8px;">
                                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                                        </td>
                                                        <td>₹<?php echo number_format($product['selling_price'], 2); ?></td>
                                                        <td><span
                                                                class="fw-semibold"><?php echo number_format($product['total_sold']); ?></span>
                                                        </td>
                                                        <td><span
                                                                class="fw-semibold">₹<?php echo number_format($product['selling_price'] * $product['total_sold']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span
                                                                class="badge badge-sm bg-success-transparent text-success">Available</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center text-muted py-3">
                                                        <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                        <p class="mb-0">No products found</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                    <div class="card-footer">
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <div class="view-all"> <a href="./product-management.php"
                                                    class="view-all">View All Products <i
                                                        class="bi bi-arrow-right ms-2 fw-semibold"></i></a>

                                            </div>
                                            <div class="ms-auto">
                                                <div class="d-flex justify-content-center mt-3 mb-3">
                                                    <nav>
                                                        <ul class="pagination s-sm mb-0" id="products-pagination">
                                                            <?php if ($products_page > 1): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link"
                                                                        href="?products_page=<?php echo $products_page - 1; ?>">Prev</a>
                                                                </li>
                                                            <?php endif; ?>

                                                            <?php for ($i = max(1, $products_page - 2); $i <= min($total_products_pages, $products_page + 2); $i++): ?>
                                                                <li
                                                                    class="page-item <?php echo $i == $products_page ? 'active' : ''; ?>">
                                                                    <a class="page-link"
                                                                        href="?products_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                                </li>
                                                            <?php endfor; ?>

                                                            <?php if ($products_page < $total_products_pages): ?>
                                                                <li class="page-item">
                                                                    <a class="page-link"
                                                                        href="?products_page=<?php echo $products_page + 1; ?>">Next</a>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </nav>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>


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

    <!-- Dashboard Refresh Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const vendorBaseUrl = '<?php echo isset($vendor_baseurl) ? $vendor_baseurl : ""; ?>';
            const refreshBtn = document.getElementById('refresh-dashboard');
            const dateRangeInput = document.getElementById('daterange');

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function () {
                    refreshDashboard();
                });
            }

            // Initialize date range picker
            if (dateRangeInput) {
                flatpickr(dateRangeInput, {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    onChange: function (selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            applyDateFilter(selectedDates[0], selectedDates[1]);
                        }
                    }
                });
            }

            function applyDateFilter(startDate, endDate) {
                const start = startDate.toISOString().split('T')[0];
                const end = endDate.toISOString().split('T')[0];

                // Update URL with date parameters
                const url = new URL(window.location);
                url.searchParams.set('date_start', start);
                url.searchParams.set('date_end', end);
                window.history.pushState({}, '', url);

                // Refresh dashboard with date filter
                refreshDashboard(start, end);
            }

            function refreshDashboard(startDate = null, endDate = null) {
                const btn = document.getElementById('refresh-dashboard');
                const icon = btn ? btn.querySelector('i') : null;

                // Show loading state (guard if button not present)
                if (icon) icon.className = 'ri-loader-4-line';
                if (btn) btn.disabled = true;

                // Build URL with date parameters
                let url = 'index.php?ajax=dashboard_data';
                if (startDate && endDate) {
                    url += `&date_start=${startDate}&date_end=${endDate}`;
                }

                // Fetch updated data
                fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    cache: 'no-store',
                    credentials: 'same-origin'
                })
                    .then(async (response) => {
                        const text = await response.text();
                        if (!response.ok) {
                            console.error('Dashboard fetch failed:', response.status, text);
                            throw new Error('Server error');
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('Invalid JSON from server:', text);
                            throw new Error('Invalid server response');
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            // Update statistics
                            updateElement('total-commission', '₹' + formatNumber(data.stats.total_commission));
                            updateElement('total-products', formatNumber(data.stats.total_products));
                            updateElement('total-vendors', formatNumber(data.stats.total_vendors));
                            updateElement('vendor-requests-count', data.stats.pending_vendors);

                            // Update user statistics
                            updateElement('active-users', formatNumber(data.stats.active_users));
                            updateElement('inactive-users', formatNumber(data.stats.inactive_users));
                            updateElement('new-users', formatNumber(data.stats.new_users));

                            // Update top vendors
                            updateTopVendors(data.top_vendors);

                            // Update top referrals
                            updateTopReferrals(data.top_referrals);

                            // Update best products
                            updateBestProducts(data.best_products);

                            // Show success message
                        
                        } else {
                            const msg = (data && (data.message || (data.errors && data.errors[0]))) || 'Failed to refresh dashboard';
                            console.error('Dashboard data error:', data);
                            showToast(msg, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('Error refreshing dashboard', 'error');
                    })
                    .finally(() => {
                        // Reset button state
                        if (icon) icon.className = 'ri-refresh-line';
                        if (btn) btn.disabled = false;
                    });
            }

            function updateElement(id, content) {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = content;
                }
            }

            function updateTopVendors(vendors) {
                const container = document.getElementById('top-vendors-list');
                if (!container) return;

                const ul = container.querySelector('ul');
                if (!ul) return;

                if (vendors && vendors.length > 0) {
                    ul.innerHTML = vendors.map((vendor, index) => {
                        const name = (vendor.business_name || '').trim();
                        const words = name.split(/\s+/).filter(Boolean);
                        let initials = '';
                        for (let i = 0; i < words.length; i++) {
                            initials += words[i].charAt(0).toUpperCase();
                            if (initials.length >= 2) break;
                        }
                        if (!initials) initials = 'V';

                        return `
                        <li class="${index < vendors.length - 1 ? 'mb-3' : 'mb-0'}">
                            <a href="javascript:void(0);">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-top justify-content-center">
                                        <div class="me-2">
                                            <span class="avatar avatar-md avatar-rounded bg-primary text-white d-flex align-items-center justify-content-center">${initials}</span>
                                        </div>
                                        <div>
                                            <p class="mb-0 fw-semibold">${vendor.business_name || ''}</p>
                                            <p class="mb-0 text-muted fs-12">${vendor.order_count} Orders</p>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="fs-14">₹${formatNumber((vendor.order_count || 0) * 1000)}</span>
                                    </div>
                                </div>
                            </a>
                        </li>`;
                    }).join('');
                } else {
                    ul.innerHTML = '<li class="mb-0"><div class="text-center text-muted py-3"><p class="mb-0">No vendors found</p></div></li>';
                }
            }

            function updateTopReferrals(referrals) {
                const container = document.getElementById('top-referrals-list');
                if (!container) return;

                const ul = container.querySelector('ul');
                if (!ul) return;

                if (referrals && referrals.length > 0) {
                    ul.innerHTML = referrals.map((referral, index) => {
                        // Generate initials from name
                        const name = referral.full_name;
                        const words = name.trim().split(' ');
                        let initials = '';
                        words.forEach(word => {
                            if (word.length > 0) {
                                initials += word.charAt(0).toUpperCase();
                            }
                        });
                        initials = initials.substring(0, 2);

                        return `
                        <li class="${index < referrals.length - 1 ? 'mb-3' : 'mb-0'}">
                            <a href="javascript:void(0);">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-top justify-content-center">
                                        <div class="me-2">
                                            <span class="avatar avatar-md avatar-rounded bg-primary text-white d-flex align-items-center justify-content-center">
                                                ${initials}
                                            </span>
                                        </div>
                                        <div>
                                            <p class="mb-0 fw-semibold">${referral.full_name}</p>
                                            <p class="mb-0 text-muted fs-12">${referral.referral_count} Referrals</p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                    `;
                    }).join('');
                } else {
                    ul.innerHTML = '<li class="mb-0"><div class="text-center text-muted py-3"><p class="mb-0">No referrals found</p></div></li>';
                }
            }

            function updateBestProducts(products) {
                const tbody = document.getElementById('best-products-list');
                if (!tbody) return;

                if (products && products.length > 0) {
                    tbody.innerHTML = products.map((product) => {
                        let firstImage = 'uploads/vendors/no-product.png';
                        try {
                            if (product.images) {
                                const imgs = typeof product.images === 'string' ? JSON.parse(product.images) : product.images;
                                if (Array.isArray(imgs) && imgs.length > 0 && imgs[0]) {
                                    firstImage = imgs[0];
                                }
                            }
                        } catch (e) {
                            // fallback keeps default firstImage
                        }

                        const imageUrl = (vendorBaseUrl || '') + firstImage;
                        const skuOrId = product.sku_id || product.id || '';
                        const sellerId = product.vendor_id || '';
                        const price = Number(product.selling_price || 0);
                        const sold = Number(product.total_sold || 0);
                        const revenue = price * sold;

                        return `
                            <tr>
                                <td>#${skuOrId}</td>
                                <td>#${sellerId}</td>
                                <td>
                                    <img src="${imageUrl}"
                                        class="p-2 rounded-pill bg-light" alt=""
                                        style="width:40px;height:40px;border-radius:6px;margin-right:8px;">
                                    ${product.product_name || ''}
                                </td>
                                <td>₹${formatNumber(price, 2)}</td>
                                <td><span class="fw-semibold">${formatNumber(sold)}</span></td>
                                <td><span class="fw-semibold">₹${formatNumber(revenue, 2)}</span></td>
                                <td>
                                    <span class="badge badge-sm bg-success-transparent text-success">Available</span>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3"><p class="mb-0">No products found</p></td></tr>';
                }
            }

            function formatNumber(num, decimals = 0) {
                return new Intl.NumberFormat('en-IN', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }).format(num);
            }

            function showToast(message, type = 'info') {
                // Simple toast notification
                const toast = document.createElement('div');
                toast.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show position-fixed`;
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                toast.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                document.body.appendChild(toast);

                // Auto remove after 3 seconds
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 3000);
            }
        });
    </script>

</body>

</html>
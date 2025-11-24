<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/init.php';

require_once __DIR__ . '/../../handlers/acess_guard.php';

$vendor_reg_id = $_SESSION['vendor_reg_id'];

// Read filters & pagination
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? strtolower(trim($_GET['status'])) : 'all';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, (int) $_GET['per_page']) : 10;

// Date range filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Sort filter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Search filter
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch orders and filter to only those that have at least one line item
// for this vendor with product_status matching the filter
$vendor_pending_orders = [];

// Build date filter condition
$dateCondition = '';
if (!empty($start_date) && !empty($end_date)) {
    $start_date_esc = mysqli_real_escape_string($con, $start_date);
    $end_date_esc = mysqli_real_escape_string($con, $end_date);
    $dateCondition = " AND DATE(created_date) BETWEEN '$start_date_esc' AND '$end_date_esc'";
}

// Build search condition
$searchCondition = '';
if (!empty($searchTerm)) {
    $searchTerm_esc = mysqli_real_escape_string($con, $searchTerm);
    $searchCondition = " AND (order_id LIKE '%$searchTerm_esc%' OR user_id LIKE '%$searchTerm_esc%')";
}

$orders_rs = mysqli_query($con, "SELECT * FROM `order` WHERE 1=1 $dateCondition $searchCondition ORDER BY id DESC");
if ($orders_rs) {
    while ($order = mysqli_fetch_assoc($orders_rs)) {
        $products_json = $order['products'] ?? '';
        $items = @json_decode($products_json, true);
        if (!is_array($items)) {
            continue;
        }

        // Filter items that belong to this vendor and match status
        $vendor_items = array_values(array_filter($items, function ($it) use ($vendor_reg_id, $statusFilter) {
            return isset($it['vendor_id'], $it['product_status'])
                && (string) $it['vendor_id'] === (string) $vendor_reg_id
                && ($statusFilter === 'all' || strtolower((string) $it['product_status']) === $statusFilter);
        }));
        if (empty($vendor_items)) {
            continue;
        }

        // Compute vendor amount and aggregate status for this vendor for this order
        $vendor_amount = 0;
        $vendor_quantity = 0;
        $flags = [
            'pending' => false,
            'confirmed' => false,
            'cancelled' => false,
            'shipped' => false,
            'out_for_delivery' => false,
            'delivered' => false
        ];
        $totalVendorItems = count($vendor_items);
        $cancelCount = 0;
        foreach ($vendor_items as $vi) {
            $qty = isset($vi['quantity']) ? (int) $vi['quantity'] : 0;
            $price = isset($vi['price']) ? (float) $vi['price'] : 0.0;
            $vendor_amount += ($qty * $price);
            $vendor_quantity += $qty;
            $st = strtolower((string) ($vi['product_status'] ?? ''));
            if (isset($flags[$st])) {
                $flags[$st] = true;
            }
            if ($st === 'cancelled') {
                $cancelCount++;
            }
        }

        // Determine current vendor status by priority
        $vendor_status = 'pending';
        if ($totalVendorItems > 0 && $cancelCount === $totalVendorItems) {
            $vendor_status = 'cancelled';
        } elseif ($flags['delivered']) {
            $vendor_status = 'delivered';
        } elseif ($flags['out_for_delivery']) {
            $vendor_status = 'out_for_delivery';
        } elseif ($flags['shipped']) {
            $vendor_status = 'shipped';
        } elseif ($flags['confirmed']) {
            $vendor_status = 'confirmed';
        }

        // Load last update date from order_track per (order_id, vendor_id)
        $vendor_last_update = '';
        $track_rs = mysqli_query($con, "SELECT track_json FROM `order_track` WHERE order_id='" . mysqli_real_escape_string($con, (string) $order['order_id']) . "' AND vendor_id='" . mysqli_real_escape_string($con, (string) $vendor_reg_id) . "' AND (product_id IS NULL OR product_id='') LIMIT 1");
        if ($track_rs && mysqli_num_rows($track_rs) > 0) {
            $trow = mysqli_fetch_assoc($track_rs);
            $tarr = @json_decode($trow['track_json'] ?? '[]', true);
            if (is_array($tarr)) {
                $map = [
                    'confirmed' => 'processing',
                    'shipped' => 'shipped',
                    'out_for_delivery' => 'out_for_delivery',
                    'delivered' => 'delivered',
                    'cancelled' => 'cancelled',
                ];
                $key = $map[$vendor_status] ?? '';
                if ($key !== '') {
                    foreach ($tarr as $ev) {
                        $st = strtolower((string) ($ev['status'] ?? ''));
                        if ($st === $key || ($key === 'out_for_delivery' && $st === 'out for delivery')) {
                            $vendor_last_update = (string) ($ev['date'] ?? '');
                        }
                    }
                }
            }
        }

        $order['__vendor_items'] = $vendor_items;
        $order['__vendor_amount'] = $vendor_amount;
        $order['__vendor_quantity'] = $vendor_quantity;
        $order['__vendor_status'] = $vendor_status;
        $order['__vendor_last_update'] = $vendor_last_update;
        $vendor_pending_orders[] = $order;
    }
}

// Export to Excel using current filters (no pagination limit)
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Build flat rows expanded per product for this vendor
    $rows = [];
    foreach ($vendor_pending_orders as $ord) {
        $base = [
            'order_id' => $ord['order_id'] ?? '',
            'cart_amount' => $ord['cart_amount'] ?? 0,
            'coupan_saving' => $ord['coupan_saving'] ?? 0,
            'total_amount' => $ord['total_amount'] ?? 0,
            'coin_earn' => $ord['coin_earn'] ?? 0,
            'payment_method' => $ord['payment_method'] ?? '',
            'payment_status' => $ord['payment_status'] ?? '',
            'order_status' => $ord['order_status'] ?? '',
            'created_date' => $ord['created_date'] ?? '',
            'vendor_id' => $vendor_reg_id,
        ];
        $items = $ord['__vendor_items'] ?? [];
        if (is_array($items) && !empty($items)) {
            foreach ($items as $it) {
                $qty = isset($it['quantity']) ? (float) $it['quantity'] : 0;
                $price = isset($it['price']) ? (float) $it['price'] : 0;
                $rows[] = array_merge($base, [
                    'product_id' => $it['product_id'] ?? '',
                    'quantity' => $qty,
                    'product_amount' => $qty * $price,
                    'product_status' => $it['product_status'] ?? '',
                ]);
            }
        } else {
            $rows[] = array_merge($base, [
                'product_id' => '',
                'quantity' => '',
                'product_amount' => '',
                'product_status' => '',
            ]);
        }
    }

    // Resolve vendor name once
    $vendorName = '';
    $vrs = mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id=" . (int) $vendor_reg_id . " LIMIT 1");
    if ($vrs && mysqli_num_rows($vrs) > 0) {
        $vr = mysqli_fetch_assoc($vrs);
        $vendorName = (string) ($vr['business_name'] ?? '');
    }

    // Try to load PhpSpreadsheet
    $autoloadPath = realpath(__DIR__ . '/../../../vendor/autoload.php');
    if ($autoloadPath && file_exists($autoloadPath)) {
        @include_once $autoloadPath;
    }

    if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Orders');

        $headers = [
            'S.No',
            'Order ID',
            'Order Amount',
            'Coupon Savings',
            'Amount Paid',
            'Coins Earned',
            'Payment Method',
            'Payment Status',
            'Order Status',
            'Order Date',
            'Order Time',
            'Vendor Name',
            'Product',
            'Quantity',
            'Product Amount',
            'Product Status'
        ];
        $colLetters = [];
        for ($i = 0; $i < count($headers); $i++) {
            $n = $i;
            $col = '';
            do {
                $col = chr($n % 26 + 65) . $col;
                $n = intdiv($n, 26) - 1;
            } while ($n >= 0);
            $colLetters[] = $col;
        }
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($colLetters[$i] . '1', $h);
        }

        // Helpers
        $fetch_product_name = function ($db, $productId) {
            if (!$productId)
                return '';
            $q = mysqli_query($db, "SELECT product_name FROM products WHERE id=" . (int) $productId . " LIMIT 1");
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                return (string) ($r['product_name'] ?? '');
            }
            return '';
        };
        $format_ddmmyyyy = function ($ts) {
            if (!$ts)
                return '';
            $t = strtotime($ts);
            if ($t === false)
                return '';
            return date('d/m/Y', $t); };
        $format_time = function ($ts) {
            if (!$ts)
                return '';
            $t = strtotime($ts);
            if ($t === false)
                return '';
            return date('h:i A', $t); };

        // Group by order_id and write with merged order-level cells
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['order_id']][] = $r;
        }
        $rowNum = 2;
        $serial = 1;
        foreach ($grouped as $orderId => $items) {
            $startRow = $rowNum;
            foreach ($items as $item) {
                if ($rowNum === $startRow) {
                    $sheet->setCellValue($colLetters[0] . $rowNum, $serial++);
                    $sheet->setCellValue($colLetters[1] . $rowNum, $orderId);
                    $sheet->setCellValue($colLetters[2] . $rowNum, $item['cart_amount']);
                    $sheet->setCellValue($colLetters[3] . $rowNum, $item['coupan_saving']);
                    $sheet->setCellValue($colLetters[4] . $rowNum, $item['total_amount']);
                    $sheet->setCellValue($colLetters[5] . $rowNum, $item['coin_earn']);
                    $sheet->setCellValue($colLetters[6] . $rowNum, strtoupper((string) $item['payment_method']));
                    $sheet->setCellValue($colLetters[7] . $rowNum, (string) $item['payment_status']);
                    $sheet->setCellValue($colLetters[8] . $rowNum, ucfirst((string) $item['order_status']));
                    $sheet->setCellValue($colLetters[9] . $rowNum, $format_ddmmyyyy($item['created_date']));
                    $sheet->setCellValue($colLetters[10] . $rowNum, $format_time($item['created_date']));
                    $sheet->setCellValue($colLetters[11] . $rowNum, $vendorName);
                }
                $productName = $fetch_product_name($con, $item['product_id'] ?? '');
                $sheet->setCellValue($colLetters[12] . $rowNum, $productName);
                $sheet->setCellValue($colLetters[13] . $rowNum, $item['quantity']);
                $sheet->setCellValue($colLetters[14] . $rowNum, $item['product_amount']);
                $sheet->setCellValue($colLetters[15] . $rowNum, $item['product_status']);
                $rowNum++;
            }
            $endRow = $rowNum - 1;
            if ($endRow > $startRow) {
                foreach (range(0, 11) as $ci) {
                    $sheet->mergeCells($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow);
                    $sheet->getStyle($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow)
                        ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }
            }
        }
        // Output
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }
        $fileName = 'orders_export_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // CSV fallback with the same headers
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
    $fileName = 'orders_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['S.No', 'Order ID', 'Order Amount', 'Coupon Savings', 'Amount Paid', 'Coins Earned', 'Payment Method', 'Payment Status', 'Order Status', 'Order Date', 'Order Time', 'Vendor Name', 'Product', 'Quantity', 'Product Amount', 'Product Status']);
    $serial = 1;
    $lastOrder = null;
    foreach ($rows as $r) {
        // fetch product name
        $pname = '';
        if (!empty($r['product_id'])) {
            $qp = mysqli_query($con, "SELECT product_name FROM products WHERE id=" . (int) $r['product_id'] . " LIMIT 1");
            if ($qp && mysqli_num_rows($qp) > 0) {
                $pr = mysqli_fetch_assoc($qp);
                $pname = (string) ($pr['product_name'] ?? '');
            }
        }
        $dateStr = $r['created_date'] ? date('d/m/Y', strtotime($r['created_date'])) : '';
        $timeStr = $r['created_date'] ? date('h:i A', strtotime($r['created_date'])) : '';
        $sn = ($lastOrder !== $r['order_id']) ? $serial++ : '';
        $lastOrder = $r['order_id'];
        fputcsv($out, [
            $sn,
            $r['order_id'],
            $r['cart_amount'],
            $r['coupan_saving'],
            $r['total_amount'],
            $r['coin_earn'],
            strtoupper((string) $r['payment_method']),
            $r['payment_status'],
            ucfirst((string) $r['order_status']),
            $dateStr,
            $timeStr,
            $vendorName,
            $pname,
            $r['quantity'],
            $r['product_amount'],
            $r['product_status']
        ]);
    }
    fclose($out);
    exit;
}

// Apply sorting
switch ($sortBy) {
    case 'oldest':
        usort($vendor_pending_orders, function ($a, $b) {
            return strtotime($a['created_date']) - strtotime($b['created_date']);
        });
        break;
    case 'amount_high':
        usort($vendor_pending_orders, function ($a, $b) {
            return $b['__vendor_amount'] - $a['__vendor_amount'];
        });
        break;
    case 'amount_low':
        usort($vendor_pending_orders, function ($a, $b) {
            return $a['__vendor_amount'] - $b['__vendor_amount'];
        });
        break;
    case 'latest':
    default:
        usort($vendor_pending_orders, function ($a, $b) {
            return strtotime($b['created_date']) - strtotime($a['created_date']);
        });
        break;
}

// Pagination over filtered orders
$totalOrders = count($vendor_pending_orders);
$totalPages = (int) ceil($totalOrders / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$vendor_orders_paged = array_slice($vendor_pending_orders, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>ORDER MANAGEMENT | HADIDY</title>
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

    <!-- Custom CSS for Address Truncation -->

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
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-3 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Order Management</h1>
                    <div>
                        <?php $exportUrl = 'order-management.php?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle1"><i
                                class="fa-solid fa-file-arrow-down me-1"></i> Export to Excel</a>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="input-group selecton-order1">
                                        <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i>
                                        </div> <input type="text" class="form-control flatpickr-input " id="daterange"
                                            placeholder="Date range picker" readonly="readonly"
                                            value="<?php echo !empty($start_date) && !empty($end_date) ? $start_date . ' to ' . $end_date : ''; ?>">
                                    </div>
                                    <div class="selecton-order">
                                        <select id="inputState" class="form-select form-select-lg ">
                                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All
                                            </option>
                                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                            <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div for="Default-sorting"><b>Sort By :</b></div>
                                    <select id="Default-sorting" class="form-select form-select-lg selecton-order">
                                        <option value="latest" <?php echo $sortBy === 'latest' ? 'selected' : ''; ?>>Sort by
                                            latest Order</option>
                                        <option value="oldest" <?php echo $sortBy === 'oldest' ? 'selected' : ''; ?>>Sort by
                                            oldest Order</option>
                                        <option value="amount_high" <?php echo $sortBy === 'amount_high' ? 'selected' : ''; ?>>
                                            Sort by Amount: high to low</option>
                                        <option value="amount_low" <?php echo $sortBy === 'amount_low' ? 'selected' : ''; ?>>
                                            Sort by Amount: low to high</option>
                                    </select>
                                    <div class="selecton-order position-relative">
                                        <input type="search" id="searchInput" class="form-control"
                                            placeholder="Search by Order ID or User ID" aria-describedby="button-addon2"
                                            value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <?php if (!empty($searchTerm)): ?>
                                            <button type="button" id="clearSearch" class="btn btn-sm position-absolute"
                                                style="right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d;">
                                                <i class="fa fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table text-nowrap table-striped table-order-heading">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">Order ID</th>
                                            <th scope="col">Order Date</th>
                                            <th scope="col">Order Items</th>
                                            <th scope="col">Order Amount</th>
                                            <th scope="col">Delivery address</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Last Update</th>
                                            <!-- <th scope="col">Tracking ID</th> -->
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php if (!empty($vendor_orders_paged)): ?>
                                            <?php foreach ($vendor_orders_paged as $ord): ?>
                                                <?php
                                                $orderId = isset($ord['order_id']) ? (int) $ord['order_id'] : 0;
                                                $createdDate = $ord['created_date'] ?? '';
                                                $orderStatus = $ord['order_status'] ?? 'Pending';
                                                $orderTrack = $ord['order_track'] ?? '';
                                                $amount = number_format((float) $ord['__vendor_amount'], 2);
                                                $itemsCount = (int) $ord['__vendor_quantity'];
                                                // Try to fetch address if available
                                                $addressText = '';
                                                // Build first item preview (image + name)
                                                $firstTitle = $itemsCount . ' Item(s)';
                                                $firstImage = PUBLIC_ASSETS . 'images/vendor/Comfortable.png';
                                                $firstQty = 0;
                                                if (!empty($ord['__vendor_items'][0]['product_id'])) {
                                                    $pid = (int) $ord['__vendor_items'][0]['product_id'];
                                                    $firstQty = (int) ($ord['__vendor_items'][0]['quantity'] ?? 0);
                                                    $p_rs = mysqli_query($con, "SELECT product_name, images FROM products WHERE id='" . mysqli_real_escape_string($con, (string) $pid) . "' LIMIT 1");
                                                    if ($p_rs && mysqli_num_rows($p_rs) > 0) {
                                                        $p = mysqli_fetch_assoc($p_rs);
                                                        if (!empty($p['product_name'])) {
                                                            $firstTitle = $p['product_name'];
                                                        }
                                                        if (!empty($p['images'])) {
                                                            $imgs = @json_decode($p['images'], true);
                                                            if (is_array($imgs) && !empty($imgs[0])) {
                                                                $firstImage = './' . ltrim($imgs[0], './');
                                                            }
                                                        }
                                                    }
                                                }
                                                if (!empty($ord['address_id'])) {
                                                    $aid = mysqli_real_escape_string($con, $ord['address_id']);
                                                    $addr_rs = mysqli_query($con, "SELECT * FROM user_address WHERE id='" . $aid . "' LIMIT 1");
                                                    if ($addr_rs && mysqli_num_rows($addr_rs) > 0) {
                                                        $addr = mysqli_fetch_assoc($addr_rs);
                                                        // Resolve state name from state_id if possible
                                                        $stateName = '';
                                                        if (!empty($addr['state_id'])) {
                                                            $sid = mysqli_real_escape_string($con, (string) $addr['state_id']);
                                                            $state_q = mysqli_query($con, "SELECT name FROM state WHERE id='" . $sid . "' LIMIT 1");
                                                            if ($state_q && mysqli_num_rows($state_q) > 0) {
                                                                $state_row = mysqli_fetch_assoc($state_q);
                                                                $stateName = $state_row['name'] ?? '';
                                                            }
                                                        }
                                                        // Build address from user_address schema
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
                                                        $addressText = trim(implode(', ', $parts));
                                                        // Append primary/contact info if available
                                                        $extras = [];
                                                        if (!empty($addr['mobile_number'])) {
                                                            $extras[] = 'Mob: ' . $addr['mobile_number'];
                                                        }
                                                        if (!empty($addr['email_address'])) {
                                                            $extras[] = 'Email: ' . $addr['email_address'];
                                                        }
                                                        if (!empty($extras)) {
                                                            $addressText .= ' | ' . implode(' | ', $extras);
                                                        }
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td class="rqu-id">#<?php echo htmlspecialchars($orderId); ?></td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            $rawTime = $createdDate ?: ($ord['date'] ?? '');
                                                            if (!empty($rawTime)) {
                                                                // Create a DateTime object assuming server stored UTC (or unknown)
                                                                $dt = new DateTime($rawTime, new DateTimeZone('UTC')); 
                                                                // Convert to India timezone
                                                                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                echo $dt->format('d M Y, h:i A');
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="Comfortable-img">
                                                                <img src="<?php if (!empty($firstImage)) {
                                                                    echo USER_BASEURL . 'public/' . htmlspecialchars($firstImage);
                                                                } else {
                                                                    echo  USER_BASEURL . 'public/images/vendor/default.png';
                                                                } ?>"
                                                                    alt=""
                                                                    style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                                            </div>
                                                            <div class="confortable-text">
                                                                <h4><?php echo htmlspecialchars($firstTitle); ?><?php if ($itemsCount > 1): ?>
                                                                        <span class="text-sky-blue">+<?php echo $itemsCount - 1; ?>
                                                                            Item</span><?php endif; ?></h4>
                                                                <p> Item X <span class="text-muted"><?php
                                                                $total_item_no = $itemsCount;
                                                                echo $total_item_no; ?></span></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo htmlspecialchars($amount); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-Delivery-address">
                                                            <?php echo htmlspecialchars($addressText); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $vs = strtolower((string) ($ord['__vendor_status'] ?? 'pending'));
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
                                                        <div class="order-date-tr">
                                                            <?php
                                                            if (!empty($ord['updated_date'])) {
                                                                try {
                                                                    // Create DateTime from stored value (assuming DB is in UTC or server default)
                                                                    $dt = new DateTime($ord['updated_date'], new DateTimeZone('UTC'));
                                                                    // Convert to India time zone
                                                                    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                    echo $dt->format('d M Y, h:i A');
                                                                } catch (Exception $e) {
                                                                    echo htmlspecialchars($ord['updated_date']); // fallback
                                                                }
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>

                                                    <!-- <td>
                                                <div class="order-date-tr">
                                                    <?php //echo htmlspecialchars($orderTrack); ?>
                                                </div>
                                            </td> -->
                                                    <td>
                                                        <a href="./order-details.php?id=<?php echo urlencode($orderId); ?>"
                                                            class="btn btn-view">VIEW</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="12" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fa-solid fa-box-open fa-3x mb-3"></i>
                                                        <h5>No orders found for you.</h5>
                                                        <p>Start by adding your first order or adjust your filters.</p>

                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <div class=" my-4">
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center flex-wrap">
                                            <?php
                                            $base = strtok($_SERVER['REQUEST_URI'], '?');
                                            $qs = $_GET;
                                            unset($qs['page']);
                                            $makeUrl = function ($p) use ($base, $qs) {
                                                $qs['page'] = $p;
                                                return htmlspecialchars($base . '?' . http_build_query($qs));
                                            };
                                            ?>
                                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                <a class="page-link" href="<?php echo $page <= 1 ? '#' : $makeUrl($page - 1); ?>"><i
                                                        class="bx bx-chevron-left"></i> Previous</a>
                                            </li>
                                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="<?php echo $makeUrl($p); ?>"><?php echo $p; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                <a class="page-link"
                                                    href="<?php echo $page >= $totalPages ? '#' : $makeUrl($page + 1); ?>">Next
                                                    <span aria-hidden="true"><i class="bx bx-chevron-right"></i></span></a>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
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
                        Designed with <span class="bi bi-heart-fill text-danger"></span> by <a
                            href="javascript:void(0);">
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

        <!-- JavaScript for Filters and Address Truncation -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Address truncation functionality
                const addressCells = document.querySelectorAll('.order-Delivery-address');
                addressCells.forEach(cell => {
                    const fullText = cell.textContent.trim();
                    const words = fullText.split(/\s+/);
                    if (words.length > 30) {
                        const truncatedText = words.slice(0, 30).join(' ') + '...';
                        cell.textContent = truncatedText;
                        cell.setAttribute('data-full-text', fullText);
                        cell.classList.add('truncated');
                        cell.addEventListener('click', function () {
                            if (this.classList.contains('truncated')) {
                                this.textContent = this.getAttribute('data-full-text');
                                this.classList.remove('truncated');
                            } else {
                                this.textContent = truncatedText;
                                this.classList.add('truncated');
                            }
                        });
                    }
                });


                // Search functionality with debouncing and AJAX
                let searchTimeout;
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            loadOrdersWithFilters();
                        }, 500); // 500ms debounce
                    });
                }

                // Clear search functionality
                const clearSearchBtn = document.getElementById('clearSearch');
                if (clearSearchBtn) {
                    clearSearchBtn.addEventListener('click', function () {
                        searchInput.value = '';
                        loadOrdersWithFilters();
                    });
                }

                // Date range picker functionality
                flatpickr("#daterange", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    onChange: function (selectedDates, dateStr, instance) {
                        if (selectedDates.length === 2) {
                            loadOrdersWithFilters();
                        }
                    }
                });

                // Sort dropdown functionality
                document.getElementById('Default-sorting')?.addEventListener('change', function () {
                    loadOrdersWithFilters();
                });

                // Status filter functionality
                document.getElementById('inputState')?.addEventListener('change', function () {
                    loadOrdersWithFilters();
                });


                // Loading indicator functions
                function showLoadingIndicator() {
                    const table = document.querySelector('.table-responsive');
                    if (table) {
                        table.style.opacity = '0.5';
                        table.style.pointerEvents = 'none';
                    }
                }

                function hideLoadingIndicator() {
                    const table = document.querySelector('.table-responsive');
                    if (table) {
                        table.style.opacity = '1';
                        table.style.pointerEvents = 'auto';
                    }
                }

                // Re-initialize address truncation for dynamically loaded content
                function initializeAddressTruncation() {
                    const addressCells = document.querySelectorAll('.order-Delivery-address');
                    addressCells.forEach(cell => {
                        const fullText = cell.textContent.trim();
                        const words = fullText.split(/\s+/);
                        if (words.length > 30) {
                            const truncatedText = words.slice(0, 30).join(' ') + '...';
                            cell.textContent = truncatedText;
                            cell.setAttribute('data-full-text', fullText);
                            cell.classList.add('truncated');
                            cell.addEventListener('click', function () {
                                if (this.classList.contains('truncated')) {
                                    this.textContent = this.getAttribute('data-full-text');
                                    this.classList.remove('truncated');
                                } else {
                                    this.textContent = truncatedText;
                                    this.classList.add('truncated');
                                }
                            });
                        }
                    });
                }

                // Handle pagination clicks with AJAX
                document.addEventListener('click', function (e) {
                    if (e.target.closest('.pagination a')) {
                        e.preventDefault();
                        const href = e.target.closest('a').getAttribute('href');
                        if (href) {
                            const url = new URL(href, window.location.origin);
                            const page = url.searchParams.get('page');
                            if (page) {
                                loadOrdersWithFilters(page);
                            }
                        }
                    }
                });

                // Modified AJAX function to handle pagination
                function loadOrdersWithFilters(page = 1) {
                    const params = new URLSearchParams();

                    // Get current filter values
                    const statusFilter = document.getElementById('inputState')?.value || 'all';
                    const sortBy = document.getElementById('Default-sorting')?.value || 'latest';
                    const searchTerm = document.getElementById('searchInput')?.value || '';

                    // Get date range
                    const dateRange = document.getElementById('daterange')?.value || '';
                    let startDate = '', endDate = '';
                    if (dateRange.includes(' to ')) {
                        [startDate, endDate] = dateRange.split(' to ');
                    }

                    // Build parameters
                    if (statusFilter && statusFilter !== 'all') params.set('status', statusFilter);
                    if (sortBy && sortBy !== 'latest') params.set('sort', sortBy);
                    if (searchTerm) params.set('search', searchTerm);
                    if (startDate) params.set('start_date', startDate);
                    if (endDate) params.set('end_date', endDate);
                    params.set('page', page);

                    // Show loading indicator
                    showLoadingIndicator();

                    // Make AJAX request
                    fetch('?' + params.toString())
                        .then(response => response.text())
                        .then(html => {
                            // Extract only the table content
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newTable = doc.querySelector('.table-responsive');
                            const newPagination = doc.querySelector('.pagination');

                            // Update table content
                            const currentTable = document.querySelector('.table-responsive');
                            if (currentTable && newTable) {
                                currentTable.innerHTML = newTable.innerHTML;
                            }

                            // Update pagination
                            const currentPagination = document.querySelector('.pagination');
                            if (newPagination) {
                                if (currentPagination) {
                                    currentPagination.parentElement.innerHTML = newPagination.parentElement.innerHTML;
                                }
                            } else {
                                if (currentPagination) {
                                    currentPagination.parentElement.innerHTML = '';
                                }
                            }

                            // Update URL without page refresh
                            const newUrl = window.location.pathname + '?' + params.toString();
                            window.history.pushState({}, '', newUrl);

                            // Re-initialize address truncation for new content
                            initializeAddressTruncation();

                            hideLoadingIndicator();
                        })
                        .catch(error => {
                            console.error('Error loading orders:', error);
                            hideLoadingIndicator();
                        });
                }
            });
        </script>
</body>

</html>
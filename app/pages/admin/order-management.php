<?php
include __DIR__ . '/../includes/init.php';  
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}


// Export to Excel handler (applies current filters, ignores pagination)
if (isset($_POST['export_excel']) && $_POST['export_excel'] === '1') {
    // Collect filters
    $whereConditions = [];
    $params = [];
    $paramTypes = '';

    if (isset($_POST['date_from']) && !empty($_POST['date_from'])) {
        $whereConditions[] = "DATE(o.created_date) >= ?";
        $params[] = $_POST['date_from'];
        $paramTypes .= 's';
    }
    if (isset($_POST['date_to']) && !empty($_POST['date_to'])) {
        $whereConditions[] = "DATE(o.created_date) <= ?";
        $params[] = $_POST['date_to'];
        $paramTypes .= 's';
    }
    if (isset($_POST['status']) && !empty($_POST['status'])) {
        $whereConditions[] = "o.order_status = ?";
        $params[] = $_POST['status'];
        $paramTypes .= 's';
    }
    if (isset($_POST['search']) && !empty($_POST['search'])) {
        $searchTerm = $_POST['search'];
        $whereConditions[] = "(o.order_id LIKE ? OR v.business_name LIKE ? OR ua.street_address LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.product_name LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $paramTypes .= 'ssssss';
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $orderBy = "ORDER BY o.created_date DESC";
    if (isset($_POST['sort_by']) && !empty($_POST['sort_by'])) {
        switch ($_POST['sort_by']) {
            case 'latest':
                $orderBy = "ORDER BY o.created_date DESC";
                break;
            case 'oldest':
                $orderBy = "ORDER BY o.created_date ASC";
                break;
            case 'amount_high':
                $orderBy = "ORDER BY o.total_amount DESC";
                break;
            case 'amount_low':
                $orderBy = "ORDER BY o.total_amount ASC";
                break;
            default:
                $orderBy = "ORDER BY o.created_date DESC";
                break;
        }
    }

    $exportQuery = "
        SELECT 
            o.id,
            o.user_id,
            o.products,
            o.order_id,
            o.cart_amount,
            o.shipping_charge,
            o.coupan_saving,
            o.total_amount,
            o.coin_earn,
            o.payment_method,
            o.payment_status,
            o.address_id,
            o.order_status,
            o.order_track,
            o.created_date,
            o.updated_date
        FROM `order` o
        LEFT JOIN vendor_registration v ON JSON_EXTRACT(o.products, '$[0].vendor_id') = v.id
        LEFT JOIN user_address ua ON o.address_id = ua.id
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN products p ON JSON_EXTRACT(o.products, '$[0].product_id') = p.id
        {$whereClause}
        {$orderBy}
    ";

    $stmt = mysqli_prepare($con, $exportQuery);
    if ($stmt && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
    }
    $exec = $stmt ? mysqli_stmt_execute($stmt) : mysqli_query($con, $exportQuery);
    $result = $stmt ? mysqli_stmt_get_result($stmt) : $exec;

    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products = !empty($row['products']) ? json_decode($row['products'], true) : [];
            if (is_array($products) && count($products) > 0) {
                foreach ($products as $p) {
                    $qty = isset($p['quantity']) ? (float) $p['quantity'] : 0;
                    $price = isset($p['price']) ? (float) $p['price'] : 0;
                    $rows[] = array_merge($row, [
                        'product_id' => $p['product_id'] ?? '',
                        'quantity' => $qty,
                        'price' => $price,
                        'product_amount' => $qty * $price,
                        'product_status' => $p['product_status'] ?? '',
                        'vendor_id' => $p['vendor_id'] ?? ''
                    ]);
                }
            } else {
                $rows[] = array_merge($row, [
                    'product_id' => '',
                    'quantity' => '',
                    'price' => '',
                    'product_amount' => '',
                    'product_status' => '',
                    'vendor_id' => ''
                ]);
            }
        }
    }

    // Attempt to load PhpSpreadsheet (admin context)
    $canSpreadsheet = false;
    $autoloadCandidates = [
        __DIR__ . '/../../../vendor/autoload.php'
    ];
    foreach ($autoloadCandidates as $autopath) {
        if (file_exists($autopath)) {
            @include_once $autopath;
            if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                $canSpreadsheet = true;
                break;
            }
        }
    }

    if ($canSpreadsheet) {
        // Use fully qualified names to avoid top-level use statements inside this file
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

        // Helper functions
        $fetch_product_name = function ($db, $productId) {
            if (!$productId)
                return '';
            $q = mysqli_query($db, "SELECT product_name FROM products WHERE id=" . (int) $productId);
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                return $r['product_name'] ?? '';
            }
            return '';
        };
        $fetch_vendor_name = function ($db, $vendorId) {
            if (!$vendorId)
                return '';
            $q = mysqli_query($db, "SELECT business_name FROM vendor_registration WHERE id=" . (int) $vendorId);
            if ($q && mysqli_num_rows($q) > 0) {
                $r = mysqli_fetch_assoc($q);
                return $r['business_name'] ?? '';
            }
            return '';
        };
        $format_ddmmyyyy = function ($ts) {
            if (!$ts)
                return '';
            $t = strtotime($ts);
            if ($t === false)
                return '';
            return date('d/m/Y', $t);
        };
        $format_time = function ($ts) {
            if (!$ts)
                return '';
            $t = strtotime($ts);
            if ($t === false)
                return '';
            return date('h:i A', $t);
        };

        // Group by order_id to merge order-level cells
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['order_id']][] = $r;
        }

        $rowNum = 2;
        $serial = 1;
        foreach ($grouped as $orderId => $items) {
            $startRow = $rowNum;
            $vendorName = $fetch_vendor_name($con, $items[0]['vendor_id'] ?? '');
            foreach ($items as $item) {
                $productName = $fetch_product_name($con, $item['product_id']);
                if ($rowNum === $startRow) {
                    $sheet->setCellValue($colLetters[0] . $rowNum, $serial++);
                    $sheet->setCellValue($colLetters[1] . $rowNum, $orderId);
                    $sheet->setCellValue($colLetters[2] . $rowNum, $item['cart_amount']);
                    $sheet->setCellValue($colLetters[3] . $rowNum, $item['coupan_saving']);
                    $sheet->setCellValue($colLetters[4] . $rowNum, $item['total_amount']);
                    $sheet->setCellValue($colLetters[5] . $rowNum, $item['coin_earn']);
                    $sheet->setCellValue($colLetters[6] . $rowNum, strtoupper($item['payment_method']));
                    $sheet->setCellValue($colLetters[7] . $rowNum, $item['payment_status']);
                    $sheet->setCellValue($colLetters[8] . $rowNum, ucfirst($item['order_status']));
                    $sheet->setCellValue($colLetters[9] . $rowNum, $format_ddmmyyyy($item['created_date']));
                    $sheet->setCellValue($colLetters[10] . $rowNum, $format_time($item['created_date']));
                    $sheet->setCellValue($colLetters[11] . $rowNum, $vendorName);
                }
                $sheet->setCellValue($colLetters[12] . $rowNum, $productName);
                $sheet->setCellValue($colLetters[13] . $rowNum, $item['quantity']);
                $sheet->setCellValue($colLetters[14] . $rowNum, $item['product_amount']);
                $sheet->setCellValue($colLetters[15] . $rowNum, $item['product_status']);
                $rowNum++;
            }
            $endRow = $rowNum - 1;
            if ($endRow > $startRow) {
                $orderColsToMerge = range(0, 11);
                foreach ($orderColsToMerge as $ci) {
                    $sheet->mergeCells($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow);
                    $sheet->getStyle($colLetters[$ci] . $startRow . ':' . $colLetters[$ci] . $endRow)
                        ->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                }
            }
        }

        // Clean any previous output buffers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        $filename = 'orders_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // Fallback: CSV export
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
    $filename = 'orders_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
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
    ]);
    $serial = 1;
    $currentOrder = null;
    $orderFirstRow = [];
    foreach ($rows as $r) {
        if ($currentOrder !== $r['order_id']) {
            $currentOrder = $r['order_id'];
            $orderFirstRow = [
                $serial++,
                $r['order_id'],
                $r['cart_amount'],
                $r['coupan_saving'],
                $r['total_amount'],
                $r['coin_earn'],
                strtoupper($r['payment_method']),
                $r['payment_status'],
                ucfirst($r['order_status']),
                date('d/m/Y', strtotime($r['created_date'])),
                date('h:i A', strtotime($r['created_date'])),
                '' // vendor name omitted in CSV fallback to keep simple
            ];
        }
        $row = array_merge($orderFirstRow, [
            '', // Product will align into next column after vendor in header arrangement
        ]);
        // Overwrite vendor with empty and then append product columns
        $productName = '';
        if (!empty($r['product_id'])) {
            $qpn = mysqli_query($con, "SELECT product_name FROM products WHERE id=" . (int) $r['product_id']);
            if ($qpn && mysqli_num_rows($qpn) > 0) {
                $rr = mysqli_fetch_assoc($qpn);
                $productName = $rr['product_name'] ?? '';
            }
        }
        // Emit final CSV line
        fputcsv($out, [
            $orderFirstRow[0],
            $orderFirstRow[1],
            $orderFirstRow[2],
            $orderFirstRow[3],
            $orderFirstRow[4],
            $orderFirstRow[5],
            $orderFirstRow[6],
            $orderFirstRow[7],
            $orderFirstRow[8],
            $orderFirstRow[9],
            $orderFirstRow[10],
            $orderFirstRow[11],
            $productName,
            $r['quantity'],
            $r['product_amount'],
            $r['product_status']
        ]);
    }
    fclose($out);
    exit;
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
    <title>ORDER MANAGEMENT | HADIDY</title>
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

    <!-- Custom CSS for Address Truncation -->

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


        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div
                    class="d-flex  align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Order Management</h1>
                    <div>
                        <button type="button" id="exportToExcel" class="btn-down-excle1 w-100"><i
                                class="fa-solid fa-file-arrow-down"></i>
                            Export to Excel</button>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <form method="POST" id="filterForm">
                                <div class="card-header justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="input-group selecton-order1">
                                            <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i>
                                            </div>
                                            <input type="text" class="form-control flatpickr-input" id="daterange"
                                                placeholder="Date range picker" readonly="readonly" name="date_range"
                                                value="<?php
                                                if (isset($_POST['date_from']) && isset($_POST['date_to']) && !empty($_POST['date_from']) && !empty($_POST['date_to'])) {
                                                    echo htmlspecialchars($_POST['date_from'] . ' to ' . $_POST['date_to']);
                                                } elseif (isset($_POST['date_from']) && !empty($_POST['date_from'])) {
                                                    echo htmlspecialchars($_POST['date_from']);
                                                }
                                                ?>">
                                            <button type="button" class="btn btn-outline-secondary" id="clearDateRange"
                                                title="Clear Date Range">
                                                <i class="ri-close-line"></i>
                                            </button>
                                            <input type="hidden" name="date_from" id="date_from"
                                                value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>">
                                            <input type="hidden" name="date_to" id="date_to"
                                                value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>">
                                        </div>
                                        <div class="selecton-order">
                                            <select id="inputState" class="form-select form-select-lg" name="status">
                                                <option value="" <?php echo (!isset($_POST['status']) || $_POST['status'] == '') ? 'selected' : ''; ?>>Select Status</option>
                                                <option value="Pending" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Processing" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Processing') ? 'selected' : ''; ?>>Processing
                                                </option>
                                                <option value="Shipped" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Shipped') ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="Delivered" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Delivered') ? 'selected' : ''; ?>>Delivered
                                                </option>
                                                <option value="Cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div for="Default-sorting"><b>Sort By :</b></div>
                                        <select id="Default-sorting" class="form-select form-select-lg selecton-order"
                                            name="sort_by">
                                            <option value="" <?php echo (!isset($_POST['sort_by']) || $_POST['sort_by'] == '') ? 'selected' : ''; ?>>Default sorting</option>
                                            <option value="latest" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'latest') ? 'selected' : ''; ?>>Sort by latest Order
                                            </option>
                                            <option value="oldest" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'oldest') ? 'selected' : ''; ?>>Sort by oldest Order
                                            </option>
                                            <option value="amount_high" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'amount_high') ? 'selected' : ''; ?>>Sort by Amount:
                                                high to low</option>
                                            <option value="amount_low" <?php echo (isset($_POST['sort_by']) && $_POST['sort_by'] == 'amount_low') ? 'selected' : ''; ?>>Sort by Amount:
                                                low to high</option>
                                        </select>
                                        <div class="selecton-order">
                                            <input type="search" class="form-control" placeholder="Search" name="search"
                                                value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>"
                                                aria-describedby="button-addon2">
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <form method="POST" id="exportForm" style="display:none;">
                                <input type="hidden" name="export_excel" value="1">
                                <input type="hidden" name="date_from" id="exp_date_from"
                                    value="<?php echo isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from']) : ''; ?>">
                                <input type="hidden" name="date_to" id="exp_date_to"
                                    value="<?php echo isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to']) : ''; ?>">
                                <input type="hidden" name="status" id="exp_status"
                                    value="<?php echo isset($_POST['status']) ? htmlspecialchars($_POST['status']) : ''; ?>">
                                <input type="hidden" name="sort_by" id="exp_sort_by"
                                    value="<?php echo isset($_POST['sort_by']) ? htmlspecialchars($_POST['sort_by']) : ''; ?>">
                                <input type="hidden" name="search" id="exp_search"
                                    value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                            </form>
                            <div class="table-responsive">
                                <table class="table text-nowrap table-striped">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">Order ID</th>
                                            <th scope="col">Seller ID</th>
                                            <th scope="col">Seller Name</th>
                                            <th scope="col">Order Date</th>
                                            <th scope="col">Order Items</th>
                                            <th scope="col">Order Amount</th>
                                            <th scope="col">Customer Name</th>
                                            <th scope="col">Delivery address</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Last Update</th>
                                            <th scope="col">Tracking ID</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        // Pagination settings
                                        $recordsPerPage = 10;
                                        $currentPage = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
                                        $offset = ($currentPage - 1) * $recordsPerPage;

                                        // Build WHERE clause for filtering
                                        $whereConditions = [];
                                        $params = [];
                                        $paramTypes = '';

                                        // Date range filter
                                        if (isset($_POST['date_from']) && !empty($_POST['date_from'])) {
                                            $whereConditions[] = "DATE(o.created_date) >= ?";
                                            $params[] = $_POST['date_from'];
                                            $paramTypes .= 's';
                                        }

                                        if (isset($_POST['date_to']) && !empty($_POST['date_to'])) {
                                            $whereConditions[] = "DATE(o.created_date) <= ?";
                                            $params[] = $_POST['date_to'];
                                            $paramTypes .= 's';
                                        }

                                        // Status filter
                                        if (isset($_POST['status']) && !empty($_POST['status'])) {
                                            $whereConditions[] = "o.order_status = ?";
                                            $params[] = $_POST['status'];
                                            $paramTypes .= 's';
                                        }

                                        // Search filter
                                        if (isset($_POST['search']) && !empty($_POST['search'])) {
                                            $searchTerm = $_POST['search'];
                                            $whereConditions[] = "(o.order_id LIKE ? OR v.business_name LIKE ? OR ua.street_address LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.product_name LIKE ?)";
                                            $params[] = "%{$searchTerm}%";
                                            $params[] = "%{$searchTerm}%";
                                            $params[] = "%{$searchTerm}%";
                                            $params[] = "%{$searchTerm}%";
                                            $params[] = "%{$searchTerm}%";
                                            $params[] = "%{$searchTerm}%";
                                            $paramTypes .= 'ssssss';
                                        }

                                        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

                                        // Build ORDER BY clause for sorting
                                        $orderBy = "ORDER BY o.created_date DESC"; // Default sorting
                                        if (isset($_POST['sort_by']) && !empty($_POST['sort_by'])) {
                                            switch ($_POST['sort_by']) {
                                                case 'latest':
                                                    $orderBy = "ORDER BY o.created_date DESC";
                                                    break;
                                                case 'oldest':
                                                    $orderBy = "ORDER BY o.created_date ASC";
                                                    break;
                                                case 'amount_high':
                                                    $orderBy = "ORDER BY o.total_amount DESC";
                                                    break;
                                                case 'amount_low':
                                                    $orderBy = "ORDER BY o.total_amount ASC";
                                                    break;
                                                default:
                                                    $orderBy = "ORDER BY o.created_date DESC";
                                                    break;
                                            }
                                        }

                                        // Main query to get orders with vendor, customer, and address information
                                        $mainQuery = "
    SELECT 
        o.id,
        o.order_id,
        o.user_id,
        o.products,
        o.cart_amount,
        o.shipping_charge,
        o.coupan_saving,
        o.total_amount,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.order_track,
        o.created_date,
        o.updated_date,
        v.id as vendor_id,
        v.business_name,
        v.vendor_id as vendor_public_id,
        ua.street_address,
        ua.city,
        ua.pin_code,
        ua.mobile_number as customer_mobile,
        ua.email_address as customer_email,
        u.first_name as customer_first_name,
        u.last_name as customer_last_name,
        p.product_name,
        p.images as product_images
    FROM `order` o
    LEFT JOIN vendor_registration v ON JSON_EXTRACT(o.products, '$[0].vendor_id') = v.id
    LEFT JOIN user_address ua ON o.address_id = ua.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products p ON JSON_EXTRACT(o.products, '$[0].product_id') = p.id
    {$whereClause}
    {$orderBy}
    LIMIT {$offset}, {$recordsPerPage}
";

                                        // Execute main query
                                        $stmt = mysqli_prepare($con, $mainQuery);
                                        if ($stmt && !empty($params)) {
                                            mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
                                        }
                                        $result = $stmt ? mysqli_stmt_execute($stmt) : mysqli_query($con, $mainQuery);
                                        $orders = $stmt ? mysqli_stmt_get_result($stmt) : $result;

                                        // Check for query errors
                                        if (!$orders) {
                                            echo '<tr><td colspan="12" class="text-center text-danger">Database error: ' . mysqli_error($con) . '</td></tr>';
                                            $orders = false;
                                        }

                                        // Count total records for pagination
                                        $countQuery = "
    SELECT COUNT(*) as total
    FROM `order` o
    LEFT JOIN vendor_registration v ON JSON_EXTRACT(o.products, '$[0].vendor_id') = v.id
    LEFT JOIN user_address ua ON o.address_id = ua.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN products p ON JSON_EXTRACT(o.products, '$[0].product_id') = p.id
    {$whereClause}
";

                                        $countStmt = mysqli_prepare($con, $countQuery);
                                        if ($countStmt && !empty($params)) {
                                            mysqli_stmt_bind_param($countStmt, $paramTypes, ...$params);
                                        }
                                        $countResult = $countStmt ? mysqli_stmt_execute($countStmt) : mysqli_query($con, $countQuery);
                                        $totalRecords = $countStmt ? mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['total'] : mysqli_fetch_assoc($countResult)['total'];
                                        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $recordsPerPage) : 1;

                                        $rowNumber = $offset + 1;

                                        if ($orders && mysqli_num_rows($orders) > 0) {
                                            while ($order = mysqli_fetch_assoc($orders)) {
                                                // Parse order products
                                                $productsData = !empty($order['products']) ? json_decode($order['products'], true) : [];
                                                $totalItems = 0;
                                                $firstProduct = null;
                                                $firstProductImage = $vendor_baseurl . 'uploads/vendors/no-product.png'; // Default image
                                                $firstProductName = 'Product Details';

                                                if (is_array($productsData) && !empty($productsData)) {
                                                    $totalItems = count($productsData);
                                                    $firstProduct = $productsData[0];

                                                    // Get product name and image from the main query result
                                                    if (!empty($order['product_name'])) {
                                                        $firstProductName = $order['product_name'];
                                                    } elseif (isset($firstProduct['product_name'])) {
                                                        $firstProductName = $firstProduct['product_name'];
                                                    }

                                                    // Get product image from the main query result
                                                    if (!empty($order['product_images'])) {
                                                        $productImages = !empty($order['product_images']) ? json_decode($order['product_images'], true) : [];
                                                        if (!empty($productImages) && isset($productImages[0])) {
                                                            $firstProductImage = $productImages[0];
                                                        }
                                                    } elseif (isset($firstProduct['product_id'])) {
                                                        // Fallback: get image from separate query if not in main result
                                                        $productQuery = "SELECT images FROM products WHERE id = ?";
                                                        $productStmt = mysqli_prepare($con, $productQuery);
                                                        mysqli_stmt_bind_param($productStmt, 'i', $firstProduct['product_id']);
                                                        mysqli_stmt_execute($productStmt);
                                                        $productResult = mysqli_stmt_get_result($productStmt);

                                                        if ($productRow = mysqli_fetch_assoc($productResult)) {
                                                            $productImages = !empty($productRow['images']) ? json_decode($productRow['images'], true) : [];
                                                            if (!empty($productImages) && isset($productImages[0])) {
                                                                $firstProductImage = $productImages[0];
                                                            }
                                                        }
                                                        mysqli_stmt_close($productStmt);
                                                    }
                                                }

                                                // Parse order tracking
                                                $orderTrack = !empty($order['order_track']) ? json_decode($order['order_track'], true) : [];
                                                $currentStatus = $order['order_status'];
                                                $lastUpdate = $order['updated_date'] ? $order['updated_date'] : $order['created_date'];

                                                // Determine status badge
                                                $statusBadge = '';
                                                switch ($currentStatus) {
                                                    case 'Pending':
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-warning">Pending</span>';
                                                        break;
                                                    case 'Processing':
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-info">Processing</span>';
                                                        break;
                                                    case 'Shipped':
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-success">Shipped</span>';
                                                        break;
                                                    case 'Delivered':
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-dlivered">Delivered</span>';
                                                        break;
                                                    case 'Cancelled':
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-danger">Cancelled</span>';
                                                        break;
                                                    default:
                                                        $statusBadge = '<span class="badge rounded-pill bg-outline-secondary">' . $currentStatus . '</span>';
                                                        break;
                                                }

                                                // Format dates
                                                $orderDate = date('d F, Y', strtotime($order['created_date']));
                                                $lastUpdateDate = date('d F, Y', strtotime($lastUpdate));

                                                // Format amounts
                                                $totalAmount = '₹' . number_format($order['total_amount'], 2);

                                                // Format delivery address
                                                $deliveryAddress = '';
                                                if ($order['street_address']) {
                                                    $deliveryAddress = $order['street_address'];
                                                    if ($order['city']) {
                                                        $deliveryAddress .= ', ' . $order['city'];
                                                    }
                                                    if ($order['pin_code']) {
                                                        $deliveryAddress .= ', ' . $order['pin_code'];
                                                    }
                                                }

                                                // Truncate address if too long
                                                if (strlen($deliveryAddress) > 100) {
                                                    $deliveryAddress = substr($deliveryAddress, 0, 100) . '...';
                                                }

                                                // Generate tracking ID (using order ID for now)
                                                $trackingId = '#' . strtoupper(substr($order['order_id'], 0, 8));
                                                ?>
                                                <tr>
                                                    <td class="rqu-id">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            #<?php echo htmlspecialchars(isset($order['vendor_public_id']) && $order['vendor_public_id'] !== '' ? $order['vendor_public_id'] : $order['vendor_id']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($order['business_name'] ?: 'N/A'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $orderDate; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="Comfortable-img">
                                                                <img src="<?php if ($firstProductImage !== '' || $firstProductImage !== null) {
                                                                    echo $vendor_baseurl . htmlspecialchars($firstProductImage);
                                                                } else {
                                                                    echo $vendor_baseurl . '/assets/images/default.png';
                                                                } ?>" alt="Product"
                                                                    style="width: 40px; height: 40px; border-radius: 6px;">
                                                            </div>
                                                            <div class="confortable-text">
                                                                <h4>
                                                                    <?php
                                                                    echo htmlspecialchars($firstProductName);
                                                                    if ($totalItems > 1) {
                                                                        echo ' <span class="text-sky-blue">+' . ($totalItems - 1) . ' Item' . ($totalItems > 2 ? 's' : '') . '</span>';
                                                                    }
                                                                    ?>
                                                                </h4>
                                                                <p>
                                                                    <?php
                                                                    if ($firstProduct && isset($firstProduct['quantity'])) {
                                                                        echo $firstProduct['quantity'] . ' x ' . ($firstProduct['variant'] ?? 'Standard');
                                                                    } else {
                                                                        echo '1 x Standard';
                                                                    }
                                                                    ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $totalAmount; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            $customerName = '';
                                                            if ($order['customer_first_name'] || $order['customer_last_name']) {
                                                                $customerName = trim(($order['customer_first_name'] ?? '') . ' ' . ($order['customer_last_name'] ?? ''));
                                                            }
                                                            echo htmlspecialchars($customerName ?: 'N/A');
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-Delivery-address">
                                                            <?php echo htmlspecialchars($deliveryAddress ?: 'Address not available'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo $statusBadge; ?>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $lastUpdateDate; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $trackingId; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a href="./order-details.php?id=<?php echo $order['order_id']; ?>"
                                                            class="btn btn-view">VIEW</a>
                                                    </td>
                                                </tr>
                                                <?php
                                                $rowNumber++;
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="12" class="text-center">
                                                    <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                </td>
                                                <td colspan="12" class="text-center">No orders found</td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class=" my-4">
                                <nav aria-label="Page navigation">
                                    <form method="POST" id="paginationForm">
                                        <ul class="pagination justify-content-center flex-wrap">
                                            <?php if ($currentPage > 1): ?>
                                                <li class="page-item">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $currentPage - 1; ?>" class="page-link"><i
                                                            class="bx bx-chevron-left"></i> Previous</button>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Previous</span>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            $startPage = max(1, $currentPage - 2);
                                            $endPage = min($totalPages, $currentPage + 2);

                                            for ($i = $startPage; $i <= $endPage; $i++):
                                                ?>
                                                <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                                    <button type="submit" name="page" value="<?php echo $i; ?>"
                                                        class="page-link"><?php echo $i; ?></button>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($currentPage < $totalPages): ?>
                                                <li class="page-item">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $currentPage + 1; ?>" class="page-link">Next <span
                                                            aria-hidden="true"><i
                                                                class="bx bx-chevron-right"></i></span></button>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Next</span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </form>
                                </nav>
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

        <!-- JavaScript for Address Truncation and Dynamic Filters -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Select all elements with class 'order-Delivery-address'
                const addressCells = document.querySelectorAll('.order-Delivery-address');

                addressCells.forEach(cell => {
                    // Store the original full text
                    const fullText = cell.textContent.trim();
                    const words = fullText.split(/\s+/); // Split by whitespace to count words

                    // If the address has more than 30 words, truncate it
                    if (words.length > 30) {
                        const truncatedText = words.slice(0, 30).join(' ') + '...';
                        cell.textContent = truncatedText;

                        // Add a data attribute to store the full text
                        cell.setAttribute('data-full-text', fullText);
                        cell.classList.add('truncated'); // Add a class for styling

                        // Add click event to toggle between truncated and full text
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

                // Initialize Flatpickr for date range picker
                const dateFromValue = document.getElementById('date_from').value;
                const dateToValue = document.getElementById('date_to').value;

                let defaultDate = null;
                if (dateFromValue && dateToValue) {
                    defaultDate = [dateFromValue, dateToValue];
                } else if (dateFromValue) {
                    defaultDate = [dateFromValue];
                }

                const datePicker = flatpickr("#daterange", {
                    mode: "range",
                    dateFormat: "Y-m-d",
                    defaultDate: defaultDate,
                    allowInput: false,
                    clickOpens: true,
                    onChange: function (selectedDates, dateStr, instance) {
                        const dateFromInput = document.getElementById('date_from');
                        const dateToInput = document.getElementById('date_to');
                        const dateRangeInput = document.getElementById('daterange');

                        if (selectedDates.length === 2) {
                            const fromDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                            const toDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");

                            dateFromInput.value = fromDate;
                            dateToInput.value = toDate;
                            dateRangeInput.value = fromDate + ' to ' + toDate;

                            // Toggle clear button
                            toggleClearButton();

                            // Auto-submit form when date range is selected
                            setTimeout(function () {
                                document.getElementById('filterForm').submit();
                            }, 100);

                        } else if (selectedDates.length === 1) {
                            const fromDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                            dateFromInput.value = fromDate;
                            dateToInput.value = '';
                            dateRangeInput.value = fromDate;

                            // Toggle clear button
                            toggleClearButton();

                        } else {
                            dateFromInput.value = '';
                            dateToInput.value = '';
                            dateRangeInput.value = '';

                            // Toggle clear button
                            toggleClearButton();

                            // Auto-submit form when date range is cleared
                            setTimeout(function () {
                                document.getElementById('filterForm').submit();
                            }, 100);
                        }
                    },
                    onClose: function (selectedDates, dateStr, instance) {
                        // Ensure form is submitted when date picker is closed
                        if (selectedDates.length > 0) {
                            setTimeout(function () {
                                document.getElementById('filterForm').submit();
                            }, 100);
                        }
                    }
                });

                // Auto-submit form when filters change
                const filterForm = document.getElementById('filterForm');
                const statusSelect = document.getElementById('inputState');
                const sortSelect = document.getElementById('Default-sorting');
                const searchInput = document.querySelector('input[name="search"]');

                // Add event listeners for auto-submit
                statusSelect.addEventListener('change', function () {
                    filterForm.submit();
                });

                sortSelect.addEventListener('change', function () {
                    filterForm.submit();
                });

                // Add debounced search
                let searchTimeout;
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function () {
                        filterForm.submit();
                    }, 500); // Wait 500ms after user stops typing
                });

                // Handle Enter key in search
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        filterForm.submit();
                    }
                });

                // Clear date range functionality
                const clearDateRangeBtn = document.getElementById('clearDateRange');
                clearDateRangeBtn.addEventListener('click', function () {
                    // Clear the date picker
                    datePicker.clear();

                    // Clear hidden inputs
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                    document.getElementById('daterange').value = '';

                    // Submit form to refresh results
                    filterForm.submit();
                });

                // Show/hide clear button based on date selection
                function toggleClearButton() {
                    const dateFromValue = document.getElementById('date_from').value;
                    const dateToValue = document.getElementById('date_to').value;

                    if (dateFromValue || dateToValue) {
                        clearDateRangeBtn.style.display = 'block';
                    } else {
                        clearDateRangeBtn.style.display = 'none';
                    }
                }

                // Initial toggle
                toggleClearButton();

                // Export: copy current filters into hidden export form and submit
                const exportBtn = document.getElementById('exportToExcel');
                exportBtn.addEventListener('click', function () {
                    document.getElementById('exp_date_from').value = document.getElementById('date_from').value;
                    document.getElementById('exp_date_to').value = document.getElementById('date_to').value;
                    document.getElementById('exp_status').value = document.getElementById('inputState').value;
                    document.getElementById('exp_sort_by').value = document.getElementById('Default-sorting').value;
                    document.getElementById('exp_search').value = document.querySelector('input[name="search"]').value;
                    document.getElementById('exportForm').submit();
                });
            });
        </script>
</body>

</html>
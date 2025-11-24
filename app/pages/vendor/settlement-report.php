<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/init.php';

require_once __DIR__ . '/../../handlers/acess_guard.php';

if (!isset($_SESSION['vendor_reg_id'])) {
    header('Location: login.php');
}
$vendor_reg_id = $_SESSION['vendor_reg_id'];

// Export to Excel with current filters (no pagination)
if (isset($_GET['export']) && strtolower((string)$_GET['export']) === 'excel') {
    // Clean buffers to avoid corrupting Excel output
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    } else { @ob_end_clean(); }
    $statusParam = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

    $wheres = [];
    $wheres[] = 't.vendor_id=' . (int)$vendor_reg_id;
    if ($statusParam !== '') {
        $map = [
            'settled' => ["approved", "paid", "complete", "completed", "settled"],
            'pending' => ["pending", "under review", "under_review", "underreview"],
            'failed'  => ["failed", "rejected", "declined"],
        ];
        if (isset($map[$statusParam])) {
            $in = array_map(function ($s) { return "'".$s."'"; }, $map[$statusParam]);
            $wheres[] = 't.status IN (' . implode(',', $in) . ')';
        }
    }
    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($con, $q);
        $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.transaction_id LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%')";
    }
    if ($from !== '' && $to !== '') {
        $fromEsc = mysqli_real_escape_string($con, $from . ' 00:00:00');
        $toEsc = mysqli_real_escape_string($con, $to . ' 23:59:59');
        $wheres[] = "t.created_at BETWEEN '$fromEsc' AND '$toEsc'";
    }
    $whereSql = '';
    if (!empty($wheres)) {
        $whereSql = 'WHERE ' . implode(' AND ', $wheres);
    }

    $autoloadPath = realpath(__DIR__ . '/../../../vendor/autoload.php');
    if ($autoloadPath && file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo 'Excel export packages not found. Please install PhpSpreadsheet.';
        exit;
    }

    $sql = "SELECT 
t.settlement_id,
MAX(t.transaction_id) AS transaction_id,
SUM(t.amount) AS total_amount,
SUM(t.platform_fee) AS total_platform_fee,
SUM(t.gst) AS total_gst,
MAX(t.status) AS latest_status,
MAX(t.created_at) AS latest_created_at
FROM transactions t
$whereSql
GROUP BY t.settlement_id
ORDER BY latest_created_at DESC";
    $rs = mysqli_query($con, $sql);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Settlements');
    $headers = [
        'A1' => 'Settlement ID',
        'B1' => 'Transaction ID',
        'C1' => 'Gross Amount (₹)',
        'D1' => 'Platform Fee (₹)',
        'E1' => 'GST (₹)',
        'F1' => 'Net Payable (₹)',
        'G1' => 'Status',
        'H1' => 'Settlement Date',
    ];
    foreach ($headers as $cell => $label) { $sheet->setCellValue($cell, $label); }
    $sheet->getStyle('A1:H1')->getFont()->setBold(true);

    $row = 2;
    if ($rs) {
        while ($r = mysqli_fetch_assoc($rs)) {
            $gross = (float)($r['total_amount'] ?? 0);
            $fee = (float)($r['total_platform_fee'] ?? 0);
            $gst = (float)($r['total_gst'] ?? 0);
            $net = $gross - $fee - $gst;
            $statusLc = strtolower((string)($r['latest_status'] ?? ''));

            $sheet->setCellValue('A'.$row, (string)($r['settlement_id'] ?? ''));
            $sheet->setCellValue('B'.$row, (string)($r['transaction_id'] ?? ''));
            $sheet->setCellValue('C'.$row, $gross);
            $sheet->setCellValue('D'.$row, $fee);
            $sheet->setCellValue('E'.$row, $gst);
            $sheet->setCellValue('F'.$row, $net);
            $sheet->setCellValue('G'.$row, ucfirst($statusLc));
            $sheet->setCellValue('H'.$row, (string)($r['latest_created_at'] ?? ''));
            $row++;
        }
    }
    foreach (range('A', 'H') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
    // Clean again right before output
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    } else { @ob_end_clean(); }

    $fileName = 'settlement_export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
// AJAX endpoint to fetch vendor settlements with filters & pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'settlements') {
    header('Content-Type: application/json');

    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $statusParam = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
    $from = isset($_GET['from']) ? trim((string)$_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string)$_GET['to']) : '';

    $wheres = [];
    // Scope to current vendor
    $wheres[] = 't.vendor_id=' . (int)$vendor_reg_id;

    // Map UI statuses to DB statuses
    if ($statusParam !== '') {
        $map = [
            'settled' => ["approved", "paid", "complete", "completed", "settled"],
            'pending' => ["pending", "under review", "under_review", "underreview"],
            'failed'  => ["failed", "rejected", "declined"],
        ];
        if (isset($map[$statusParam])) {
            $in = array_map(function ($s) {
                return "'" . $s . "'";
            }, $map[$statusParam]);
            $wheres[] = 't.status IN (' . implode(',', $in) . ')';
        }
    }

    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($con, $q);
        $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.transaction_id LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%')";
    }
    if ($from !== '' && $to !== '') {
        $fromEsc = mysqli_real_escape_string($con, $from . ' 00:00:00');
        $toEsc = mysqli_real_escape_string($con, $to . ' 23:59:59');
        $wheres[] = "t.created_at BETWEEN '$fromEsc' AND '$toEsc'";
    }

    $whereSql = '';
    if (!empty($wheres)) {
        $whereSql = 'WHERE ' . implode(' AND ', $wheres);
    }

    // Total count for pagination
    $countSql = "SELECT COUNT(*) AS cnt FROM transactions t $whereSql";
    $countRes = mysqli_query($con, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int)($row['cnt'] ?? 0);
    }

    // Data query
    // Data query
    $dataSql = "SELECT 
t.settlement_id,
t.transaction_id,
SUM(t.amount) AS total_amount,
SUM(t.platform_fee) AS total_platform_fee,
SUM(t.gst) AS total_gst,
MAX(t.status) AS latest_status,
MAX(t.created_at) AS latest_created_at
FROM transactions t
$whereSql
GROUP BY t.settlement_id
ORDER BY latest_created_at DESC
LIMIT $offset, $limit";

    $res = mysqli_query($con, $dataSql);

    $rowsHtml = '';
    $index = $offset + 1;
    if ($res && mysqli_num_rows($res) > 0) {
        while ($r = mysqli_fetch_assoc($res)) {
            $badgeClass = 'bg-outline-warning';
            $statusLc = strtolower((string)$r['latest_status']);
            if (in_array($statusLc, ["approved", "paid", "complete", "completed", "settled"], true)) $badgeClass = 'bg-outline-success';
            if (in_array($statusLc, ["failed", "rejected", "declined"], true)) $badgeClass = 'bg-outline-danger';

            $netPayable = (float)$r['total_amount'] - (float)$r['total_platform_fee'] - (float)$r['total_gst'];

            $rowsHtml .= '<tr>'
                . '<td class="text-center">' . $index++ . '</td>'
                . '<td>#' . htmlspecialchars($r['settlement_id']) . '</td>'
                . '<td>' . (empty($r['transaction_id']) ? '-' : htmlspecialchars($r['transaction_id'])) . '</td>'
                . '<td>₹' . number_format((float)$r['total_amount'], 2) . '</td>'
                . '<td>₹' . number_format((float)$r['total_platform_fee'], 2) . '</td>'
                . '<td>₹' . number_format((float)$r['total_gst'], 2) . '</td>'
                . '<td>₹' . number_format($netPayable, 2) . '</td>'
                . '<td><span class="badge rounded-pill ' . $badgeClass . '">' . ucfirst($statusLc) . '</span></td>'
                . '<td>' . date('d,M Y - h:i A', strtotime($r['latest_created_at'])) . '</td>'
                . '<td class="text-center d-flex justify-content-center">'
                . '<a href="./payment-settled.php?sid=' . urlencode($r['settlement_id']) . '" class="view-orders-btn">'
                . '<img src=" '.PUBLIC_ASSETS.'images/vendor/eye-icon.svg" alt="">'
                . '</a>'
                . '</td>'
                . '</tr>';
        }
    } else {
        $rowsHtml = '<tr><td colspan="10" class="text-center text-muted py-4">No settlements found.</td></tr>';
    }


    // Pagination HTML
    $totalPages = (int)ceil($total / $limit);
    $pagHtml = '';
    if ($totalPages > 1) {
        $prevDisabled = $page <= 1 ? ' disabled' : '';
        $nextDisabled = $page >= $totalPages ? ' disabled' : '';
        $pagHtml .= '<ul class="pagination pagination-sm mb-0">';
        $pagHtml .= '<li class="page-item' . $prevDisabled . '"><a class="page-link js-page" data-page="' . max(1, $page - 1) . '" href="#">Previous</a></li>';
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++) {
            $active = $p === $page ? ' active' : '';
            $pagHtml .= '<li class="page-item' . $active . '"><a class="page-link js-page" data-page="' . $p . '" href="#">' . $p . '</a></li>';
        }
        $pagHtml .= '<li class="page-item' . $nextDisabled . '"><a class="page-link js-page" data-page="' . min($totalPages, $page + 1) . '" href="#">Next</a></li>';
        $pagHtml .= '</ul>';
    }

    // Totals (respecting filters)
    $totalsSql = "SELECT 
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(t.amount),0) AS total_sales,
                    COALESCE(SUM(CASE WHEN LOWER(t.status) IN ('approved','paid','complete','completed','settled') THEN t.amount ELSE 0 END),0) AS amount_settled,
                    COALESCE(SUM(CASE WHEN LOWER(t.status) IN ('pending','under review','under_review','underreview') THEN t.amount ELSE 0 END),0) AS amount_pending
                  FROM transactions t $whereSql";
    $totals = ['total_orders' => 0, 'total_sales' => 0, 'amount_settled' => 0, 'amount_pending' => 0];
    $totRes = mysqli_query($con, $totalsSql);
    if ($totRes) {
        $tr = mysqli_fetch_assoc($totRes);
        if ($tr) {
            $totals['total_orders'] = (int)$tr['total_orders'];
            $totals['total_sales'] = (float)$tr['total_sales'];
            $totals['amount_settled'] = (float)$tr['amount_settled'];
            $totals['amount_pending'] = (float)$tr['amount_pending'];
        }
    }

    echo json_encode([
        'rows_html' => $rowsHtml,
        'pagination_html' => $pagHtml,
        'total' => $total,
        'page' => $page,
        'pages' => $totalPages,
        'totals' => $totals,
    ]);
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
    <meta http-equiv="hagidy website" content="hagidy website">
    <title> SETTLEMENT REPORT | HADIDY</title>
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
                <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Settlement Report</h1>
                    <!-- <a href="./withdrawRequest.php" type="button" class="btn btn-secondary btn-wave waves-effect waves-light">Withdrawal Request</a> -->
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card-flex card-flex1 mb-4">
                            <div class="card custom-card mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-2  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Payments.svg" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Total Sales</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b id="js-total-sales">₹0.00</b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-2  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/TotalOrders.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2">Total Orders</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b id="js-total-orders">0</b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-2  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/NewOrders.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2 fs-12">Amount Settled</div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b id="js-amount-settled">₹0.00</b>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card custom-card mb-0">
                                <div class="card-body">
                                    <div class=" d-flex align-items-center gap-2">
                                        <div
                                            class=" d-flex align-items-center justify-content-center ecommerce-icon px-0">
                                            <span class="rounded p-2  bg-primary-transparent">
                                                <div class="total-sales">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/pending.png" alt="">
                                                </div>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="mb-2 mr-2">Amount Pending
                                            </div>
                                            <div class="text-muted mb-1 fs-12">
                                                <span class="text-dark fw-semibold fs-20 lh-1 vertical-bottom">
                                                    <b id="js-amount-pending">₹0.00</b>
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
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between gap-3 flex-wrap px-4">
                                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="card-title1 mx-3">
                                        History
                                    </div>
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="input-group selecton-order1">
                                        <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i>
                                        </div> <input type="text" class="form-control flatpickr-input" id="daterange"
                                            placeholder="Date range picker" readonly="readonly">
                                    </div>
                                    <select id="Default-sorting" class="form-select form-select-lg selecton-order">
                                        <option value="" selected>All Status</option>
                                        <option value="pending">Pending</option>
                                        <option value="settled">Settled</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                    <div class="selecton-order">
                                        <input id="js-search" type="search" class="form-control " placeholder="Search by Settlement/Txn/Order"
                                            aria-describedby="button-addon2">
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class=" table table-striped text-nowrap table-bordered-vertical ">
                                        <thead>
                                            <tr>
                                                <th scope="col">No</th>
                                                <th scope="col">Settlement ID</th>
                                                <th scope="col">Bank Transaction Id</th>
                                                <th scope="col">Gross Amount</th>
                                                <th scope="col">Platform Fee</th>
                                                <th scope="col">GST</th>
                                                <th scope="col">Net Payable</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Settlement Date</th>
                                                <th scope="col">View Orders</th>
                                            </tr>
                                        </thead>
                                        <tbody id="js-rows">
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav id="js-pagination"></nav>
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
        (function() {
            const $rows = document.getElementById('js-rows');
            const $pag = document.getElementById('js-pagination');
            const $search = document.getElementById('js-search');
            const $status = document.getElementById('Default-sorting');
            const $range = document.getElementById('daterange');
            const $totalSales = document.getElementById('js-total-sales');
            const $totalOrders = document.getElementById('js-total-orders');
            const $amtSettled = document.getElementById('js-amount-settled');
            const $amtPending = document.getElementById('js-amount-pending');

            let page = 1;
            let debounceTimer;

            function parseRange(val) {
                if (!val) return {
                    from: '',
                    to: ''
                };
                let parts = val.split(' to ');
                if (parts.length < 2) {
                    parts = val.split(' - ');
                }
                if (parts.length < 2) {
                    return {
                        from: '',
                        to: ''
                    };
                }
                const from = new Date(parts[0]);
                const to = new Date(parts[1]);
                if (isNaN(from.getTime()) || isNaN(to.getTime())) return {
                    from: '',
                    to: ''
                };
                const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                return {
                    from: fmt(from),
                    to: fmt(to)
                };
            }

            async function load() {
                const {
                    from,
                    to
                } = parseRange($range ? $range.value : '');
                const params = new URLSearchParams({
                    ajax: 'settlements',
                    page: String(page),
                    limit: '10',
                    status: $status ? ($status.value || '') : '',
                    q: $search ? ($search.value || '') : '',
                    from: from,
                    to: to
                });
                try {
                    const res = await fetch('settlement-report.php?' + params.toString(), {
                        cache: 'no-store'
                    });
                    const data = await res.json();
                    if ($rows) $rows.innerHTML = data.rows_html || '';
                    if ($pag) $pag.innerHTML = data.pagination_html || '';
                    if (data.totals) {
                        if ($totalOrders) $totalOrders.textContent = (data.totals.total_orders || 0).toLocaleString();
                        const money = (n) => '₹' + Number(n || 0).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        if ($totalSales) $totalSales.textContent = money(data.totals.total_sales || 0);
                        if ($amtSettled) $amtSettled.textContent = money(data.totals.amount_settled || 0);
                        if ($amtPending) $amtPending.textContent = money(data.totals.amount_pending || 0);
                    }
                } catch (e) {
                    if ($rows) $rows.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Failed to load data.</td></tr>';
                }
            }

            document.addEventListener('click', function(e) {
                const a = e.target.closest && e.target.closest('a.page-link.js-page');
                if (a) {
                    e.preventDefault();
                    const p = parseInt(a.getAttribute('data-page') || '1', 10);
                    if (!isNaN(p)) {
                        page = p;
                        load();
                    }
                }
            });

            if ($search) {
                $search.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(function() {
                        page = 1;
                        load();
                    }, 300);
                });
            }
            if ($status) {
                $status.addEventListener('change', function() {
                    page = 1;
                    load();
                });
            }
            if ($range) {
                $range.addEventListener('change', function() {
                    page = 1;
                    load();
                });
            }

            // initial load
            load();
        })();
    </script>

</body>

</html>
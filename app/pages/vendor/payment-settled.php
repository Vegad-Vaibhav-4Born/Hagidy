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
// Read settlement id from query
$settlement_id = isset($_GET['sid']) ? trim((string) $_GET['sid']) : '';
$settlement_id_safe = mysqli_real_escape_string($con, $settlement_id);

// Export to Excel for this settlement's orders (no pagination)
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } } else { @ob_end_clean(); }

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

    if ($settlement_id === '') {
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo 'Missing settlement id.';
        exit;
    }

    $sqlExp = "SELECT t.order_id, t.amount AS gross_amount, t.platform_fee, t.gst, t.created_at
               FROM transactions t
               WHERE t.vendor_id = " . (int) $vendor_reg_id . " AND t.settlement_id='" . $settlement_id_safe . "'
               ORDER BY t.created_at DESC";
    $rsExp = mysqli_query($con, $sqlExp);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Settlement Orders');
    $headers = [
        'A1' => 'Order ID',
        'B1' => 'Gross Amount (₹)',
        'C1' => 'Platform Fee (₹)',
        'D1' => 'GST (₹)',
        'E1' => 'Other (₹)',
        'F1' => 'Net Payable (₹)',
        'G1' => 'Order Date',
    ];
    foreach ($headers as $cell => $label) {
        $sheet->setCellValue($cell, $label);
    }
    $sheet->getStyle('A1:G1')->getFont()->setBold(true);

    $row = 2;
    if ($rsExp) {
        while ($r = mysqli_fetch_assoc($rsExp)) {
            $gross = (float) ($r['gross_amount'] ?? 0);
            $fee = (float) ($r['platform_fee'] ?? 0);
            $gst = (float) ($r['gst'] ?? 0);
            $other = 0.0;
            $net = $gross - $fee - $gst - $other;
            $sheet->setCellValue('A' . $row, (string) ($r['order_id'] ?? ''));
            $sheet->setCellValue('B' . $row, $gross);
            $sheet->setCellValue('C' . $row, $fee);
            $sheet->setCellValue('D' . $row, $gst);
            $sheet->setCellValue('E' . $row, $other);
            $sheet->setCellValue('F' . $row, $net);
            $sheet->setCellValue('G' . $row, (string) ($r['created_at'] ?? ''));
            $row++;
        }
    }
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    // Clean buffers again before sending headers/output
    if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } } else { @ob_end_clean(); }

    $fileName = 'settlement_' . preg_replace('/[^A-Za-z0-9_-]/', '', $settlement_id) . '_orders_' . date('Ymd_His') . '.xlsx';
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

// Fetch orders for this settlement & vendor
$orders = [];
$tot_gross = 0.0;
$tot_fee = 0.0;
$tot_gst = 0.0;
$tot_other = 0.0; // if you start storing other deductions, map here
$tot_net = 0.0;

if ($settlement_id !== '') {
    $sql = "SELECT t.order_id, t.amount AS gross_amount, t.platform_fee, t.gst, t.created_at
            FROM transactions t
            WHERE t.vendor_id = " . (int) $vendor_reg_id . " AND t.settlement_id = '" . $settlement_id_safe . "' 
            ORDER BY t.created_at DESC";
    $res = mysqli_query($con, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        while ($r = mysqli_fetch_assoc($res)) {
            $gross = (float) ($r['gross_amount'] ?? 0);
            $fee = (float) ($r['platform_fee'] ?? 0);
            $gst = (float) ($r['gst'] ?? 0);
            $other = 0.0; // placeholder if needed later
            $net = $gross - $fee - $gst - $other;
            $orders[] = [
                'order_id' => $r['order_id'],
                'gross' => $gross,
                'fee' => $fee,
                'gst' => $gst,
                'other' => $other,
                'net' => $net,
                'created_at' => $r['created_at'],
            ];
            $tot_gross += $gross;
            $tot_fee += $fee;
            $tot_gst += $gst;
            $tot_other += $other;
            $tot_net += $net;
        }
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
    <title>PAYMENT SETTLED ORDERS| HADIDY</title>
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
                <div
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Payment Settled Orders
                        <?php if ($settlement_id !== '') {
                            echo ' - #' . htmlspecialchars($settlement_id);
                        } ?></h1>
                    <div class="ms-md-1 ms-0">
                        <nav>
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="settlement-report.php">Settlement Report</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Payment settled Orders </li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between gap-3 flex-wrap px-4">
                                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="card-title1">
                                        Orders
                                    </div>
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle"><i
                                                class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="px-2 py-1 border rounded bg-light">
                                            <span class="text-muted">Total Gross:</span>
                                            <b>₹<?php echo number_format($tot_gross, 2); ?></b>
                                        </div>
                                        <div class="px-2 py-1 border rounded bg-light">
                                            <span class="text-muted">Total Fee:</span>
                                            <b>₹<?php echo number_format($tot_fee, 2); ?></b>
                                        </div>
                                        <div class="px-2 py-1 border rounded bg-light">
                                            <span class="text-muted">Total GST:</span>
                                            <b>₹<?php echo number_format($tot_gst, 2); ?></b>
                                        </div>
                                        <div class="px-2 py-1 border rounded bg-light">
                                            <span class="text-muted">Total Net:</span>
                                            <b>₹<?php echo number_format($tot_net, 2); ?></b>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class=" table table-striped text-nowrap table-bordered-vertical ">
                                        <thead>
                                            <tr>
                                                <th scope="col">No</th>
                                                <th scope="col">Order ID</th>
                                                <th scope="col">Gross Amount</th>
                                                <th scope="col">Platform Fee</th>
                                                <th scope="col">GST</th>
                                                <th scope="col">Net Payable</th>
                                                <th scope="col">Order Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($settlement_id === ''): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">No settlement
                                                        selected.</td>
                                                </tr>
                                            <?php elseif (empty($orders)): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">No orders found for
                                                        settlement #<?php echo htmlspecialchars($settlement_id); ?>.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php $i = 1;
                                                foreach ($orders as $o): ?>
                                                    <tr>
                                                        <th scope="row"><?php echo $i++; ?></th>
                                                        <td>#<?php echo htmlspecialchars($o['order_id']); ?></td>
                                                        <td>₹<?php echo number_format($o['gross'], 2); ?></td>
                                                        <td>₹<?php echo number_format($o['fee'], 2); ?></td>
                                                        <td>₹<?php echo number_format($o['gst'], 2); ?></td>
                                                        <td>₹<?php echo number_format($o['net'], 2); ?></td>
                                                        <td><?php echo date('d, M Y - h:i A', strtotime($o['created_at'])); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
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

</body>

</html>
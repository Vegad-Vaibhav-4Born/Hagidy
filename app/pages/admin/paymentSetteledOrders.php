    <?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['vendor_id']) || !isset($_GET['settlement_id'])) {
    header('Location: withdrawManagement.php');
    exit;   
}
$vendor_id = $_GET['vendor_id'];
$settlement_id = $_GET['settlement_id'];


$get_all_pending_transactions = mysqli_query($con, "SELECT * FROM transactions WHERE vendor_id = '$vendor_id' AND settlement_id = '$settlement_id' AND status = 'pending' ORDER BY created_at DESC");
$total_pending_transactions = mysqli_num_rows($get_all_pending_transactions);

// Export to Excel for current settlement orders
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
    
    // Load PhpSpreadsheet
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if ($autoloadPath && file_exists($autoloadPath)) { require_once $autoloadPath; }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        // Clean buffers before error message
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        echo 'Excel export packages not found. Please install PhpSpreadsheet via Composer.';
        exit;
    }

    try {
        $sql = "SELECT order_id, amount, platform_fee, gst, created_at FROM transactions WHERE vendor_id = '" . mysqli_real_escape_string($con, $vendor_id) . "' AND settlement_id = '" . mysqli_real_escape_string($con, $settlement_id) . "' AND status = 'pending' ORDER BY created_at DESC";
        $rs = mysqli_query($con, $sql);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Settlement Orders');

        // Headers
        $headers = [
            'A1' => 'Order ID',
            'B1' => 'Gross Amount (₹)',
            'C1' => 'Platform Fee (₹)',
            'D1' => 'GST (₹)',
            'E1' => 'Net Payable (₹)',
            'F1' => 'Order Date',
        ];
        foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }

        $rowNum = 2;
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $gross = (float)$row['amount'];
                $platform = (float)$row['platform_fee'];
                $gst = (float)$row['gst'];
                $net = $gross - $platform - $gst;
                $sheet->setCellValue('A' . $rowNum, $row['order_id']);
                $sheet->setCellValue('B' . $rowNum, $gross);
                $sheet->setCellValue('C' . $rowNum, $platform);
                $sheet->setCellValue('D' . $rowNum, $gst);
                $sheet->setCellValue('E' . $rowNum, $net);
                $sheet->setCellValue('F' . $rowNum, date('d,M Y - h:iA', strtotime($row['created_at'])));
                $rowNum++;
            }
        }

        foreach (range('A', 'F') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

        // Clean output buffers again before sending headers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        // Set proper headers for Excel download
        $fileName = 'settlement_orders_' . preg_replace('/[^\w-]/', '', $settlement_id) . '_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');

        // Write file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    } catch (\Throwable $e) {
        // Clean buffers before error message
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        http_response_code(500);
        echo 'Failed to export settlement orders: ' . $e->getMessage();
        exit;
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
    <title>AYMENT-SETTELED | HADIDY</title>
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


        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">

                <!-- Page Header -->
                <div
                    class="d-flex align-items-center justify-content-between mx-3 mt-3 mb-3 page-header-breadcrumb g-3 flex-wrap">
                    <div>
                        <h1 class="page-title fw-semibold fs-18 dashborder-heading">Payment settled Orders </h1>
                    </div>
                    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb mb-3 flex-wrap">
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="withdrawManagement.php">Settlement Report</a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Payment settled Orders </li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
            </div>
            <div class="row m-1">
                <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <div class="card custom-card ">

                        <div class="card-body">
                            <div class="card-header justify-content-between px-1">
                               
                                <div class="card-title1">
                                    Orders
                                </div>
                                <div class="d-flex align-items-center gap-2">

                                    <div>
                                        <input type="search" class="form-control" placeholder="Search "
                                            aria-describedby="button-addon2">
                                    </div>
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                            </div>

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
                                        <?php
                                        if ($total_pending_transactions > 0) {
                                            $index = 0;
                                            while ($row = mysqli_fetch_assoc($get_all_pending_transactions)) {
                                                $index++;
                                        ?>
                                                <tr>
                                                    <th scope="row"><?php echo $index; ?></th>

                                                    <td>#<?php echo $row['order_id']; ?></td>
                                                    <td><?php echo $row['amount']; ?></td>
                                                    <td><?php echo $row['platform_fee']; ?></td>
                                                    <td><?php echo $row['gst']; ?></td>
                                                    <td><?php echo $row['amount'] - $row['platform_fee'] - $row['gst']; ?></td>
                                                    <td><?php if (!empty($row['created_at'])) {
                                                       echo date('d M y h:i A', strtotime($row['created_at']));
                                                    } else {
                                                        echo '—';
                                                    }
                                                    ?></td>
                                                </tr>
                                            <?php
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">No pending transactions found.</td>
                                            </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
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

</body>

</html>
<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

// Export to Excel (pending settlements grouped, with search filters)
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
        $vendorId = isset($_GET['vendor_id']) ? trim((string) $_GET['vendor_id']) : '';
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

        $wheres = ["t.status='pending'"];
        if ($vendorId !== '') {
            $wheres[] = "t.vendor_id='" . mysqli_real_escape_string($con, $vendorId) . "'";
        }
        if ($q !== '') {
            $qEsc = mysqli_real_escape_string($con, $q);
            $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%' OR t.vendor_name LIKE '%$qEsc%' OR v.vendor_id LIKE '%$qEsc%')";
        }
        $whereSql = 'WHERE ' . implode(' AND ', $wheres);

        $exportSql = "SELECT 
                            v.vendor_id AS store_public_id,
                            t.vendor_id,
                            t.settlement_id,
                            t.transaction_id,
                            t.vendor_name,
                            COUNT(t.id) AS orders_count,
                            SUM(t.amount) AS total_amount,
                            SUM(t.platform_fee) AS total_platform_fee,
                            SUM(t.gst) AS total_gst,
                            MAX(t.created_at) AS latest_created_at
                        FROM transactions t
                        LEFT JOIN vendor_registration v ON t.vendor_id = v.id
                        $whereSql
                        GROUP BY t.settlement_id, t.vendor_id
                        ORDER BY latest_created_at DESC";
        $rs = mysqli_query($con, $exportSql);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pending Settlements');

        // Headers
        $headers = [
            'A1' => 'Store ID',
            'B1' => 'Settlement ID',
            'C1' => 'Transaction ID',
            'D1' => 'Orders',
            'E1' => 'Business/Store Name',
            'F1' => 'Orders need to settle',
            'G1' => 'Settlement Amount (₹)',
            'H1' => 'Platform Charge (₹)',
            'I1' => 'GST (₹)',
            'J1' => 'Settlement Date',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $rowNum = 2;
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) {
                $sheet->setCellValue('A' . $rowNum, $r['store_public_id']);
                $sheet->setCellValue('B' . $rowNum, $r['settlement_id']);
                $sheet->setCellValue('C' . $rowNum, $r['transaction_id']);
                $sheet->setCellValue('D' . $rowNum, (int) $r['orders_count']);
                $sheet->setCellValue('E' . $rowNum, $r['vendor_name']);
                $sheet->setCellValue('F' . $rowNum, (int) $r['orders_count']);
                $sheet->setCellValue('G' . $rowNum, (float) $r['total_amount']);
                $sheet->setCellValue('H' . $rowNum, (float) $r['total_platform_fee']);
                $sheet->setCellValue('I' . $rowNum, (float) $r['total_gst']);
                $sheet->setCellValue('J' . $rowNum, date('d,M Y - h:iA', strtotime($r['latest_created_at'])));
                $rowNum++;
            }
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Clean output buffers again before sending headers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        // Set proper headers for Excel download
        $fileName = 'withdraw_pending_settlements_' . date('Ymd_His') . '.xlsx';
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
        echo 'Failed to export settlements: ' . $e->getMessage();
        exit;
    }
}

// AJAX endpoint: grouped pending settlements (by settlement_id) with filters & pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pending_settlements') {
    if (ob_get_length()) {
        @ob_end_clean();
    }
    header('Content-Type: application/json; charset=UTF-8');

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $vendorId = isset($_GET['vendor_id']) ? trim((string) $_GET['vendor_id']) : '';
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

    $wheres = ["t.status='pending'"];
    if ($vendorId !== '') {
        $wheres[] = "t.vendor_id='" . mysqli_real_escape_string($con, $vendorId) . "'";
    }
    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($con, $q);
        $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%' OR t.vendor_name LIKE '%$qEsc%' OR v.vendor_id LIKE '%$qEsc%')";
    }
    $whereSql = 'WHERE ' . implode(' AND ', $wheres);

    // Count groups
    $countSql = "SELECT COUNT(*) AS cnt FROM (SELECT t.settlement_id FROM transactions t LEFT JOIN vendor_registration v ON t.vendor_id=v.id $whereSql GROUP BY t.settlement_id) g";
    $countRes = mysqli_query($con, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int) ($row['cnt'] ?? 0);
    }

    $dataSql = "SELECT 
                    v.vendor_id AS store_public_id,
                    t.vendor_id,
                    t.settlement_id,
                    t.transaction_id,
                    t.vendor_name,
                    COUNT(t.id) AS orders_count,
                    SUM(t.amount) AS total_amount,
                    SUM(t.platform_fee) AS total_platform_fee,
                    SUM(t.gst) AS total_gst,
                    MAX(t.created_at) AS latest_created_at
                FROM transactions t
                LEFT JOIN vendor_registration v ON t.vendor_id = v.id
                $whereSql
                GROUP BY t.settlement_id, t.vendor_id
                ORDER BY latest_created_at DESC
                LIMIT $offset, $limit";
    $res = mysqli_query($con, $dataSql);

    $rowsHtml = '';
    if ($res && mysqli_num_rows($res) > 0) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rowsHtml .= '<tr>'
                . '<th scope="row"><input type="checkbox" class="js-row-check" data-settlement="' . htmlspecialchars($r['settlement_id']) . '"></th>'
                . '<td>#' . htmlspecialchars($r['store_public_id'] ?? '') . '</td>'
                . '<td>#' . htmlspecialchars($r['settlement_id']) . '</td>'
                . '<td>' . (empty($r['transaction_id']) ? '-' : htmlspecialchars($r['transaction_id'])) . '</td>'
                . '<td>' . htmlspecialchars($r['orders_count']) . '</td>'
                . '<td>' . htmlspecialchars($r['vendor_name']) . '</td>'
                . '<td class="text-center">' . (int) $r['orders_count'] . '</td>'
                . '<td>₹' . number_format((float) $r['total_amount'], 2) . '</td>'
                . '<td>₹' . number_format((float) $r['total_platform_fee'], 2) . '</td>'
                . '<td>₹' . number_format((float) $r['total_gst'], 2) . '</td>'
                . '<td>' . date('d,M Y - h:iA', strtotime($r['latest_created_at'])) . '</td>'
                . '<td class="text-center align-middle"><div class="d-inline-flex align-items-center justify-content-center" style="height: 100%;"><a href="#" class="i-icon-product js-view" data-vendor="' . (int) $r['vendor_id'] . '" data-settlement="' . htmlspecialchars($r['settlement_id']) . '"><i class="fa-solid fa-eye"></i></a></div></td>'
                . '</tr>';
        }
    } else {
        $rowsHtml = '<tr><td colspan="11" class="text-center text-muted py-4">No pending settlements found.</td></tr>';
    }

    $totalPages = (int) ceil($total / $limit);
    $pagHtml = '';
    if ($totalPages > 1) {
        $page = min($page, $totalPages);
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

    echo json_encode([
        'rows_html' => $rowsHtml,
        'pagination_html' => $pagHtml,
        'total' => $total,
        'pages' => $totalPages,
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
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>WITHDRAW | HADIDY</title>
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

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" />


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
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Settlement Report</h1>
                    <!-- <a href="./withdrawRequest.php" type="button" class="btn btn-secondary btn-wave waves-effect waves-light">Withdrawal Request</a> -->
                    <div>
                        <a href="#" type="button" class="btn btn-secondary btn-wave waves-effect waves-light"
                            data-bs-toggle="modal" data-bs-target="#bulkImportModal">Bulk settlement</a>
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="row">
                </div>

                <div class="row">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between px-4">
                                <div class="card-title1">
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" id="exportSettlementsBtn"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="selecton-order">
                                        <input type="search" id="searchInput" class="form-control"
                                            placeholder="Search by Settlement / Store / Order"
                                            aria-describedby="button-addon2">
                                    </div>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class=" table table-striped text-nowrap table-bordered-vertical ">
                                        <thead>
                                            <tr>
                                                <th scope="col">
                                                    <input type="checkbox" name="store-check" id="">
                                                </th>
                                                <th scope="col">Store ID</th>
                                                <th scope="col">Settlement ID</th>
                                                <th scope="col">Transaction ID</th>
                                                <th scope="col">Orders</th>
                                                <th scope="col">Business/Store Name</th>
                                                <th scope="col">Orders need to settle</th>
                                                <th scope="col">Settlement Amount</th>
                                                <th scope="col">Platform Charge</th>
                                                <th scope="col">GST</th>
                                                <th scope="col">Settlement Date</th>
                                                <th scope="col">View Report</th>
                                            </tr>
                                        </thead>
                                        <tbody id="settlementsBody"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <div id="settlementsPagination"></div>
                                </nav>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- End:: row-2 -->

            </div>
        </div>
        <!-- End::app-content -->
        <!-- Bulk Import Modal -->
        <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="p-4">
                        <div class="bulk-product-heading">
                            <h2>
                                Bulk Settlement
                            </h2>
                            <p class="text-muted">Upload Excel file with Settlement ID and Transaction ID columns</p>
                        </div>
                        <form id="bulkSettlementForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="excelFile" class="form-label text-default">Excel File <span
                                        class="text-danger">*</span></label>
                                <div class="drop-zone" id="dropZone">
                                    <span class="drop-zone__prompt">Drag &amp; Drop your files or Browse</span>
                                    <input type="file" name="excel_file" id="excelFile" class="drop-zone__input"
                                        accept=".xlsx,.xls,.csv" required>
                                </div>
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary w-100 mb-3" id="submitBtn">
                                    <span class="btn-text">Submit</span>
                                    <span class="btn-loading d-none">
                                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                        Processing...
                                    </span>
                                </button>
                                <a href="bulk_settlement_upload.php?download_template=1"
                                    class="text-sky-blue text-center border-download">Download Template</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Modal -->
        <div class="modal fade" id="resultsModal" tabindex="-1" aria-labelledby="resultsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-black" id="resultsModalLabel">Bulk Settlement Results</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="resultsContent">
                            <!-- Results will be populated here -->
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="input-group">
                            <a href="javascript:void(0);" class="input-group-text" id="Search-Grid"><i
                                    class="fe fe-search header-link-icon fs-18"></i></a>
                            <input type="search" class="form-control border-0 px-2" placeholder="Search"
                                aria-label="Username">
                            <a href="javascript:void(0);" class="input-group-text" id="voice-search"><i
                                    class="fe fe-mic header-link-icon"></i></a>
                            <a href="javascript:void(0);" class="btn btn-light btn-icon" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fe fe-more-vertical"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Another action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                            <span class="search-tags"><i class="fe fe-user me-2"></i>People<a href="javascript:void(0)"
                                    class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a href="javascript:void(0)"
                                    class="tag-addon"><i class="fe fe-x"></i></a></span>
                        </div>
                        <div class="my-4">
                            <p class="font-weight-semibold text-muted mb-2">Recent Search :</p>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="#"><span>Notifications</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="alerts.php"><span>Alerts</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                <a href="mail.php"><span>Mail</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="btn-group ms-auto">
                            <button class="btn btn-sm btn-primary-light">Search</button>
                            <button class="btn btn-sm btn-primary">Clear Recents</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

    <!-- Custom CSS for Bulk Settlement -->
    <style>
        .drop-zone {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .drop-zone:hover {
            border-color: #007bff;
            background-color: #e3f2fd;
        }

        .drop-zone--over {
            border-color: #007bff !important;
            background-color: #e3f2fd !important;
        }

        .drop-zone__prompt {
            color: #6c757d;
            font-size: 1rem;
            margin: 0;
        }

        .drop-zone__input {
            display: none;
        }

        .border-download {
            text-decoration: none;
            border-bottom: 1px dashed #007bff;
            color: #007bff;
        }

        .border-download:hover {
            color: #0056b3;
            text-decoration: none;
        }

        .btn-loading {
            display: none;
        }

        .btn:disabled .btn-loading {
            display: inline-flex !important;
        }

        .btn:disabled .btn-text {
            display: none !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const bodyEl = document.getElementById('settlementsBody');
            const pagEl = document.getElementById('settlementsPagination');
            const searchEl = document.getElementById('searchInput');
            const exportBtn = document.getElementById('exportSettlementsBtn');

            let page = 1;
            const limit = 10;
            const vendorId = '';
            let typingTimer = null;

            // open view (redirect) with vendor_id to paymentSetteledOrders
            document.addEventListener('click', function (e) {
                const a = e.target.closest('a.js-view');
                if (!a) return;
                e.preventDefault();
                const v = a.getAttribute('data-vendor') || '';
                const s = a.getAttribute('data-settlement') || '';
                const url = 'paymentSetteledOrders.php' + (v ? ('?vendor_id=' + encodeURIComponent(v) + (s ? ('&settlement_id=' + encodeURIComponent(s)) : '')) : '');
                window.location.href = url;
            });

            function loadData() {
                const params = new URLSearchParams();
                params.set('ajax', 'pending_settlements');
                params.set('page', String(page));
                params.set('limit', String(limit));
                if (vendorId) params.set('vendor_id', vendorId);
                const q = searchEl?.value || '';
                if (q) params.set('q', q);

                fetch('withdrawManagement.php?' + params.toString(), { cache: 'no-store' })
                    .then(r => r.json())
                    .then(data => {
                        bodyEl.innerHTML = data.rows_html || '';
                        pagEl.innerHTML = data.pagination_html || '';
                    })
                    .catch(() => {
                        bodyEl.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Failed to load settlements.</td></tr>';
                        pagEl.innerHTML = '';
                    });
            }

            pagEl?.addEventListener('click', function (e) {
                const a = e.target.closest('a.js-page');
                if (!a) return;
                e.preventDefault();
                const p = parseInt(a.getAttribute('data-page') || '1', 10);
                if (!isNaN(p)) { page = p; loadData(); }
            });

            searchEl?.addEventListener('input', function () {
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function () { page = 1; loadData(); }, 300);
            });

            // Export using current filters
            exportBtn?.addEventListener('click', function (e) {
                e.preventDefault();
                const params = new URLSearchParams();
                params.set('export', 'excel');
                if (vendorId) params.set('vendor_id', vendorId);
                const q = searchEl?.value || '';
                if (q) params.set('q', q);
                window.location.href = 'withdrawManagement.php?' + params.toString();
            });

            // Bulk Settlement Form Handling
            const bulkForm = document.getElementById('bulkSettlementForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoading = submitBtn.querySelector('.btn-loading');
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('excelFile');

            // File upload handling
            dropZone.addEventListener('click', () => fileInput.click());

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drop-zone--over');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('drop-zone--over');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drop-zone--over');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    updateDropZoneDisplay(files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    updateDropZoneDisplay(e.target.files[0]);
                }
            });

            function updateDropZoneDisplay(file) {
                const prompt = dropZone.querySelector('.drop-zone__prompt');
                prompt.textContent = `Selected: ${file.name}`;
                prompt.style.color = '#28a745';
            }

            // Form submission
            bulkForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(bulkForm);

                // Show loading state
                submitBtn.disabled = true;
                btnText.classList.add('d-none');
                btnLoading.classList.remove('d-none');

                fetch('bulk_settlement_upload.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => {
                        // Check if response is JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            // If not JSON, read as text to see what we got
                            return response.text().then(text => {
                                console.error('Non-JSON response received:', text.substring(0, 200));
                                throw new Error('Server returned non-JSON response. Please check server logs.');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Hide loading state
                        submitBtn.disabled = false;
                        btnText.classList.remove('d-none');
                        btnLoading.classList.add('d-none');

                        // Show results modal
                        showResultsModal(data);

                        // Close bulk import modal
                        const bulkModal = bootstrap.Modal.getInstance(document.getElementById('bulkImportModal'));
                        bulkModal.hide();

                        // Reload data if successful
                        if (data.success) {
                            loadData();
                        }
                    })
                    .catch(error => {
                        // Hide loading state
                        submitBtn.disabled = false;
                        btnText.classList.remove('d-none');
                        btnLoading.classList.add('d-none');

                        console.error('Bulk settlement upload error:', error);
                        showResultsModal({
                            success: false,
                            message: 'An error occurred while processing the file: ' + (error.message || 'Unknown error'),
                            data: { 
                                processed: 0,
                                successful: 0,
                                failed: 0,
                                errors: [error.message || 'Failed to process file. Please check the file format and try again.'] 
                            }
                        });
                    });
            });

            function showResultsModal(data) {
                const resultsContent = document.getElementById('resultsContent');
                let html = '';

                if (data.success) {
                    html += `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Success!</strong> ${data.message}
                        </div>
                    `;
                } else {
                    html += `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Error!</strong> ${data.message}
                        </div>
                    `;
                }

                if (data.data) {
                    html += `
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">${data.data.processed || 0}</h5>
                                        <p class="card-text">Total Processed</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">${data.data.successful || 0}</h5>
                                        <p class="card-text">Successful</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">${data.data.failed || 0}</h5>
                                        <p class="card-text">Failed</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    if (data.data.errors && data.data.errors.length > 0) {
                        html += `
                            <div class="mt-3">
                                <h6>Errors:</h6>
                                <div class="alert alert-warning">
                                    <ul class="mb-0">
                                        ${data.data.errors.map(error => `<li>${error}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        `;
                    }
                }

                resultsContent.innerHTML = html;

                // Show results modal
                const resultsModal = new bootstrap.Modal(document.getElementById('resultsModal'));
                resultsModal.show();
            }

            loadData();
        });
    </script>

</body>

</html>
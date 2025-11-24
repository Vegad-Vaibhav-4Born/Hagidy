<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

// Export to Excel (all filtered transactions, no pagination)
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
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
        $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

        $wheres = [];
        if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'paid', 'settled'], true)) {
            $wheres[] = "t.status='" . mysqli_real_escape_string($con, $status) . "'";
        }
        if ($q !== '') {
            $qEsc = mysqli_real_escape_string($con, $q);
            $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.vendor_name LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%' OR t.customer_name LIKE '%$qEsc%' OR t.vendor_id LIKE '%$qEsc%' OR v.vendor_id LIKE '%$qEsc%')";
        }
        if ($from !== '' && $to !== '') {
            $fromEsc = mysqli_real_escape_string($con, $from . ' 00:00:00');
            $toEsc = mysqli_real_escape_string($con, $to . ' 23:59:59');
            $wheres[] = "t.created_at BETWEEN '$fromEsc' AND '$toEsc'";
        }
        $whereSql = empty($wheres) ? '' : ('WHERE ' . implode(' AND ', $wheres));

        $exportSql = "SELECT v.vendor_id AS seller_id, t.id, t.settlement_id, t.transaction_id, t.vendor_id, t.vendor_name, t.order_id, t.amount, t.platform_fee, t.gst, t.customer_name, t.status, t.created_at
                      FROM transactions t
                      LEFT JOIN vendor_registration v ON t.vendor_id = v.id
                      $whereSql
                      ORDER BY t.created_at DESC";
        $rs = mysqli_query($con, $exportSql);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transactions');

        // Headers
        $headers = [
            'A1' => 'Settlement ID',
            'B1' => 'Transaction ID',
            'C1' => 'Vendor ID',
            'D1' => 'Vendor Name',
            'E1' => 'Order ID',
            'F1' => 'Amount (₹)',
            'G1' => 'Platform Fee (₹)',
            'H1' => 'GST (₹)',
            'I1' => 'Customer Name',
            'J1' => 'Timestamp',
            'K1' => 'Status',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $rowNum = 2;
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) {
                $sheet->setCellValue('A' . $rowNum, $r['settlement_id']);
                $sheet->setCellValue('B' . $rowNum, $r['transaction_id']);
                $sheet->setCellValue('C' . $rowNum, (int) $r['seller_id']);
                $sheet->setCellValue('D' . $rowNum, $r['vendor_name']);
                $sheet->setCellValue('E' . $rowNum, $r['order_id']);
                $sheet->setCellValue('F' . $rowNum, (float) $r['amount']);
                $sheet->setCellValue('G' . $rowNum, (float) $r['platform_fee']);
                $sheet->setCellValue('H' . $rowNum, (float) $r['gst']);
                $sheet->setCellValue('I' . $rowNum, $r['customer_name']);
                $sheet->setCellValue('J' . $rowNum, date('d, M Y - h:i A', strtotime($r['created_at'])));
                $sheet->setCellValue('K' . $rowNum, ucfirst($r['status']));
                $rowNum++;
            }
        }

        foreach (range('A', 'K') as $col) {
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
        $fileName = 'transactions_export_' . date('Ymd_His') . '.xlsx';
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
        echo 'Failed to export transactions: ' . $e->getMessage();
        exit;
    }
}
// AJAX endpoint to fetch transactions with filters & pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'transactions') {
    header('Content-Type: application/json');

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    $status = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $from = isset($_GET['from']) ? trim((string) $_GET['from']) : '';
    $to = isset($_GET['to']) ? trim((string) $_GET['to']) : '';

    $wheres = [];
    if ($status !== '' && in_array($status, ['pending', 'settled', 'rejected', 'paid'], true)) {
        $wheres[] = "t.status='" . mysqli_real_escape_string($con, $status) . "'";
    }
    if ($q !== '') {
        $qEsc = mysqli_real_escape_string($con, $q);
        // include vendor public id (v.vendor_id) in search as well
        $wheres[] = "(t.settlement_id LIKE '%$qEsc%' OR t.vendor_name LIKE '%$qEsc%' OR t.order_id LIKE '%$qEsc%' OR t.customer_name LIKE '%$qEsc%' OR t.vendor_id LIKE '%$qEsc%' OR v.vendor_id LIKE '%$qEsc%')";
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

    // Count with same FROM/JOIN and aliases used in filters
    $countSql = "SELECT COUNT(*) AS cnt FROM transactions t LEFT JOIN vendor_registration v ON t.vendor_id = v.id $whereSql";
    $countRes = mysqli_query($con, $countSql);
    $total = 0;
    if ($countRes) {
        $row = mysqli_fetch_assoc($countRes);
        $total = (int) ($row['cnt'] ?? 0);
    }

    $dataSql = "SELECT v.vendor_id AS seller_id, t.id, t.settlement_id, t.transaction_id, t.vendor_id, t.vendor_name, t.order_id, t.amount, t.platform_fee, t.gst, t.customer_name, t.status, t.created_at
                FROM transactions t
                LEFT JOIN vendor_registration v ON t.vendor_id = v.id
                $whereSql
                ORDER BY t.created_at DESC
                LIMIT $offset, $limit";
    $res = mysqli_query($con, $dataSql);

    $rowsHtml = '';
    $index = $offset + 1;
    if ($res && mysqli_num_rows($res) > 0) {
        while ($r = mysqli_fetch_assoc($res)) {
            $badgeClass = 'bg-outline-warning';
            if ($r['status'] === 'settled' || $r['status'] === 'paid')
                $badgeClass = 'bg-outline-success';
            if ($r['status'] === 'rejected')
                $badgeClass = 'bg-outline-danger';
            $rowsHtml .= '<tr>'
                . '<td class="text-center">' . $index++ . '</td>'
                . '<td>#' . htmlspecialchars($r['settlement_id']) . '</td>'
                . '<td>' . (empty($r['transaction_id']) ? '-' : '#' . htmlspecialchars($r['transaction_id'])) . '</td>'
                . '<td>#' . (int) $r['seller_id'] . '</td>'
                . '<td>' . htmlspecialchars($r['vendor_name']) . '</td>'
                . '<td>' . htmlspecialchars($r['order_id']) . '</td>'
                . '<td>₹' . number_format((float) $r['amount'], 2) . '</td>'
                . '<td>₹' . number_format((float) $r['platform_fee'], 2) . '</td>'
                . '<td>₹' . number_format((float) $r['gst'], 2) . '</td>'
                . '<td>' . htmlspecialchars($r['customer_name']) . '</td>'
                . '<td class="rqu-id">' . date('d, M Y - h:i A', strtotime($r['created_at'])) . '</td>'
                . '<td><span class="badge rounded-pill ' . $badgeClass . '">' . ucfirst($r['status']) . '</span></td>'
                . '</tr>';
        }
    } else {
        $rowsHtml = '<tr><td colspan="12" class="text-center text-muted py-4">No transactions found.</td></tr>';
    }

    // Build pagination HTML
    $totalPages = (int) ceil($total / $limit);
    $pagHtml = '';
    if ($totalPages > 1) {
        $prevDisabled = $page <= 1 ? ' disabled' : '';
        $nextDisabled = $page >= $totalPages ? ' disabled' : '';
        $pagHtml .= '<ul class="pagination pagination-sm mb-0">';
        $pagHtml .= '<li class="page-item' . $prevDisabled . '"><a class="page-link js-page" data-page="' . max(1, $page - 1) . '" href="#">Previous</a></li>';
        // show limited pages
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
        'page' => $page,
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
    <title>TRANSACTION | HADIDY</title>
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
                    class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Vendor Transactions</h1>
                    <div>
                        <a href="#" id="exportExcelBtn" class="btn-down-excle1 w-100"><i
                                class="fa-solid fa-file-arrow-down"></i>
                            Export to Excel</a>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                    <div class="input-group selecton-order1">
                                        <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i>
                                        </div> <input type="text" class="form-control flatpickr-input " id="daterange"
                                            placeholder="Date range picker" readonly="readonly">
                                    </div>
                                    <div class="selecton-order">
                                        <select id="statusFilter" class="form-select form-select-lg ">
                                            <option value="">All Status</option>
                                            <option value="pending">Pending</option>
                                            <option value="settled">Settled</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="paid">Paid</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                   
                                    <div class="selecton-order">
                                        <input type="search" id="searchInput" class="form-control"
                                            placeholder="Search by Settlement / Order / Vendor / Customer"
                                            aria-describedby="button-addon2">
                                    </div>

                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical ">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col" class="text-center">No</th>
                                            <th scope="col">Settlement ID</th>
                                            <th scope="col">Transaction ID</th>
                                            <th scope="col">Vendor ID</th>
                                            <th scope="col">Vendor Name</th>
                                            <th scope="col">Order ID</th>
                                            <th scope="col">Amount</th>
                                            <th scope="col">Platform Fee</th>
                                            <th scope="col">GST</th>
                                            <th scope="col">Customer Name</th>
                                            <th scope="col">Timestamp</th>
                                            <th scope="col">Settlement Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionsBody" class="table-group-divider"></tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <div id="transactionsPagination"></div>
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

        <!-- Transactions - AJAX Filters & Pagination -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const bodyEl = document.getElementById('transactionsBody');
                const pagEl = document.getElementById('transactionsPagination');
                const statusEl = document.getElementById('statusFilter');
                const searchEl = document.getElementById('searchInput');
                const rangeEl = document.getElementById('daterange');
                const exportBtn = document.getElementById('exportExcelBtn');

                let fromDate = '';
                let toDate = '';
                let page = 1;
                const limit = 10;
                let typingTimer = null;

                // Init date range using flatpickr range mode
                if (window.flatpickr && rangeEl) {
                    flatpickr(rangeEl, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        onClose: function (selectedDates, dateStr) {
                            if (selectedDates.length === 2) {
                                fromDate = selectedDates[0].toISOString().slice(0, 10);
                                toDate = selectedDates[1].toISOString().slice(0, 10);
                            } else {
                                fromDate = '';
                                toDate = '';
                            }
                            page = 1;
                            loadData();
                        }
                    });
                }

                statusEl?.addEventListener('change', function () { page = 1; loadData(); });

                searchEl?.addEventListener('input', function () {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(function () { page = 1; loadData(); }, 300);
                });

                pagEl?.addEventListener('click', function (e) {
                    const a = e.target.closest('a.js-page');
                    if (!a) return;
                    e.preventDefault();
                    const p = parseInt(a.getAttribute('data-page') || '1', 10);
                    if (!isNaN(p)) { page = p; loadData(); }
                });

                function loadData() {
                    const params = new URLSearchParams();
                    params.set('ajax', 'transactions');
                    params.set('page', String(page));
                    params.set('limit', String(limit));
                    const st = statusEl?.value || '';
                    const q = searchEl?.value || '';
                    if (st) params.set('status', st);
                    if (q) params.set('q', q);
                    if (fromDate && toDate) { params.set('from', fromDate); params.set('to', toDate); }

                    fetch('transaction.php?' + params.toString(), { cache: 'no-store' })
                        .then(r => r.json())
                        .then(data => {
                            bodyEl.innerHTML = data.rows_html || '';
                            pagEl.innerHTML = data.pagination_html || '';
                        })
                        .catch(() => {
                            bodyEl.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-4">Failed to load transactions.</td></tr>';
                            pagEl.innerHTML = '';
                        });
                }

                // initial load
                loadData();

                // Build export URL from current filters
                exportBtn?.addEventListener('click', function (e) {
                    e.preventDefault();
                    const params = new URLSearchParams();
                    params.set('export', 'excel');
                    const st = statusEl?.value || '';
                    const q = searchEl?.value || '';
                    if (st) params.set('status', st);
                    if (q) params.set('q', q);
                    if (fromDate && toDate) { params.set('from', fromDate); params.set('to', toDate); }
                    window.location.href = 'transaction.php?' + params.toString();
                });
            });
        </script>

        

        <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>


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

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                new Choices('#product-s2', {
                    removeItemButton: true,
                    placeholder: true,
                    placeholderValue: '',
                    searchEnabled: true,
                    allowHTML: true
                });
            });
        </script>

</body>

</html>
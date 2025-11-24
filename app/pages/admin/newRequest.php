<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

// Export to Excel for pending vendors with optional search filter
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $where = "v.status = 'pending'";
    if ($q !== '') {
        $safe = mysqli_real_escape_string($con, $q);
        $like = "%$safe%";
        $where .= " AND (v.business_name LIKE '$like' OR v.owner_name LIKE '$like' OR v.mobile_number LIKE '$like' OR v.city LIKE '$like')";
    }

    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if ($autoloadPath && file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
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

    $sql = "SELECT v.* FROM vendor_registration v WHERE $where ORDER BY v.created_at DESC";
    $rs = mysqli_query($con, $sql);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pending Vendors');
    // Full texts columns; resolve IDs to names where possible
    $headers = [
        'A1' => 'id',
        'B1' => 'vendor_id',
        'C1' => 'business_name',
        'D1' => 'business_address',
        'E1' => 'business_type',
        'F1' => 'business_type_other',
        'G1' => 'state',
        'H1' => 'city',
        'I1' => 'pincode',
        'J1' => 'owner_name',
        'K1' => 'mobile_number',
        'L1' => 'password',
        'M1' => 'whatsapp_number',
        'N1' => 'email',
        'O1' => 'website_social',
        'P1' => 'gst_number',
        'Q1' => 'gst_certificate',
        'R1' => 'account_name',
        'S1' => 'account_type',
        'T1' => 'account_number',
        'U1' => 'confirm_account_number',
        'V1' => 'bank_name',
        'W1' => 'ifsc_code',
        'X1' => 'cancelled_cheque',
        'Y1' => 'product_categories',
        'Z1' => 'avg_products',
        'AA1' => 'manufacture_products',
        'AB1' => 'signature_name',
        'AC1' => 'profile_image',
        'AD1' => 'banner_image',
        'AE1' => 'registration_date',
        'AF1' => 'status',
        'AG1' => 'status_note',
        'AH1' => 'seen',
        'AI1' => 'active_status',
        'AJ1' => 'created_at',
        'AK1' => 'updated_at'
    ];
    foreach ($headers as $cell => $label) {
        $sheet->setCellValue($cell, $label);
    }
    $sheet->getStyle('A1:AK1')->getFont()->setBold(true);

    $row = 2;
    if ($rs) {
        while ($v = mysqli_fetch_assoc($rs)) {
            // business type name
            $btName = '';
            if (!empty($v['business_type'])) {
                $bt_rs = mysqli_query($con, "SELECT type_name FROM business_types WHERE id=" . (int) $v['business_type'] . " LIMIT 1");
                if ($bt_rs && ($bt = mysqli_fetch_assoc($bt_rs))) {
                    $btName = (string) $bt['type_name'];
                }
            }
            // state and city names
            $stateName = '';
            if (!empty($v['state'])) {
                $st_rs = mysqli_query($con, "SELECT name FROM state WHERE id=" . (int) $v['state'] . " LIMIT 1");
                if ($st_rs && ($st = mysqli_fetch_assoc($st_rs))) {
                    $stateName = (string) $st['name'];
                }
            }
            $cityName = '';
            if (!empty($v['city']) && ctype_digit((string) $v['city'])) {
                $ct_rs = mysqli_query($con, "SELECT name FROM city WHERE id=" . (int) $v['city'] . " LIMIT 1");
                if ($ct_rs && ($ct = mysqli_fetch_assoc($ct_rs))) {
                    $cityName = (string) $ct['name'];
                }
            } else {
                $cityName = (string) ($v['city'] ?? '');
            }
            // category names
            $catNames = '';
            if (!empty($v['product_categories'])) {
                $ids = array_filter(array_map('trim', explode(',', (string) $v['product_categories'])));
                if (!empty($ids)) {
                    $idsEsc = array_map(function ($id) {
                        return (int) $id;
                    }, $ids);
                    $cat_rs = mysqli_query($con, "SELECT name FROM category WHERE id IN (" . implode(',', $idsEsc) . ")");
                    $names = [];
                    if ($cat_rs) {
                        while ($cr = mysqli_fetch_assoc($cat_rs)) {
                            $names[] = (string) $cr['name'];
                        }
                    }
                    $catNames = implode(',', $names);
                }
            }

            // write cells
            $sheet->setCellValue('A' . $row, (string) ($v['id'] ?? ''));
            $sheet->setCellValue('B' . $row, (string) ($v['vendor_id'] ?? ''));
            $sheet->setCellValue('C' . $row, (string) ($v['business_name'] ?? ''));
            $sheet->setCellValue('D' . $row, (string) ($v['business_address'] ?? ''));
            $sheet->setCellValue('E' . $row, (string) ($btName !== '' ? $btName : ($v['business_type'] ?? '')));
            $sheet->setCellValue('F' . $row, (string) ($v['business_type_other'] ?? ''));
            $sheet->setCellValue('G' . $row, (string) ($stateName !== '' ? $stateName : ($v['state'] ?? '')));
            $sheet->setCellValue('H' . $row, (string) ($cityName));
            $sheet->setCellValue('I' . $row, (string) ($v['pincode'] ?? ''));
            $sheet->setCellValue('J' . $row, (string) ($v['owner_name'] ?? ''));
            $sheet->setCellValue('K' . $row, (string) ($v['mobile_number'] ?? ''));
            $sheet->setCellValue('L' . $row, (string) ($v['password'] ?? ''));
            $sheet->setCellValue('M' . $row, (string) ($v['whatsapp_number'] ?? ''));
            $sheet->setCellValue('N' . $row, (string) ($v['email'] ?? ''));
            $sheet->setCellValue('O' . $row, (string) ($v['website_social'] ?? ''));
            $sheet->setCellValue('P' . $row, (string) ($v['gst_number'] ?? ''));
            $sheet->setCellValue('Q' . $row, (string) ($v['gst_certificate'] ?? ''));
            $sheet->setCellValue('R' . $row, (string) ($v['account_name'] ?? ''));
            $sheet->setCellValue('S' . $row, (string) ($v['account_type'] ?? ''));
            $sheet->setCellValue('T' . $row, (string) ($v['account_number'] ?? ''));
            $sheet->setCellValue('U' . $row, (string) ($v['confirm_account_number'] ?? ''));
            $sheet->setCellValue('V' . $row, (string) ($v['bank_name'] ?? ''));
            $sheet->setCellValue('W' . $row, (string) ($v['ifsc_code'] ?? ''));
            $sheet->setCellValue('X' . $row, (string) ($v['cancelled_cheque'] ?? ''));
            $sheet->setCellValue('Y' . $row, (string) ($catNames !== '' ? $catNames : ($v['product_categories'] ?? '')));
            $sheet->setCellValue('Z' . $row, (string) ($v['avg_products'] ?? ''));
            $sheet->setCellValue('AA' . $row, (string) ($v['manufacture_products'] ?? ''));
            $sheet->setCellValue('AB' . $row, (string) ($v['signature_name'] ?? ''));
            $sheet->setCellValue('AC' . $row, (string) ($v['profile_image'] ?? ''));
            $sheet->setCellValue('AD' . $row, (string) ($v['banner_image'] ?? ''));
            $sheet->setCellValue('AE' . $row, (string) ($v['registration_date'] ?? ''));
            $sheet->setCellValue('AF' . $row, (string) ($v['status'] ?? ''));
            $sheet->setCellValue('AG' . $row, (string) ($v['status_note'] ?? ''));
            $sheet->setCellValue('AH' . $row, (string) ($v['seen'] ?? ''));
            $sheet->setCellValue('AI' . $row, (string) ($v['active_status'] ?? ''));
            $sheet->setCellValue('AJ' . $row, (string) ($v['created_at'] ?? ''));
            $sheet->setCellValue('AK' . $row, (string) ($v['updated_at'] ?? ''));
            $row++;
        }
    }

    foreach (range('A', 'AK') as $col) {
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
    $fileName = 'pending_vendors_export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');

    // Write file
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Inline AJAX for filtering/pagination
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pending_list') {
    header('Content-Type: application/json');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1)
        $page = 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $where = "v.status = 'pending'";
    if ($q !== '') {
        $safe = mysqli_real_escape_string($con, $q);
        $like = "%$safe%";
        $where .= " AND (v.business_name LIKE '$like' OR v.owner_name LIKE '$like' OR v.mobile_number LIKE '$like' OR v.city LIKE '$like')";
    }

    $countRes = mysqli_query($con, "SELECT COUNT(*) AS total FROM vendor_registration v WHERE $where");
    $total = ($countRes && ($r = mysqli_fetch_assoc($countRes))) ? (int) $r['total'] : 0;
    $pages = max(1, (int) ceil($total / $limit));
    if ($page > $pages) {
        $page = $pages;
        $offset = ($page - 1) * $limit;
    }

    $sql = "SELECT v.*, business_types.type_name AS business_type, state.name AS state_name
            FROM vendor_registration v
            LEFT JOIN business_types ON v.business_type = business_types.id
            LEFT JOIN state ON v.state = state.id
            WHERE $where
            ORDER BY v.created_at DESC
            LIMIT $limit OFFSET $offset";
    $rs = mysqli_query($con, $sql);

    ob_start();
    $serial = $offset + 1;
    if ($rs && mysqli_num_rows($rs) > 0) {
        while ($vendor = mysqli_fetch_assoc($rs)) {
            $requestedAt = !empty($vendor['updated_at']) ? date('d,M Y - h:iA', strtotime($vendor['updated_at'])) : (!empty($vendor['registration_date']) ? date('d,M Y', strtotime($vendor['registration_date'])) : '—');

            $isManufacture = !empty($vendor['manufacture_products']) ? $vendor['manufacture_products'] : 'No';
            ?>
            <tr>
                <td><?php echo $serial++; ?></td>
                <td><b><?php echo htmlspecialchars($vendor['business_name']); ?></b></td>
                <td><?php echo htmlspecialchars($vendor['business_type']); ?></td>
                <td><?php echo htmlspecialchars($vendor['state_name']); ?></td>
                <td><?php echo htmlspecialchars($vendor['city']); ?></td>
                <td><?php echo htmlspecialchars($vendor['owner_name']); ?></td>
                <td><?php echo htmlspecialchars($vendor['mobile_number']); ?></td>
                <td><?php echo htmlspecialchars($isManufacture); ?></td>
                <td>
                    <?php
                    if (!empty($requestedAt)) {
                        try {
                            // Convert UTC time to IST
                            $dt = new DateTime($requestedAt, new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                            echo $dt->format('d M Y - h:i A'); // Example: 11 Nov 2025 - 02:45 PM
                        } catch (Exception $e) {
                            echo htmlspecialchars($requestedAt); // fallback if format invalid
                        }
                    } else {
                        echo '—';
                    }
                    ?>
                </td>

                <a href="./sellerDetail.php?vendor_id=<?php echo urlencode($vendor['id']); ?>" type="button"
                    class="btn btn-secondary btn-wave waves-effect waves-light">View
                    Request</a>
                </td>
            </tr>
            <?php
        }
    } else {
        ?>
        <tr>
            <td colspan="10" class="text-center">No pending vendors found.</td>
        </tr>
        <?php
    }
    $tbody = ob_get_clean();

    ob_start();
    ?>
    <li class="page-item <?php if ($page <= 1)
        echo 'disabled'; ?>">
        <a class="page-link" href="#" data-page="<?php echo max(1, $page - 1); ?>">Previous</a>
    </li>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?php if ($page == $i)
            echo 'active'; ?>">
            <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
    <?php endfor; ?>
    <li class="page-item <?php if ($page >= $pages)
        echo 'disabled'; ?>">
        <a class="page-link" href="#" data-page="<?php echo min($pages, $page + 1); ?>">Next</a>
    </li>
    <?php
    $pagination = ob_get_clean();

    echo json_encode(['success' => true, 'tbody' => $tbody, 'pagination' => $pagination, 'total' => $total, 'page' => $page, 'pages' => $pages]);
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
    <title>NEW_REQUEST | HADIDY</title>
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
            <div class="container-fluid ">

                <div class="d-md-flex d-block align-items-center   justify-content-between mt-4 page-header-breadcrumb">

                </div>


                <!-- Start:: row-2 -->
                <div class="row mt-4">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card ">

                            <div class="card-body">
                                <div class="card-header justify-content-between">


                                    <?php if (!empty($_SESSION['flash_text'])): ?>
                                        <div class="alert alert-<?php echo ($_SESSION['flash_type'] ?? 'info'); ?> alert-dismissible fade show"
                                            role="alert" id="flashMessage">
                                            <?php echo htmlspecialchars($_SESSION['flash_text']);
                                            unset($_SESSION['flash_text']); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"
                                                aria-label="Close"></button>
                                        </div>
                                        <script>
                                            setTimeout(function () {
                                                var el = document.getElementById('flashMessage');
                                                if (el) { var alert = bootstrap.Alert.getOrCreateInstance(el); alert.close(); }
                                            }, 5000);
                                        </script>
                                    <?php endif; ?>

                                    <div class="card-title1">
                                        New Requests
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="position-relative">
                                            <input type="search" id="pending-search" class="form-control"
                                                placeholder="Search" autocomplete="off">
                                            <button type="button" id="clear-search"
                                                class="btn btn-link position-absolute end-0 top-50 translate-middle-y px-2"
                                                style="text-decoration:none; display:none;">&times;</button>
                                        </div>
                                        <div>
                                            <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                            <a id="exportPendingExcel"
                                                href="<?php echo htmlspecialchars($exportUrl); ?>"
                                                class="btn-down-excle1 w-100"><i
                                                    class="fa-solid fa-file-arrow-down"></i>
                                                Export to Excel</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                        <thead class="table-light">
                                            <tr>
                                                <th>No</th>
                                                <th>Business/Store Name</th>
                                                <th>Business Type</th>
                                                <th>State</th>
                                                <th>City</th>
                                                <th>Contact Person Name</th>
                                                <th>Mobile Number</th>
                                                <th>Manufacture ?</th>
                                                <th>Requested At</th>

                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pending-tbody">
                                            <?php
                                            $serialNumber = 1;
                                            $limit = 10; // records per page
                                            $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
                                            if ($page < 1)
                                                $page = 1;
                                            $offset = ($page - 1) * $limit;

                                            // Count total records
                                            $countQuery = "SELECT COUNT(*) as total FROM vendor_registration WHERE status = 'pending'";
                                            $countResult = mysqli_query($con, $countQuery);
                                            $row = mysqli_fetch_assoc($countResult);
                                            $totalRecords = $row['total'];
                                            $totalPages = ceil($totalRecords / $limit);

                                            // Fetch records for current page
                                            $pendingVendorsQuery = "
                                                SELECT v.*, business_types.type_name AS business_type, state.name AS state_name
                                                FROM vendor_registration v
                                                LEFT JOIN business_types ON v.business_type = business_types.id
                                                LEFT JOIN state ON v.state = state.id
                                                WHERE v.status = 'pending' 
                                                ORDER BY created_at DESC 
                                                LIMIT $limit OFFSET $offset";
                                            $pendingVendorsResult = mysqli_query($con, $pendingVendorsQuery);
                                            if ($pendingVendorsResult && mysqli_num_rows($pendingVendorsResult) > 0) {
                                                while ($vendor = mysqli_fetch_assoc($pendingVendorsResult)) {
                                                   if (!empty($vendor['updated_at'])) {
                                                        $dt = new DateTime($vendor['updated_at'], new DateTimeZone('UTC'));
                                                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                        $requestedAt = $dt->format('d M Y - h:i A');
                                                    } elseif (!empty($vendor['registration_date'])) {
                                                        $dt = new DateTime($vendor['registration_date'], new DateTimeZone('UTC'));
                                                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                        $requestedAt = $dt->format('d M Y');
                                                    } else {
                                                        $requestedAt = '—';
                                                    }
                                                    $isManufacture = !empty($vendor['manufacture_products']) ? $vendor['manufacture_products'] : 'No';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo $serialNumber++; ?></td>
                                                        <td><b><?php echo htmlspecialchars($vendor['business_name']); ?></b>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($vendor['business_type']); ?></td>

                                                        <td><?php echo htmlspecialchars($vendor['state_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($vendor['city']); ?></td>
                                                        <td><?php echo htmlspecialchars($vendor['owner_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($vendor['mobile_number']); ?></td>
                                                        <td><?php echo htmlspecialchars($isManufacture); ?></td>
                                                        <td>
                                                            <?php
                                                            echo $requestedAt;
                                                            ?>
</td>

                                                        <td>
                                                            <a href="./sellerDetail.php?vendor_id=<?php echo urlencode($vendor['id']); ?>"
                                                                type="button"
                                                                class="btn btn-secondary btn-wave waves-effect waves-light">View
                                                                Request</a>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                }
                                            } else {
                                                ?>
                                                <tr>
                                                    <td colspan="10" class="text-center">
                                                        <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                        <div class="text-muted">No pending / hold requests found.</div>
                                                    </td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0" id="pagination-ul">
                                        <!-- Previous Button -->
                                        <li class="page-item <?php if ($page <= 1)
                                            echo 'disabled'; ?>">
                                            <a class="page-link" href="#"
                                                data-page="<?php echo $page - 1; ?>">Previous</a>
                                        </li>

                                        <!-- Page Numbers -->
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php if ($page == $i)
                                                echo 'active'; ?>">
                                                <a class="page-link" href="#"
                                                    data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <!-- Next Button -->
                                        <li class="page-item <?php if ($page >= $totalPages)
                                            echo 'disabled'; ?>">
                                            <a class="page-link" href="#" data-page="<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
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

    <!-- Add this script before </body> to open modal on Submit -->
    <script>
        // Open confirm modal
        var confirmBtn = document.querySelector('.btn.btn-primary[style*="background:#3B4B6B"]');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var modal = new bootstrap.Modal(document.getElementById('confirmWithdrawModal'));
                modal.show();
            });
        }

        // Debounced AJAX search + pagination
        const searchInput = document.getElementById('pending-search');
        const clearBtn = document.getElementById('clear-search');
        const tbody = document.getElementById('pending-tbody');
        const pagination = document.getElementById('pagination-ul');
        let debounceTimer = null;

        function loadPending(page) {
            const q = (searchInput && searchInput.value) ? searchInput.value.trim() : '';
            const params = new URLSearchParams({ ajax: 'pending_list', page: String(page || 1), q });
            fetch('newRequest.php?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success) return;
                    if (tbody) tbody.innerHTML = data.tbody || '';
                    if (pagination) pagination.innerHTML = data.pagination || '';
                })
                .catch(() => { });
        }

        function scheduleLoad() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { loadPending(1); }, 300);
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearBtn.style.display = this.value.trim() ? 'inline' : 'none';
                scheduleLoad();
            });
        }
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) { searchInput.value = ''; }
                clearBtn.style.display = 'none';
                loadPending(1);
                searchInput && searchInput.focus();
            });
        }

        // Delegate pagination clicks
        document.addEventListener('click', function (e) {
            const a = e.target.closest('#pagination-ul a.page-link');
            if (a && a.dataset.page) {
                e.preventDefault();
                const nextPage = parseInt(a.dataset.page, 10) || 1;
                loadPending(nextPage);
            }
        });

        // Update export link with current search filter
        const exportLink = document.getElementById('exportPendingExcel');
        if (exportLink) {
            exportLink.addEventListener('click', function (ev) {
                const q = searchInput ? (searchInput.value || '') : '';
                const url = new URL(exportLink.href, window.location.origin);
                if (q) { url.searchParams.set('q', q); } else { url.searchParams.delete('q'); }
                exportLink.href = url.toString();
            });
        }
    </script>


</body>

</html>
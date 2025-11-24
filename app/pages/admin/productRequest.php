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
    $whereConditions[] = "(LOWER(p.status) = 'pending' OR LOWER(p.status) = 'under_review' OR p.status = 0 OR p.status IS NULL)";
    $params = [];
    $paramTypes = '';

    if (isset($_POST['vendor_id']) && !empty($_POST['vendor_id'])) {
        $whereConditions[] = "p.vendor_id = ?";
        $params[] = (int) $_POST['vendor_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
        $whereConditions[] = "p.category_id = ?";
        $params[] = (int) $_POST['category_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['sub_category_id']) && !empty($_POST['sub_category_id'])) {
        $whereConditions[] = "p.sub_category_id = ?";
        $params[] = (int) $_POST['sub_category_id'];
        $paramTypes .= 'i';
    }
    if (isset($_POST['q']) && !empty($_POST['q'])) {
        $searchTerm = trim($_POST['q']);
        $whereConditions[] = "(LOWER(p.product_name) LIKE ? OR LOWER(v.business_name) LIKE ? OR LOWER(p.hsn_id) LIKE ? OR LOWER(p.sku_id) LIKE ?)";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $params[] = "%{$searchTerm}%";
        $paramTypes .= 'ssss';
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $exportQuery = "
        SELECT 
            p.id,
            p.vendor_id,
            p.product_name,
            p.category_id,
            p.sub_category_id,
            p.mrp,
            p.selling_price,
            p.gst,
            p.hsn_id,
            p.sku_id,
            p.images,
            p.created_date,
            v.business_name AS vendor_name,
            v.vendor_id AS vendor_public_id,
            c.name AS category_name,
            sc.name AS sub_category_name
        FROM products p
        LEFT JOIN vendor_registration v ON v.id = p.vendor_id
        LEFT JOIN category c ON c.id = p.category_id
        LEFT JOIN sub_category sc ON sc.id = p.sub_category_id
        {$whereClause}
        ORDER BY p.created_date DESC
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
            $rows[] = $row;
        }
    }

    // Attempt to load PhpSpreadsheet
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
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Product Requests');

        $headers = [
            'S.No',
            'Seller ID',
            'Seller Name',
            'Product Name',
            'Category',
            'Sub Category',
            'HSN ID',
            'SKU ID',
            'MRP',
            'Price',
            'GST (%)',
            'Requested At'
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

        // Add headers
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($colLetters[$i] . '1', $h);
        }

        // Header styling and freeze pane
        $headerRange = $colLetters[0] . '1:' . $colLetters[count($colLetters) - 1] . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFEFEF');
        $sheet->freezePane('A2');

        // Add data
        $rowNum = 2;
        $serial = 1;
        foreach ($rows as $product) {
            $sheet->setCellValue($colLetters[0] . $rowNum, $serial++);
            $sheet->setCellValue($colLetters[1] . $rowNum, '#' . ($product['vendor_public_id'] ?: $product['vendor_id']));
            $sheet->setCellValue($colLetters[2] . $rowNum, $product['vendor_name']);
            $sheet->setCellValue($colLetters[3] . $rowNum, $product['product_name']);
            $sheet->setCellValue($colLetters[4] . $rowNum, $product['category_name']);
            $sheet->setCellValue($colLetters[5] . $rowNum, $product['sub_category_name']);
            $sheet->setCellValue($colLetters[6] . $rowNum, $product['hsn_id']);
            $sheet->setCellValue($colLetters[7] . $rowNum, $product['sku_id']);
            $sheet->setCellValue($colLetters[8] . $rowNum, $product['mrp']);
            $sheet->setCellValue($colLetters[9] . $rowNum, $product['selling_price']);

            // Format GST
            $gstVal = is_null($product['gst']) ? '' : rtrim(rtrim(number_format((float) $product['gst'], 2), '0'), '.');
            $sheet->setCellValue($colLetters[10] . $rowNum, $gstVal !== '' ? $gstVal . '%' : '');

            $sheet->setCellValue($colLetters[11] . $rowNum, date('d/m/Y h:i A', strtotime($product['created_date'])));
            $rowNum++;
        }

        // Auto-size columns
        foreach ($colLetters as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Thin borders around data region
        $lastDataRow = $rowNum - 1;
        if ($lastDataRow >= 1) {
            $sheet->getStyle($colLetters[0] . '1:' . $colLetters[count($colLetters) - 1] . $lastDataRow)
                ->getBorders()->getAllBorders()
                ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }

        // Clean any previous output buffers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        $filename = 'product_requests_' . date('Ymd_His') . '.xlsx';
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
    $filename = 'product_requests_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'S.No',
        'Seller ID',
        'Seller Name',
        'Product Name',
        'Category',
        'Sub Category',
        'HSN ID',
        'SKU ID',
        'MRP',
        'Price',
        'GST (%)',
        'Requested At'
    ]);
    $serial = 1;
    foreach ($rows as $product) {
        $gstVal = is_null($product['gst']) ? '' : rtrim(rtrim(number_format((float) $product['gst'], 2), '0'), '.');
        fputcsv($out, [
            $serial++,
            '#' . ($product['vendor_public_id'] ?: $product['vendor_id']),
            $product['vendor_name'],
            $product['product_name'],
            $product['category_name'],
            $product['sub_category_name'],
            $product['hsn_id'],
            $product['sku_id'],
            $product['mrp'],
            $product['selling_price'],
            $gstVal !== '' ? $gstVal . '%' : '',
            date('d/m/Y h:i A', strtotime($product['created_date']))
        ]);
    }
    fclose($out);
    exit;
}

// Handle bulk status update (Approve / On Hold / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = trim((string) ($_POST['bulk_action']));
    $allowedStatuses = ['approved', 'hold', 'rejected', 'pending'];
    if (in_array($bulkAction, $allowedStatuses, true) && !empty($_POST['product_ids']) && is_array($_POST['product_ids'])) {
        $ids = array_map('intval', $_POST['product_ids']);
        $ids = array_values(array_filter($ids, function ($v) {
            return $v > 0;
        }));
        if (!empty($ids)) {
            $idsList = implode(',', $ids);
            $statusEsc = mysqli_real_escape_string($con, $bulkAction);
            $comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
            $commentEsc = mysqli_real_escape_string($con, $comment);
            $commentSql = ($bulkAction === 'hold' || $bulkAction === 'rejected') ? ", status_note = '" . $commentEsc . "'" : '';
            $updateSql = "UPDATE products SET seen = 1, status = '" . $statusEsc . "'" . $commentSql . ", updated_date = NOW() WHERE id IN (" . $idsList . ")";
            mysqli_query($con, $updateSql);

            // Coin crediting & platform fee when approved
            if ($bulkAction === 'approved') {
                $coinMap = isset($_POST['coin_amounts']) && is_array($_POST['coin_amounts']) ? $_POST['coin_amounts'] : [];
                $feeMap = isset($_POST['platform_fees']) && is_array($_POST['platform_fees']) ? $_POST['platform_fees'] : [];
                // Normalize coin map to ints
                $perProductCoins = [];
                $perProductFees = [];
                foreach ($coinMap as $pid => $val) {
                    $pid = (int) $pid;
                    $val = (int) $val;
                    if ($pid > 0 && $val > 0) {
                        $perProductCoins[$pid] = $val;
                    }
                }
                foreach ($feeMap as $pid => $val) {
                    $pid = (int) $pid;
                    $fee = (float) $val;
                    if ($pid > 0 && $fee >= 0) {
                        $perProductFees[$pid] = $fee;
                    }
                }
                if (!empty($perProductCoins)) {
                    $idsRes = mysqli_query($con, "SELECT id, vendor_id FROM products WHERE id IN (" . $idsList . ")");
                    $vendorToTotal = [];
                    if ($idsRes) {
                        while ($r = mysqli_fetch_assoc($idsRes)) {
                            $pid = (int) $r['id'];
                            if (!isset($perProductCoins[$pid])) {
                                continue;
                            }
                            $coins = (int) $perProductCoins[$pid];
                            $vId = (int) $r['vendor_id'];
                            if (!isset($vendorToTotal[$vId]))
                                $vendorToTotal[$vId] = 0;
                            $vendorToTotal[$vId] += $coins;
                            // Update product coin column
                            @mysqli_query($con, "UPDATE products SET coin = " . $coins . " WHERE id = " . $pid);
                            // Update platform fee if provided
                            if (isset($perProductFees[$pid])) {
                                $feeVal = (float) $perProductFees[$pid];
                                @mysqli_query($con, "UPDATE products SET platform_fee = " . $feeVal . " WHERE id = " . $pid);
                            }
                        }
                    }
                    // Update each vendor's coin total

                }
            }
        }
    }
    // Flash message for result
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $msgMap = [
        'approved' => 'Selected products approved successfully.',
        'hold' => 'Selected products put on hold.',
        'rejected' => 'Selected products rejected.',
        'pending' => 'Selected products moved to pending.'
    ];
    $_SESSION['flash_type'] = ($bulkAction === 'approved') ? 'success' : (($bulkAction === 'rejected') ? 'danger' : 'warning');
    $_SESSION['flash_text'] = $msgMap[$bulkAction] ?? 'Operation completed.';
    $_SESSION['flash_refresh'] = true;
    header('Location: productRequest.php');
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
    <title>PRODUCT REQUEST | HADIDY</title>
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
                    class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Product Request</h1>
                    <div class=" d-flex align-items-center flex-wrap  gap-2">
                        <form method="post" id="bulkActionForm" class="d-flex align-items-center gap-2">
                            <input type="hidden" name="bulk_action" id="bulk_action" value="">
                            <input type="hidden" name="comment" id="bulk_comment" value="">
                            <button type="button" class="btn btn-secondary btn-wave waves-effect waves-light reject-btn"
                                id="btnApprove" data-bs-toggle="modal" data-bs-target="#approveModal">Approve</button>
                            <button type="button"
                                class="btn btn-outline-danger btn-wave waves-effect waves-light reject-btn" id="btnHold"
                                data-bs-toggle="modal" data-bs-target="#addAttributeModal">On Hold</button>
                            <button type="button" class="btn btn-danger btn-wave waves-effect waves-lights reject-btn"
                                id="btnReject" data-bs-toggle="modal"
                                data-bs-target="#addAttributeModal">Reject</button>
                        </form>
                    </div>
                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                    <?php
                                    $filterVendorId = isset($_POST['vendor_id']) ? (int) $_POST['vendor_id'] : 0;
                                    $filterCategoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
                                    $filterSubCategoryId = isset($_POST['sub_category_id']) ? (int) $_POST['sub_category_id'] : 0;

                                    $vendorsRes = mysqli_query($con, "SELECT id, business_name FROM vendor_registration ORDER BY business_name ASC");
                                    $categoriesRes = mysqli_query($con, "SELECT id, name FROM category ORDER BY name ASC");
                                    $searchTerm = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
                                    $subCategoriesRes = null;
                                    if ($filterCategoryId > 0) {
                                        $subCategoriesRes = mysqli_query($con, "SELECT id, name FROM sub_category WHERE category_id = " . $filterCategoryId . " ORDER BY name ASC");
                                    }
                                    ?>
                                    <form method="post" id="filterForm"
                                        class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                        <input type="hidden" name="page" id="pageInput"
                                            value="<?php echo isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1; ?>">
                                        <div class="selecton-order">
                                            <select name="vendor_id" id="vendorSelect"
                                                class="form-select form-select-lg" onchange="this.form.submit()">
                                                <option value="0" <?php echo $filterVendorId === 0 ? ' selected' : ''; ?>>
                                                    Seller (All)</option>
                                                <?php if ($vendorsRes) {
                                                    while ($v = mysqli_fetch_assoc($vendorsRes)) { ?>
                                                        <option value="<?php echo (int) $v['id']; ?>" <?php echo $filterVendorId === (int) $v['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($v['business_name']); ?>
                                                        </option>
                                                    <?php }
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="selecton-order">
                                            <select name="category_id" id="categorySelect"
                                                class="form-select form-select-lg" onchange="this.form.submit()">
                                                <option value="0" <?php echo $filterCategoryId === 0 ? ' selected' : ''; ?>>Category (All)</option>
                                                <?php if ($categoriesRes) {
                                                    while ($c = mysqli_fetch_assoc($categoriesRes)) { ?>
                                                        <option value="<?php echo (int) $c['id']; ?>" <?php echo $filterCategoryId === (int) $c['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($c['name']); ?>
                                                        </option>
                                                    <?php }
                                                } ?>
                                            </select>
                                        </div>
                                        <div class="selecton-order">
                                            <select name="sub_category_id" id="subCategorySelect"
                                                class="form-select form-select-lg selecton-order"
                                                onchange="this.form.submit()">
                                                <option value="0" <?php echo $filterSubCategoryId === 0 ? ' selected' : ''; ?>>Sub Category (All)</option>
                                                <?php
                                                if ($subCategoriesRes) {
                                                    while ($sc = mysqli_fetch_assoc($subCategoriesRes)) {
                                                        ?>
                                                        <option value="<?php echo (int) $sc['id']; ?>" <?php echo $filterSubCategoryId === (int) $sc['id'] ? ' selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($sc['name']); ?>
                                                        </option>
                                                        <?php
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="selecton-order">
                                        <?php $searchTerm = isset($_POST['q']) ? trim((string) $_POST['q']) : ''; ?>
                                        <input type="search" class="form-control" placeholder="Search " name="q"
                                            form="filterForm" value="<?php echo htmlspecialchars($searchTerm); ?>"
                                            aria-describedby="button-addon2" id="searchBox">
                                    </div>
                                    <div>
                                        <button type="button" id="exportToExcel" class="btn-down-excle1 w-100"><i
                                                class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</button>
                                    </div>
                                </div>
                            </div>
                            <form method="POST" id="exportForm" style="display:none;">
                                <input type="hidden" name="export_excel" value="1">
                                <input type="hidden" name="vendor_id" id="exp_vendor_id"
                                    value="<?php echo $filterVendorId; ?>">
                                <input type="hidden" name="category_id" id="exp_category_id"
                                    value="<?php echo $filterCategoryId; ?>">
                                <input type="hidden" name="sub_category_id" id="exp_sub_category_id"
                                    value="<?php echo $filterSubCategoryId; ?>">
                                <input type="hidden" name="q" id="exp_search"
                                    value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </form>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">
                                                <input type="checkbox" id="selectAllProducts" />
                                            </th>
                                            <th>Seller</th>
                                            <th scope="col">Product</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Sub Category</th>
                                            <th scope="col">HSN ID</th>
                                            <th scope="col">MRP</th>
                                            <th scope="col">Price</th>
                                            <th scope="col">GST</th>
                                            <th scope="col">Requested At</th>
                                            <th scope="col">View</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        // Build WHERE
                                        $where = [];
                                        $where[] = "(LOWER(p.status) = 'pending' OR LOWER(p.status) = 'under_review' OR p.status = 0 OR p.status IS NULL)";
                                        if ($filterVendorId > 0) {
                                            $where[] = "p.vendor_id = " . $filterVendorId;
                                        }
                                        if ($filterCategoryId > 0) {
                                            $where[] = "p.category_id = " . $filterCategoryId;
                                        }
                                        if ($filterSubCategoryId > 0) {
                                            $where[] = "p.sub_category_id = " . $filterSubCategoryId;
                                        }
                                        if ($searchTerm !== '') {
                                            $safeQ = mysqli_real_escape_string($con, strtolower($searchTerm));
                                            $where[] = "(LOWER(p.product_name) LIKE '%" . $safeQ . "%' OR LOWER(v.business_name) LIKE '%" . $safeQ . "%' OR LOWER(p.hsn_id) LIKE '%" . $safeQ . "%' OR LOWER(p.sku_id) LIKE '%" . $safeQ . "%')";
                                        }
                                        $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

                                        // Pagination
                                        $perPage = 10;
                                        $currentPage = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
                                        $countSql = "SELECT COUNT(*) AS total
                 FROM products p
                 LEFT JOIN vendor_registration v ON v.id = p.vendor_id
                 LEFT JOIN category c ON c.id = p.category_id
                 LEFT JOIN sub_category sc ON sc.id = p.sub_category_id
                 $whereSql";
                                        $countRes = mysqli_query($con, $countSql);
                                        $totalRows = 0;
                                        if ($countRes) {
                                            $rowC = mysqli_fetch_assoc($countRes);
                                            $totalRows = (int) ($rowC['total'] ?? 0);
                                        }
                                        $totalPages = max(1, (int) ceil($totalRows / $perPage));
                                        if ($currentPage > $totalPages) {
                                            $currentPage = $totalPages;
                                        }
                                        $offset = ($currentPage - 1) * $perPage;

                                        // Data query with pagination
                                        $pendingProductsQuery = "SELECT p.id, p.vendor_id, p.product_name, p.category_id, p.sub_category_id, p.mrp, p.selling_price, p.gst, p.hsn_id, p.images, p.created_date,p.updated_date,p.coin, p.platform_fee,
                                    v.business_name AS vendor_name,
                                    c.name AS category_name,
                                    sc.name AS sub_category_name
                             FROM products p
                             LEFT JOIN vendor_registration v ON v.id = p.vendor_id
                             LEFT JOIN category c ON c.id = p.category_id
                             LEFT JOIN sub_category sc ON sc.id = p.sub_category_id
                             $whereSql
                             ORDER BY p.updated_date DESC
                             LIMIT $perPage OFFSET $offset";
                                        //  print_r($pendingProductsQuery);
                                        $pendingResult = mysqli_query($con, $pendingProductsQuery);
                                        if (!$pendingResult) {
                                            echo '<tr><td colspan="11" class="text-danger">No pending products.</td></tr>';
                                        } elseif (mysqli_num_rows($pendingResult) === 0) {
                                            echo '<tr><td colspan="11" class="text-center">
                                                    <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                    <div class="text-muted">No pending products.</div>
                                                </td></tr>';
                                        } else {
                                            while ($row = mysqli_fetch_assoc($pendingResult)) {
                                                $imgSrc = '';
                                                if (!empty($row['images'])) {
                                                    $decoded = @json_decode($row['images'], true);
                                                    if (is_array($decoded) && count($decoded) > 0) {
                                                        $first = reset($decoded);
                                                        if (is_string($first)) {
                                                            $candidate = trim($first);
                                                            if ($candidate !== '') {
                                                                if (preg_match('/^(https?:)?\/\//i', $candidate) || str_starts_with($candidate, '/')) {
                                                                    $imgSrc = $candidate;
                                                                } else {
                                                                    $imgSrc = $vendor_baseurl . $candidate;
                                                                }
                                                            }
                                                        } elseif (is_array($first)) {
                                                            $candidate = $first['url'] ?? $first['path'] ?? '';
                                                            if ($candidate !== '') {
                                                                if (preg_match('/^(https?:)?\/\//i', $candidate) || str_starts_with($candidate, '/')) {
                                                                    $imgSrc = $candidate;
                                                                } else {
                                                                    $imgSrc = $vendor_baseurl . $candidate;
                                                                }
                                                            }
                                                        }
                                                    } else {
                                                        $raw = trim((string) $row['images']);
                                                        if ($raw !== '') {
                                                            $parts = explode(',', $raw);
                                                            $candidate = trim($parts[0]);
                                                            if ($candidate !== '') {
                                                                if (preg_match('/^(https?:)?\/\//i', $candidate) || str_starts_with($candidate, '/')) {
                                                                    $imgSrc = $candidate;
                                                                } else {
                                                                    $imgSrc = $vendor_baseurl . $candidate;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                                if ($imgSrc === '') {
                                                    $imgSrc = $vendor_baseurl . 'uploads/vendors/no-product.png';
                                                }
                                                ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" class="product-checkbox" name="product_ids[]"
                                                            form="bulkActionForm" value="<?php echo (int) $row['id']; ?>"
                                                            data-pname="<?php echo htmlspecialchars($row['product_name']); ?>"
                                                            data-coin="<?php echo htmlspecialchars($row['coin'] ?? 0); ?>"
                                                            data-fee="<?php echo htmlspecialchars($row['platform_fee'] ?? 0); ?>" />

                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['vendor_name'] ?? ''); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div class="product-img">
                                                                <img src="<?php if ($imgSrc !== '') {
                                                                    echo htmlspecialchars($imgSrc);
                                                                } else {
                                                                    echo $vendor_baseurl . '/assets/images/default.png';
                                                                } ?>" alt=""
                                                                    style="width:32px;height:32px;border-radius:6px;">
                                                            </div>
                                                            <div class="product-name-text">
                                                                <H4>
                                                                    <?php echo htmlspecialchars($row['product_name']); ?>
                                                                </H4>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-light1 text-dark"><?php echo htmlspecialchars($row['category_name'] ?? ''); ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($row['sub_category_name'] ?? ''); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($row['hsn_id']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo number_format((float) $row['mrp'], 2); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo number_format((float) $row['selling_price'], 2); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            $gstVal = is_null($row['gst']) ? '' : rtrim(rtrim(number_format((float) $row['gst'], 2), '0'), '.');
                                                            echo $gstVal !== '' ? htmlspecialchars($gstVal) . '%' : '';
                                                            ?>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo !empty($row['created_date']) ? date('d,M Y ', strtotime($row['created_date'])) : ''; ?>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="./viewProduct.php?id=<?php echo (int) $row['id']; ?>"
                                                                class="i-icon-product"><i class="fa-solid fa-eye"></i></a>
                                                        </div>
                                                    </td>

                                                </tr>
                                                <?php
                                            }
                                        }
                                        ?>

                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($totalPages > 1) {
                                            $prevPage = max(1, $currentPage - 1);
                                            $nextPage = min($totalPages, $currentPage + 1);
                                            ?>
                                            <li class="page-item<?php echo $currentPage == 1 ? ' disabled' : ''; ?>">
                                                <button type="button" class="page-link"
                                                    onclick="setPageAndSubmit(<?php echo $prevPage; ?>)">Previous</button>
                                            </li>
                                            <?php
                                            $start = max(1, $currentPage - 2);
                                            $end = min($totalPages, $currentPage + 2);
                                            if ($start > 1) {
                                                ?>
                                                <li class="page-item"><button type="button" class="page-link"
                                                        onclick="setPageAndSubmit(1)">1</button></li>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php }
                                            for ($p = $start; $p <= $end; $p++) { ?>
                                                <li class="page-item<?php echo $p == $currentPage ? ' active' : ''; ?>">
                                                    <button type="button" class="page-link"
                                                        onclick="setPageAndSubmit(<?php echo $p; ?>)"><?php echo $p; ?></button>
                                                </li>
                                            <?php }
                                            if ($end < $totalPages) { ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <li class="page-item"><button type="button" class="page-link"
                                                        onclick="setPageAndSubmit(<?php echo $totalPages; ?>)"><?php echo $totalPages; ?></button>
                                                </li>
                                            <?php } ?>
                                            <li
                                                class="page-item<?php echo $currentPage == $totalPages ? ' disabled' : ''; ?>">
                                                <button type="button" class="page-link"
                                                    onclick="setPageAndSubmit(<?php echo $nextPage; ?>)">Next</button>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </nav>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->
            </div>
            <!-- End::app-content -->

            <!-- Approve Confirmation Modal -->
            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content"
                        style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="modal-body text-center p-4">
                            <!-- Icon -->
                            <div class="mb-3">
                                <div
                                    style="width: 60px; height: 60px; background-color: #3B4B6B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-check" style="color: white; font-size: 24px;"></i>
                                </div>
                            </div>

                            <!-- Message -->
                            <h5 class="mb-3" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                                Enter coins and platform fee per product
                            </h5>

                            <!-- Dynamic coins list -->
                            <div id="approveCoinsList" class="text-start" style="max-height:260px;overflow:auto;"></div>

                            <!-- Buttons -->
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="button" class="btn btn-outline-danger" id="approveNoBtn"
                                    data-bs-dismiss="modal"
                                    style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    No
                                </button>
                                <button type="button" class="btn btn-primary" id="approveYesBtn"
                                    style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    Yes
                                </button>
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

        <!-- JavaScript for Address Truncation -->

        <!-- Add Comment Modal -->

        <div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content add-attribute-modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Comment</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <form id="attributeForm" onsubmit="return false;">
                            <div class="mb-3">
                                <label for="variant" class="form-label fw-semibold">Comment <span
                                        class="text-danger">*</span></label>
                                <textarea class="form-control form-control-lg" id="variant"
                                    placeholder=" Enter Comment"></textarea>
                            </div>
                            <button type="submit" class="btn save-attribute-btn w-100">Save</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal fade" id="approveConfirmModal" tabindex="-1" aria-labelledby="approveConfirmModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content"
                    style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <div class="modal-body text-center p-4">
                        <!-- Icon -->
                        <div class="mb-3">
                            <div
                                style="width: 60px; height: 60px;border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png"
                                    alt="Approve Icon" style="width:60px; height:60px;">
                            </div>
                        </div>

                        <!-- Message -->
                        <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                            Are you sure, you want to Approve <br> the Product ?
                        </h5>

                        <!-- Buttons -->
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="button" class="btn btn-outline-danger" id="cancelNoBtn"
                                data-bs-dismiss="modal"
                                style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                No
                            </button>
                            <button type="button" class="btn btn-primary" id="cancelYesBtn"
                                style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                Yes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <script>
            document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
                const dropZoneElement = inputElement.closest(".drop-zone");

                dropZoneElement.addEventListener("click", (e) => {
                    inputElement.click();
                });

                inputElement.addEventListener("change", (e) => {
                    if (inputElement.files.length) {
                        updateThumbnail(dropZoneElement, inputElement.files[0]);
                    }
                });

                dropZoneElement.addEventListener("dragover", (e) => {
                    e.preventDefault();
                    dropZoneElement.classList.add("drop-zone--over");
                });

                ["dragleave", "dragend"].forEach((type) => {
                    dropZoneElement.addEventListener(type, (e) => {
                        dropZoneElement.classList.remove("drop-zone--over");
                    });
                });

                dropZoneElement.addEventListener("drop", (e) => {
                    e.preventDefault();

                    if (e.dataTransfer.files.length) {
                        inputElement.files = e.dataTransfer.files;
                        updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                    }

                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });
        </script>

        <!-- Modal JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Function to clean up modal backdrop
                function cleanupModalBackdrop() {
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(function (backdrop) {
                        backdrop.remove();
                    });
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }

                // Global event listener for ALL modals to clean up backdrop
                document.addEventListener('hidden.bs.modal', function (event) {
                    cleanupModalBackdrop();
                });

                // Approve modal handlers (coins/platform fee modal)
                const approveModalEl = document.getElementById('approveModal');
                if (approveModalEl) {
                    const approveModal = new bootstrap.Modal(approveModalEl);
                    const approveNoBtn = document.getElementById('approveNoBtn');
                    const approveYesBtn = document.getElementById('approveYesBtn');
                    const approveCoinsList = document.getElementById('approveCoinsList');
                    const bulkForm2 = document.getElementById('bulkActionForm');
                    const bulkAction2 = document.getElementById('bulk_action');

                    // Enhanced No button handler with backdrop cleanup
                    if (approveNoBtn) {
                        approveNoBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            approveModal.hide();
                            // Clean up backdrop after modal closes
                            setTimeout(cleanupModalBackdrop, 300);
                        });
                    }

                    // Clean up backdrop when modal is fully hidden
                    approveModalEl.addEventListener('hidden.bs.modal', function () {
                        cleanupModalBackdrop();
                    });
                    if (approveYesBtn) approveYesBtn.addEventListener('click', () => {
                        // Remove previous generated inputs
                        bulkForm2.querySelectorAll('input[name^="coin_amounts["]').forEach(n => n.remove());
                        // Add current ones
                        approveCoinsList?.querySelectorAll('input[data-coin-for]').forEach(inp => {
                            const pid = inp.getAttribute('data-coin-for');
                            const val = parseInt(inp.value || '0', 10);
                            if (pid && !isNaN(val) && val >= 0) {
                                const h = document.createElement('input');
                                h.type = 'hidden';
                                h.name = `coin_amounts[${pid}]`;
                                h.value = String(val);
                                bulkForm2.appendChild(h);
                            }
                        });
                        approveCoinsList?.querySelectorAll('input[data-fee-for]').forEach(inp => {
                            const pid = inp.getAttribute('data-fee-for');
                            const val = parseFloat(inp.value || '0');
                            if (pid && !isNaN(val) && val >= 0) {
                                const h = document.createElement('input');
                                h.type = 'hidden';
                                h.name = `platform_fees[${pid}]`;
                                h.value = String(val);
                                bulkForm2.appendChild(h);
                            }
                        });
                        bulkAction2.value = 'approved';
                        bulkForm2.submit();
                    });

                    // Populate dynamic list when modal opens
                    approveModalEl.addEventListener('show.bs.modal', function () {
                        if (!approveCoinsList) return;
                        approveCoinsList.innerHTML = '';
                        const selected = document.querySelectorAll('.product-checkbox:checked');
                        if (selected.length === 0) {
                            approveCoinsList.innerHTML = '<div class="text-muted">No products selected.</div>';
                            return;
                        }

                        selected.forEach(cb => {
                            const pid = cb.value;
                            const pname = cb.getAttribute('data-pname') || 'Product';
                            const existingCoin = cb.getAttribute('data-coin') || '';
                            const existingFee = cb.getAttribute('data-fee') || '';

                            const row = document.createElement('div');
                            row.className = 'd-flex align-items-center justify-content-between mb-2 gap-2';
                            row.innerHTML = `
                            <div class="me-2" style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                ${pname}
                            </div>
                            <div class="d-flex gap-2" style="width:320px">
                                <div class="input-group input-group-sm" style="width: 50%">
                                    <span class="input-group-text">Coin</span>
                                    <input type="number" class="form-control" min="0" step="1" 
                                        value="${existingCoin}" placeholder="Coin" aria-label="Coin" data-coin-for="${pid}">
                                </div>
                                <div class="input-group input-group-sm" style="width: 50%">
                                    <span class="input-group-text">Platform</span>
                                    <input type="number" class="form-control" min="0" step="0.01" 
                                        value="${existingFee}" placeholder="Platform Fee" aria-label="Platform Fee" data-fee-for="${pid}">
                                </div>
                            </div>`;
                            approveCoinsList.appendChild(row);
                        });
                    });

                }

                // Handle approveConfirmModal (second confirmation modal)
                const approveConfirmModalEl = document.getElementById('approveConfirmModal');
                if (approveConfirmModalEl) {
                    const approveConfirmModal = new bootstrap.Modal(approveConfirmModalEl);
                    const cancelNoBtn = document.getElementById('cancelNoBtn');
                    const cancelYesBtn = document.getElementById('cancelYesBtn');

                    // Enhanced No button handler with backdrop cleanup
                    if (cancelNoBtn) {
                        cancelNoBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            approveConfirmModal.hide();
                            // Clean up backdrop after modal closes
                            setTimeout(cleanupModalBackdrop, 300);
                        });
                    }

                    // Clean up backdrop when modal is fully hidden
                    approveConfirmModalEl.addEventListener('hidden.bs.modal', function () {
                        cleanupModalBackdrop();
                    });
                }
            });
        </script>


        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectAll = document.getElementById('selectAllProducts');
                const productCheckboxes = document.querySelectorAll('.product-checkbox');

                selectAll.addEventListener('change', function () {
                    productCheckboxes.forEach(function (cb) {
                        cb.checked = selectAll.checked;
                    });
                });

                // Optional: If any product checkbox is unchecked, uncheck the header checkbox
                productCheckboxes.forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        if (!cb.checked) {
                            selectAll.checked = false;
                        } else if ([...productCheckboxes].every(c => c.checked)) {
                            selectAll.checked = true;
                        }
                    });
                });
            });
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Debounced search auto-submit
                const searchBox = document.getElementById('searchBox');
                const filterForm = document.getElementById('filterForm');
                const pageInput = document.getElementById('pageInput');
                let searchTimer = null;

                function submitFilter() {
                    if (pageInput) pageInput.value = 1; // reset to first page on change
                    filterForm?.submit();
                }
                searchBox?.addEventListener('input', function () {
                    if (searchTimer) clearTimeout(searchTimer);
                    searchTimer = setTimeout(submitFilter, 300);
                });

                window.setPageAndSubmit = function (page) {
                    if (pageInput) pageInput.value = page;
                    filterForm?.submit();
                }

                const approveBtn = document.getElementById('btnApprove');
                const holdBtn = document.getElementById('btnHold');
                const rejectBtn = document.getElementById('btnReject');
                const bulkForm = document.getElementById('bulkActionForm');
                const bulkAction = document.getElementById('bulk_action');
                const bulkComment = document.getElementById('bulk_comment');
                const commentTextarea = document.getElementById('variant');
                const commentModalEl = document.getElementById('addAttributeModal');
                const commentModal = new bootstrap.Modal(commentModalEl);

                function anyChecked() {
                    const cbs = document.querySelectorAll('.product-checkbox');
                    return Array.from(cbs).some(cb => cb.checked);
                }

                approveBtn?.addEventListener('click', function () {
                    if (!anyChecked()) {
                        alert('Please select at least one product.');
                        return;
                    }
                    // Open confirmation modal; actual submit happens on Yes
                    const approveModalLocal = new bootstrap.Modal(document.getElementById('approveModal'));
                    approveModalLocal.show();
                });

                let pendingAction = '';
                holdBtn?.addEventListener('click', function () {
                    if (!anyChecked()) {
                        alert('Please select at least one product.');
                        return;
                    }
                    pendingAction = 'hold';
                    commentTextarea.value = '';
                });
                rejectBtn?.addEventListener('click', function () {
                    if (!anyChecked()) {
                        alert('Please select at least one product.');
                        return;
                    }
                    pendingAction = 'rejected';
                    commentTextarea.value = '';
                });

                const attributeForm = document.getElementById('attributeForm');
                attributeForm?.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const text = (commentTextarea?.value || '').trim();
                    if (text === '') {
                        alert('Please enter a comment.');
                        return;
                    }
                    bulkComment.value = text;
                    bulkAction.value = pendingAction || 'hold';
                    commentModal.hide();
                    bulkForm.submit();
                });

                // Export to Excel functionality
                const exportBtn = document.getElementById('exportToExcel');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function () {
                        // Copy current filter values to export form
                        const vendorSelect = document.getElementById('vendorSelect');
                        const categorySelect = document.getElementById('categorySelect');
                        const subCategorySelect = document.getElementById('subCategorySelect');
                        const searchInput = document.getElementById('searchBox');

                        if (vendorSelect) {
                            document.getElementById('exp_vendor_id').value = vendorSelect.value;
                        }
                        if (categorySelect) {
                            document.getElementById('exp_category_id').value = categorySelect.value;
                        }
                        if (subCategorySelect) {
                            document.getElementById('exp_sub_category_id').value = subCategorySelect.value;
                        }
                        if (searchInput) {
                            document.getElementById('exp_search').value = searchInput.value;
                        }

                        // Submit export form
                        document.getElementById('exportForm').submit();
                    });
                }
            });
        </script>
</body>

</html>
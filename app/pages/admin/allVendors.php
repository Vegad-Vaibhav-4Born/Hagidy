<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}
function formatDateForExcel($dateString)
{
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00')
        return '';
    try {
        // Assume DB time = server time, but we always want current local (IST)
        $dt = new DateTime($dateString);
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));

        // Adjust to current IST offset difference
        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));

        // Optional: If you literally want it to show “as if approved now”, uncomment below:
        // $dt = $now;

        return $dt->format('d M Y, h:i A');
    } catch (Exception $e) {
        return $dateString;
    }
}
if (isset($_GET['export']) && strtolower((string) $_GET['export']) === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }

    // Get filter parameters
    $exp_search = $_GET['search'] ?? ($_POST['search'] ?? '');
    $exp_category = $_GET['category'] ?? ($_POST['category'] ?? '');
    $exp_active = $_GET['active_status'] ?? ($_POST['active_status'] ?? '');
    $exp_approval = $_GET['approval_status'] ?? ($_POST['approval_status'] ?? '');
    $exp_date_from = $_GET['date_from'] ?? ($_POST['date_from'] ?? '');
    $exp_date_to = $_GET['date_to'] ?? ($_POST['date_to'] ?? '');

    $exp_where_conditions = ["vr.status IN ('approved','rejected','hold')"];
    $exp_params = [];
    $exp_joins = [];

    // Combined search
    if (!empty($exp_search)) {
        $exp_joins[] = "LEFT JOIN state s ON vr.state = s.id";
        $exp_where_conditions[] = "(vr.business_name LIKE ? OR s.name LIKE ? OR vr.pincode LIKE ?)";
        $exp_params = array_fill(0, 3, "%$exp_search%");
    }
    if (!empty($exp_category)) {
        $exp_where_conditions[] = "FIND_IN_SET(?, vr.product_categories)";
        $exp_params[] = $exp_category;
    }
    if (!empty($exp_active)) {
        $exp_where_conditions[] = "vr.active_status = ?";
        $exp_params[] = $exp_active;
    }
    if (in_array($exp_approval, ['approved', 'rejected', 'hold'], true)) {
        $exp_where_conditions[] = "vr.status = ?";
        $exp_params[] = $exp_approval;
    }
    if (!empty($exp_date_from)) {
        $exp_where_conditions[] = "DATE(vr.registration_date) >= ?";
        $exp_params[] = $exp_date_from;
    }
    if (!empty($exp_date_to)) {
        $exp_where_conditions[] = "DATE(vr.registration_date) <= ?";
        $exp_params[] = $exp_date_to;
    }

    $exp_join_clause = implode(' ', array_unique($exp_joins));
    $exp_where_clause = implode(' AND ', $exp_where_conditions);

    // Load spreadsheet package
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        while (ob_get_level() > 0)
            @ob_end_clean();
        header('Content-Type: text/plain');
        echo 'Excel export packages not found. Please install PhpSpreadsheet via Composer.';
        exit;
    }

    // Prepare and run query
    $exp_sql = "SELECT vr.* FROM vendor_registration vr $exp_join_clause WHERE $exp_where_clause ORDER BY vr.id DESC";
    $stmt = mysqli_prepare($con, $exp_sql);
    if ($stmt) {
        if (!empty($exp_params)) {
            $types = str_repeat('s', count($exp_params));
            mysqli_stmt_bind_param($stmt, $types, ...$exp_params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
    } else {
        $res = false;
    }

    // Initialize spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Vendors');

    // Headers (Camel Case, removed ID and Password)
    $headers = [
        'A1' => 'Vendor ID',
        'B1' => 'Business Name',
        'C1' => 'Business Address',
        'D1' => 'Business Type',
        'E1' => 'Other Business Type',
        'F1' => 'State',
        'G1' => 'City',
        'H1' => 'Pincode',
        'I1' => 'Owner Name',
        'J1' => 'Mobile Number',
        'K1' => 'WhatsApp Number',
        'L1' => 'Email',
        'M1' => 'Website / Social',
        'N1' => 'GST Number',
        'O1' => 'Account Name',
        'P1' => 'Account Type',
        'Q1' => 'Account Number',
        'R1' => 'Bank Name',
        'S1' => 'IFSC Code',
        'T1' => 'Product Categories',
        'U1' => 'Average Products',
        'V1' => 'Manufacture Products',
        'W1' => 'Signature Name',
        'X1' => 'Registration Date',
        'Y1' => 'Status',
        'Z1' => 'Created At',
        'AA1' => 'Updated At',
        'AB1' => 'No. of Products',
        'AC1' => 'Approved At',
        'AD1' => 'Rejected At',
        'AE1' => 'Hold At'
    ];

    foreach ($headers as $cell => $label) {
        $sheet->setCellValue($cell, $label);
    }
    $sheet->getStyle('A1:AE1')->getFont()->setBold(true);

    $row = 2;
    if ($res) {
        while ($vendor = mysqli_fetch_assoc($res)) {
            // Resolve business type name
            $btName = '';
            if (!empty($vendor['business_type'])) {
                $bt_rs = mysqli_query($con, "SELECT type_name FROM business_types WHERE id=" . (int) $vendor['business_type'] . " LIMIT 1");
                $btName = mysqli_fetch_assoc($bt_rs)['type_name'] ?? '';
            }

            // Resolve state name
            $stateName = '';
            if (!empty($vendor['state'])) {
                $st_rs = mysqli_query($con, "SELECT name FROM state WHERE id=" . (int) $vendor['state'] . " LIMIT 1");
                $stateName = mysqli_fetch_assoc($st_rs)['name'] ?? '';
            }

            // Resolve categories
            $catNames = '';
            if (!empty($vendor['product_categories'])) {
                $ids = array_filter(array_map('intval', explode(',', $vendor['product_categories'])));
                if (!empty($ids)) {
                    $cat_rs = mysqli_query($con, "SELECT name FROM category WHERE id IN (" . implode(',', $ids) . ")");
                    $catNames = implode(', ', array_column(mysqli_fetch_all($cat_rs, MYSQLI_ASSOC), 'name'));
                }
            }

            // Product count
            $pc = 0;
            $pc_q = mysqli_query($con, "SELECT COUNT(*) AS count FROM products WHERE vendor_id=" . (int) $vendor['id']);
            $pc = mysqli_fetch_assoc($pc_q)['count'] ?? 0;

            // Average product number
            $get_num_avg_product = mysqli_fetch_assoc(mysqli_query($con, "SELECT number FROM productno_list WHERE id=" . (int) $vendor['avg_products']));
            $num_avg_product = $get_num_avg_product['number'] ?? '';

            // Write row (skipping id & password)
            $sheet->fromArray([
                $vendor['vendor_id'] ?? '',
                $vendor['business_name'] ?? '',
                $vendor['business_address'] ?? '',
                $btName ?: $vendor['business_type'] ?? '',
                $vendor['business_type_other'] ?? '',
                $stateName ?: $vendor['state'] ?? '',
                $vendor['city'] ?? '',
                $vendor['pincode'] ?? '',
                $vendor['owner_name'] ?? '',
                $vendor['mobile_number'] ?? '',
                $vendor['whatsapp_number'] ?? '',
                $vendor['email'] ?? '',
                $vendor['website_social'] ?? '',
                $vendor['gst_number'] ?? '',
                $vendor['account_name'] ?? '',
                $vendor['account_type'] ?? '',
                $vendor['account_number'] ?? '',
                $vendor['bank_name'] ?? '',
                $vendor['ifsc_code'] ?? '',
                $catNames,
                $num_avg_product,
                $vendor['manufacture_products'] ?? '',
                $vendor['signature_name'] ?? '',
                formatDateForExcel($vendor['registration_date'] ?? ''),
                $vendor['status'] ?? '',
                formatDateForExcel($vendor['created_at'] ?? ''),
                formatDateForExcel($vendor['updated_at'] ?? ''),
                $pc,
                $vendor['approved_at'] ?? '',
                $vendor['rejected_at'] ?? '',
                $vendor['hold_at'] ?? ''
            ], null, "A$row");
            $row++;
        }
    }

    // Auto-size columns
    foreach (range('A', 'AE') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Output file
    while (ob_get_level() > 0)
        @ob_end_clean();
    $fileName = 'vendors_export_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: public');
    header('Expires: 0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Inline AJAX endpoint for listing vendors without full page reload
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: text/html; charset=UTF-8');
    // Read filters from query string (mirror form fields)
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
    $active_status_filter = isset($_GET['active_status']) ? $_GET['active_status'] : '';
    $date_from_filter = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to_filter = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
    $per_page = in_array($per_page, [5, 10, 25, 50]) ? $per_page : 10;
    $current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Show only vendors that are not pending
    $where_conditions = ["vr.status IN ('approved','rejected','hold')"];
    $params = [];
    $joins = [];

    // Combined search: business name, state name, and pincode
    if ($search_term !== '') {
        $joins[] = "LEFT JOIN state s ON vr.state = s.id";
        $where_conditions[] = "(vr.business_name LIKE ? OR s.name LIKE ? OR vr.pincode LIKE ?)";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
    }
    if (!empty($category_filter)) {
        $where_conditions[] = "FIND_IN_SET(?, vr.product_categories)";
        $params[] = $category_filter;
    }
    if (!empty($active_status_filter)) {
        $where_conditions[] = "vr.active_status = ?";
        $params[] = $active_status_filter;
    }
    if (!empty($date_from_filter)) {
        $where_conditions[] = "DATE(vr.registration_date) >= ?";
        $params[] = $date_from_filter;
    }
    if (!empty($date_to_filter)) {
        $where_conditions[] = "DATE(vr.registration_date) <= ?";
        $params[] = $date_to_filter;
    }

    $join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';
    $where_clause = implode(' AND ', $where_conditions);

    // Count
    $count_sql = "SELECT COUNT(*) as total FROM vendor_registration vr $join_clause WHERE $where_clause";
    $count_stmt = mysqli_prepare($con, $count_sql);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_records = $count_result ? (int) mysqli_fetch_assoc($count_result)['total'] : 0;
    $total_pages = max(1, (int) ceil($total_records / $per_page));
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $per_page;
    }

    // Data
    $sql = "SELECT vr.* FROM vendor_registration vr $join_clause WHERE $where_clause ORDER BY vr.id DESC LIMIT $per_page OFFSET $offset";
    $stmt = mysqli_prepare($con, $sql);
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    ob_start();
    if (!$result || mysqli_num_rows($result) === 0) {
        echo '<tr><td colspan="10" class="text-center py-4"><div class="alert alert-info"><i class="fe fe-info me-2"></i>No vendors found (approved/hold/rejected).</div></td></tr>';
    } else {
        $index = $offset;
        while ($vendor = mysqli_fetch_assoc($result)) {
            $index++;
            $product_count_stmt = mysqli_prepare($con, "SELECT COUNT(*) AS count FROM products WHERE vendor_id = ?");
            $pc = 0;
            if ($product_count_stmt) {
                mysqli_stmt_bind_param($product_count_stmt, "i", $vendor['id']);
                mysqli_stmt_execute($product_count_stmt);
                $product_count_result = mysqli_stmt_get_result($product_count_stmt);
                if ($product_count_result && ($r = mysqli_fetch_assoc($product_count_result))) {
                    $pc = (int) $r['count'];
                }
                mysqli_stmt_close($product_count_stmt);
            }
            echo '<tr>';
            echo '<td>' . ($index) . '</td>';
            echo '<td><b>#' . str_pad((int) $vendor['id'], 6, '0', STR_PAD_LEFT) . '</b></td>';
            echo '<td>' . htmlspecialchars($vendor['business_name']) . '</td>';
            echo '<td><span class="badge bg-light text-dark">' . $pc . '</span></td>';
            echo '<td>0</td><td>0</td><td>₹0</td>';
            $badge = ($vendor['active_status'] === 'active') ? 'success-transparent bg-outline-success' : 'danger-transparent bg-outline-danger';
            echo '<td><span class="badge rounded-pill bg-' . $badge . '">' . htmlspecialchars($vendor['active_status']) . '</span></td>';
            $regDate = !empty($vendor['registration_date']) ? date('d, M Y', strtotime($vendor['registration_date'])) : '';
            echo '<td>' . $regDate . '</td>';
            echo '<td class="action-cell">'
                . '<a href="./vendorDetail.php?vendor_id=' . (int) $vendor['id'] . '" class="action-header-btn view-btn"><img src="' . PUBLIC_ASSETS . 'images/admin/view.png" alt="View"></a>'
                . '<a href="./vendorDetail.php?vendor_id=' . (int) $vendor['id'] . '" class="action-header-btn edit-btn"><img src="' . PUBLIC_ASSETS . 'images/admin/edit.png" alt="Edit"></a>'
                . '<button class="action-header-btn delete-btn custom-delete-btn" onclick="deleteVendor(' . (int) $vendor['id'] . ')"><img src="' . PUBLIC_ASSETS . 'images/admin/delete.png" alt="Delete" /></button>'
                . '</td>';
            echo '</tr>';
        }
    }
    $tbodyHtml = ob_get_clean();

    // Pagination HTML
    $prev = max(1, $current_page - 1);
    $next = min($total_pages, $current_page + 1);
    ob_start();
    echo '<form class="d-flex gap-2" onsubmit="return false;">';
    echo '<button type="button" class="btn ' . ($current_page == 1 ? 'btn-outline-secondary' : 'btn-outline-primary') . '" ' . ($current_page == 1 ? 'disabled' : '') . ' data-page="' . $prev . '">Previous</button>';
    echo '<span class="btn btn-primary">' . $current_page . ' of ' . $total_pages . '</span>';
    echo '<button type="button" class="btn ' . ($current_page == $total_pages ? 'btn-outline-secondary' : 'btn-outline-primary') . '" ' . ($current_page == $total_pages ? 'disabled' : '') . ' data-page="' . $next . '">Next</button>';
    echo '</form>';
    $paginationHtml = ob_get_clean();

    echo json_encode(['tbody' => $tbodyHtml, 'pagination' => $paginationHtml]);
    exit;
}

// Handle vendor deletion using prepared statement
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];
    $delete_stmt = mysqli_prepare($con, "DELETE FROM vendor_registration WHERE id = ? AND status = 'approved'");
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "i", $delete_id);
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Vendor deleted successfully!";
        } else {
            $error_message = "Error deleting vendor: " . mysqli_stmt_error($delete_stmt);
        }
        mysqli_stmt_close($delete_stmt);
    } else {
        $error_message = "Error preparing delete statement: " . mysqli_error($con);
    }
}

// Simple filter parameters from POST
$search_term = isset($_POST['search']) ? trim($_POST['search']) : '';
$category_filter = isset($_POST['category']) ? $_POST['category'] : '';
$active_status_filter = isset($_POST['active_status']) ? $_POST['active_status'] : '';
$approval_status_filter = isset($_POST['approval_status']) ? $_POST['approval_status'] : '';
$date_from_filter = isset($_POST['date_from']) ? trim($_POST['date_from']) : '';
$date_to_filter = isset($_POST['date_to']) ? trim($_POST['date_to']) : '';
$per_page = isset($_POST['per_page']) ? (int) $_POST['per_page'] : 10;
$per_page = in_array($per_page, [5, 10, 25, 50]) ? $per_page : 10;

// Pagination settings
$current_page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $per_page;

// Build WHERE clause for simple filters
$where_conditions = ["vr.status IN ('approved','rejected','hold')"];
$params = [];
$joins = [];

// Combined search: business name, state name, and pincode
if (!empty($search_term)) {
    $joins[] = "LEFT JOIN state s ON vr.state = s.id";
    $where_conditions[] = "(vr.business_name LIKE ? OR s.name LIKE ? OR vr.pincode LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "FIND_IN_SET(?, vr.product_categories)";
    $params[] = $category_filter;
}

if (!empty($active_status_filter)) {
    $where_conditions[] = "vr.active_status = ?";
    $params[] = $active_status_filter;
}
if (in_array($approval_status_filter, ['approved', 'rejected', 'hold'], true)) {
    $where_conditions[] = "vr.status = ?";
    $params[] = $approval_status_filter;
}

if (!empty($date_from_filter)) {
    $where_conditions[] = "DATE(vr.registration_date) >= ?";
    $params[] = $date_from_filter;
}

if (!empty($date_to_filter)) {
    $where_conditions[] = "DATE(vr.registration_date) <= ?";
    $params[] = $date_to_filter;
}

$join_clause = !empty($joins) ? implode(' ', array_unique($joins)) : '';
$where_clause = implode(' AND ', $where_conditions);

// Export to Excel using current filters (no pagination limit)


// Get total count with filters
$count_query = "SELECT COUNT(*) as total FROM vendor_registration vr $join_clause WHERE $where_clause";
$count_stmt = mysqli_prepare($con, $count_query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $per_page);

// Fetch vendors with simple filters and pagination
$query = "SELECT vr.* FROM vendor_registration vr $join_clause WHERE $where_clause ORDER BY vr.id DESC LIMIT $per_page OFFSET $offset";
$stmt = mysqli_prepare($con, $query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$vendors = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>ALL-VENDORS | HADIDY</title>
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
                <div
                    class="d-flex align-items-center   justify-content-between mt-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">All Vendors</h1>
                    <!-- <a href="./withdrawRequest.html" type="button" class="btn btn-secondary btn-wave waves-effect waves-light">Withdrawal Request</a> -->
                    <div>
                        <?php
                        $exportParams = array_merge($_GET, $_POST);
                        $exportParams['export'] = 'excel';
                        $exportUrl = basename(__FILE__) . '?' . http_build_query($exportParams);
                        ?>
                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle1"><i
                                class="fa-solid fa-file-arrow-down"></i>
                            Export to Excel</a>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card ">

                            <div class="card-body">



                                <div class="card-header justify-content-between">

                                    <div class="card-title1">

                                    </div>
                                    <form method="POST" id="filterForm"
                                        class="d-flex align-items-center gap-2 flex-wrap">
                                        <div for="Default-sorting"><b>Sort By :</b></div>
                                        <select name="category" class="form-select form-select-md" style="width: 100px;"
                                            onchange="this.form.submit()">
                                            <option value="">All Categories</option>
                                            <?php
                                            $categories = mysqli_query($con, "SELECT * FROM category");
                                            while ($row = mysqli_fetch_assoc($categories)) {
                                                echo "<option value='" . $row['id'] . "' " . ($category_filter == $row['id'] ? 'selected' : '') . ">" . $row['name'] . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <select name="active_status" class="form-select form-select-md"
                                            style="width: 100px;" onchange="this.form.submit()">
                                            <option value="">All Status</option>
                                            <option value="active" <?php echo ($active_status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($active_status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="blocked" <?php echo ($active_status_filter == 'blocked') ? 'selected' : ''; ?>>Blocked</option>
                                        </select>
                                        <select name="approval_status" class="form-select form-select-md"
                                            style="width: 100px;" onchange="this.form.submit()">
                                            <option value="">All Approval Status</option>
                                            <option value="approved" <?php echo ($approval_status_filter == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="hold" <?php echo ($approval_status_filter == 'hold') ? 'selected' : ''; ?>>Hold</option>
                                            <option value="rejected" <?php echo ($approval_status_filter == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <select name="per_page" class="form-select form-select-md" style="width: 120px;"
                                            onchange="this.form.submit()">
                                            <option value="5" <?php echo ($per_page == 5) ? 'selected' : ''; ?>>5 records
                                            </option>
                                            <option value="10" <?php echo ($per_page == 10) ? 'selected' : ''; ?>>10
                                                records</option>
                                            <option value="25" <?php echo ($per_page == 25) ? 'selected' : ''; ?>>25
                                                records</option>
                                            <option value="50" <?php echo ($per_page == 50) ? 'selected' : ''; ?>>50
                                                records</option>
                                        </select>
                                        <div>
                                            <input type="search" name="search" id="searchInput"
                                                class="form-control form-control-md"
                                                placeholder="business name, state, or pincode..."
                                                value="<?php echo htmlspecialchars($search_term); ?>">
                                        </div>
                                        <div>
                                            <input type="date" name="date_from" id="dateFromInput" class="form-control"
                                                placeholder="Date From"
                                                value="<?php echo htmlspecialchars($date_from_filter); ?>">
                                        </div>
                                        <div>
                                            <input type="date" name="date_to" id="dateToInput" class="form-control"
                                                placeholder="Date To"
                                                value="<?php echo htmlspecialchars($date_to_filter); ?>">
                                        </div>
                                    </form>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                        <thead class="table-light">
                                            <tr>
                                                <th>No</th>
                                                <th>Seller ID</th>
                                                <th>Business/Store Name</th>
                                                <th>No Of Product</th>
                                                <th>Pending Order</th>
                                                <th>Completed Order</th>
                                                <th>Revenue</th>
                                                <th>Approval</th>
                                                <th>Status</th>
                                                <th>Registration Date</th>
                                                <th>Approved At</th>
                                                <!-- <th>Last Update</th> -->
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($vendors)): ?>
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
                                                        <div class="alert alert-info">
                                                            <i class="fe fe-info me-2"></i>
                                                            No approved vendors found.
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($vendors as $index => $vendor): ?>
                                                    <tr>
                                                        <td><?php echo $index + 1; ?></td>
                                                        <td><b>#<?php echo str_pad($vendor['vendor_id'], 6, '0', STR_PAD_LEFT); ?></b>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($vendor['business_name']); ?>
                                                        </td>
                                                        <?php
                                                        $product_count = mysqli_query($con, "SELECT COUNT(*) as count FROM products WHERE vendor_id = '{$vendor['id']}'");
                                                        $product_count = mysqli_fetch_assoc($product_count);
                                                        $product_count = $product_count['count'];
                                                        ?>
                                                        <td><span
                                                                class="badge bg-light text-dark"><?php echo $product_count; ?></span>
                                                        </td>
                                                        <td>0</td>
                                                        <td>0</td>
                                                        <td>₹0</td>

                                                        <td><span
                                                                class="badge rounded-pill bg-<?php echo ($vendor['status'] === 'approved') ? 'success-transparent bg-outline-success' : (($vendor['status'] === 'hold') ? 'warning-transparent bg-outline-warning' : 'danger-transparent bg-outline-danger'); ?>"><?php echo htmlspecialchars($vendor['status']); ?></span>
                                                        </td>
                                                        <td><span
                                                                class="badge rounded-pill bg-<?php echo ($vendor['active_status'] === 'active') ? 'success-transparent bg-outline-success' : (($vendor['active_status'] === 'inactive') ? 'danger-transparent bg-outline-danger' : 'danger-transparent bg-outline-danger'); ?>"><?php echo htmlspecialchars($vendor['active_status']); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            if (!empty($vendor['registration_date'])) {
                                                                $dt_reg = new DateTime($vendor['registration_date'], new DateTimeZone('UTC')); // assuming stored as UTC/server time
                                                                $dt_reg->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                echo $dt_reg->format('d M Y, h:i A');
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </td>

                                                        <td>
                                                            <?php
                                                            if (!empty($vendor['approved_at'])) {

                                                                echo date('d M Y, h:i A', strtotime($vendor['approved_at']));
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </td>



                                                        <td class="action-cell">
                                                            <a href="./vendorDetail.php?vendor_id=<?php echo $vendor['id']; ?>"
                                                                class="action-header-btn view-btn"><img
                                                                    src="<?php echo PUBLIC_ASSETS; ?>images/admin/view.png"
                                                                    alt="View"></a>
                                                            <a href="./vendorDetail.php?vendor_id=<?php echo $vendor['id']; ?>"
                                                                class="action-header-btn edit-btn"><img
                                                                    src="<?php echo PUBLIC_ASSETS; ?>images/admin/edit.png"
                                                                    alt="Edit"></a>
                                                            <button class="action-header-btn delete-btn custom-delete-btn"
                                                                onclick="deleteVendor(<?php echo $vendor['id']; ?>)"><img
                                                                    src="<?php echo PUBLIC_ASSETS; ?>images/admin/delete.png"
                                                                    alt="Delete" /></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $current_page - 1; ?>"
                                                        class="page-link">Previous</button>
                                                </form>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Previous</span>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $startPage = max(1, $current_page - 2);
                                        $endPage = min($total_pages, $current_page + 2);

                                        for ($i = $startPage; $i <= $endPage; $i++):
                                            ?>
                                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page" value="<?php echo $i; ?>"
                                                        class="page-link"><?php echo $i; ?></button>
                                                </form>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item">
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="vendor_id"
                                                        value="<?php echo $pmFilterVendorId; ?>">
                                                    <input type="hidden" name="category_id"
                                                        value="<?php echo $pmFilterCategoryId; ?>">
                                                    <input type="hidden" name="sub_category_id"
                                                        value="<?php echo $pmFilterSubCategoryId; ?>">
                                                    <input type="hidden" name="q"
                                                        value="<?php echo htmlspecialchars($pmSearchTerm); ?>">
                                                    <input type="hidden" name="sort_by"
                                                        value="<?php echo htmlspecialchars($pmSortBy); ?>">
                                                    <button type="submit" name="page"
                                                        value="<?php echo $current_page + 1; ?>"
                                                        class="page-link">Next</button>
                                                </form>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Next</span>
                                            </li>
                                        <?php endif; ?>
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


        <!-- Confirm Withdraw Modal -->
        <div class="modal fade" id="confirmWithdrawModal" tabindex="-1" aria-labelledby="confirmWithdrawModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius:16px; padding:20px;">
                    <div class="modal-body text-center py-4">
                        <div class="mb-4">
                            <!-- Example SVG icon, replace src with your actual icon if needed -->
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/confirm.png" alt="Withdraw Icon"
                                style="width:56px; height:56px;">
                        </div>
                        <h4 class="fw-bold mb-4">Confirm withdrawal Amount</h4>
                        <div class="d-flex justify-content-center align-items-center mb-2" style="gap:0;">
                            <span class="d-flex align-items-center justify-content-center"
                                style="background:#3B4B6B; color:#fff; font-size:22px; font-weight:500; border-radius:8px 0 0 8px; height:48px; width:48px;">₹</span>
                            <span class="d-flex align-items-center justify-content-center bg-white"
                                style="font-size:28px; font-weight:700; border-radius:0 8px 8px 0; height:48px; min-width:140px; border:1px solid #e5e7eb;">29,368</span>
                        </div>
                        <div class="mb-4" style="font-size:16px;">
                            Available for withdrawal : <b>₹29,368</b>
                        </div>
                        <div class="d-flex justify-content-center gap-4 mt-4">
                            <button type="button" class="btn btn-outline-danger px-5 py-2" data-bs-dismiss="modal"
                                style="font-weight:500; border-radius:12px; font-size:18px; border:2px solid #ff3c3c;">No</button>
                            <button type="button" class="btn px-5 py-2"
                                style="background:#3B4B6B; color:#fff; font-weight:500; border-radius:12px; font-size:18px;">Yes,
                                Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm Delete Modal -->


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
                                <a href="alerts.html"><span>Alerts</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                <a href="mail.html"><span>Mail</span></a>
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

        <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content"
                    style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <div class="modal-body text-center p-4">
                        <!-- Icon -->
                        <div class="mb-3">
                            <div
                                style="width: 60px; height: 60px; background-color: #F45B4B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-info" style="color: white; font-size: 24px;"></i>
                            </div>
                        </div>

                        <!-- Message -->
                        <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                            Are you sure, you want to Delete <br> the Vendor ?
                        </h5>

                        <!-- Buttons -->
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="button" class="btn btn-outline-danger" id="confirmDeleteNoBtn"
                                data-bs-dismiss="modal"
                                style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                No
                            </button>
                            <button type="button" class="btn btn-primary" id="confirmDeleteYesBtn"
                                onclick="confirmDeleteVendor()"
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
        document.querySelector('.btn.btn-primary[style*="background:#3B4B6B"]').addEventListener('click', function (e) {
            e.preventDefault();
            var modal = new bootstrap.Modal(document.getElementById('confirmWithdrawModal'));
            modal.show();
        });
    </script>

    <script>
        // Delete vendor function using modal
        let vendorIdToDelete = null;
        function deleteVendor(vendorId) {
            vendorIdToDelete = vendorId;
            var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
            modal.show();
        }

        function confirmDeleteVendor() {
            if (!vendorIdToDelete) return;
            window.location.href = 'allVendors.php?delete_id=' + vendorIdToDelete;
        }


        // Auto-search functionality
        document.addEventListener('DOMContentLoaded', function () {
            var searchInput = document.getElementById('searchInput');
            var dateFromInput = document.getElementById('dateFromInput');
            var dateToInput = document.getElementById('dateToInput');
            var searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(function () {
                        document.getElementById('filterForm').submit();
                    }, 500); // Wait 500ms after user stops typing
                });
            }
            if (dateFromInput) {
                dateFromInput.addEventListener('change', function () {
                    document.getElementById('filterForm').submit();
                });
            }
            if (dateToInput) {
                dateToInput.addEventListener('change', function () {
                    document.getElementById('filterForm').submit();
                });
            }
        });
    </script>

</body>

</html>
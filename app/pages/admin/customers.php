<?php
include __DIR__ . '/../includes/init.php';

// Handle Excel export
if (isset($_POST['export_excel']) && $_POST['export_excel'] === '1') {
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
    
    // Get filter parameters for export
    $filterStatus = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
    $searchTerm = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
    
    // Build WHERE conditions (same as main query)
    $where = [];
    if ($filterStatus !== '') {
        $safeStatus = mysqli_real_escape_string($con, strtolower($filterStatus));
        $where[] = "LOWER(u.status) = '" . $safeStatus . "'";
    }
    if ($searchTerm !== '') {
        $safeQ = mysqli_real_escape_string($con, strtolower($searchTerm));
        $where[] = "(LOWER(u.first_name) LIKE '%" . $safeQ . "%' OR LOWER(u.last_name) LIKE '%" . $safeQ . "%' OR LOWER(u.email) LIKE '%" . $safeQ . "%' OR LOWER(u.mobile) LIKE '%" . $safeQ . "%' OR LOWER(u.referral_code) LIKE '%" . $safeQ . "%' OR LOWER(u.city) LIKE '%" . $safeQ . "%')";
    }
    $whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    
    // Export query (get all records, no pagination)
    $exportSql = "SELECT 
                    u.id, 
                    u.first_name, 
                    u.last_name, 
                    u.mobile, 
                    u.email, 
                    u.referral_code, 
                    u.referred_by, 
                    u.created_date,
                    u.city,
                    u.status,
                    COALESCE(SUM(o.total_amount), 0) as total_spend,
                    COUNT(o.id) as order_count,
                    CONCAT(COALESCE(ref.first_name, ''), ' ', COALESCE(ref.last_name, '')) as referrer_name
                FROM users u 
                LEFT JOIN `order` o ON u.id = o.user_id 
                LEFT JOIN users ref ON u.referred_by = ref.id
                $whereSql
                GROUP BY u.id 
                ORDER BY u.id DESC";
    
    $exportResult = mysqli_query($con, $exportSql);
    
    if (!$exportResult) {
        die('Database error: ' . mysqli_error($con));
    }
    
    // Create spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Customers');
    
    // Set headers
    $headers = [
        'S.No',
        'First Name',
        'Last Name',
        'City',
        'Mobile Number',
        'Email Address',
        'Referral Code',
        'Referred By',
        'Total Spend (₹)',
        'No. of Orders',
        'Status',
        'Registration Date'
    ];
    
    // Add headers to sheet
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }
    
    // Style headers
    $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE0E0E0');
    
    // Add data
    $row = 2;
    $serial = 1;
    
    while ($customer = mysqli_fetch_assoc($exportResult)) {
        $sheet->setCellValue('A' . $row, $serial++);
        $sheet->setCellValue('B' . $row, $customer['first_name']);
        $sheet->setCellValue('C' . $row, $customer['last_name']);
        $sheet->setCellValue('D' . $row, $customer['city'] ?: '-');
        $sheet->setCellValue('E' . $row, $customer['mobile'] ? '+91 ' . $customer['mobile'] : '-');
        $sheet->setCellValue('F' . $row, $customer['email']);
        $sheet->setCellValue('G' . $row, $customer['referral_code'] ?: '-');
        $sheet->setCellValue('H' . $row, $customer['referrer_name'] ?: '-');
        $sheet->setCellValue('I' . $row, '₹' . number_format($customer['total_spend'], 2));
        $sheet->setCellValue('J' . $row, $customer['order_count']);
        $sheet->setCellValue('K' . $row, ucfirst($customer['status']));
        $sheet->setCellValue('L' . $row, date('d M Y', strtotime($customer['created_date'])));
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
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
    $filename = 'customers_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    
    // Write file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Handle customer edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_customer') {
    $customer_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $referral_code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    // Validation
    $errors = [];
    if ($customer_id <= 0)
        $errors[] = 'Invalid customer ID';
    if (empty($first_name))
        $errors[] = 'First name is required';
    if (empty($last_name))
        $errors[] = 'Last name is required';
    if (empty($city))
        $errors[] = 'City is required';
    if (empty($mobile))
        $errors[] = 'Mobile number is required';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email format';
    if (empty($status))
        $errors[] = 'Status is required';

    if (empty($errors)) {
        // Escape data for security
        $safe_first_name = mysqli_real_escape_string($con, $first_name);
        $safe_last_name = mysqli_real_escape_string($con, $last_name);
        $safe_city = mysqli_real_escape_string($con, $city);
        $safe_mobile = mysqli_real_escape_string($con, $mobile);
        $safe_email = mysqli_real_escape_string($con, $email);
        $safe_referral_code = mysqli_real_escape_string($con, $referral_code);
        $safe_status = mysqli_real_escape_string($con, $status);

        // Check if email already exists for another customer
        $check_email_sql = "SELECT id FROM users WHERE email = '$safe_email' AND id != $customer_id";
        $check_result = mysqli_query($con, $check_email_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $errors[] = 'Email already exists for another customer';
        } else {
            // Update customer
            $update_sql = "UPDATE users SET 
                          first_name = '$safe_first_name',
                          last_name = '$safe_last_name',
                          city = '$safe_city',
                          mobile = '$safe_mobile',
                          email = '$safe_email',
                          referral_code = '$safe_referral_code',
                          status = '$safe_status'
                          WHERE id = $customer_id";

            if (mysqli_query($con, $update_sql)) {
                $_SESSION['success_message'] = 'Customer updated successfully!';
            } else {
                $errors[] = 'Database error: ' . mysqli_error($con);
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }

    // Redirect to prevent form resubmission
    header('Location: customers.php');
    exit;
}

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');

    $customer_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
    $new_status = isset($_POST['new_status']) ? trim($_POST['new_status']) : '';

    // Debug logging
    error_log("Status update request - Customer ID: $customer_id, New Status: $new_status");

    $allowed_statuses = ['Active', 'Inactive', 'Blocked', 'New'];

    if ($customer_id > 0 && in_array($new_status, $allowed_statuses)) {
        $safe_status = mysqli_real_escape_string($con, $new_status);
        $update_sql = "UPDATE users SET status = '$safe_status' WHERE id = $customer_id";

        error_log("Update SQL: $update_sql");

        if (mysqli_query($con, $update_sql)) {
            $affected_rows = mysqli_affected_rows($con);
            error_log("Update successful. Affected rows: $affected_rows");

            // Set success message in session
            $_SESSION['success_message'] = "Customer status updated to <strong>$new_status</strong> successfully!";

            echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'new_status' => $new_status, 'affected_rows' => $affected_rows]);
        } else {
            $error = mysqli_error($con);
            error_log("Database error: $error");

            // Set error message in session
            $_SESSION['error_message'] = "Failed to update customer status: " . $error;

            echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
        }
    } else {
        error_log("Invalid parameters - Customer ID: $customer_id, Status: $new_status");

        // Set error message in session
        $_SESSION['error_message'] = "Invalid parameters for status update. Please try again.";

        echo json_encode(['success' => false, 'message' => 'Invalid parameters - Customer ID: ' . $customer_id . ', Status: ' . $new_status]);
    }
    exit;
}

// Get filter parameters
$filterStatus = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
$searchTerm = isset($_POST['q']) ? trim((string) $_POST['q']) : '';

// Build WHERE conditions
$where = [];
if ($filterStatus !== '') {
    $safeStatus = mysqli_real_escape_string($con, strtolower($filterStatus));
    $where[] = "LOWER(u.status) = '" . $safeStatus . "'";
}
if ($searchTerm !== '') {
    $safeQ = mysqli_real_escape_string($con, strtolower($searchTerm));
    $where[] = "(LOWER(u.first_name) LIKE '%" . $safeQ . "%' OR LOWER(u.last_name) LIKE '%" . $safeQ . "%' OR LOWER(u.email) LIKE '%" . $safeQ . "%' OR LOWER(u.mobile) LIKE '%" . $safeQ . "%' OR LOWER(u.referral_code) LIKE '%" . $safeQ . "%' OR LOWER(u.city) LIKE '%" . $safeQ . "%')";
}
$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Pagination
$perPage = 10;
$currentPage = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;

// Count total records - use subquery to get correct count with GROUP BY
$countSql = "SELECT COUNT(*) AS total FROM (
                SELECT u.id
                FROM users u 
                LEFT JOIN `order` o ON u.id = o.user_id 
                LEFT JOIN users ref ON u.referred_by = ref.id
                $whereSql
                GROUP BY u.id
             ) AS customer_count";
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

// Main query with pagination
$sql = "SELECT 
            u.id, 
            u.first_name, 
            u.last_name, 
            u.mobile, 
            u.email, 
            u.referral_code, 
            u.referred_by, 
            u.created_date,
            u.city,
            u.status,
            COALESCE(SUM(o.total_amount), 0) as total_spend,
            COUNT(o.id) as order_count,
            CONCAT(COALESCE(ref.first_name, ''), ' ', COALESCE(ref.last_name, '')) as referrer_name
        FROM users u 
        LEFT JOIN `order` o ON u.id = o.user_id 
        LEFT JOIN users ref ON u.referred_by = ref.id
        $whereSql
        GROUP BY u.id 
        ORDER BY u.id DESC
        LIMIT $perPage OFFSET $offset";

$result = mysqli_query($con, $sql);
$customers = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
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
    <title>CUSTOMERS MANAGEMENT | HADIDY</title>
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
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/customer.css" rel="stylesheet">


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
                    class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Customers</h1>
                    <div class="text-muted">
                        Showing
                        <?php echo $totalRows > 0 ? (($currentPage - 1) * $perPage + 1) : 0; ?>-<?php echo min($currentPage * $perPage, $totalRows); ?>
                        of <?php echo $totalRows; ?> customers
                    </div>
                </div>
                <!-- Page Header Close -->

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])) { ?>
                    <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
                        <i class="fa fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php } ?>

                <?php if (isset($_SESSION['error_message'])) { ?>
                    <div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php } ?>
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">

                                    <button type="button" id="exportToExcel" class="btn btn-light btn-wave waves-effect waves-light px-2">
                                        <img src="./assets/images/export.png" alt="" class="mb-1"
                                            style="height: 16px; width: 13px;"> Export to Excel
                                    </button>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <form method="post" id="filterForm"
                                        class="d-flex align-items-center gap-2 flex-wrap">
                                        <input type="hidden" name="page" id="pageInput"
                                            value="<?php echo isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1; ?>">
                                        <div class="selecton-order">
                                            <select name="status" id="statusFilter" class="form-select form-select-lg"
                                                onchange="this.form.submit()">
                                                <option value="" <?php echo $filterStatus === '' ? ' selected' : ''; ?>>
                                                    All
                                                    Status</option>
                                                <option value="active" <?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $filterStatus === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
                                                <option value="blocked" <?php echo $filterStatus === 'blocked' ? ' selected' : ''; ?>>Blocked</option>
                                                <option value="new" <?php echo $filterStatus === 'new' ? ' selected' : ''; ?>>
                                                    New</option>
                                            </select>
                                        </div>
                                        <div class="selecton-order">
                                            <input type="search" class="form-control" placeholder="Search customers..."
                                                name="q" form="filterForm"
                                                value="<?php echo htmlspecialchars($searchTerm); ?>"
                                                aria-describedby="button-addon2" id="searchBox">
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">First Name</th>
                                            <th scope="col">Last Name</th>
                                            <th scope="col">City</th>
                                            <th scope="col">Mobile Number</th>
                                            <th scope="col">Email Address</th>
                                            <th scope="col">Referred ID</th>
                                            <th scope="col">Referred by</th>
                                            <th scope="col">Total Spend</th>
                                            <th scope="col">No Of Order</th>
                                            <th scope="col">Network</th>
                                            <th scope="col">Registered at</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        $counter = 1;
                                        if (!empty($customers)) {
                                            foreach ($customers as $customer) {
                                                // Format date
                                                $created_date = date('d,M Y h:i A', strtotime($customer['created_date']));

                                                // Format mobile number
                                                $mobile = $customer['mobile'] ? '+91 ' . $customer['mobile'] : '-';

                                                // Format total spend
                                                $total_spend = $customer['total_spend'] > 0 ? '₹' . number_format($customer['total_spend']) : '₹0';

                                                // Get status badge class
                                                $status = $customer['status'] ?: 'Active';
                                                $status_class = '';
                                                switch (strtolower($status)) {
                                                    case 'active':
                                                        $status_class = 'bg-outline-success';
                                                        break;
                                                    case 'inactive':
                                                        $status_class = 'bg-outline-warning';
                                                        break;
                                                    case 'blocked':
                                                        $status_class = 'bg-outline-danger';
                                                        break;
                                                    case 'new':
                                                        $status_class = 'bg-outline-info';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-outline-success';
                                                }

                                                // Get city,referral_code, referred_by
                                                $city = $customer['city'] ?: '-';

                                                $referral_code = $customer['referral_code'] ?: '-';
                                                $referred_by = $customer['referrer_name'] ?: '-';
                                                ?>
                                                <tr data-customer-id="<?php echo $customer['id']; ?>">
                                                    <td><?php echo $counter; ?></td>
                                                    <td class="rqu-id"><?php echo htmlspecialchars($customer['first_name']); ?>
                                                    </td>
                                                    <td class="rqu-id"><?php echo htmlspecialchars($customer['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($city); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($mobile); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($customer['email']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($referral_code); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($referred_by); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $total_spend; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $customer['order_count']; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            -
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php //echo $created_date; 
                                                            if (!empty($customer['created_date'])) {
                                                                $dt_created = new DateTime($customer['created_date'], new DateTimeZone('UTC')); // assuming stored as UTC/server time
                                                                $dt_created->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                echo $dt_created->format('d M Y, h:i A');
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="dropdown">
                                                            <button
                                                                class="badge rounded-pill <?php echo $status_class; ?> dropdown-toggle status-badge"
                                                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                                <?php echo ucfirst($status); ?>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li><a class="dropdown-item <?php echo strtolower($status) === 'active' ? 'active' : ''; ?>"
                                                                        href="#"
                                                                        onclick="updateStatus(<?php echo $customer['id']; ?>, 'Active', this)">
                                                                        <i
                                                                            class="fa fa-check-circle text-success me-2"></i>Active
                                                                    </a></li>
                                                                <li><a class="dropdown-item <?php echo strtolower($status) === 'inactive' ? 'active' : ''; ?>"
                                                                        href="#"
                                                                        onclick="updateStatus(<?php echo $customer['id']; ?>, 'Inactive', this)">
                                                                        <i
                                                                            class="fa fa-pause-circle text-warning me-2"></i>Inactive
                                                                    </a></li>
                                                                <li><a class="dropdown-item <?php echo strtolower($status) === 'blocked' ? 'active' : ''; ?>"
                                                                        href="#"
                                                                        onclick="updateStatus(<?php echo $customer['id']; ?>, 'Blocked', this)">
                                                                        <i class="fa fa-ban text-danger me-2"></i>Blocked
                                                                    </a></li>
                                                                <li><a class="dropdown-item <?php echo strtolower($status) === 'new' ? 'active' : ''; ?>"
                                                                        href="#"
                                                                        onclick="updateStatus(<?php echo $customer['id']; ?>, 'New', this)">
                                                                        <i class="fa fa-star text-info me-2"></i>New
                                                                    </a></li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="#" class="i-icon-eidt"
                                                                onclick="editCustomer(<?php echo $customer['id']; ?>)">
                                                                <i class="fa-regular fa-pen-to-square" data-bs-toggle="modal"
                                                                    data-bs-target="#addAttributeModal"></i>
                                                            </a>
                                                            <!-- <a href="#" class="i-icon-trash cancelOrderBtn"><i class="fa-solid fa-trash-can"></i></a> -->
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                                $counter++;
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="14" class="text-center">
                                                    <?php if ($searchTerm !== '' || $filterStatus !== '') { ?>
                                                        No customers found matching your search criteria.
                                                    <?php } else { ?>
                                                        No customers found.
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($totalPages > 1) { ?>
                                <div class="d-flex justify-content-center mt-3 mb-3">
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php
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
                                                <?php if ($start > 2) { ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php } ?>
                                            <?php }
                                            for ($p = $start; $p <= $end; $p++) { ?>
                                                <li class="page-item<?php echo $p == $currentPage ? ' active' : ''; ?>">
                                                    <button type="button" class="page-link"
                                                        onclick="setPageAndSubmit(<?php echo $p; ?>)"><?php echo $p; ?></button>
                                                </li>
                                            <?php }
                                            if ($end < $totalPages) { ?>
                                                <?php if ($end < $totalPages - 1) { ?>
                                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                                <?php } ?>
                                                <li class="page-item"><button type="button" class="page-link"
                                                        onclick="setPageAndSubmit(<?php echo $totalPages; ?>)"><?php echo $totalPages; ?></button>
                                                </li>
                                            <?php } ?>
                                            <li
                                                class="page-item<?php echo $currentPage == $totalPages ? ' disabled' : ''; ?>">
                                                <button type="button" class="page-link"
                                                    onclick="setPageAndSubmit(<?php echo $nextPage; ?>)">Next</button>
                                            </li>
                                        </ul>
                                    </nav>
                                </div>
                            <?php } ?>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->
            </div>
            <!-- End::app-content -->

            <!-- Cancel Order Confirmation Modal -->
            <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel"
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
                                Are you sure, you want to Delete <br> the Customer ?
                            </h5>

                            <!-- Buttons -->
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="button" class="btn btn-outline-danger" id="cancelNoBtn"
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

            <!-- Status Update Confirmation Modal -->
            <div class="modal fade" id="confirmStatusModal" tabindex="-1" aria-labelledby="confirmStatusModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="modal-body text-center p-4">
                            <div class="mb-3">
                                <div style="width: 60px; height: 60px; background-color: #4A5568; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-question" style="color: white; font-size: 24px;"></i>
                                </div>
                            </div>
                            <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">Confirm Status Update</h5>
                            <p id="confirmStatusText" class="text-muted mb-4"></p>
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal" style="border-radius: 8px; padding: 8px 24px; font-weight: 500;">Cancel</button>
                                <button type="button" class="btn btn-primary" id="confirmStatusBtn" style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">Confirm</button>
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

        <!-- Add Attribute Modal -->

        <div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content add-attribute-modal-content">
                    <div class="modal-header border-0 pb-0">
                        <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Edit Customer</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <form id="editCustomerForm" method="post">
                            <input type="hidden" name="action" value="edit_customer">
                            <input type="hidden" name="customer_id" id="edit_customer_id">
                            <div class="mb-3">
                                <label for="edit_firstname" class="form-label fw-semibold">First Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="edit_firstname"
                                    name="first_name" placeholder="Enter First Name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_lastname" class="form-label fw-semibold">Last Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="edit_lastname"
                                    name="last_name" placeholder="Enter Last Name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_city" class="form-label fw-semibold">City <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="edit_city" name="city"
                                    placeholder="Enter City Name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_mobile" class="form-label fw-semibold">Mobile Number <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="edit_mobile" name="mobile"
                                    placeholder="Enter Mobile Number" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_email" class="form-label fw-semibold">Email Address <span
                                        class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-lg" id="edit_email" name="email"
                                    placeholder="Enter Email Address" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_referral" class="form-label fw-semibold">Referral ID</label>
                                <input type="text" class="form-control form-control-lg" id="edit_referral"
                                    name="referral_code" placeholder="Enter Referral ID">
                            </div>
                            <div class="mb-3">
                                <label for="edit_status" class="form-label fw-semibold">Status <span
                                        class="text-danger">*</span></label>
                                <select id="edit_status" name="status" class="form-select form-select-lg" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="blocked">Blocked</option>
                                    <option value="new">New</option>
                                </select>
                            </div>
                            <button type="submit" class="btn save-attribute-btn w-100">Save Changes</button>
                            </form>
                            
                            <!-- Hidden export form -->
                            <form method="POST" id="exportForm" style="display:none;">
                                <input type="hidden" name="export_excel" value="1">
                                <input type="hidden" name="status" id="exp_status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                                <input type="hidden" name="q" id="exp_search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </form>
                        </div>
                    </div>
                </div>
        </div>


        <!-- Modal JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Get all cancel buttons and modal elements
                const cancelBtns = document.querySelectorAll('.cancelOrderBtn');
                const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
                const cancelNoBtn = document.getElementById('cancelNoBtn');
                const cancelYesBtn = document.getElementById('cancelYesBtn');

                // Show modal when any cancel button is clicked
                cancelBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        modal.show();
                    });
                });

                // Handle No button click
                if (cancelNoBtn) {
                    cancelNoBtn.addEventListener('click', function () {
                        modal.hide();
                    });
                }

                // Handle Yes button click
                if (cancelYesBtn) {
                    cancelYesBtn.addEventListener('click', function () {
                        alert('Customer has been deleted successfully!');
                        modal.hide();
                    });
                }
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

            // Function to update customer status with confirmation modal
            function updateStatus(customerId, newStatus, clickedElement) {
                const modalEl = document.getElementById('confirmStatusModal');
                if (!modalEl) {
                    console.error('Status confirmation modal not found');
                    return;
                }
                
                modalEl.querySelector('#confirmStatusText').textContent = 'Are you sure you want to update the customer status to ' + newStatus + '?';
                const bsModal = new bootstrap.Modal(modalEl);
                bsModal.show();

                const confirmBtn = modalEl.querySelector('#confirmStatusBtn');
                const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');

                // Remove any existing event listeners
                const newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

                const proceed = function () {
                    // Find the button element
                    const button = clickedElement.closest('.dropdown').querySelector('.dropdown-toggle');
                    const originalText = button.textContent;
                    button.textContent = 'Updating...';
                    button.disabled = true;
                    button.classList.add('updating');

                    // Make AJAX call to update status
                    const formData = new FormData();
                    formData.append('action', 'update_status');
                    formData.append('customer_id', customerId);
                    formData.append('new_status', newStatus);

                    fetch('customers.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                showNotification('Customer status updated to ' + newStatus + ' successfully!', 'success');
                                // Update the button text and class
                                button.textContent = newStatus;
                                button.className = 'badge rounded-pill ' + getStatusClass(newStatus) + ' dropdown-toggle status-badge';
                                // Update dropdown active state
                                updateDropdownActiveState(button, newStatus);
                                // Reload page after a short delay to show the success message
                                setTimeout(() => {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                showNotification('Error updating status: ' + (data.message || 'Unknown error'), 'error');
                                button.textContent = originalText;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('Error updating status: ' + error.message, 'error');
                            button.textContent = originalText;
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.classList.remove('updating');
                            bsModal.hide();
                        });
                };

                // Add event listener to the new button
                newConfirmBtn.addEventListener('click', proceed);
            }

            // Helper function to get status class
            function getStatusClass(status) {
                switch (status.toLowerCase()) {
                    case 'active': return 'bg-outline-success';
                    case 'inactive': return 'bg-outline-warning';
                    case 'blocked': return 'bg-outline-danger';
                    case 'new': return 'bg-outline-info';
                    default: return 'bg-outline-success';
                }
            }

            // Function to update dropdown active state
            function updateDropdownActiveState(button, newStatus) {
                const dropdown = button.nextElementSibling;
                const items = dropdown.querySelectorAll('.dropdown-item');

                items.forEach(item => {
                    item.classList.remove('active');
                    if (item.textContent.trim().toLowerCase() === newStatus.toLowerCase()) {
                        item.classList.add('active');
                    }
                });
            }

            // Function to show notifications
            function showNotification(message, type) {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible fade show position-fixed notification-toast';
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);';
                notification.innerHTML = `
            <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

                // Add to page
                document.body.appendChild(notification);

                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.classList.add('fade');
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.remove();
                            }
                        }, 150);
                    }
                }, 5000);
            }

            // Function to edit customer
            function editCustomer(customerId) {
                // Find the customer row
                const customerRow = document.querySelector(`tr[data-customer-id="${customerId}"]`);
                if (!customerRow) {
                    alert('Customer data not found');
                    return;
                }

                // Extract customer data from the row
                const customerData = {
                    id: customerId,
                    first_name: customerRow.querySelector('td:nth-child(2)').textContent.trim(),
                    last_name: customerRow.querySelector('td:nth-child(3)').textContent.trim(),
                    city: customerRow.querySelector('td:nth-child(4) .order-date-tr').textContent.trim(),
                    mobile: customerRow.querySelector('td:nth-child(5) .order-date-tr').textContent.trim().replace('+91 ', ''),
                    email: customerRow.querySelector('td:nth-child(6) .order-date-tr').textContent.trim(),
                    referral_code: customerRow.querySelector('td:nth-child(7) .order-date-tr').textContent.trim(),
                    status: customerRow.querySelector('td:nth-child(13) .dropdown-toggle').textContent.trim().toLowerCase()
                };

                // Populate the form fields
                document.getElementById('edit_customer_id').value = customerData.id;
                document.getElementById('edit_firstname').value = customerData.first_name;
                document.getElementById('edit_lastname').value = customerData.last_name;
                document.getElementById('edit_city').value = customerData.city === '-' ? '' : customerData.city;
                document.getElementById('edit_mobile').value = customerData.mobile === '-' ? '' : customerData.mobile;
                document.getElementById('edit_email').value = customerData.email;
                document.getElementById('edit_referral').value = customerData.referral_code === '-' ? '' : customerData.referral_code;
                document.getElementById('edit_status').value = customerData.status;

                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('addAttributeModal'));
                modal.show();
            }

            // Dynamic search and pagination functionality
            document.addEventListener('DOMContentLoaded', function () {
                // Auto-dismiss alerts after 5 seconds
                const autoDismissAlerts = document.querySelectorAll('.auto-dismiss');
                autoDismissAlerts.forEach(alert => {
                    setTimeout(() => {
                        if (alert && alert.parentNode) {
                            alert.classList.add('fade');
                            setTimeout(() => {
                                if (alert && alert.parentNode) {
                                    alert.remove();
                                }
                            }, 150);
                        }
                    }, 5000); // 5 seconds
                });

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

                // Form validation for edit customer
                const editForm = document.getElementById('editCustomerForm');
                if (editForm) {
                    editForm.addEventListener('submit', function (e) {
                        e.preventDefault();

                        // Get form data
                        const formData = new FormData(editForm);
                        const customerId = formData.get('customer_id');
                        const firstName = formData.get('first_name').trim();
                        const lastName = formData.get('last_name').trim();
                        const city = formData.get('city').trim();
                        const mobile = formData.get('mobile').trim();
                        const email = formData.get('email').trim();
                        const status = formData.get('status');

                        // Basic validation
                        if (!firstName || !lastName || !city || !mobile || !email || !status) {
                            alert('Please fill in all required fields');
                            return;
                        }

                        // Email validation
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            alert('Please enter a valid email address');
                            return;
                        }

                        // Mobile validation (basic)
                        if (mobile.length < 10) {
                            alert('Please enter a valid mobile number');
                            return;
                        }

                        // Submit the form
                        editForm.submit();
                    });
                }

                // Handle Excel export
                const exportBtn = document.getElementById('exportToExcel');
                if (exportBtn) {
                    exportBtn.addEventListener('click', function() {
                        // Copy current filter values to export form
                        const statusFilter = document.getElementById('statusFilter');
                        const searchBox = document.getElementById('searchBox');
                        
                        if (statusFilter) {
                            document.getElementById('exp_status').value = statusFilter.value;
                        }
                        if (searchBox) {
                            document.getElementById('exp_search').value = searchBox.value;
                        }
                        
                        // Submit export form
                        document.getElementById('exportForm').submit();
                    });
                }
            });
        </script>

</body>

</html>
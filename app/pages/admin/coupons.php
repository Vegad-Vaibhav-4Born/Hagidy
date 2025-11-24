<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

// Handle delete functionality
if (isset($_GET['delete_id'])) {
    $delete_id = (int) $_GET['delete_id'];

    // First, check if the coupon exists
    $checkQuery = "SELECT id, coupan_code FROM `coupans` WHERE id = ?";
    $checkStmt = mysqli_prepare($con, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, 'i', $delete_id);
    mysqli_stmt_execute($checkStmt);
    $couponResult = mysqli_stmt_get_result($checkStmt);

    if (mysqli_num_rows($couponResult) > 0) {
        $couponData = mysqli_fetch_assoc($couponResult);

        // Delete the coupon
        $delete_query = "DELETE FROM `coupans` WHERE `id` = ?";
        $deleteStmt = mysqli_prepare($con, $delete_query);
        mysqli_stmt_bind_param($deleteStmt, 'i', $delete_id);

        if (mysqli_stmt_execute($deleteStmt)) {
            $_SESSION['success_message'] = "Coupon '{$couponData['coupan_code']}' has been deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting coupon: " . mysqli_error($con);
        }

        mysqli_stmt_close($deleteStmt);
    } else {
        $_SESSION['error_message'] = "Coupon not found!";
    }

    mysqli_stmt_close($checkStmt);

    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch coupons data
$coupons_query = "SELECT `id`, `coupan_code`, `discount`, `coupan_limit`, `category`, `sub_category`, `type`, `minimum_order_value`, `start_date`, `end_date`, `status`, `created_date` FROM `coupans` WHERE 1 ORDER BY `created_date` DESC";
$coupons_result = mysqli_query($con, $coupons_query);

// Fetch categories data
$categories_query = "SELECT `id`, `category_id`, `image`, `name`, `date`, `created_date` FROM `category` WHERE 1";
$categories_result = mysqli_query($con, $categories_query);
$categories = [];
while ($category = mysqli_fetch_assoc($categories_result)) {
    $categories[$category['id']] = $category['name'];
}

// Fetch subcategories data
$subcategories_query = "SELECT `id`, `sub_category_id`, `category_id`, `name`, `date`, `created_date`, `mandatory_attributes`, `optional_attributes` FROM `sub_category` WHERE 1";
$subcategories_result = mysqli_query($con, $subcategories_query);
$subcategories = [];
while ($subcategory = mysqli_fetch_assoc($subcategories_result)) {
    $subcategories[$subcategory['id']] = $subcategory['name'];
}

// Handle search functionality
$search = isset($_POST['search']) ? mysqli_real_escape_string($con, $_POST['search']) : '';
$where_clause = "WHERE 1";
if (!empty($search)) {
    $where_clause .= " AND (`coupan_code` LIKE '%$search%' OR `type` LIKE '%$search%')";
}

// Handle pagination
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM `coupans` $where_clause";
$count_result = mysqli_query($con, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $limit);

// Fetch paginated data
$final_query = "SELECT `id`, `coupan_code`, `discount`, `coupan_limit`, `category`, `sub_category`, `type`, `minimum_order_value`, `start_date`, `end_date`, `status`, `created_date` FROM `coupans` $where_clause ORDER BY `created_date` DESC LIMIT $limit OFFSET $offset";
$final_result = mysqli_query($con, $final_query);

// Export to Excel (all filtered data, no pagination)
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
        // Rebuild search WHERE from GET (mirror POST search)
        $searchQ = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
        $where_export = "WHERE 1";
        if (!empty($searchQ)) {
            $where_export .= " AND (`coupan_code` LIKE '%$searchQ%' OR `type` LIKE '%$searchQ%')";
        }

        $export_query = "SELECT `id`, `coupan_code`, `discount`, `coupan_limit`, `category`, `sub_category`, `type`, `minimum_order_value`, `start_date`, `end_date`, `status`, `created_date` FROM `coupans` $where_export ORDER BY `created_date` DESC";
        $export_rs = mysqli_query($con, $export_query);

        // Build category/subcategory lookup for names
        $cat_lookup = [];
        $cat_rs = mysqli_query($con, "SELECT `id`, `name` FROM `category`");
        if ($cat_rs)
            while ($c = mysqli_fetch_assoc($cat_rs)) {
                $cat_lookup[$c['id']] = $c['name'];
            }
        $sub_lookup = [];
        $sub_rs = mysqli_query($con, "SELECT `id`, `name` FROM `sub_category`");
        if ($sub_rs)
            while ($s = mysqli_fetch_assoc($sub_rs)) {
                $sub_lookup[$s['id']] = $s['name'];
            }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Coupons');

        // Headers
        $headers = [
            'A1' => 'ID',
            'B1' => 'Created On',
            'C1' => 'Coupon Code',
            'D1' => 'Category',
            'E1' => 'Sub Category',
            'F1' => 'Type',
            'G1' => 'Order Value',
            'H1' => 'Limit',
            'I1' => 'Start Date',
            'J1' => 'End Date',
            'K1' => 'Status',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $rowNum = 2;
        if ($export_rs) {
            while ($coupon = mysqli_fetch_assoc($export_rs)) {
                $sheet->setCellValue('A' . $rowNum, $coupon['id']);
                $sheet->setCellValue('B' . $rowNum, date('d,M Y - h:iA', strtotime($coupon['created_date'])));
                $sheet->setCellValue('C' . $rowNum, $coupon['coupan_code']);
                
                // Handle "All Categories" and "All Sub Categories"
                if ($coupon['category'] == 0 || $coupon['category'] == '') {
                    $cat_name = 'All Categories';
                } else {
                    $cat_name = $cat_lookup[$coupon['category']] ?? 'N/A';
                }
                
                if ($coupon['sub_category'] == 0 || $coupon['sub_category'] == '') {
                    $sub_name = 'All Sub Categories';
                } else {
                    $sub_name = $sub_lookup[$coupon['sub_category']] ?? 'N/A';
                }
                
                $sheet->setCellValue('D' . $rowNum, $cat_name);
                $sheet->setCellValue('E' . $rowNum, $sub_name);
                $sheet->setCellValue('F' . $rowNum, $coupon['type']);
                $sheet->setCellValue('G' . $rowNum, (float) $coupon['minimum_order_value']);
                $sheet->setCellValue('H' . $rowNum, $coupon['coupan_limit']);
                $sheet->setCellValue('I' . $rowNum, date('d,M Y', strtotime($coupon['start_date'])));
                $sheet->setCellValue('J' . $rowNum, date('d,M Y', strtotime($coupon['end_date'])));
                $sheet->setCellValue('K' . $rowNum, $coupon['status']);
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
        $fileName = 'coupons_export_' . date('Ymd_His') . '.xlsx';
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
        echo 'Failed to export coupons: ' . $e->getMessage();
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
    <title>COUPONS MANAGEMENT | HADIDY</title>
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
                    <div>
                        <h1 class="page-title fw-semibold fs-18 mb-0">Coupons</h1>
                        <p class="text-muted mb-0">Total: <?php echo $total_records; ?> coupons found</p>
                    </div>
                    <a href="./addCoupons.php" type="button"
                        class="btn btn-secondary btn-wave waves-effect waves-light">+ Add Coupons</a>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">

                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel', 'search' => isset($_POST['search']) ? $_POST['search'] : (isset($_GET['search']) ? $_GET['search'] : '')])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                    <div class="selecton-order">
                                        <form method="POST" class="d-flex">
                                            <input type="search" name="search" class="form-control"
                                                placeholder="Search coupons..."
                                                value="<?php echo htmlspecialchars($search); ?>"
                                                aria-describedby="button-addon2">
                                            <?php if (!empty($search)): ?>
                                                <button type="button" class="btn btn-outline-secondary ms-1"
                                                    onclick="clearSearch()">
                                                    <i class="fa-solid fa-times"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>

                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">Created On</th>
                                            <th scope="col">Coupons Code</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Sub Category</th>
                                            <th scope="col">Types</th>
                                            <th scope="col">Order Value</th>
                                            <th scope="col">Limit</th>
                                            <th scope="col">Start Date</th>
                                            <th scope="col">End Date</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php
                                        $row_number = $offset + 1;
                                        if (mysqli_num_rows($final_result) > 0) {
                                            while ($coupon = mysqli_fetch_assoc($final_result)) {
                                                // Format dates
                                                $created_date = date('d,M Y - h:iA', strtotime($coupon['created_date']));
                                                $start_date = date('d,M Y', strtotime($coupon['start_date']));
                                                $end_date = date('d,M Y', strtotime($coupon['end_date']));

                                                // Get category and subcategory names
                                                if ($coupon['category'] == 0 || $coupon['category'] == '') {
                                                    $category_name = 'All Categories';
                                                } else {
                                                    $category_name = isset($categories[$coupon['category']]) ? $categories[$coupon['category']] : 'N/A';
                                                }
                                                
                                                if ($coupon['sub_category'] == 0 || $coupon['sub_category'] == '') {
                                                    $subcategory_name = 'All Sub Categories';
                                                } else {
                                                    $subcategory_name = isset($subcategories[$coupon['sub_category']]) ? $subcategories[$coupon['sub_category']] : 'N/A';
                                                }

                                                // Status badge
                                                $status_badge = $coupon['status'] == 'Active' ?
                                                    '<span class="badge rounded-pill bg-outline-success">Active</span>' :
                                                    '<span class="badge rounded-pill bg-outline-danger">Inactive</span>';
                                                ?>
                                                <tr>
                                                    <td><?php echo $row_number; ?></td>
<td>
    <?php
    if (!empty($created_date)) {
        // Clean up your custom format: remove comma and dash
        $clean_date = str_replace([',', '-'], '', $created_date);
        $clean_date = trim($clean_date); // now: "11 Nov 2025 04:45AM"

        // Parse that custom format manually
        $dt = DateTime::createFromFormat('j M Y h:iA', $clean_date, new DateTimeZone('UTC'));

        if ($dt) {
            // Convert UTC → IST
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            echo $dt->format('d M Y, h:i A');
        } else {
            echo htmlspecialchars($created_date); // fallback if parsing fails
        }
    } else {
        echo '—';
    }
    ?>
</td>


                                                    <td class="rqu-id">
                                                        <strong><?php echo htmlspecialchars($coupon['coupan_code']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <span
                                                                class="badge bg-light text-dark"><?php echo htmlspecialchars($category_name); ?></span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($subcategory_name); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($coupon['type']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            ₹<?php echo number_format($coupon['minimum_order_value']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $coupon['coupan_limit']; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $start_date; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo $end_date; ?>
                                                        </div>
                                                    </td>
                                                    <td><?php echo $status_badge; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="./editCoupons.php?id=<?php echo $coupon['id']; ?>"
                                                                class="i-icon-eidt" title="Edit Coupon">
                                                                <i class="fa-regular fa-pen-to-square"></i>
                                                            </a>
                                                            <a href="#" class="i-icon-trash cancelOrderBtn"
                                                                data-coupon-id="<?php echo $coupon['id']; ?>"
                                                                title="Delete Coupon">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php
                                                $row_number++;
                                            }
                                        } else {
                                            ?>
                                            <tr>
                                                <td colspan="12" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                                        <p>No coupons found</p>
                                                        <?php if (!empty($search)): ?>
                                                            <p>Try adjusting your search criteria</p>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div class="my-4">
                                    <nav aria-label="Page navigation">
                                        <form method="POST" id="paginationForm">
                                            <?php if (!empty($search)): ?>
                                                <input type="hidden" name="search"
                                                    value="<?php echo htmlspecialchars($search); ?>">
                                            <?php endif; ?>
                                            <ul class="pagination justify-content-center flex-wrap">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <button type="submit" name="page" value="<?php echo $page - 1; ?>"
                                                            class="page-link"><i class="bx bx-chevron-left"></i>
                                                            Previous</button>
                                                    </li>
                                                <?php else: ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">Previous</span>
                                                    </li>
                                                <?php endif; ?>

                                                <?php
                                                $startPage = max(1, $page - 2);
                                                $endPage = min($total_pages, $page + 2);

                                                for ($i = $startPage; $i <= $endPage; $i++):
                                                    ?>
                                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                        <button type="submit" name="page" value="<?php echo $i; ?>"
                                                            class="page-link"><?php echo $i; ?></button>
                                                    </li>
                                                <?php endfor; ?>

                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <button type="submit" name="page" value="<?php echo $page + 1; ?>"
                                                            class="page-link">Next <span aria-hidden="true"><i
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
                            <?php endif; ?>
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
                                Are you sure, you want to Delete <br> the Coupon ?
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
        <script src="./assets/libs/simplebar/simplebar.min.js"></script>
        <script src="./assets/js/simplebar.js"></script>

        <!-- Color Picker JS -->
        <script src="./assets/libs/@simonwep/pickr/pickr.es5.min.js"></script>

        <!-- Apex Charts JS -->
        <script src="./assets/libs/apexcharts/apexcharts.min.js"></script>

        <!-- Ecommerce-Dashboard JS -->
        <script src="./assets/js/ecommerce-dashboard.js"></script>

        <!-- Custom-Switcher JS -->
        <script src="./assets/js/custom-switcher.min.js"></script>

        <!-- Date & Time Picker JS -->
        <script src="./assets/libs/flatpickr/flatpickr.min.js"></script>
        <script src="./assets/js/date&time_pickers.js"></script>

        <!-- Custom JS -->
        <script src="./assets/js/custom.js"></script>

        <!-- JavaScript for Address Truncation -->

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
                        const couponId = btn.getAttribute('data-coupon-id');
                        cancelYesBtn.setAttribute('data-coupon-id', couponId);
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
                        const couponId = cancelYesBtn.getAttribute('data-coupon-id');
                        if (couponId) {
                            window.location.href = 'coupons.php?delete_id=' + couponId;
                        }
                    });
                }
            });
        </script>


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
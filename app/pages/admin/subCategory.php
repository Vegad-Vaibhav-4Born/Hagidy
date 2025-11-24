<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit();
}


$message = '';
$error = '';

// Handle success messages from redirects
if (isset($_GET['success'])) {
    $message = "Subcategory added successfully!";
}
if (isset($_GET['updated'])) {
    $message = "Subcategory updated successfully!";
}
if (isset($_GET['deleted'])) {
    $message = "Subcategory deleted successfully!";
}

// Handle form submission for adding subcategory
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_subcategory']) || (!isset($_POST['update_subcategory']) && !isset($_POST['subcategory_id']) || $_POST['subcategory_id'] == ''))) {
    $subcategory_name = mysqli_real_escape_string($con, $_POST['subcategory_name']);
    $category_id = (int) $_POST['category_id'];
    $mandatory_attributes = isset($_POST['mandatory_attributes']) ? implode(',', $_POST['mandatory_attributes']) : '';
    $optional_attributes = isset($_POST['optional_attributes']) ? implode(',', $_POST['optional_attributes']) : '';

    // Generate unique subcategory ID (6 digits)
    $subcategory_id = rand(100000, 999999);

    // Insert subcategory into database
    $query = "INSERT INTO sub_category (sub_category_id, category_id, name, mandatory_attributes, optional_attributes, date, created_date) 
              VALUES ('$subcategory_id', '$category_id', '$subcategory_name', '$mandatory_attributes', '$optional_attributes', NOW(), NOW())";

    if (mysqli_query($con, $query)) {
        $message = "Subcategory added successfully!";
        // Redirect to prevent form resubmission
        header("Location: subCategory.php?success=1");
        exit();
    } else {
        $error = "Error adding subcategory: " . mysqli_error($con);
    }
}

// Handle form submission for updating subcategory
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_subcategory'])) {
    $id = (int) $_POST['subcategory_id'];
    $subcategory_name = mysqli_real_escape_string($con, $_POST['subcategory_name']);
    $category_id = (int) $_POST['category_id'];
    $mandatory_attributes = isset($_POST['mandatory_attributes']) ? implode(',', $_POST['mandatory_attributes']) : '';
    $optional_attributes = isset($_POST['optional_attributes']) ? implode(',', $_POST['optional_attributes']) : '';

    // Update subcategory in database
    $query = "UPDATE sub_category SET name='$subcategory_name', category_id='$category_id', mandatory_attributes='$mandatory_attributes', optional_attributes='$optional_attributes' WHERE id=$id";

    if (mysqli_query($con, $query)) {
        $message = "Subcategory updated successfully!";
        // Redirect to prevent form resubmission
        header("Location: subCategory.php?updated=1");
        exit();
    } else {
        $error = "Error updating subcategory: " . mysqli_error($con);
    }
}

// Handle delete subcategory
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $query = "DELETE FROM sub_category WHERE id=$id";
    if (mysqli_query($con, $query)) {
        $message = "Subcategory deleted successfully!";
        // Redirect to prevent form resubmission
        header("Location: subCategory.php?deleted=1");
        exit();
    } else {
        $error = "Error deleting subcategory: " . mysqli_error($con);
    }
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE sc.name LIKE '%$search%' OR c.name LIKE '%$search%'";
}

// Export to Excel (all filtered data, resolve category_id to name and attribute ids to names)
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
        $exportQuery = "SELECT sc.*, c.name AS category_name FROM sub_category sc LEFT JOIN category c ON sc.category_id = c.id $search_condition ORDER BY sc.created_date DESC";
        $exportResult = mysqli_query($con, $exportQuery);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sub Categories');

        // Headers
        $headers = [
            'A1' => 'ID',
            'B1' => 'Sub Category ID',
            'C1' => 'Category', // show category name instead of id
            'D1' => 'Sub Category',
            'E1' => 'Mandatory Attributes',
            'F1' => 'Optional Attributes',
            'G1' => 'Created On',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Cache for attribute id->name resolution
        $attributeNameCache = [];

        $rowNum = 2;
        if ($exportResult) {
            while ($row = mysqli_fetch_assoc($exportResult)) {
                // Resolve attribute id lists to names
                $mandNames = function_exists('getAttributeNames') ? getAttributeNames($con, $row['mandatory_attributes'] ?? '', $attributeNameCache) : [];
                $optNames = function_exists('getAttributeNames') ? getAttributeNames($con, $row['optional_attributes'] ?? '', $attributeNameCache) : [];

                $sheet->setCellValue('A' . $rowNum, $row['id']);
                $sheet->setCellValue('B' . $rowNum, $row['sub_category_id']);
                $sheet->setCellValue('C' . $rowNum, $row['category_name'] ?? '');
                $sheet->setCellValue('D' . $rowNum, $row['name']);
                $sheet->setCellValue('E' . $rowNum, empty($mandNames) ? '' : implode(', ', $mandNames));
                $sheet->setCellValue('F' . $rowNum, empty($optNames) ? '' : implode(', ', $optNames));
                $sheet->setCellValue('G' . $rowNum, date('d,M Y - h:iA', strtotime($row['created_date'])));
                $rowNum++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'G') as $col) {
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
        $fileName = 'subcategories_export_' . date('Ymd_His') . '.xlsx';
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
        echo 'Failed to export subcategories: ' . $e->getMessage();
        exit;
    }
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM sub_category sc 
                LEFT JOIN category c ON sc.category_id = c.id $search_condition";
$count_result = mysqli_query($con, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get subcategories with pagination and search
$query = "SELECT sc.*, c.name as category_name 
          FROM sub_category sc 
          LEFT JOIN category c ON sc.category_id = c.id 
          $search_condition 
          ORDER BY sc.created_date DESC 
          LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($con, $query);

// Get all categories for dropdown
$categories_query = "SELECT * FROM category ORDER BY name";
$categories_result = mysqli_query($con, $categories_query);
$attributeNameCache = [];

// Helper: get attribute names from comma-separated ids
function getAttributeNames($con, $idsCsv, &$cache)
{
    if (empty($idsCsv))
        return [];
    $ids = array_filter(array_map('intval', explode(',', $idsCsv)));
    if (empty($ids))
        return [];

    $names = [];
    $missing = [];
    foreach ($ids as $id) {
        if (isset($cache[$id])) {
            $names[] = $cache[$id];
        } else {
            $missing[] = $id;
        }
    }
    if (!empty($missing)) {
        $idsStr = implode(',', $missing);
        $q = "SELECT id, name FROM attributes WHERE id IN ($idsStr)";
        if ($r = mysqli_query($con, $q)) {
            while ($row = mysqli_fetch_assoc($r)) {
                $cache[(int) $row['id']] = $row['name'];
            }
        }
        // Rebuild names with newly cached values
        $names = [];
        foreach ($ids as $id) {
            if (isset($cache[$id]))
                $names[] = $cache[$id];
        }
    }
    return $names;
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
    <title>SUB-CATEGORY MANAGEMENT | HADIDY</title>
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
        <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div
                    class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Sub Category</h1>
                    <a href="#" type="button" class="btn btn-secondary btn-wave waves-effect waves-light"
                        data-bs-toggle="modal" data-bs-target="#addAttributeModal">+ Add Sub Category</a>
                </div>
                <!-- Page Header Close -->

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert" id="successMessage">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">

                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                    <div class="selecton-order">
                                        <form method="GET" class="d-flex">
                                            <input type="search" name="search" class="form-control"
                                                placeholder="Search subcategory or category"
                                                value="<?php echo htmlspecialchars($search); ?>"
                                                aria-describedby="button-addon2">
                                            <button type="submit" class="btn btn-outline-secondary" type="button">
                                                <i class="fe fe-search"></i>
                                            </button>
                                            <?php if (!empty($search)): ?>
                                                <a href="subCategory.php" class="btn btn-outline-danger ms-1" type="button">
                                                    <i class="fe fe-x"></i>
                                                </a>
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
                                            <th scope="col">ID</th>
                                            <th scope="col">Category</th>
                                            <th scope="col">Sub Category</th>
                                            <th scope="col">Mandatory Attributes</th>
                                            <th scope="col">Optional Attributes</th>
                                            <th scope="col">Created On</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php if (mysqli_num_rows($result) > 0): ?>
                                            <?php $index = 0; ?>
                                            <?php while ($subcategory = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td><?php echo $offset + $index + 1; ?></td>
                                                    <td class="rqu-id">#<?php echo $subcategory['sub_category_id']; ?></td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($subcategory['category_name'] ?? 'N/A'); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($subcategory['name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            $mandNames = getAttributeNames($con, $subcategory['mandatory_attributes'] ?? '', $attributeNameCache);
                                                            if (!empty($mandNames)) {
                                                                foreach ($mandNames as $n) {
                                                                    echo '<span class="badge bg-light text-dark">' . htmlspecialchars($n) . '</span> ';
                                                                }
                                                            } else {
                                                                echo '<span class="text-muted">None</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            $optNames = getAttributeNames($con, $subcategory['optional_attributes'] ?? '', $attributeNameCache);
                                                            if (!empty($optNames)) {
                                                                foreach ($optNames as $n) {
                                                                    echo '<span class="badge bg-light text-dark">' . htmlspecialchars($n) . '</span> ';
                                                                }
                                                            } else {
                                                                echo '<span class="text-muted">None</span>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            if (!empty($subcategory['created_date'])) {
                                                                try {
                                                                    // Convert UTC time from DB to India time
                                                                    $dt = new DateTime($subcategory['created_date'], new DateTimeZone('UTC'));
                                                                    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                    echo $dt->format('d M Y - h:i A');
                                                                } catch (Exception $e) {
                                                                    echo htmlspecialchars($subcategory['created_date']); // fallback
                                                                }
                                                            } else {
                                                                echo '—';
                                                            }
                                                            ?>
                                                        </div>
                                                    </td>

                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <a href="#" class="i-icon-eidt" data-bs-toggle="modal"
                                                                data-bs-target="#addAttributeModal"
                                                                onclick="editSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>', <?php echo $subcategory['category_id']; ?>, '<?php echo $subcategory['mandatory_attributes'] ?? ''; ?>', '<?php echo $subcategory['optional_attributes'] ?? ''; ?>')">
                                                                <i class="fa-regular fa-pen-to-square"></i>
                                                            </a>
                                                            <a href="#" class="i-icon-trash cancelOrderBtn"
                                                                data-subcategory-id="<?php echo $subcategory['id']; ?>">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php $index++; ?>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="fe fe-inbox fs-48 mb-3"></i>
                                                        <p class="mb-0">No subcategories found</p>
                                                        <?php if (!empty($search)): ?>
                                                            <small>Try adjusting your search criteria</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <div class="d-flex justify-content-center mt-3 mb-3">
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0">
                                            <!-- Previous Button -->
                                            <?php if ($current_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Previous</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Previous</span>
                                                </li>
                                            <?php endif; ?>

                                            <!-- Page Numbers -->
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Next Button -->
                                            <?php if ($current_page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">Next</a>
                                                </li>
                                            <?php else: ?>
                                                <li class="page-item disabled">
                                                    <span class="page-link">Next</span>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
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
                                Are you sure, you want to Delete <br> the Sub Category ?
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
                        <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Sub Category
                        </h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <form id="subcategoryForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" id="subcategory_id" name="subcategory_id" value="">
                            <div class="mb-3">
                                <label for="category_id" class="form-label fw-semibold">Category <span
                                        class="text-danger">*</span></label>
                                <select id="category_id" name="category_id"
                                    class="form-select form-select-lg selecton-order w-100" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    // Reset the categories result pointer
                                    mysqli_data_seek($categories_result, 0);
                                    while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="subcategory_name" class="form-label fw-semibold">Sub Category Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="subcategory_name"
                                    name="subcategory_name" placeholder="Enter Sub Category Name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mandatory Attributes <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="mandatory_attributes[]" id="mandatory_attributes"
                                    multiple>
                                    <?php
                                    $attributes_query = "SELECT * FROM attributes ORDER BY name ASC";
                                    $attributes_result = mysqli_query($con, $attributes_query);
                                    while ($attribute = mysqli_fetch_assoc($attributes_result)): ?>
                                        <option value="<?php echo $attribute['id']; ?>">
                                            <?php echo htmlspecialchars($attribute['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Optional Attributes</label>
                                <select class="form-control" name="optional_attributes[]" id="optional_attributes"
                                    multiple>
                                    <?php
                                    $attributes_result2 = mysqli_query($con, $attributes_query);
                                    while ($attribute2 = mysqli_fetch_assoc($attributes_result2)): ?>
                                        <option value="<?php echo $attribute2['id']; ?>">
                                            <?php echo htmlspecialchars($attribute2['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn save-attribute-btn w-100" id="submitBtn"
                                name="add_subcategory">Add Sub Category</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

     

        <!-- Modal JavaScript -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Auto-hide success message after 5 seconds and clean URL
                const successMessage = document.getElementById('successMessage');
                if (successMessage) {
                    // Clean URL parameters immediately to prevent message on refresh
                    if (window.history.replaceState) {
                        const url = new URL(window.location);
                        url.searchParams.delete('success');
                        url.searchParams.delete('updated');
                        url.searchParams.delete('deleted');
                        window.history.replaceState({}, document.title, url.pathname + url.search);
                    }

                    // Hide message after 5 seconds
                    setTimeout(function () {
                        successMessage.style.transition = 'opacity 0.5s';
                        successMessage.style.opacity = '0';
                        setTimeout(function () {
                            successMessage.remove();
                        }, 500);
                    }, 5000);
                }

                // Get all cancel buttons and modal elements
                const cancelBtns = document.querySelectorAll('.cancelOrderBtn');
                const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
                const cancelNoBtn = document.getElementById('cancelNoBtn');
                const cancelYesBtn = document.getElementById('cancelYesBtn');

                let subcategoryIdToDelete = null;

                // Show modal when any cancel button is clicked
                cancelBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        subcategoryIdToDelete = this.getAttribute('data-subcategory-id');
                        modal.show();
                    });
                });

                // Handle No button click
                if (cancelNoBtn) {
                    cancelNoBtn.addEventListener('click', function () {
                        modal.hide();
                        subcategoryIdToDelete = null;
                    });
                }

                // Handle Yes button click
                if (cancelYesBtn) {
                    cancelYesBtn.addEventListener('click', function () {
                        if (subcategoryIdToDelete) {
                            // Redirect to delete the subcategory
                            window.location.href = '?delete=' + subcategoryIdToDelete;
                        }
                    });
                }
            });
        </script>
        <script>
            document.getElementById('addMoreValue').addEventListener('click', function () {
                const valueFields = document.getElementById('valueFields');
                const div = document.createElement('div');
                div.className = 'input-group mb-2';
                div.innerHTML = `
        <input type="text" class="form-control form-control-lg" placeholder="Enter Value">
        <button type="button" class="btn delete-value-btn">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/delete.png" alt="Delete" style="width:24px;height:24px;">
        </button>
    `;
                valueFields.appendChild(div);
            });

            document.getElementById('valueFields').addEventListener('click', function (e) {
                if (e.target.closest('.delete-value-btn')) {
                    e.target.closest('.input-group').remove();
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
            // Initialize Choices for attributes
            let mandatoryChoices, optionalChoices;

            document.addEventListener('DOMContentLoaded', function () {
                try {
                    mandatoryChoices = new Choices('#mandatory_attributes', {
                        removeItemButton: true,
                        placeholder: true,
                        placeholderValue: 'Select mandatory attributes',
                        searchEnabled: true,
                        allowHTML: true
                    });
                    optionalChoices = new Choices('#optional_attributes', {
                        removeItemButton: true,
                        placeholder: true,
                        placeholderValue: 'Select optional attributes',
                        searchEnabled: true,
                        allowHTML: true
                    });
                } catch (e) {
                    console.error('Choices init failed', e);
                }
            });

            // Reset form for adding new subcategory
            function resetForm() {
                document.getElementById('subcategoryForm').reset();
                document.getElementById('subcategory_id').value = '';
                document.getElementById('addAttributeModalLabel').textContent = 'Add Sub Category';
                document.getElementById('submitBtn').textContent = 'Add Sub Category';
                document.getElementById('submitBtn').setAttribute('name', 'add_subcategory');
                if (mandatoryChoices) mandatoryChoices.removeActiveItems();
                if (optionalChoices) optionalChoices.removeActiveItems();
            }

            // Edit subcategory function (with attributes)
            function editSubcategory(id, name, category_id, mandatory_attrs, optional_attrs) {
                document.getElementById('subcategory_id').value = id;
                document.getElementById('subcategory_name').value = name;
                document.getElementById('category_id').value = category_id;
                document.getElementById('addAttributeModalLabel').textContent = 'Edit Sub Category';
                document.getElementById('submitBtn').textContent = 'Update Sub Category';
                document.getElementById('submitBtn').setAttribute('name', 'update_subcategory');
                if (mandatoryChoices) {
                    mandatoryChoices.removeActiveItems();
                    if (mandatory_attrs) {
                        mandatory_attrs.split(',').forEach(function (v) {
                            if (v.trim()) mandatoryChoices.setChoiceByValue(v.trim());
                        });
                    }
                }
                if (optionalChoices) {
                    optionalChoices.removeActiveItems();
                    if (optional_attrs) {
                        optional_attrs.split(',').forEach(function (v) {
                            if (v.trim()) optionalChoices.setChoiceByValue(v.trim());
                        });
                    }
                }
            }

            // Form submission handling
            document.getElementById('subcategoryForm').addEventListener('submit', function (e) {
                const subcategoryName = document.getElementById('subcategory_name').value.trim();
                const categoryId = document.getElementById('category_id').value;

                if (!subcategoryName) {
                    e.preventDefault();
                    alert('Please enter a subcategory name.');
                    return false;
                }

                if (!categoryId) {
                    e.preventDefault();
                    alert('Please select a category.');
                    return false;
                }

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const isUpdate = submitBtn.getAttribute('name') === 'update_subcategory';
                submitBtn.textContent = isUpdate ? 'Updating...' : 'Adding...';
                submitBtn.classList.add('disabled');
            });
        </script>

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
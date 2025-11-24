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
    $message = "Category added successfully!";
}
if (isset($_GET['updated'])) {
    $message = "Category updated successfully!";
}
if (isset($_GET['deleted'])) {
    $message = "Category deleted successfully!";
}


// Handle form submission for adding category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_category']) || (!isset($_POST['update_category']) && !isset($_POST['category_id']) || $_POST['category_id'] == ''))) {
    $category_name = mysqli_real_escape_string($con, $_POST['category_name']);

    // Generate unique category ID (6 digits)
    $category_id = rand(100000, 999999);

    // Handle image upload
    $image_name = '';
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $upload_dir = __DIR__ . '/../../../public/uploads/categories/';
        if (!file_exists(filename: $upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
        $image_name = $category_id . '.' . $file_extension;
        $upload_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $upload_path)) {
            $image_name = 'uploads/categories/' . $image_name;
        }
    }

    // Insert category into database (attributes moved to sub_category)
    $query = "INSERT INTO category (category_id, name, image, date, created_date) 
              VALUES ('$category_id', '$category_name', '$image_name', NOW(), NOW())";

    if (mysqli_query($con, $query)) {
        $message = "Category added successfully!";
        // Redirect to prevent form resubmission
        header("Location: category.php?success=1");
        exit();
    } else {
        $error = "Error adding category: " . mysqli_error($con);
    }
}

// Handle form submission for updating category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_category'])) {
    $id = (int) $_POST['category_id'];
    $category_name = mysqli_real_escape_string($con, $_POST['category_name']);


    // Handle image upload
    $image_name = '';
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] == 0) {
        $upload_dir = __DIR__ . '/../../../public/uploads/categories/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['category_image']['name'], PATHINFO_EXTENSION);
        $image_name = 'CAT' . $id . '.' . $file_extension;
        $upload_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['category_image']['tmp_name'], $upload_path)) {
            $image_name = 'uploads/categories/' . $image_name;
        }
    }

    // Update category in database (attributes moved to sub_category)
    if (!empty($image_name)) {
        $query = "UPDATE category SET name='$category_name', image='$image_name' WHERE id=$id";
    } else {
        $query = "UPDATE category SET name='$category_name' WHERE id=$id";
    }

    if (mysqli_query($con, $query)) {
        $message = "Category updated successfully!";
        // Redirect to prevent form resubmission
        header("Location: category.php?updated=1");
        exit();
    } else {
        $error = "Error updating category: " . mysqli_error($con);
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $query = "DELETE FROM category WHERE id=$id";
    if (mysqli_query($con, $query)) {
        $message = "Category deleted successfully!";
        // Redirect to prevent form resubmission
        header("Location: category.php?deleted=1");
        exit();
    } else {
        $error = "Error deleting category: " . mysqli_error($con);
    }
}

// Pagination settings
$records_per_page = 10;
$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$current_page = max(1, $current_page);

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($con, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = " WHERE name LIKE '%$search%'";
}

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
        $exportQuery = "SELECT id, category_id, name, image, created_date, date FROM category" . $search_condition . " ORDER BY created_date DESC";
        $exportResult = mysqli_query($con, $exportQuery);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Categories');

        // Headers
        $headers = [
            'A1' => 'ID',
            'B1' => 'Category ID',
            'C1' => 'Name',
            'D1' => 'No. of Subcategories',
            'E1' => 'Created On',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $rowNum = 2;
        if ($exportResult) {
            while ($row = mysqli_fetch_assoc($exportResult)) {
                // Resolve subcategory count using existing helper
                $subCount = 0;
                if (function_exists('getSubcategoryCount')) {
                    $subCount = (int) getSubcategoryCount($con, (int) $row['id']);
                } else {
                    // Inline fallback if helper not available yet
                    $cntRes = mysqli_query($con, 'SELECT COUNT(*) as count FROM sub_category WHERE category_id = ' . (int) $row['id']);
                    $subCount = ($cntRes && ($cntRow = mysqli_fetch_assoc($cntRes))) ? (int) $cntRow['count'] : 0;
                }

                $sheet->setCellValue('A' . $rowNum, $row['id']);
                $sheet->setCellValue('B' . $rowNum, $row['category_id']);
                $sheet->setCellValue('C' . $rowNum, $row['name']);
                $sheet->setCellValue('D' . $rowNum, $subCount);
                $sheet->setCellValue('E' . $rowNum, date('d,M Y - h:iA', strtotime($row['created_date'])));
                $rowNum++;
            }
        }

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
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
        $fileName = 'categories_export_' . date('Ymd_His') . '.xlsx';
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
        echo 'Failed to export categories: ' . $e->getMessage();
        exit;
    }
}

// Calculate offset
$offset = ($current_page - 1) * $records_per_page;

// Get total count of categories
$count_query = "SELECT COUNT(*) as total FROM category" . $search_condition;
$count_result = mysqli_query($con, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get categories for current page
$query = "SELECT * FROM category" . $search_condition . " ORDER BY created_date DESC LIMIT $records_per_page OFFSET $offset";
$result = mysqli_query($con, $query);
$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Attributes moved to subcategory; no attributes needed here

// Get single category for editing
$edit_category = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $query = "SELECT * FROM category WHERE id=$id";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $edit_category = mysqli_fetch_assoc($result);
    }
}

// Attribute display moved to subcategory

// Function to get subcategory count
function getSubcategoryCount($con, $category_id)
{
    $query = "SELECT COUNT(*) as count FROM sub_category WHERE category_id = $category_id";
    $result = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
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
    <title>CATEGORY MANAGEMENT | HADIDY</title>
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
                    <h1 class="page-title fw-semibold fs-18 mb-0">Category</h1>
                    <a href="#" type="button" class="btn btn-secondary btn-wave waves-effect waves-light"
                        data-bs-toggle="modal" data-bs-target="#addAttributeModal">+ Add Category</a>
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
                                 
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                  
                                    <div class="selecton-order">
                                        <div class="search-container">
                                            <input type="search" name="search" class="form-control" id="searchInput"
                                                placeholder="Search categories..."
                                                value="<?php echo htmlspecialchars($search); ?>"
                                                aria-describedby="button-addon2">
                                        </div>
                                    </div>
                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">ID</th>
                                            <th scope="col">Image</th>
                                            <th scope="col">Category</th>
                                            <!-- Attributes moved to subcategory -->
                                            <th scope="col">No of Sub Categorys</th>
                                            <th scope="col">Created On</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php if (empty($categories)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4">
                                                    <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
                                                    <div class="text-muted">No categories found. <a href="#"
                                                            data-bs-toggle="modal" data-bs-target="#addAttributeModal"
                                                            onclick="resetForm()">Add your first category</a></div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($categories as $index => $category):
                                                // Attributes moved to subcategory
                                                $subcategory_count = getSubcategoryCount($con, $category['id']);
                                                ?>
                                                <tr>
                                                    <td><?php echo $offset + $index + 1; ?></td>
                                                    <td class="rqu-id">#<?php echo $category['category_id']; ?></td>
                                                    <td class="d-flex justify-content-center">
                                                        <?php if (!empty($category['image'])): ?>
                                                            <img src="<?php if ($category['image'] !== '' || $category['image'] !== null) {
                                                                echo PUBLIC_ASSETS . $category['image'];
                                                            } else {
                                                                echo PUBLIC_ASSETS . 'uploads/categories/no-Categories.png';
                                                            } ?>"
                                                                alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                                style="width:50px;height:50px;border-radius:6px;margin-right:8px; background-color: #ecedf1;">
                                                        <?php else: ?>
                                                            <img src="<?php echo PUBLIC_ASSETS . 'uploads/categories/no-Categories.png'; ?>"
                                                                alt="<?php echo htmlspecialchars($category['name']); ?>"
                                                                style="width:50px;height:50px;border-radius:6px;margin-right:8px; background-color: #ecedf1;">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </div>
                                                    </td>
                                                    <!-- Attribute columns removed -->
                                                    <td class="text-center"><?php echo $subcategory_count; ?></td>
                                                    <td>
                                                        <div class="order-date-tr">
                                                            <?php
                                                            if (!empty($category['created_date'])) {
                                                                // Convert UTC → IST
                                                                $dt = new DateTime($category['created_date'], new DateTimeZone('UTC'));
                                                                $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                                echo $dt->format('d M Y - h:i A');
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
                                                                onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '', '', '<?php echo $category['image']; ?>')">
                                                                <i class="fa-regular fa-pen-to-square"></i>
                                                            </a>
                                                            <a href="#" class="i-icon-trash cancelOrderBtn"
                                                                data-category-id="<?php echo $category['id']; ?>">
                                                                <i class="fa-solid fa-trash-can"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
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
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);

                                            // Show first page if not in range
                                            if ($start_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">1</a>
                                                </li>
                                                <?php if ($start_page > 2): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <!-- Page numbers in range -->
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <!-- Show last page if not in range -->
                                            <?php if ($end_page < $total_pages): ?>
                                                <?php if ($end_page < $total_pages - 1): ?>
                                                    <li class="page-item disabled">
                                                        <span class="page-link">...</span>
                                                    </li>
                                                <?php endif; ?>
                                                <li class="page-item">
                                                    <a class="page-link"
                                                        href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $total_pages; ?></a>
                                                </li>
                                            <?php endif; ?>

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

                                <!-- Pagination Info -->
                                <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                                    <div class="text-muted">
                                        Showing <?php echo $offset + 1; ?> to
                                        <?php echo min($offset + $records_per_page, $total_records); ?> of
                                        <?php echo $total_records; ?> entries
                                    </div>
                                    <div class="text-muted">
                                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                    </div>
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
                                Are you sure, you want to Delete <br> the Category ?
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
                        <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Category</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <form id="attributeForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" id="category_id" name="category_id" value="">
                            <div class="mb-3">
                                <label for="category_name" class="form-label fw-semibold">Category Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="category_name"
                                    name="category_name" placeholder="Enter Category Name" required>
                            </div>
                            <!-- Attributes moved to Sub Category; inputs removed -->
                            <div class="mb-3">
                                <label for="variant" class="form-label fw-semibold">Image <span
                                        class="text-danger">*</span></label>
                                <div id="dropZone" class="border border-2 border-dashed rounded text-center">
                                    <label for="fileInput" class="file-label">
                                        Drag &amp; Drop your files or <span>Browse</span>
                                    </label>
                                    <input type="file" class="form-control d-none" id="category_image"
                                        name="category_image" accept="image/*">
                                </div>
                                <div id="current_image" class="mt-2" style="display: none;">
                                    <small class="text-muted">Current image:</small>

                                    <img id="current_image_preview" src="" alt="Current image"
                                        style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px;">
                                </div>
                            </div>
                            <button type="submit" class="btn save-attribute-btn w-100" id="submitBtn"
                                name="add_category">Add Category</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Import Modal -->
        <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-labelledby="bulkImportModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="p-4">
                        <div class="bulk-product-heading">
                            <h2>
                                Product Bulk Upload
                            </h2>
                            <p>
                                Please import your product data using our predefined Excel format
                            </p>
                        </div>
                        <div>
                            <div class="mb-3">
                                <label for="signin-Account-type" class="form-label text-default">Category <span
                                        class="text-danger">*</span></label>
                                <select class="form-control form-control-lg" name="product-Other"
                                    id="product-Account-type">
                                    <option value="Select Category">Select Category</option>
                                    <option value="Category1">Category 1</option>
                                    <option value="Category2">Category 2</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="signin-Account-type" class="form-label text-default">Sub Category <span
                                        class="text-danger">*</span></label>
                                <select class="form-control form-control-lg" name="product-Other"
                                    id="product-Account-type">
                                    <option value="Select Sub Category">Select Sub Category</option>
                                    <option value="SubCategory 1">Sub Category 1</option>
                                    <option value="SubCategory2">Sub Category 2</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="signin-GST" class="form-label text-default">GST Certificate <span
                                        class="text-danger">*</span></label>
                                <div class="drop-zone">
                                    <span class="drop-zone__prompt">Drag &amp; Drop your files or Browse</span>
                                    <input type="file" name="myFile" class="drop-zone__input">
                                </div>
                            </div>
                            <div class="excel-format">
                                <p>
                                    Excel format may vary based on the product category
                                </p>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-primary w-100 mb-3">Submit</button>
                                <a href="#" class="text-sky-blue text-center border-download">Download Category
                                    Template</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom CSS for Search and Bulk Import Modal -->
        <style>
            /* Search Input Styling */
            .search-container {
                position: relative;
                display: flex;
                align-items: center;
            }

            #searchInput {
                padding-right: 40px;
                border-radius: 6px;
                transition: all 0.3s ease;
            }

            #searchInput:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            }

            /* Loading state for search */
            .search-loading {
                position: relative;
            }

            .search-loading::after {
                content: '';
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                width: 16px;
                height: 16px;
                border: 2px solid #f3f3f3;
                border-top: 2px solid #007bff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: translateY(-50%) rotate(0deg); }
                100% { transform: translateY(-50%) rotate(360deg); }
            }

            .file-upload-area {
                transition: all 0.3s ease;
                cursor: pointer;
            }

            .file-upload-area:hover {
                border-color: #007bff !important;
                background-color: #f8f9fa;
            }

            .file-upload-area.dragover {
                border-color: #007bff !important;
                background-color: #e3f2fd;
            }

            .modal-content {
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            }

            .save-attribute-btn.disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }

            .save-attribute-btn.disabled:hover {
                transform: none;
                box-shadow: none;
            }

            .modal-header {
                padding: 1.5rem 1.5rem 0 1.5rem;
            }

            .modal-body {
                padding: 0 1.5rem 1.5rem 1.5rem;
            }

            .form-select:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25);
            }
        </style>

        <!-- JavaScript for Bulk Import Modal -->
        <script>
            // File upload functionality
            const fileUploadArea = document.getElementById('fileUploadArea');
            const excelFile = document.getElementById('excelFile');
            const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');
            const fileSelected = fileUploadArea.querySelector('.file-selected');
            const selectedFileName = document.getElementById('selectedFileName');

            // Click to upload
            fileUploadArea.addEventListener('click', () => {
                excelFile.click();
            });

            // File selection
            excelFile.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    selectedFileName.textContent = file.name;
                    fileUploadContent.classList.add('d-none');
                    fileSelected.classList.remove('d-none');
                }
            });

            // Drag and drop functionality
            fileUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });

            fileUploadArea.addEventListener('dragleave', () => {
                fileUploadArea.classList.remove('dragover');
            });

            fileUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    if (file.type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
                        file.type === 'application/vnd.ms-excel') {
                        excelFile.files = files;
                        selectedFileName.textContent = file.name;
                        fileUploadContent.classList.add('d-none');
                        fileSelected.classList.remove('d-none');
                    } else {
                        alert('Please select a valid Excel file (.xlsx or .xls)');
                    }
                }
            });

            // Clear file function
            function clearFile() {
                excelFile.value = '';
                fileUploadContent.classList.remove('d-none');
                fileSelected.classList.add('d-none');
            }

            // Download Excel format function
            function downloadExcelFormat() {
                // This would typically download a template file
                alert('Excel format template download functionality would be implemented here');
            }

            // Form submission
            document.getElementById('bulkImportForm').addEventListener('submit', (e) => {
                e.preventDefault();

                const category = document.getElementById('categorySelect').value;
                const file = document.getElementById('excelFile').files[0];

                if (!category) {
                    alert('Please select a category');
                    return;
                }

                if (!file) {
                    alert('Please select an Excel file');
                    return;
                }

                // Here you would typically handle the file upload
                alert('Bulk import functionality would be implemented here. Category: ' + category + ', File: ' + file.name);

                // Close modal after successful submission
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkImportModal'));
                modal.hide();
            });
        </script>
        <script>
            // Function to update thumbnail preview
            function updateThumbnail(dropZone, file) {
                // Remove existing preview
                const existingPreview = dropZone.querySelector('.image-preview');
                if (existingPreview) {
                    existingPreview.remove();
                }

                // Create preview element
                const preview = document.createElement('div');
                preview.className = 'image-preview mt-2';
                preview.innerHTML = `
                <img src="${URL.createObjectURL(file)}" alt="Preview" style="max-width: 150px; max-height: 150px; border-radius: 8px; border: 2px solid #dee2e6;">
                <div class="mt-1">
                    <small class="text-muted">${file.name}</small>
                </div>
            `;

                dropZone.appendChild(preview);
            }

            document.addEventListener('DOMContentLoaded', function () {
                const dropZone = document.getElementById('dropZone');
                const fileInput = document.getElementById('category_image');

                if (dropZone && fileInput) {
                    // Click to browse files
                    dropZone.addEventListener("click", (e) => {
                        fileInput.click();
                    });

                    // Handle file selection
                    fileInput.addEventListener("change", (e) => {
                        if (fileInput.files.length) {
                            updateThumbnail(dropZone, fileInput.files[0]);
                        }
                    });

                    // Drag over
                    dropZone.addEventListener("dragover", (e) => {
                        e.preventDefault();
                        dropZone.classList.add("border-primary", "bg-light");
                    });

                    // Drag leave and drag end
                    ["dragleave", "dragend"].forEach((type) => {
                        dropZone.addEventListener(type, (e) => {
                            dropZone.classList.remove("border-primary", "bg-light");
                        });
                    });

                    // Drop files
                    dropZone.addEventListener("drop", (e) => {
                        e.preventDefault();

                        if (e.dataTransfer.files.length) {
                            fileInput.files = e.dataTransfer.files;
                            updateThumbnail(dropZone, e.dataTransfer.files[0]);
                        }

                        dropZone.classList.remove("border-primary", "bg-light");
                    });
                }
            });
        </script>

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
                let categoryIdToDelete = null;

                // Show modal when any cancel button is clicked
                cancelBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        categoryIdToDelete = this.getAttribute('data-category-id');
                        modal.show();
                    });
                });

                // Handle No button click
                if (cancelNoBtn) {
                    cancelNoBtn.addEventListener('click', function () {
                        modal.hide();
                        categoryIdToDelete = null;
                    });
                }

                // Handle Yes button click
                if (cancelYesBtn) {
                    cancelYesBtn.addEventListener('click', function () {
                        if (categoryIdToDelete) {
                            // Redirect to delete the category
                            window.location.href = '?delete=' + categoryIdToDelete;
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
                // No attribute choices needed at Category level
            });
        </script>

        <script>
            // AJAX Search functionality
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput = document.getElementById('searchInput');
                const searchContainer = document.querySelector('.search-container');
                let searchTimeout;

                if (searchInput) {
                    // Search on input with debounce
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        const searchTerm = this.value.trim();
                        
                        // Show loading state
                        searchContainer.classList.add('search-loading');
                        
                        if (searchTerm.length > 0) {
                            searchTimeout = setTimeout(() => {
                                performSearch(searchTerm);
                            }, 500); // 500ms delay
                        } else {
                            // Clear search if input is empty
                            clearSearch();
                        }
                    });

                    // Search on Enter key
                    searchInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const searchTerm = this.value.trim();
                            if (searchTerm.length > 0) {
                                performSearch(searchTerm);
                            }
                        }
                    });

                    // Handle clear button (X) click
                    searchInput.addEventListener('search', function() {
                        if (this.value === '') {
                            clearSearch();
                        }
                    });
                }

                // Perform AJAX search
                function performSearch(searchTerm) {
                    // Create URL with search parameter
                    const url = new URL(window.location);
                    url.searchParams.set('search', searchTerm);
                    url.searchParams.delete('page'); // Reset to first page

                    // Fetch the page with search results
                    fetch(url.toString())
                        .then(response => response.text())
                        .then(html => {
                            // Create a temporary DOM element to parse the response
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Extract the table body content
                            const newTableBody = doc.querySelector('tbody');
                            const currentTableBody = document.querySelector('tbody');
                            
                            if (newTableBody && currentTableBody) {
                                currentTableBody.innerHTML = newTableBody.innerHTML;
                            }

                            // Update pagination if exists
                            const newPagination = doc.querySelector('.pagination');
                            const currentPagination = document.querySelector('.pagination');
                            
                            if (newPagination && currentPagination) {
                                currentPagination.innerHTML = newPagination.innerHTML;
                            }

                            // Update pagination info
                            const newPaginationInfo = doc.querySelector('.d-flex.justify-content-between.align-items-center.mt-2.mb-3');
                            const currentPaginationInfo = document.querySelector('.d-flex.justify-content-between.align-items-center.mt-2.mb-3');
                            
                            if (newPaginationInfo && currentPaginationInfo) {
                                currentPaginationInfo.innerHTML = newPaginationInfo.innerHTML;
                            }

                            // Update URL without page reload
                            window.history.pushState({}, '', url.toString());
                        })
                        .catch(error => {
                            console.error('Search error:', error);
                            alert('Search failed. Please try again.');
                        })
                        .finally(() => {
                            // Remove loading state
                            searchContainer.classList.remove('search-loading');
                        });
                }

                // Clear search function
                function clearSearch() {
                    // Remove search parameter from URL
                    const url = new URL(window.location);
                    url.searchParams.delete('search');
                    url.searchParams.delete('page');

                    // Fetch the page without search
                    fetch(url.toString())
                        .then(response => response.text())
                        .then(html => {
                            // Create a temporary DOM element to parse the response
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            // Extract the table body content
                            const newTableBody = doc.querySelector('tbody');
                            const currentTableBody = document.querySelector('tbody');
                            
                            if (newTableBody && currentTableBody) {
                                currentTableBody.innerHTML = newTableBody.innerHTML;
                            }

                            // Update pagination if exists
                            const newPagination = doc.querySelector('.pagination');
                            const currentPagination = document.querySelector('.pagination');
                            
                            if (newPagination && currentPagination) {
                                currentPagination.innerHTML = newPagination.innerHTML;
                            }

                            // Update pagination info
                            const newPaginationInfo = doc.querySelector('.d-flex.justify-content-between.align-items-center.mt-2.mb-3');
                            const currentPaginationInfo = document.querySelector('.d-flex.justify-content-between.align-items-center.mt-2.mb-3');
                            
                            if (newPaginationInfo && currentPaginationInfo) {
                                currentPaginationInfo.innerHTML = newPaginationInfo.innerHTML;
                            }

                            // Update URL without page reload
                            window.history.pushState({}, '', url.toString());
                        })
                        .catch(error => {
                            console.error('Clear search error:', error);
                            alert('Failed to clear search. Please refresh the page.');
                        })
                        .finally(() => {
                            // Remove loading state
                            searchContainer.classList.remove('search-loading');
                        });
                }
            });

            // Reset form for adding new category
            function resetForm() {
                document.getElementById('attributeForm').reset();
                document.getElementById('category_id').value = '';
                document.getElementById('addAttributeModalLabel').textContent = 'Add Category';
                document.getElementById('submitBtn').textContent = 'Add Category';
                document.getElementById('submitBtn').setAttribute('name', 'add_category');
                document.getElementById('current_image').style.display = 'none';

                // Clear image preview
                const dropZone = document.getElementById('dropZone');
                const existingPreview = dropZone.querySelector('.image-preview');
                if (existingPreview) {
                    existingPreview.remove();
                }
                // No attribute choices to reset at Category level
            }

            // Edit category function
            function editCategory(id, name, mandatory_attrs, optional_attrs, image) {
                document.getElementById('category_id').value = id;
                document.getElementById('category_name').value = name;
                document.getElementById('addAttributeModalLabel').textContent = 'Edit Category';
                document.getElementById('submitBtn').textContent = 'Update Category';
                document.getElementById('submitBtn').setAttribute('name', 'update_category');

                // Clear any existing image preview
                const dropZone = document.getElementById('dropZone');
                const existingPreview = dropZone.querySelector('.image-preview');
                if (existingPreview) {
                    existingPreview.remove();
                }

                // Show current image if exists
                if (image && image !== '') {
                    let public_assets = '<?php echo PUBLIC_ASSETS; ?>';
                    document.getElementById('current_image').style.display = 'block';
                    document.getElementById('current_image_preview').src = public_assets +  image;
                } else {
                    document.getElementById('current_image').style.display = 'none';
                }

                // No attribute choices at Category level
            }

            // Form submission handling
            document.getElementById('attributeForm').addEventListener('submit', function (e) {
                const categoryName = document.getElementById('category_name').value.trim();

                if (!categoryName) {
                    e.preventDefault();
                    alert('Please enter a category name.');
                    return false;
                }

                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const isUpdate = submitBtn.getAttribute('name') === 'update_category';

                // Don't disable the button as it prevents the name attribute from being submitted
                // Instead, just change the text and add a loading class
                submitBtn.textContent = isUpdate ? 'Updating...' : 'Adding...';
                submitBtn.classList.add('disabled');
            });
        </script>

</body>

</html>
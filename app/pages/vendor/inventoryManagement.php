<?php


if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../includes/init.php';

$vendor_reg_id = $_SESSION['vendor_reg_id'];

require_once __DIR__ . '/../../handlers/acess_guard.php';

// Get filter parameters from GET
$stockFilter = isset($_GET['stock_filter']) ? $_GET['stock_filter'] : 'all';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';
$subCategoryFilter = isset($_GET['sub_category']) ? $_GET['sub_category'] : '';
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Pagination parameters
$perPage = 10; // Items per page
$offset = ($page - 1) * $perPage;

// Build WHERE conditions
$whereConditions = ["vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."'"];
$params = [];

// Stock filter
if ($stockFilter === 'low') {
    $whereConditions[] = "inventory < 10 AND inventory > 0";
} elseif ($stockFilter === 'out') {
    $whereConditions[] = "inventory = 0";
}

// Category filter
if (!empty($categoryFilter)) {
    $whereConditions[] = "p.category_id = '".mysqli_real_escape_string($con, $categoryFilter)."'";
    $params[] = $categoryFilter;
}

// Sub category filter
if (!empty($subCategoryFilter)) {
    $whereConditions[] = "p.sub_category_id = '".mysqli_real_escape_string($con, $subCategoryFilter)."'";
}

// Search filter
if (!empty($searchTerm)) {
    $whereConditions[] = "(product_name LIKE '%".mysqli_real_escape_string($con, $searchTerm)."%' OR sku_id LIKE '%".mysqli_real_escape_string($con, $searchTerm)."%')";
}

$whereClause = implode(' AND ', $whereConditions);

// Export to Excel using current filters (no pagination limit)
if (isset($_GET['export']) && strtolower((string)$_GET['export']) === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    } else { @ob_end_clean(); }

    $autoloadPath = realpath(__DIR__ . '/../../../vendor/autoload.php');
    if ($autoloadPath && file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        // Ensure no buffered content leaks
        if (function_exists('ob_get_level')) { while (ob_get_level() > 0) { @ob_end_clean(); } }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        echo 'Excel export packages not found. Please install PhpSpreadsheet.';
        exit;
    }

    // Query all filtered products without LIMIT
    $exportQuery = "SELECT p.* FROM products p WHERE $whereClause ORDER BY p.id DESC";
    $exportResult = mysqli_query($con, $exportQuery);

    $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Inventory');

    // Headers (Full texts similar to products export)
    $headers = [
        'A1'=>'id','B1'=>'vendor_id','C1'=>'product_name','D1'=>'category_id','E1'=>'sub_category_id','F1'=>'mrp',
        'G1'=>'selling_price','H1'=>'discount','I1'=>'description','J1'=>'coin','K1'=>'platform_fee','L1'=>'brand',
        'M1'=>'gst','N1'=>'hsn_id','O1'=>'manufacture_details','P1'=>'packaging_details','Q1'=>'sku_id','R1'=>'group_id',
        'S1'=>'style_id','T1'=>'Inventory','U1'=>'product_brand','V1'=>'images','W1'=>'video','X1'=>'specifications',
        'Y1'=>'status','Z1'=>'seen','AA1'=>'status_note','AB1'=>'created_date','AC1'=>'updated_date'
    ];
    foreach ($headers as $cell => $label) { $sheet->setCellValue($cell, $label); }
    $sheet->getStyle('A1:AC1')->getFont()->setBold(true);

    $row = 2;
    if ($exportResult) {
        while ($r = mysqli_fetch_assoc($exportResult)) {
            $imagesVal = $r['images'] ?? '';
            if (is_array($imagesVal)) { $imagesVal = json_encode($imagesVal); }
            $specVal = $r['specifications'] ?? '';
            if (is_array($specVal)) { $specVal = json_encode($specVal); }

            $category_name = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM category WHERE id = '{$r['category_id']}'"));
            $subcategory_name = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM sub_category WHERE id = '{$r['sub_category_id']}'"));
            $vendor_name = mysqli_fetch_assoc(mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id = '{$r['vendor_id']}'"));
            $category_name = $category_name['name'] ?? '';
            $subcategory_name = $subcategory_name['name'] ?? '';
            $vendor_name = $vendor_name['business_name'] ?? '';

            $sheet->setCellValue('A'.$row, (string)($r['id'] ?? ''));
            $sheet->setCellValue('B'.$row, (string)($vendor_name ?? ''));
            $sheet->setCellValue('C'.$row, (string)($r['product_name'] ?? ''));
            $sheet->setCellValue('D'.$row, (string)($category_name ?? ''));
            $sheet->setCellValue('E'.$row, (string)($subcategory_name ?? ''));
            $sheet->setCellValue('F'.$row, (string)($r['mrp'] ?? ''));
            $sheet->setCellValue('G'.$row, (string)($r['selling_price'] ?? ''));
            $sheet->setCellValue('H'.$row, (string)($r['discount'] ?? ''));
            $sheet->setCellValue('I'.$row, (string)($r['description'] ?? ''));
            $sheet->setCellValue('J'.$row, (string)($r['coin'] ?? ''));
            $sheet->setCellValue('K'.$row, (string)($r['platform_fee'] ?? ''));
            $sheet->setCellValue('L'.$row, (string)($r['brand'] ?? ''));
            $sheet->setCellValue('M'.$row, (string)($r['gst'] ?? ''));
            $sheet->setCellValue('N'.$row, (string)($r['hsn_id'] ?? ''));
            $sheet->setCellValue('O'.$row, (string)($r['manufacture_details'] ?? ''));
            $sheet->setCellValue('P'.$row, (string)($r['packaging_details'] ?? ''));
            $sheet->setCellValue('Q'.$row, (string)($r['sku_id'] ?? ''));
            $sheet->setCellValue('R'.$row, (string)($r['group_id'] ?? ''));
            $sheet->setCellValue('S'.$row, (string)($r['style_id'] ?? ''));
            $inv = isset($r['Inventory']) ? $r['Inventory'] : (isset($r['inventory']) ? $r['inventory'] : '');
            $sheet->setCellValue('T'.$row, (string)$inv);
            $sheet->setCellValue('U'.$row, (string)($r['product_brand'] ?? ''));
            $sheet->setCellValue('V'.$row, (string)$imagesVal);
            $sheet->setCellValue('W'.$row, (string)($r['video'] ?? ''));
            $sheet->setCellValue('X'.$row, (string)$specVal);
            $sheet->setCellValue('Y'.$row, (string)($r['status'] ?? ''));
            $sheet->setCellValue('Z'.$row, (string)($r['seen'] ?? ''));
            $sheet->setCellValue('AA'.$row, (string)($r['status_note'] ?? ''));
            $sheet->setCellValue('AB'.$row, (string)($r['created_date'] ?? ''));
            $sheet->setCellValue('AC'.$row, (string)($r['updated_date'] ?? ''));
            $row++;
        }
    }
    foreach (range('A','AC') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }
    // Clean buffers again before sending headers/output
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) { @ob_end_clean(); }
    } else { @ob_end_clean(); }

    $fileName = 'inventory_export_'.date('Ymd_His').'.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$fileName.'"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: public');
    header('Expires: 0');
    header('Content-Transfer-Encoding: binary');
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Fetch products with pagination
$products = [];

// First get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM products p 
               LEFT JOIN category c ON p.category_id = c.id 
               LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
               WHERE $whereClause";
$countResult = mysqli_query($con, $countQuery);
$totalProducts = 0;
if ($countResult) {
    $countRow = mysqli_fetch_assoc($countResult);
    $totalProducts = (int)$countRow['total'];
}

// Calculate pagination
$totalPages = (int)ceil($totalProducts / $perPage);
if ($page > $totalPages && $totalPages > 0) { 
    $page = $totalPages; 
    $offset = ($page - 1) * $perPage;
}

// Fetch products with pagination
$productsQuery = "SELECT p.*, c.name as category_name, sc.name as subcategory_name 
                  FROM products p 
                  LEFT JOIN category c ON p.category_id = c.id 
                  LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
                  WHERE $whereClause 
                  ORDER BY p.id DESC 
                  LIMIT $perPage OFFSET $offset";

$result = mysqli_query($con, $productsQuery);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

// Get categories for filter dropdown
$categories = [];
$catResult = mysqli_query($con, "SELECT * FROM category ORDER BY name");
if ($catResult) {
    while ($cat = mysqli_fetch_assoc($catResult)) {
        $categories[] = $cat;
    }
}

// Get sub categories for filter dropdown (only if category is selected)
$subCategories = [];
if (!empty($categoryFilter)) {
    $subCatResult = mysqli_query($con, "SELECT * FROM sub_category WHERE category_id = '".mysqli_real_escape_string($con, $categoryFilter)."' ORDER BY name");
    if ($subCatResult) {
        while ($subCat = mysqli_fetch_assoc($subCatResult)) {
            $subCategories[] = $subCat;
        }
    }
}

// Count products by stock status
$allStockCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM products WHERE vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."'"));
$lowStockCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM products WHERE vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."' AND inventory < 10 AND inventory > 0"));
$outOfStockCount = mysqli_num_rows(mysqli_query($con, "SELECT id FROM products WHERE vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."' AND inventory = 0"));

?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>BULK-INVENTORY | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.css" rel="stylesheet">

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
        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php';?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php';?>
            <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid ">
                <div
                    class="d-flex align-items-center   justify-content-between mt-4 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Inventory Management </h1>
                     <div>
                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel'])); ?>
                        <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="btn-down-excle1"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                    </div>
                </div>

                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row mt-4">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card ">

                            <div class="card-body">
                                <div class="card-header justify-content-between p-0">
                                    <div class="card-header d-flex align-items-center justify-content-between">

                                        <div class="nav-index">
                                            <ul class="nav nav-pills " id="pills-tab" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <a href="?stock_filter=all" class="nav-link <?php echo $stockFilter === 'all' ? 'active' : ''; ?> nav-linkindex">
                                                        All Stock (<?php echo $allStockCount; ?>)
                                                    </a>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <a href="?stock_filter=low" class="nav-link <?php echo $stockFilter === 'low' ? 'active' : ''; ?> nav-linkindex">
                                                        Low Stock (<?php echo $lowStockCount; ?>)
                                                    </a>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <a href="?stock_filter=out" class="nav-link <?php echo $stockFilter === 'out' ? 'active' : ''; ?> nav-linkindex d-flex align-items-center">
                                                        Out Of Stock
                                                        <span class="badge ms-2 bg-danger"><?php echo $outOfStockCount; ?></span>
                                                    </a>
                                                </li>
                                            </ul>

                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <div class="selecton-order">
                                                <select id="categorySelect" class="form-select form-select-lg">
                                                    <option value="">All Categories</option>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cat['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="selecton-order">
                                                <select id="subcategorySelect" class="form-select form-select-lg">
                                                    <option value="">All Sub Categories</option>
                                                    <?php foreach ($subCategories as $subCat): ?>
                                                        <option value="<?php echo $subCat['id']; ?>" <?php echo $subCategoryFilter == $subCat['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($subCat['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="selecton-order position-relative">
                                                <input type="search" name="search" id="searchInput" class="form-control" placeholder="Search products..." 
                                                       value="<?php echo htmlspecialchars($searchTerm); ?>" aria-describedby="button-addon2">
                                                <?php if (!empty($searchTerm)): ?>
                                                    <button type="button" class="btn btn-sm position-absolute" id="clearSearch" 
                                                            style="right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d;">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <div class="tab-content " id="pills-tabContent">
                                    <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                                        aria-labelledby="pills-home-tab">
                                        
                                        <!-- Success Message -->
                                        <div id="successMessage" class="alert alert-success alert-dismissible fade show mb-3" style="display: none;">
                                            <i class="fas fa-check-circle me-2"></i>
                                            <span id="successText">Product updated successfully!</span>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                        
                                        <!-- Delete Success Message -->
                                        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                                        <div class="alert alert-success alert-dismissible fade show mb-3">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Product deleted successfully!
                                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php include './inventory_table_content.php'; ?>
                                    </div>
                                </div>

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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>


    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/ecommerce-dashboard.js"></script>


    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>

    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>

    <!-- Add this script before </body> to open modal on Submit -->
    <script>
        document.querySelector('.btn.btn-primary[style*="background:#3B4B6B"]').addEventListener('click', function (e) {
            e.preventDefault();
            var modal = new bootstrap.Modal(document.getElementById('confirmWithdrawModal'));
            modal.show();
        });
    </script>

    <script>
        // AJAX Search and Filter functionality
        let searchTimeout;
        
        function showSuccessMessage(message) {
            const successMsg = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            successText.textContent = message;
            successMsg.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(function() {
                successMsg.style.display = 'none';
            }, 5000);
        }
        
        function loadProducts() {
            // Show loading state
            const tableBody = document.querySelector('tbody');
            tableBody.innerHTML = '<tr><td colspan="11" class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            
            // Get current filter values
            const stockFilter = document.querySelector('.nav-link.active')?.getAttribute('data-filter') || 'all';
            const category = document.getElementById('categorySelect')?.value || '';
            const subCategory = document.getElementById('subcategorySelect')?.value || '';
            const search = document.getElementById('searchInput')?.value || '';
            
            // Build query string for GET request
            const params = new URLSearchParams();
            params.append('stock_filter', stockFilter);
            params.append('category', category);
            params.append('sub_category', subCategory);
            params.append('search', search);
            params.append('page', '1');
            
            fetch('inventoryManagement.php?' + params.toString(), {
                method: 'GET'
            })
            .then(response => response.text())
            .then(data => {
                // Extract table content from response
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newTableBody = doc.querySelector('tbody');
                const newPagination = doc.querySelector('.pagination') ? doc.querySelector('.pagination').parentElement : null;
                const newSubcategorySelect = doc.querySelector('#subcategorySelect');
                
                if (newTableBody) {
                    tableBody.innerHTML = newTableBody.innerHTML;
                } else {
                    tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No products found matching your criteria.</td></tr>';
                }
                
                // Update subcategory dropdown if it exists in response
                const currentSubcategorySelect = document.getElementById('subcategorySelect');
                if (currentSubcategorySelect && newSubcategorySelect) {
                    currentSubcategorySelect.innerHTML = newSubcategorySelect.innerHTML;
                }
                
                // Update pagination
                const paginationContainer = document.querySelector('.d-flex.justify-content-center.mb-3');
                if (paginationContainer && newPagination) {
                    paginationContainer.innerHTML = newPagination.innerHTML;
                } else if (paginationContainer) {
                    paginationContainer.innerHTML = '';
                }
                
                // Re-attach event listeners
                attachEditListeners();
            })
            .catch(error => {
                console.error('Error:', error);
                tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Error loading data</td></tr>';
            });
        }
        
        // Search input with AJAX
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const searchTerm = this.value.trim();
            
            // Show/hide clear button
            const clearBtn = document.getElementById('clearSearch');
            if (searchTerm.length > 0) {
                if (!clearBtn) {
                    const clearButton = document.createElement('button');
                    clearButton.type = 'button';
                    clearButton.className = 'btn btn-sm position-absolute';
                    clearButton.id = 'clearSearch';
                    clearButton.style.cssText = 'right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d;';
                    clearButton.innerHTML = '<i class="fas fa-times"></i>';
                    this.parentNode.appendChild(clearButton);
                }
            } else {
                if (clearBtn) {
                    clearBtn.remove();
                }
            }
            
            // Debounce search
            searchTimeout = setTimeout(function() {
                if (searchTerm.length >= 2 || searchTerm.length === 0) {
                    loadProducts();
                }
            }, 500);
        });
        
        // Clear search functionality
        document.addEventListener('click', function(e) {
            if (e.target && e.target.id === 'clearSearch') {
                document.getElementById('searchInput').value = '';
                if (document.getElementById('clearSearch')) {
                    document.getElementById('clearSearch').remove();
                }
                loadProducts();
            }
        });
        
        // Category filter functionality
        document.getElementById('categorySelect')?.addEventListener('change', function(){
            const categoryId = this.value;
            loadSubcategories(categoryId);
            loadProducts();
        });

        // Subcategory filter functionality
        document.getElementById('subcategorySelect')?.addEventListener('change', function(){
            loadProducts();
        });

        // Function to load subcategories dynamically
        function loadSubcategories(categoryId) {
            const subcategorySelect = document.getElementById('subcategorySelect');
            if (!subcategorySelect) return;

            // Clear existing options except the first one
            subcategorySelect.innerHTML = '<option value="">All Sub Categories</option>';

            if (!categoryId || categoryId === '') {
return;
            }
// Fetch subcategories via AJAX
            fetch(`./api/get_subcategories.php?category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
if (data.subcategories && data.subcategories.length > 0) {
                        data.subcategories.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.id;
                            option.textContent = subcategory.name;
                            subcategorySelect.appendChild(option);
                        });
} else {
}
                })
                .catch(error => {
                    console.error('Error loading subcategories:', error);
                });
        }

        function attachEditListeners() {
            document.querySelectorAll('.edit-btn').forEach(function (editBtn) {
                editBtn.addEventListener('click', function () {
                    var productId = this.getAttribute('data-product-id');
                    
                    // Revert any other row in edit mode
                    document.querySelectorAll('.action-update-btn').forEach(function (btn) {
                        var td = btn.closest('.action-cell');
                        var tr = td.closest('tr');
                        var editBtnOther = td.querySelector('.edit-btn');
                        var viewBtnOther = td.querySelector('.view-btn');
                        // Revert cells to text
                        var mrpCell = tr.querySelector('.mrp-cell');
                        var sellingCell = tr.querySelector('.selling-price-cell');
                        var gstCell = tr.querySelector('.gst-cell');
                        var stockCell = tr.querySelector('.stock-cell');
                        if (mrpCell && mrpCell.querySelector('input')) {
                            mrpCell.textContent = '₹' + parseFloat(mrpCell.querySelector('input').value).toFixed(2);
                        }
                        if (sellingCell && sellingCell.querySelector('input')) {
                            sellingCell.textContent = '₹' + parseFloat(sellingCell.querySelector('input').value).toFixed(2);
                        }
                        if (gstCell && gstCell.querySelector('input')) {
                            gstCell.textContent = gstCell.querySelector('input').value + '%';
                        }
                        if (stockCell && stockCell.querySelector('input')) {
                            stockCell.textContent = stockCell.querySelector('input').value;
                        }
                        btn.remove();
                        if (editBtnOther) editBtnOther.style.display = 'inline-flex';
                        if (viewBtnOther) viewBtnOther.style.display = 'inline-flex';
                    });
                    
                    // Make the clicked row editable
                    var td = editBtn.closest('.action-cell');
                    var tr = td.closest('tr');
                    var viewBtn = td.querySelector('.view-btn');
                    
                    // Hide edit and view buttons
                    editBtn.style.display = 'none';
                    if (viewBtn) viewBtn.style.display = 'none';
                    
                    // Prevent multiple Update buttons
                    if (!td.querySelector('.action-update-btn')) {
                        // Convert cells to input fields
                        var mrpCell = tr.querySelector('.mrp-cell');
                        var sellingCell = tr.querySelector('.selling-price-cell');
                        var gstCell = tr.querySelector('.gst-cell');
                        var stockCell = tr.querySelector('.stock-cell');
                        
                        if (mrpCell && !mrpCell.querySelector('input')) {
                            var mrpValue = mrpCell.textContent.replace('₹', '').replace(',', '').trim();
                            mrpCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + mrpValue + '" style="width:80px;" step="0.01">';
                        }
                        if (sellingCell && !sellingCell.querySelector('input')) {
                            var sellingValue = sellingCell.textContent.replace('₹', '').replace(',', '').trim();
                            sellingCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + sellingValue + '" style="width:80px;" step="0.01">';
                        }
                        if (gstCell && !gstCell.querySelector('input')) {
                            var gstValue = gstCell.textContent.replace('%', '').trim();
                            gstCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + gstValue + '" style="width:50px;" step="0.01">';
                        }
                        if (stockCell && !stockCell.querySelector('input')) {
                            var stockValue = stockCell.textContent.trim();
                            stockCell.innerHTML = '<input type="number" class="form-control form-control-sm" value="' + stockValue + '" style="width:60px;">';
                        }
                        
                        var updateBtn = document.createElement('button');
                        updateBtn.className = 'action-update-btn btn btn-primary btn-sm';
                        updateBtn.textContent = 'Update';
                        updateBtn.onclick = function () {
                            var mrpValue = mrpCell.querySelector('input').value;
                            var sellingValue = sellingCell.querySelector('input').value;
                            var gstValue = gstCell.querySelector('input').value;
                            var stockValue = stockCell.querySelector('input').value;
                            
                            // Send AJAX request to update product
                            var xhr = new XMLHttpRequest();
                            xhr.open('POST', 'update_product_inventory.php', true);
                            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                            xhr.onreadystatechange = function() {
                                if (xhr.readyState === 4 && xhr.status === 200) {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        // Update display values
                                        mrpCell.textContent = '₹' + parseFloat(mrpValue).toFixed(2);
                                        sellingCell.textContent = '₹' + parseFloat(sellingValue).toFixed(2);
                                        gstCell.textContent = gstValue + '%';
                                        stockCell.textContent = stockValue;
                                        
                                        // Update stock styling
                                        var stockQuantity = parseInt(stockValue);
                                        stockCell.className = 'stock-cell';
                                        if (stockQuantity == 0) {
                                            stockCell.className += ' text-danger fw-bold';
                                        } else if (stockQuantity < 10) {
                                            stockCell.className += ' text-warning fw-bold';
                                        }
                                        
                                        updateBtn.remove();
                                        editBtn.style.display = 'inline-flex';
                                        if (viewBtn) viewBtn.style.display = 'inline-flex';
                                        
                                        showSuccessMessage('Product updated successfully!');
                                    } else {
                                        showSuccessMessage('Error updating product: ' + response.message);
                                    }
                                }
                            };
                            xhr.send('product_id=' + productId + 
                                    '&mrp=' + mrpValue + 
                                    '&selling_price=' + sellingValue + 
                                    '&gst=' + gstValue + 
                                    '&stock_quantity=' + stockValue);
                        };
                        td.appendChild(updateBtn);
                    }
                });
            });
        }
        
        // Initialize edit listeners on page load
        attachEditListeners();
    </script>

</body>

</html>
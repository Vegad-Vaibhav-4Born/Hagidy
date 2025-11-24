<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $coupon_code = mysqli_real_escape_string($con, $_POST['coupon_code']);
    $coupon_limit = (int) $_POST['coupon_limit'];
    $category = isset($_POST['category']) && $_POST['category'] !== '' ? (int) $_POST['category'] : 0;
    $sub_category = isset($_POST['sub_category']) && $_POST['sub_category'] !== '' ? (int) $_POST['sub_category'] : 0;
    $type = mysqli_real_escape_string($con, $_POST['type']);
    $minimum_order_value = (float) $_POST['minimum_order_value'];
    $start_date = mysqli_real_escape_string($con, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($con, $_POST['end_date']);
    $status = mysqli_real_escape_string($con, $_POST['status']);
    // Determine discount based on type
    if ($type === 'Free Shipping') {
        $discount = 0;
    } else {
        $discount = isset($_POST['discount']) ? (float) $_POST['discount'] : 0;
        if ($type === 'Percentage') {
            if ($discount < 0)
                $discount = 0;
            if ($discount > 100)
                $discount = 100;
        }
        if ($type === 'Fixed Amount' && $discount < 0) {
            $discount = 0;
        }
    }
    // Check if coupon code already exists using prepared statement
    $check_stmt = mysqli_prepare($con, "SELECT id FROM `coupans` WHERE `coupan_code` = ?");
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, "s", $coupon_code);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if (mysqli_num_rows($check_result) > 0) {
            $_SESSION['error_message'] = "Coupon code already exists!";
        } else {
            // Insert new coupon using prepared statement
            $insert_stmt = mysqli_prepare($con, "INSERT INTO `coupans` (`coupan_code`, `discount`, `coupan_limit`, `category`, `sub_category`, `type`, `minimum_order_value`, `start_date`, `end_date`, `status`, `created_date`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($insert_stmt) {
                mysqli_stmt_bind_param($insert_stmt, "sdiissssss", $coupon_code, $discount, $coupon_limit, $category, $sub_category, $type, $minimum_order_value, $start_date, $end_date, $status);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $_SESSION['success_message'] = "Coupon '$coupon_code' has been added successfully!";
                    header('Location: coupons.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = "Error adding coupon: " . mysqli_stmt_error($insert_stmt);
                }
                mysqli_stmt_close($insert_stmt);
            } else {
                $_SESSION['error_message'] = "Error preparing insert statement: " . mysqli_error($con);
            }
        }
        mysqli_stmt_close($check_stmt);
    } else {
        $_SESSION['error_message'] = "Error preparing check statement: " . mysqli_error($con);
    }
}
// Fetch categories and subcategories for dropdowns using prepared statements
$categories_stmt = mysqli_prepare($con, "SELECT `id`, `name` FROM `category` ORDER BY `name` ASC");
if ($categories_stmt) {
    mysqli_stmt_execute($categories_stmt);
    $categories_result = mysqli_stmt_get_result($categories_stmt);
} else {
    $categories_result = false;
}
$subcategories_stmt = mysqli_prepare($con, "SELECT `id`, `name`, `category_id` FROM `sub_category` ORDER BY `name` ASC");
if ($subcategories_stmt) {
    mysqli_stmt_execute($subcategories_stmt);
    $subcategories_result = mysqli_stmt_get_result($subcategories_stmt);
} else {
    $subcategories_result = false;
}
$subcategories = [];
while ($sub = mysqli_fetch_assoc($subcategories_result)) {
    $subcategories[$sub['category_id']][] = $sub;
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
    <title>ADD-COUPONS | HADIDY</title>
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
                <div class="row  justify-content-center">
                    <div class="col-12 col-xl-9 col-lg-9 col-md-12 col-sm-12">
                        <div
                            class="d-md-flex d-block align-items-center justify-content-between my-2  page-header-breadcrumb">
                            <div class="d-flex align-items-center gap-4">
                                <h1 class="page-title fw-semibold fs-18 mb-0">Add Coupons</h1>
                            </div>
                            <div
                                class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                                <div class="ms-md-1 ms-0">
                                    <nav>
                                        <ol class="breadcrumb mb-0">
                                            <li class="breadcrumb-item">
                                                <a href="coupons.php">Coupons</a>
                                            </li>
                                            <li class="breadcrumb-item active" aria-current="page">Add Coupons</li>
                                        </ol>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <div class="card custom-card ">
                            <div class="card-body p-4">
                                <!-- Success/Error Messages -->
                                <?php if (isset($_SESSION['success_message'])): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fa-solid fa-check-circle me-2"></i>
                                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['success_message']); ?>
                                <?php endif; ?>
                                <?php if (isset($_SESSION['error_message'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"
                                            aria-label="Close"></button>
                                    </div>
                                    <?php unset($_SESSION['error_message']); ?>
                                <?php endif; ?>
                                <form method="POST" id="addCouponForm">
                                    <div class="row">
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Code<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="coupon_code"
                                                placeholder="Enter Coupon Code" required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Limits<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="number" name="coupon_limit"
                                                placeholder="Enter Coupons Limits" min="1" required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Category<span class="text-danger">*</span></label>
                                            <select class="form-select" name="category" id="categorySelect" required>
                                                <option value="">Select Category</option>
                                                <option value="0">All Categories</option>
                                                <?php 
                                                // Reset result pointer to show all categories
                                                mysqli_data_seek($categories_result, 0);
                                                while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                                    <option value="<?php echo $category['id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Sub Category<span class="text-danger" id="subCategoryRequired">*</span></label>
                                            <select class="form-select" name="sub_category" id="subCategorySelect">
                                                <option value="0">All Sub Categories</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Types<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="type" id="couponType" required>
                                                <option value="">Select Type</option>
                                                <option value="Free Shipping">Free Shipping</option>
                                                <option value="Percentage">Percentage</option>
                                                <option value="Fixed Amount">Fixed Amount</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3"
                                            id="discountWrapper" style="display:none;">
                                            <label class="form-label" id="discountLabel">Discount</label>
                                            <div class="input-group">
                                                <input class="form-control" type="number" name="discount"
                                                    id="discountInput" placeholder="Enter discount" min="0" step="0.01">
                                                <span class="input-group-text" id="discountSuffix"></span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Minimum order value<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="number" name="minimum_order_value"
                                                placeholder="Enter Min. Order Value" min="0" step="0.01" required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Start Date<span
                                                    class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-text text-muted"> <i
                                                        class="ri-calendar-line"></i></div>
                                                <input type="date" class="form-control" name="start_date" id="startDate"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">End Date<span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-text text-muted"> <i
                                                        class="ri-calendar-line"></i></div>
                                                <input type="date" class="form-control" name="end_date" id="endDate"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Status<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="Active">Active</option>
                                                <option value="Inactive">Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                                            <a href="coupons.php"
                                                class="btn btn-outline-secondary btn-wave waves-effect waves-light">Cancel</a>
                                            <button type="submit"
                                                class="btn btn-secondary btn-wave waves-effect waves-light"
                                                style="background-color: #3B4B6B !important; border: none;">Add
                                                Coupon</button>
                                        </div>
                                </form>
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
                                    <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                                </ul>
                            </div>
                            <div class="mt-4">
                                <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                                <span class="search-tags"><i class="fe fe-user me-2"></i>People<a
                                        href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                                <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a
                                        href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                                <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                        href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                                <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a
                                        href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
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
        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>
        <!-- Internal Add Products JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/add-products.js"></script>
        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>
        <!-- Date & Time Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/date&time_pickers.js"></script>
</body>
<script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new Choices('#product-s', {
            removeItemButton: true,
            placeholder: true,
            placeholderValue: '',
            searchEnabled: true,
            allowHTML: true
        });
    });
</script>
<!-- JavaScript for dynamic functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Subcategory loading based on category selection
        const categorySelect = document.getElementById('categorySelect');
        const subCategorySelect = document.getElementById('subCategorySelect');
        const subCategoryRequired = document.getElementById('subCategoryRequired');
        const couponType = document.getElementById('couponType');
        const discountWrapper = document.getElementById('discountWrapper');
        const discountLabel = document.getElementById('discountLabel');
        const discountInput = document.getElementById('discountInput');
        const discountSuffix = document.getElementById('discountSuffix');
        // Subcategories data from PHP
        const subcategories = <?php echo json_encode($subcategories); ?>;
        
        categorySelect.addEventListener('change', function () {
            const categoryId = this.value;
            if (categoryId === '' || categoryId === '0') {
                // All Categories selected - disable subcategory
                subCategorySelect.innerHTML = '<option value="0">All Sub Categories</option>';
                subCategorySelect.disabled = true;
                subCategorySelect.removeAttribute('required');
                subCategoryRequired.style.display = 'none';
            } else {
                // Specific category selected - enable subcategory
                subCategorySelect.disabled = false;
                subCategorySelect.setAttribute('required', 'required');
                subCategoryRequired.style.display = '';
                subCategorySelect.innerHTML = '<option value="0">All Sub Categories</option>';
                if (subcategories[categoryId]) {
                    subcategories[categoryId].forEach(function (sub) {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.textContent = sub.name;
                        subCategorySelect.appendChild(option);
                    });
                }
            }
        });
        // Date validation
        const startDate = document.getElementById('startDate');
        const endDate = document.getElementById('endDate');
        startDate.addEventListener('change', function () {
            if (this.value) {
                endDate.min = this.value;
            }
        });
        endDate.addEventListener('change', function () {
            if (this.value && startDate.value && this.value < startDate.value) {
                alert('End date must be after start date');
                this.value = '';
            }
        });
        // Coupon type change handling
        function updateDiscountField() {
            const type = couponType.value;
            if (type === 'Percentage') {
                discountWrapper.style.display = '';
                discountLabel.textContent = 'Discount Percentage';
                discountSuffix.textContent = '%';
                discountInput.min = '0';
                discountInput.max = '100';
                discountInput.step = '0.01';
                discountInput.required = true;
                discountInput.placeholder = 'e.g. 10';
            } else if (type === 'Fixed Amount') {
                discountWrapper.style.display = '';
                discountLabel.textContent = 'Discount Amount';
                discountSuffix.textContent = '₹';
                discountInput.removeAttribute('max');
                discountInput.min = '0';
                discountInput.step = '0.01';
                discountInput.required = true;
                discountInput.placeholder = 'e.g. 100';
            } else if (type === 'Free Shipping') {
                discountWrapper.style.display = 'none';
                discountInput.required = false;
                discountInput.value = '';
            } else {
                discountWrapper.style.display = 'none';
                discountInput.required = false;
                discountInput.value = '';
            }
        }
        couponType.addEventListener('change', updateDiscountField);
        updateDiscountField();
        // Form validation
        const form = document.getElementById('addCouponForm');
        form.addEventListener('submit', function (e) {
            const startDateValue = startDate.value;
            const endDateValue = endDate.value;
            if (startDateValue && endDateValue && endDateValue < startDateValue) {
                e.preventDefault();
                alert('End date must be after start date');
                return false;
            }
            // Additional validation for discount
            if (couponType.value === 'Percentage') {
                const v = parseFloat(discountInput.value || '0');
                if (isNaN(v) || v < 0 || v > 100) {
                    e.preventDefault();
                    alert('Percentage must be between 0 and 100');
                    return false;
                }
            }
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Adding...';
            submitBtn.disabled = true;
        });
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function () {
            document.querySelectorAll('.alert').forEach(function (el) {
                try {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                    bsAlert.close();
                } catch (err) {
                    if (el && el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }
            });
        }, 5000);
    });
</script>
</body>
</html>
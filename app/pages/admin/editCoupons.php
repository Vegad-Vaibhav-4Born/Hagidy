<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}
// Get coupon ID from URL
$coupon_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($coupon_id <= 0) {
    $_SESSION['error_message'] = "Invalid coupon ID!";
    header('Location: coupons.php');
    exit;
}
// Fetch coupon data
$coupon_query = "SELECT * FROM `coupans` WHERE `id` = $coupon_id";
$coupon_result = mysqli_query($con, $coupon_query);
if (mysqli_num_rows($coupon_result) == 0) {
    $_SESSION['error_message'] = "Coupon not found!";
    header('Location: coupons.php');
    exit;
}
$coupon = mysqli_fetch_assoc($coupon_result);
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
    // Check if coupon code already exists (excluding current coupon)
    $check_query = "SELECT id FROM `coupans` WHERE `coupan_code` = '$coupon_code' AND `id` != $coupon_id";
    $check_result = mysqli_query($con, $check_query);
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['error_message'] = "Coupon code already exists!";
    } else {
        // Update coupon
        $update_query = "UPDATE `coupans` SET 
                        `coupan_code` = '$coupon_code',
                        `discount` = $discount,
                        `coupan_limit` = $coupon_limit,
                        `category` = $category,
                        `sub_category` = $sub_category,
                        `type` = '$type',
                        `minimum_order_value` = $minimum_order_value,
                        `start_date` = '$start_date',
                        `end_date` = '$end_date',
                        `status` = '$status'
                        WHERE `id` = $coupon_id";
        if (mysqli_query($con, $update_query)) {
            $_SESSION['success_message'] = "Coupon '$coupon_code' has been updated successfully!";
            header('Location: coupons.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Error updating coupon: " . mysqli_error($con);
        }
    }
}
// Fetch categories and subcategories for dropdowns
$categories_query = "SELECT `id`, `name` FROM `category` ORDER BY `name` ASC";
$categories_result = mysqli_query($con, $categories_query);
$subcategories_query = "SELECT `id`, `name`, `category_id` FROM `sub_category` ORDER BY `name` ASC";
$subcategories_result = mysqli_query($con, $subcategories_query);
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
    <title>Edit-COUPONS | HADIDY</title>
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
        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid ">
                <div class="d-md-flex d-block align-items-center   justify-content-between mt-4 page-header-breadcrumb">
                </div>
                <!-- Start:: row-2 -->
                <div class="row  justify-content-center">
                    <div class="col-12 col-xl-9 col-lg-9 col-md-12 col-sm-12">
                        <div
                            class="d-md-flex d-block align-items-center justify-content-between my-2  page-header-breadcrumb">
                            <div class="d-flex align-items-center gap-4">
                                <h1 class="page-title fw-semibold fs-18 mb-0">Edit Coupons</h1>
                            </div>
                            <div
                                class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                                <div class="ms-md-1 ms-0">
                                    <nav>
                                        <ol class="breadcrumb mb-0">
                                            <li class="breadcrumb-item">
                                                <a href="coupons.php">Coupons</a>
                                            </li>
                                            <li class="breadcrumb-item active" aria-current="page">Edit Coupons</li>
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
                                <form method="POST" id="editCouponForm">
                                    <div class="row">
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Code<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="coupon_code"
                                                placeholder="Enter Coupon Code"
                                                value="<?php echo htmlspecialchars($coupon['coupan_code']); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Limits<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="number" name="coupon_limit"
                                                placeholder="Enter Coupons Limits" min="1"
                                                value="<?php echo $coupon['coupan_limit']; ?>" required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Category<span class="text-danger">*</span></label>
                                            <select class="form-select" name="category" id="categorySelect" required>
                                                <option value="">Select Category</option>
                                                <option value="0" <?php echo ($coupon['category'] == 0 || $coupon['category'] == '' || $coupon['category'] == null) ? 'selected' : ''; ?>>All Categories</option>
                                                <?php
                                                mysqli_data_seek($categories_result, 0); // Reset result pointer
                                                while ($category = mysqli_fetch_assoc($categories_result)):
                                                    ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $coupon['category'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Sub Category<span class="text-danger" id="subCategoryRequired">*</span></label>
                                            <select class="form-select" name="sub_category" id="subCategorySelect">
                                                <option value="0" <?php echo ($coupon['sub_category'] == 0 || $coupon['sub_category'] == '' || $coupon['sub_category'] == null) ? 'selected' : ''; ?>>All Sub Categories</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Types<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="type" id="couponType" required>
                                                <option value="">Select Type</option>
                                                <option value="Free Shipping" <?php echo $coupon['type'] == 'Free Shipping' ? 'selected' : ''; ?>>Free Shipping</option>
                                                <option value="Percentage" <?php echo $coupon['type'] == 'Percentage' ? 'selected' : ''; ?>>Percentage</option>
                                                <option value="Fixed Amount" <?php echo $coupon['type'] == 'Fixed Amount' ? 'selected' : ''; ?>>Fixed Amount</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3"
                                            id="discountWrapper" style="display:none;">
                                            <label class="form-label" id="discountLabel">Discount</label>
                                            <div class="input-group">
                                                <input class="form-control" type="number" name="discount"
                                                    id="discountInput" placeholder="Enter discount" min="0" step="0.01"
                                                    value="<?php echo htmlspecialchars($coupon['discount']); ?>">
                                                <span class="input-group-text" id="discountSuffix"></span>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Minimum order value<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="number" name="minimum_order_value"
                                                placeholder="Enter Min. Order Value" min="0" step="0.01"
                                                value="<?php echo $coupon['minimum_order_value']; ?>" required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Start Date<span
                                                    class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-text text-muted"> <i
                                                        class="ri-calendar-line"></i></div>
                                                <input type="date" class="form-control" name="start_date" id="startDate"
                                                    value="<?php echo $coupon['start_date']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">End Date<span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <div class="input-group-text text-muted"> <i
                                                        class="ri-calendar-line"></i></div>
                                                <input type="date" class="form-control" name="end_date" id="endDate"
                                                    value="<?php echo $coupon['end_date']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Coupons Status<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="status" required>
                                                <option value="">Select Status</option>
                                                <option value="Active" <?php echo $coupon['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="Inactive" <?php echo $coupon['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                        <div class="col-12 d-flex justify-content-end gap-3 mt-4">
                                            <a href="coupons.php"
                                                class="btn btn-outline-secondary btn-wave waves-effect waves-light">Cancel</a>
                                            <button type="submit"
                                                class="btn btn-secondary btn-wave waves-effect waves-light"
                                                style="background-color: #3B4B6B !important; border: none;">Update
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
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
</body>
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
        // Current coupon data - handle NULL, empty string, or 0 as "All Categories/Sub Categories"
        const currentCategoryId = <?php echo ($coupon['category'] == '' || $coupon['category'] == null || $coupon['category'] == 0) ? 0 : (int)$coupon['category']; ?>;
        const currentSubCategoryId = <?php echo ($coupon['sub_category'] == '' || $coupon['sub_category'] == null || $coupon['sub_category'] == 0) ? 0 : (int)$coupon['sub_category']; ?>;
        // Load subcategories for current category
        function loadSubcategories(categoryId, selectedSubId = null) {
            if (categoryId === '' || categoryId === '0' || categoryId === 0) {
                // All Categories selected - disable subcategory
                subCategorySelect.innerHTML = '<option value="0"' + (selectedSubId == 0 || selectedSubId == null ? ' selected' : '') + '>All Sub Categories</option>';
                subCategorySelect.disabled = true;
                subCategorySelect.removeAttribute('required');
                subCategoryRequired.style.display = 'none';
            } else {
                // Specific category selected - enable subcategory
                subCategorySelect.disabled = false;
                subCategorySelect.setAttribute('required', 'required');
                subCategoryRequired.style.display = '';
                subCategorySelect.innerHTML = '<option value="0"' + (selectedSubId == 0 ? ' selected' : '') + '>All Sub Categories</option>';
                if (subcategories[categoryId]) {
                    subcategories[categoryId].forEach(function (sub) {
                        const option = document.createElement('option');
                        option.value = sub.id;
                        option.textContent = sub.name;
                        if (sub.id == selectedSubId) {
                            option.selected = true;
                        }
                        subCategorySelect.appendChild(option);
                    });
                }
            }
        }
        // Load subcategories on page load
        loadSubcategories(currentCategoryId, currentSubCategoryId);
        categorySelect.addEventListener('change', function () {
            const categoryId = this.value;
            loadSubcategories(categoryId);
        });
        // Setup discount field visibility and config based on type
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
            } else {
                discountWrapper.style.display = 'none';
                discountInput.required = false;
            }
        }
        couponType.addEventListener('change', updateDiscountField);
        updateDiscountField();
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
        // Form validation
        const form = document.getElementById('editCouponForm');
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
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Updating...';
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
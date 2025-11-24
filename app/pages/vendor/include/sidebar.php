<?php
$vendor_reg_id = isset($_SESSION['vendor_reg_id']) ? $_SESSION['vendor_reg_id'] : '';
$vendor_mobile = isset($_SESSION['vendor_mobile']) ? $_SESSION['vendor_mobile'] : '';
$vendor_business_name = isset($_SESSION['vendor_business_name']) ? $_SESSION['vendor_business_name'] : '';

$vendor_details = mysqli_query($con, "SELECT * FROM vendor_registration WHERE id = '$vendor_reg_id'");
if ($vendor_details && mysqli_num_rows($vendor_details) > 0) {
    $vendor_details = mysqli_fetch_assoc($vendor_details);
    $vendor_status = $vendor_details['status'];
} else {
    $vendor_status = 'unknown';
}

$get_added_category = mysqli_query($con, "SELECT distinct(category_id) FROM products WHERE vendor_id = '$vendor_reg_id'");
// print_r($get_added_category);
?>
<aside class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div class="main-sidebar-header">
        <a href="index.php" class="header-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/toggle-logo.png" alt="logo" class="toggle-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-dark">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="toggle-dark">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-white">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="toggle-white">
        </a>
    </div>
    <!-- End::main-sidebar-header -->

    <!-- Start::main-sidebar -->
    <div class="main-sidebar" id="sidebar-scroll">

        <!-- Start::nav -->
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>
            <ul class="main-menu">
                <!-- Start::slide__category -->
                <li class="slide__category"><span class="category-name">Main</span></li>
                <!-- End::slide__category -->

                <!-- Start::slide -->
                <li class="slide">
                    <a href="index.php" class="side-menu__item">
                        <!-- <i class="bx bx-home side-menu__icon"></i> -->
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Dashboardicon.png" alt="">
                        </span>
                        <span class="side-menu__label">Dashboards
                    </a>
                </li>
                <li class="slide ">
                    <a href="order-management.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/OrderManagementIcon.png" alt="">
                        </span>
                        <span class="side-menu__label">Order Management
                    </a>
                </li>
                <!-- Start::slide -->
                <li class="slide has-sub">
                    <a href="#" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/ProductManagement Icon.png" alt="">
                        </span>
                        <span class="side-menu__label">Product Management </span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="./product-management.php" class="side-menu__item">All Products</a>
                        </li>
                        <?php if (!empty($get_added_category)): ?>
                            <?php foreach ($get_added_category as $category):
                                $category_name = mysqli_fetch_assoc(mysqli_query($con, "SELECT name FROM category WHERE id = '$category[category_id]'"));
                                //print_r($category_name);
                                ?>
                                <li class="slide">
                                    <a href="./product-management.php?category=<?php echo urlencode($category['category_id']); ?>"
                                        class="side-menu__item">
                                        <?php echo htmlspecialchars($category_name['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="slide ">
                    <a href="inventoryManagement.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/InventoryManagementicon .png" alt="">
                        </span>
                        <span class="side-menu__label">Inventory Management
                    </a>
                </li>
           
                <li class="slide ">
                    <a href="settlement-report.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Withdrawalicon.png" alt="">
                        </span>
                        <span class="side-menu__label">Settlement Report
                    </a>
                </li>
                <!-- End::slide -->
              
            </ul>
            <div class="slide-right" id="slide-right"><svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24"
                    height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                </svg></div>
        </nav>
        <!-- End::nav -->

    </div>
    <!-- End::main-sidebar -->

</aside>
<!-- Access Block Modal -->
<div class="modal fade" id="accessBlockModal" tabindex="-1" aria-labelledby="accessBlockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="" id="accessBlockModalLabel">Access Restricted</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Your account status is <b><?php echo htmlspecialchars(strtolower($vendor_status)); ?></b>. You can't
                access this section yet.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                <a href="profileSetting.php" class="btn btn-primary">Check Profile Settings</a>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        try {
            var status = ('<?php echo strtolower($vendor_status); ?>' || '').toLowerCase();
            if (status === 'pending' || status === 'hold') {
                var sidebar = document.getElementById('sidebar');
                if (!sidebar) return;
                var links = sidebar.querySelectorAll('a.side-menu__item');
                links.forEach(function (a) {
                    var href = (a.getAttribute('href') || '').toLowerCase();
                    var isProfile = href.indexOf('profilesetting.php') !== -1;
                    var isLogout = href.indexOf('logout') !== -1;
                    if (!isProfile && !isLogout) {
                        a.addEventListener('click', function (ev) {
                            ev.preventDefault();
                            var modalEl = document.getElementById('accessBlockModal');
                            try {
                                if (window.bootstrap && modalEl) {
                                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: true });
                                    modal.show();
                                } else {
                                    alert("Your account status is '" + status + "'. Please open Profile Settings to proceed.");
                                    window.location.href = 'profileSetting.php';
                                }
                            } catch (e) {
                                alert("Your account status is '" + status + "'. Please open Profile Settings to proceed.");
                                window.location.href = 'profileSetting.php';
                            }
                        });
                    }
                });
            }
        } catch (e) { }
    })();
</script>

<script>
    document.addEventListener('keydown', function (e) {
        // Disable F12
        if (e.key === "F12") {
            e.preventDefault();
            return false;
        }

        // Disable Ctrl+Shift+I
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'i') {
            e.preventDefault();
            return false;
        }

        // Disable Ctrl+Shift+C
        if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'c') {
            e.preventDefault();
            return false;
        }

        // Disable Ctrl+U
        if (e.ctrlKey && e.key.toLowerCase() === 'u') {
            e.preventDefault();
            return false;
        }
    });

    // Optional: Disable right-click
    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });
</script>
<script src="./include/security.js"></script>
<?php

$unseen_vendors = mysqli_query($con, "SELECT * FROM vendor_registration WHERE (status = 'pending' OR status = 'under_review')");
$unseen_vendors = mysqli_num_rows($unseen_vendors);
$unseen_products = mysqli_query($con, "SELECT * FROM products where  (status = 'pending' OR status = 'under_review')");
$unseen_pending_products = mysqli_num_rows($unseen_products);
?>


<aside class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div class="main-sidebar-header">
        <a href="index.php" class="header-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/toggle-logo.png" alt="logo" class="toggle-logo">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-dark">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="toggle-dark">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-white">
            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="toggle-white">
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
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/Dashboardicon.png" alt="">
                        </span>
                        <span class="side-menu__label">Dashboard
                    </a>
                </li>
                <li class="slide has-sub">
                    <a href="#" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/vendor-management.png" alt="">
                        </span>
                        <span class="side-menu__label">Vendor Management </span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="./allVendors.php" class="side-menu__item">All Vendors</a>
                        </li>
                        <li class="slide d-flex align-items-center">
                            <a href="./newRequest.php" class="side-menu__item">New Request</a><span
                                class="badge ms-2 bg-danger"><?php echo $unseen_vendors; ?></span>
                        </li>

                    </ul>
                </li>
                <li class="slide has-sub">
                    <a href="#" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/ProductManagement Icon.png" alt="">
                        </span>
                        <span class="side-menu__label">Product Management </span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="./product-management.php" class="side-menu__item">All Products</a>
                        </li>
                        <li class="slide d-flex align-items-center">
                            <a href="./productRequest.php" class="side-menu__item">Product Request</a><span
                                class="badge ms-2 bg-danger"><?php echo $unseen_pending_products; ?></span>
                        </li>

                    </ul>
                </li>
                <li class="slide ">
                    <a href="order-management.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/OrderManagementIcon.png" alt="">
                        </span>
                        <span class="side-menu__label">Order Management
                    </a>
                </li>
                <li class="slide ">
                    <a href="./attributes.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/attribute.png" alt="">
                        </span>
                        <span class="side-menu__label">Attribute Management
                    </a>
                </li>
                <li class="slide has-sub">
                    <a href="#" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/vendor-management.png" alt="">
                        </span>
                        <span class="side-menu__label">Category Management </span>
                        <i class="fe fe-chevron-right side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide">
                            <a href="./category.php" class="side-menu__item">Category</a>
                        </li>
                        <li class="slide d-flex align-items-center">
                            <a href="./subCategory.php" class="side-menu__item">Sub Category</a>
                        </li>

                    </ul>
                </li>
                <!-- Start::slide -->
                <li class="slide ">
                    <a href="./coupons.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/coupons.png" alt="">
                        </span>
                        <span class="side-menu__label">Coupons
                    </a>
                </li>
                <li class="slide ">
                    <a href="./customers.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/profile.png" alt="">
                        </span>
                        <span class="side-menu__label">Customers
                    </a>
                </li>
                <li class="slide ">
                    <a href="./bannerManagement.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/profile.png" alt="">
                        </span>
                        <span class="side-menu__label">Banner Management
                    </a>
                </li>
                <li class="slide ">
                    <a href="./BrandManagement.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/profile.png" alt="">
                        </span>
                        <span class="side-menu__label">Brand Management
                    </a>
                </li>
                <li class="slide ">
                    <a href="contactUs.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/dispute.png" alt="">
                        </span>
                        <span class="side-menu__label">Contact Us
                    </a>
                </li>
                <li class="slide ">
                    <a href="transaction.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/currency-rupee.png" alt="">
                        </span>
                        <span class="side-menu__label">Vendor Transactions
                    </a>
                </li>
                <li class="slide ">
                    <a href="withdrawManagement.php" class="side-menu__item">
                        <span class="icon-sidebar">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/Withdrawalicon.png" alt="">
                        </span>
                        <span class="side-menu__label">Settlement report

                    </a>
                </li>
                <!-- End::slide -->
                <!-- End::slide -->

                <!-- Start::slide -->

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
<script src="<?php echo PUBLIC_ASSETS; ?>js/security.js"></script>
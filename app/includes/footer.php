<!-- Start of Footer -->
<footer class="footer appear-animate" data-animation-options="{
            'name': 'fadeIn'  }">
    <div class="container">
        <div class="footer-top">
            <div class="row">
                <div class="col-lg-4 col-sm-6">
                    <div class="widget widget-about mt-0 mb-4">
                        <a href="<?php echo USER_BASEURL; ?>/index.php" class="logo-footer">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/demos/demo12/logo.png" alt="logo-footer" width="145" height="45" />
                        </a>
                        <div class="widget-body">
                            <p class="widget-about-title">Got Question? Call us Mon to Sat (10AM - 6PM)</p>
                            <a href="tel: +918939555771" class="widget-about-call">+91 8939555771</a>
                            <a href="mailto: connect@hagidy.com" class="widget-about-call"> connect@hagidy.com</a>
                            <!-- <p class="widget-about-desc">Register now to get updates on pronot get up icons
                                        & coupons ster now toon.
                             </p> -->

                            <div class="social-icons social-icons-colored">
                                <a href="https://www.facebook.com/HagidyTech"
                                    class="social-icon social-facebook w-icon-facebook"></a>
                                <a href="https://x.com/hagidysoci88423"
                                    class="social-icon social-twitter w-icon-twitter"></a>
                                <a href="https://www.instagram.com/hagidytech?igsh=dGFzNzlqaDE4ODZh"
                                    class="social-icon social-instagram w-icon-instagram"></a>
                                <a href="https://www.youtube.com/channel/UCtZ-HfbEt5pvx7IrAuET43A"
                                    class="social-icon social-youtube w-icon-youtube"></a>
                                <!-- <a href="https://www.linkedin.com/in/hagidy-tech-090295387/" class="social-icon social-pinterest w-icon-pinterest"></a> -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="widget">
                        <h3 class="widget-title">Company</h3>
                        <ul class="widget-body">
                            <li><a href="<?php echo USER_BASEURL; ?>/about-us.php">About Us</a></li>
                            <!-- <li><a href="#">Team Member</a></li>
                             <li><a href="#">Career</a></li> -->
                            <li><a href="<?php echo USER_BASEURL; ?>/contact-us.php">Contact Us</a></li>
                            <!-- <li><a href="#">Affilate</a></li>
                             <li><a href="#">Order History</a></li> -->
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="widget">
                        <h4 class="widget-title">My Account</h4>
                        <ul class="widget-body">
                            <!-- <li><a href="#">Track My Order</a></li> -->
                            <li><a href="<?php echo USER_BASEURL; ?>/cart.php">View Cart</a></li>
                            <li><a href="<?php echo USER_BASEURL; ?>/login.php">Sign In</a></li>
                            <!-- <li><a href="#">Help</a></li> -->
                            <li><a href="<?php echo USER_BASEURL; ?>/wishlist.php">My Wishlist</a></li>
                            <!-- <li><a href="./return-policy.php">Return Policy</a></li> -->
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-sm-6">
                    <div class="widget">
                        <h4 class="widget-title">Term and Conditions</h4>
                        <ul class="widget-body">
                            <!-- <li><a href="#">Support Center</a></li> -->
                            <li><a href="<?php echo USER_BASEURL; ?>/disclaimer-policy.php">Disclaimer Policy</a></li>
                            <li><a href="<?php echo USER_BASEURL; ?>/shipping-policy.php">Shipping</a></li>
                            <!-- <li><a href="return-refund-policy.php">return and Refund Policy</a></li> -->
                            <li><a href="<?php echo USER_BASEURL; ?>/privacy_policy.php">Privacy Policy</a></li>
                            <li><a href="<?php echo USER_BASEURL; ?>/terms_conditions.php">Term and Conditions</a></li>
                            <li><a href="<?php echo USER_BASEURL; ?>/return-refund-policy.php">Return Policy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-middle">
            <div class="widget widget-category">
                <?php
                // Fetch categories that have sub-categories
                $footer_categories_query = "
                            SELECT DISTINCT c.id, c.name, c.category_id 
                            FROM category c 
                            INNER JOIN sub_category sc ON c.id = sc.category_id 
                            ORDER BY c.name ASC 
                            LIMIT 7
                        ";
                $footer_categories_result = mysqli_query($db_connection, $footer_categories_query);

                if ($footer_categories_result && mysqli_num_rows($footer_categories_result) > 0) {
                    while ($category = mysqli_fetch_assoc($footer_categories_result)) {
                        // Fetch sub-categories for this category
                        $sub_categories_query = "
                                    SELECT id, name, sub_category_id 
                                    FROM sub_category 
                                    WHERE category_id = " . intval($category['id']) . " 
                                    ORDER BY name ASC 
                                    LIMIT 10
                                ";
                        $sub_categories_result = mysqli_query($db_connection, $sub_categories_query);

                        if ($sub_categories_result && mysqli_num_rows($sub_categories_result) > 0) {
                            echo '<div class="category-box">';
                            echo '<h6 class="category-name">' . htmlspecialchars($category['name']) . ':</h6>';

                            while ($sub_category = mysqli_fetch_assoc($sub_categories_result)) {
                                echo '<a href="' . USER_BASEURL . '/shop.php?category=' . intval($category['id']) . '&subcategory=' . intval($sub_category['id']) . '">' . htmlspecialchars($sub_category['name']) . '</a>';
                            }

                            // Add "View All" link for the category
                            echo '<a href="' . USER_BASEURL . '/shop.php?category=' . intval($category['id']) . '">View All</a>';

                            echo '</div>';
                            echo '<br>';
                        }
                    }
                }
                ?>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-left">
                <p class="copyright">Copyright © 2025 Hagidy Store. All Rights Reserved.</p>
            </div>
            <div class="footer-right">
                <span class="payment-label mr-lg-8">We're using safe payment for</span>
                <figure class="payment">
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/payment.png" alt="payment" width="159" height="25" />
                </figure>
            </div>
        </div>
    </div>
</footer>
<!-- End of Footer -->
<!-- Start of Sticky Footer -->
<div class="sticky-footer sticky-content fix-bottom">
    <a href="index.php" class="sticky-link active">
        <i class="w-icon-home"></i>
        <p>Home</p>
    </a>
    <a href="./shop.php" class="sticky-link">
        <i class="w-icon-category"></i>
        <p>Shop</p>
    </a>
    <a href="my-account.php" class="sticky-link">
        <i class="w-icon-account"></i>
        <p>Account</p>
    </a>
    <div class="cart-dropdown dir-up">
        <a href="cart.php" class="sticky-link">
            <i class="w-icon-cart"></i>
            <p>Cart</p>
        </a>
        <div class="dropdown-box">
            <div class="d-flex align-items-end justify-content-end ">
                <a href="#" class="btn-close"><i class="fa-solid fa-xmark"></i></a>
            </div>
            <div class="products">
                <div class="product product-cart">
                    <div class="product-detail">
                        <h3 class="product-name">
                            <a href="product-detail.php">Beige knitted elas<br>tic
                                runner shoes</a>
                        </h3>
                        <div class="price-box">
                            <span class="product-quantity">1</span>
                            <span class="product-price">₹25.68</span>
                        </div>
                    </div>
                    <figure class="product-media">
                        <a href="product-detail.php">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/cart/product-1.jpg" alt="product" height="84" width="94" />
                        </a>
                    </figure>
                    <button class="btn btn-link btn-close">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                    </button>
                </div>

                <div class="product product-cart">
                    <div class="product-detail">
                        <h3 class="product-name">
                            <a href="product-detail.php">Blue utility pina<br>fore
                                denim dress</a>
                        </h3>
                        <div class="price-box">
                            <span class="product-quantity">1</span>
                            <span class="product-price">₹32.99</span>
                        </div>
                    </div>
                    <figure class="product-media">
                        <a href="product-detail.php">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/cart/product-2.jpg" alt="product" width="84" height="94" />
                        </a>
                    </figure>
                    <button class="btn btn-link btn-close">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                    </button>
                </div>
            </div>

            <div class="cart-total">
                <label>Subtotal:</label>
                <span class="price">₹58.67</span>
            </div>

            <div class="cart-action">
                <a href="cart.php" class="btn btn-dark btn-outline btn-rounded">View Cart</a>
                <a href="cart.php" class="btn btn-primary  btn-rounded">Checkout</a>
            </div>
        </div>
        <!-- End of Dropdown Box -->
    </div>

</div>
<!-- End of Sticky Footer -->

<!-- Start of Scroll Top -->
<a id="scroll-top" class="scroll-top" href="#top" title="Top" role="button"> <i class="w-icon-angle-up"></i> <svg
        version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 70 70">
        <circle id="progress-indicator" fill="transparent" stroke="#000000" stroke-miterlimit="10" cx="35" cy="35"
            r="34" style="stroke-dasharray: 16.4198, 400;"></circle>
    </svg>
</a>
<!-- End of Scroll Top -->

<!-- Start of Mobile Menu -->
<div class="mobile-menu-wrapper">
    <div class="mobile-menu-overlay"></div>
    <!-- End of .mobile-menu-overlay -->

    <a href="#" class="mobile-menu-close"><i class="close-icon"></i></a>
    <!-- End of .mobile-menu-close -->

    <div class="mobile-menu-container scrollable">

        <!-- End of Search Form -->
        <ul class="mobile-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="./wishlist.php">My Wishlist</a></li>
            <li><a href="./my-account.php#refer-friend">Referral Link</a></li>
            <li><a href="/hagidy/contact-us.php">Contact us</a></li>
            <li><a href="./login.php">Sign In / Register</a></li>
        </ul>
    </div>
</div>
<!-- End of Mobile Menu -->
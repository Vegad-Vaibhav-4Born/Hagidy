<?php
require_once __DIR__ . '/includes/init.php';
if (!function_exists('e')) {
    function e($string) { return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8'); }
}

// Initialize variables
$wishlist_products = [];
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;
$base_image_url = $vendor_baseurl;
$default_image = $vendor_baseurl . 'uploads/vendors/no-product.png';

// Debug information
$debug_info = [
    'session_status' => session_status(),
    'session_data' => $_SESSION ?? [],
    'is_logged_in' => $is_logged_in,
    'user_id' => $user_id
];

if ($is_logged_in) {
    // Get wishlist products from database for logged-in user (using correct column names)
    $wishlist_query = "
        SELECT w.id as wishlist_id, w.created_date, 
               p.id, p.product_name, p.selling_price, p.mrp, p.images, p.Inventory as stock_quantity, p.coin, p.discount as discount_percentage
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.user_id = ? AND p.status = 'approved' 
        ORDER BY w.created_date DESC
    ";

    $stmt = mysqli_prepare($db_connection, $wishlist_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Process images
                $product_images = [];
                if (!empty($row['images'])) {
                    $images_data = json_decode($row['images'], true);
                    if (is_array($images_data)) {
                        foreach ($images_data as $image_name) {
                            if (!empty($image_name)) {
                                $product_images[] = $image_name;
                            }
                        }
                    }
                }

                // Use default image if no images
                if (empty($product_images)) {
                    $product_images[] = $default_image;
                }

                $row['images_array'] = $product_images;
                $wishlist_products[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Add debug information
    $debug_info['wishlist_query_executed'] = true;
    $debug_info['wishlist_products_count'] = count($wishlist_products);
    $debug_info['db_connection_status'] = mysqli_ping($db_connection) ? 'Connected' : 'Disconnected';
}
// For guest users, we'll handle this with JavaScript
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>WISHLIST | HAGIDY</title>

    <meta name="keywords" content="" />
    <meta name="description" content="">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">

    <!-- WebFont.js -->
    <script>
        WebFontConfig = {
            google: { families: ['Poppins:400,500,600,700'] }
        };
        (function (d) {
            var wf = d.createElement('script'), s = d.scripts[0];
            wf.src = '<?php echo PUBLIC_ASSETS; ?>js/webfont.js';
            wf.async = true;
            s.parentNode.insertBefore(wf, s);
        })(document);
    </script>

    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-regular-400.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-solid-900.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-brands-400.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>fonts/wolmart.woff?png09e" as="font" type="font/woff" crossorigin="anonymous">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">


    <!-- Plugin CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/default-skin/default-skin.min.css">

    <!-- Default CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/wishlist-main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>


        <!-- Start of Main -->
        <main class="main wishlist-page">
            <!-- Start of Page Header -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li>Wishlist</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Page Header -->

            <!-- Start of PageContent -->
            <div class="page-content">
                <div class="container mb-5">
                    <h3 class="wishlist-title">My wishlist</h3>

                    <!-- Debug Information (remove in production) -->
                    <?php if (isset($_GET['debug'])): ?>
                        <div class="alert alert-info">
                            <h5>Debug Information:</h5>
                            <p><strong>Is Logged In:</strong> <?php echo $is_logged_in ? 'Yes' : 'No'; ?></p>
                            <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                            <p><strong>Session Data:</strong> <?php echo json_encode($_SESSION ?? []); ?></p>
                            <p><strong>Wishlist Products Count:</strong> <?php echo count($wishlist_products); ?></p>
                            <p><strong>Database Status:</strong>
                                <?php echo isset($debug_info['db_connection_status']) ? $debug_info['db_connection_status'] : 'Unknown'; ?>
                            </p>
                            <p><strong>Query Executed:</strong>
                                <?php echo isset($debug_info['wishlist_query_executed']) ? 'Yes' : 'No'; ?></p>
                            <?php if (!empty($wishlist_products)): ?>
                                <p><strong>First Product:</strong> <?php echo json_encode($wishlist_products[0]); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="shop-table wishlist-table">
                            <thead>
                                <tr>
                                    <th class="product-name"><span><b>Product</b></span></th>
                                    <th></th>
                                    <th class="product-price text-start"><span><b>Price</b></span></th>
                                    <th class="product-stock-status"><span><b>Stock Status</b></span></th>
                                    <th class="wishlist-action"><span><b>Actions</b></span></th>
                                </tr>
                            </thead>
                            <tbody id="wishlist-table-body">
                                <?php if ($is_logged_in && !empty($wishlist_products)): ?>
                                    <?php foreach ($wishlist_products as $product): ?>
                                        <tr data-product-id="<?php echo $product['id']; ?>">
                                            <td class="product-thumbnail">
                                                <div class="p-relative">
                                                    <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                                        <figure>
                                                            <img src="<?php echo e($product['images_array'][0]); ?>"
                                                                alt="<?php echo e($product['product_name']); ?>" width="300"
                                                                height="338"
                                                                onerror="this.src='<?php echo $default_image; ?>';">
                                                        </figure>
                                                    </a>
                                                    <button type="button" class="btn btn-link btn-close btn-remove-wishlist"
                                                        data-product-id="<?php echo $product['id']; ?>">
                                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                                                    </button>
                                                </div>
                                            </td>
                                            <td class="product-name">
                                                <a href="product-detail.php?id=<?php echo $product['id']; ?>">
                                                    <?php echo e($product['product_name']); ?>
                                                </a>
                                            </td>
                                            <td class="product-price text-start">
                                                <ins
                                                    class="new-price">₹<?php echo number_format($product['selling_price'], 2); ?></ins>
                                                <?php if ($product['mrp'] > $product['selling_price']): ?>
                                                    <del
                                                        class="old-price ml-2">₹<?php echo number_format($product['mrp'], 2); ?></del>
                                                <?php endif; ?>
                                                <?php if (isset($product['coin']) && $product['coin'] > 0): ?>
                                                    <span class="d-block product-price1 justify-content-start">
                                                        You will earn <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png"
                                                            class="img-fluid ml-1" alt=""> <?php echo intval($product['coin']); ?>
                                                        coins
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-stock-status text-center">
                                                <?php if (isset($product['stock_quantity']) && $product['stock_quantity'] > 0): ?>
                                                    <span class="wishlist-in-stock">In Stock</span>
                                                <?php else: ?>
                                                    <span class="wishlist-out-of-stock">Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="wishlist-action">
                                                <div class="d-lg-flex">
                                                    <a href="#" class="btn btn-primary btn-rounded btn-sm btn-cart"
                                                        data-product-id="<?php echo $product['id']; ?>">Add to cart</a>
                                                    <a href="#" class="btn btn-outline btn-rounded btn-sm btn-quickview ml-2"
                                                        data-product-id="<?php echo $product['id']; ?>">Quick View</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif ($is_logged_in): ?>
                                    <tr id="empty-wishlist-row">
                                        <td colspan="5" class="text-center py-5">
                                            <div class="empty-wishlist">
                                                <i class="w-icon-heart"
                                                    style="font-size: 48px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                                                <h4>Your wishlist is empty</h4>
                                                <p>Add some products to your wishlist to see them here.</p>
                                                <a href="shop.php" class="btn btn-primary btn-rounded">Continue Shopping</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <!-- Guest user wishlist will be populated by JavaScript -->
                                    <tr id="loading-row">
                                        <td colspan="5" class="text-center py-5">
                                            <div class="loading-wishlist">
                                                <i class="fas fa-spinner fa-spin"
                                                    style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                <p>Loading your wishlist...</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- End of PageContent -->
        </main>
        <!-- End of Main -->

        <!-- Modal for Remove Wishlist Item Confirmation -->
        <div id="removeWishlistItemModal" class="modal cart-clar-modal" style="display:none;">
            <div class="cart-clar-modal1"
                style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:400px; text-align:center; position:relative;">
                <span id="closeRemoveWishlistItemModal"
                    style="position:absolute; right:15px; top:5px; font-size:18px; cursor:pointer; z-index: 1;">×</span>
                <div>
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" style="width: 40px; height: 40px;" alt="">
                </div>
                <p style="font-size:18px; margin-bottom:20px;">Are you sure you want to remove this item from your
                    wishlist?</p>
                <button class="btn btn-wl-remove-no"
                    style="background-color:transparent; color:#E6533C;  border:1px solid #E6533C; border-radius:5px; margin-right:10px; cursor:pointer;">No</button>
                <button class="btn btn-wl-remove-yes"
                    style="background-color:#3B4B6B; color:#fff;  border:1px solid #3B4B6B; border-radius:5px; cursor:pointer;">Yes</button>
            </div>
        </div>

        <!-- Start of Quick View -->
        <div class="product product-single product-popup" id="quickview-modal">
            <!-- Dynamic content will be loaded here -->
        </div>
        <!-- End of Quick view -->

        <!-- Start of Footer -->
        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/sticky/sticky.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery.plugin/jquery.plugin.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/zoom/jquery.zoom.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe-ui-default.js"></script>

    <!-- Swiper JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.js"></script>

    <!-- Main JS File -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>

    <!-- Search Suggestions JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search');
            const searchSuggestions = document.getElementById('searchSuggestions');

            // Show suggestions when input is focused
            searchInput.addEventListener('focus', function () {
                searchSuggestions.classList.add('show');
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function (event) {
                if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                    searchSuggestions.classList.remove('show');
                }
            });

            // Handle suggestion item clicks
            const suggestionItems = document.querySelectorAll('.suggestion-item');
            suggestionItems.forEach(function (item) {
                item.addEventListener('click', function () {
                    const title = this.querySelector('.suggestion-title').textContent;
                    searchInput.value = title;
                    searchSuggestions.classList.remove('show');
                });
            });

            // Hide suggestions on form submit
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function () {
                    searchSuggestions.classList.remove('show');
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const stickyElement = document.querySelector('.containt-sticy2');

            window.addEventListener('scroll', function () {
                if (window.scrollY >= 60) {
                    stickyElement.classList.add('containt-sticy');
                } else {
                    stickyElement.classList.remove('containt-sticy');
                }
            });
        });
    </script>

    <!-- Dynamic Wishlist JavaScript -->
    <script>
        class WishlistPageManager {
            constructor() {
                this.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
                this.baseImageUrl = '<?php echo $base_image_url; ?>';
                this.defaultImage = '<?php echo $default_image; ?>';
                this.userId = <?php echo $user_id; ?>;
                this.init();
            }

            init() {
                document.addEventListener('DOMContentLoaded', () => {
                    console.log('Wishlist Page Manager Init:', {
                        isLoggedIn: this.isLoggedIn,
                        userId: this.userId,
                        baseImageUrl: this.baseImageUrl
                    });

                    if (!this.isLoggedIn) {
                        console.log('Loading guest wishlist...');
                        this.loadGuestWishlist();
                    } else {
                        console.log('User is logged in, PHP should handle the display');
                        // Check if we have products displayed
                        const tbody = document.getElementById('wishlist-table-body');
                        const rows = tbody.querySelectorAll('tr[data-product-id]');
                        console.log('Found', rows.length, 'wishlist products for logged user');
                    }
                    this.initEventListeners();

                    // Initialize cart count on page load
                    this.updateHeaderCartCount();

                    // Initialize wishlist button states
                    this.initializeWishlistButtonStates();

                    // Force update all buttons after a short delay to ensure proper display
                    setTimeout(() => {
                        this.forceUpdateAllWishlistButtons();
                    }, 500);
                });
            }

            initEventListeners() {
                // Remove from wishlist buttons - open confirmation modal
                document.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-remove-wishlist')) {
                        e.preventDefault();
                        const button = e.target.closest('.btn-remove-wishlist');
                        const productId = button.getAttribute('data-product-id');
                        this.openRemoveWishlistModal(productId, button);
                    }
                });


                // Add to cart buttons - use proper event delegation with duplicate prevention
                document.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-cart')) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        const button = e.target.closest('.btn-cart');
                        const productId = button.getAttribute('data-product-id');

                        // Prevent duplicate processing
                        if (button.hasAttribute('data-cart-processing')) {
                            return;
                        }

                        // Only handle cart buttons outside of quickview modal
                        if (!button.closest('.mfp-content')) {
                            this.addToCart(productId);
                        }
                    }
                });

                // Delegated handler for wishlist (favorite) inside quickview modal as a reliable fallback
                document.addEventListener('click', (e) => {
                    const likeBtn = e.target.closest('.mfp-content .btn-product-icon.btn-wishlist, .mfp-content .btn-wishlist');
                    if (!likeBtn) return;
                    e.preventDefault();
                    // Avoid duplicate triggers from any global handlers
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    // Resolve product id: prefer data attribute, fallback to modal container
                    let productId = likeBtn.getAttribute('data-product-id');
                    if (!productId) {
                        const popup = likeBtn.closest('.mfp-content')?.querySelector('.product-popup');
                        if (popup && popup.getAttribute('data-product-id')) {
                            productId = popup.getAttribute('data-product-id');
                        }
                    }
                    if (productId) {
                        this.toggleWishlist(productId, likeBtn);
                    }
                }, true);
            }

            async loadGuestWishlist() {
                const wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');

                if (wishlist.length === 0) {
                    this.showEmptyWishlist();
                    return;
                }

                try {
                    // Fetch product details for guest wishlist items
                    const productIds = wishlist.join(',');
                    const response = await fetch(`get_products.php?ids=${productIds}`);
                    const data = await response.json();

                    if (data.success && data.products.length > 0) {
                        this.renderGuestWishlist(data.products);
                    } else {
                        this.showEmptyWishlist();
                    }
                } catch (error) {
                    console.error('Error loading guest wishlist:', error);
                    this.showEmptyWishlist();
                }
            }

            renderGuestWishlist(products) {
                const tbody = document.getElementById('wishlist-table-body');
                let html = '';

                products.forEach(product => {
                    const images = this.parseProductImages(product.images);
                    const mainImage = images.length > 0 ? images[0] : this.defaultImage;

                    html += `
                        <tr data-product-id="${product.id}">
                            <td class="product-thumbnail">
                                <div class="p-relative">
                                    <a href="product-detail.php?id=${product.id}">
                                        <figure>
                                            <img src="${mainImage}" 
                                                 alt="${this.escapeHtml(product.product_name)}" 
                                                 width="300" height="338"
                                                 onerror="this.src='${this.defaultImage}';">
                                        </figure>
                                    </a>
                                    <button type="button" class="btn btn-link btn-close btn-remove-wishlist" 
                                            data-product-id="${product.id}">
                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/trash-icon.svg" alt="">
                                    </button>
                                </div>
                            </td>
                            <td class="product-name">
                                <a href="product-detail.php?id=${product.id}">
                                    ${this.escapeHtml(product.product_name)}
                                </a>
                            </td>
                            <td class="product-price text-start">
                                <ins class="new-price">₹${this.formatPrice(product.selling_price)}</ins>
                                ${product.mrp > product.selling_price ? `<del class="old-price ml-2">₹${this.formatPrice(product.mrp)}</del>` : ''}
                                ${product.coin > 0 ? `
                                    <span class="d-block product-price1 justify-content-start">
                                        You will earn <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid ml-1" alt=""> ${product.coin} coins
                                    </span>
                                ` : ''}
                            </td>
                            <td class="product-stock-status text-center">
                                ${product.Inventory > 0 ?
                            '<span class="wishlist-in-stock">In Stock</span>' :
                            '<span class="wishlist-out-of-stock">Out of Stock</span>'
                        }
                            </td>
                            <td class="wishlist-action">
                                <div class="d-lg-flex">
                                    <a class="btn btn-primary btn-rounded btn-sm btn-cart"
                                       data-product-id="${product.id}">Add to cart</a>
                                    <a href="#" class="btn btn-outline btn-rounded btn-sm btn-quickview ml-2"
                                       data-product-id="${product.id}">Quick View</a>
                                </div>
                            </td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
            }

            parseProductImages(imagesData) {
                if (!imagesData) return [];

                try {
                    const parsed = JSON.parse(imagesData);
                    if (Array.isArray(parsed)) {
                        return parsed.map(img => this.baseImageUrl + img).filter(img => img);
                    }
                } catch (e) {
                    // If not JSON, try comma-separated
                    const images = imagesData.split(',').map(img => img.trim()).filter(img => img);
                    return images.map(img => this.baseImageUrl + img);
                }
                return [];
            }

            showEmptyWishlist() {
                const tbody = document.getElementById('wishlist-table-body');
                tbody.innerHTML = `
                    <tr id="empty-wishlist-row">
                        <td colspan="5" class="text-center py-5">
                            <div class="empty-wishlist">
                                <i class="w-icon-heart" style="font-size: 48px; color: #ccc; margin-bottom: 20px; display: block;"></i>
                                <h4>Your wishlist is empty</h4>
                                <p>Add some products to your wishlist to see them here.</p>
                                <a href="shop.php" class="btn btn-primary btn-rounded">Continue Shopping</a>
                            </div>
                        </td>
                    </tr>
                `;
            }

            async removeFromWishlist(productId, button) {
                button.style.opacity = '0.6';
                button.style.pointerEvents = 'none';

                try {
                    if (this.isLoggedIn) {
                        // Remove from database
                        const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'remove', product_id: parseInt(productId) })
                        });

                        const data = await response.json();
                        if (data.success) {
                            this.showNotification('Product removed from your wishlist', 'error');
                            this.updateHeaderCount();

                            // Reload the page after successful removal
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            this.showNotification(data.message || 'Failed to remove from wishlist', 'error');
                        }
                    } else {
                        // Remove from localStorage
                        let wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                        wishlist = wishlist.filter(id => id != productId);
                        localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));

                        this.showNotification('Product removed from your wishlist', 'error');
                        this.updateHeaderCount();

                        // Reload the page after successful removal
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                } catch (error) {
                    console.error('Error removing from wishlist:', error);
                    this.showNotification('An error occurred. Please try again.', 'error');
                } finally {
                    button.style.opacity = '';
                    button.style.pointerEvents = '';
                }
            }

            openRemoveWishlistModal(productId, button) {
                const modal = document.getElementById('removeWishlistItemModal');
                if (!modal) { this.removeFromWishlist(productId, button); return; }
                modal.style.display = 'block';

                const closeBtn = document.getElementById('closeRemoveWishlistItemModal');
                const noBtn = modal.querySelector('.btn-wl-remove-no');
                const yesBtn = modal.querySelector('.btn-wl-remove-yes');

                const cleanup = () => {
                    modal.style.display = 'none';
                    yesBtn.replaceWith(yesBtn.cloneNode(true));
                    noBtn.replaceWith(noBtn.cloneNode(true));
                    closeBtn.replaceWith(closeBtn.cloneNode(true));
                };

                modal.querySelector('.btn-wl-remove-yes').addEventListener('click', () => {
                    cleanup();
                    this.removeFromWishlist(productId, button);
                }, { once: true });
                modal.querySelector('.btn-wl-remove-no').addEventListener('click', () => {
                    cleanup();
                }, { once: true });
                document.getElementById('closeRemoveWishlistItemModal').addEventListener('click', () => {
                    cleanup();
                }, { once: true });

                // Click outside to close
                const outsideHandler = (e) => {
                    if (e.target === modal) {
                        cleanup();
                        window.removeEventListener('click', outsideHandler);
                    }
                };
                window.addEventListener('click', outsideHandler);
            }

            removeProductRow(productId) {
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    row.remove();

                    // Check if wishlist is now empty
                    const tbody = document.getElementById('wishlist-table-body');
                    if (tbody.children.length === 0) {
                        this.showEmptyWishlist();
                    }
                }
            }



            async addToCart(productId, quantity = 1) {
                try {
                    // Prevent duplicate notifications by checking if already processing
                    if (this.isAddingToCart) {
                        return { success: false, message: 'Already processing' };
                    }
                    this.isAddingToCart = true;

                    // Use the global cart manager if available (matches shop.php)
                    if (typeof window.cartManager !== 'undefined') {
                        const result = await window.cartManager.addToCart(productId, quantity);

                        // Update header cart count after successful add
                        if (result && result.success) {
                            this.updateHeaderCartCount();
                        }

                        this.isAddingToCart = false;
                        return result;
                    } else {
                        // Fallback implementation for both guest and logged-in users
                        if (this.isLoggedIn) {
                            // Logged-in user - add to database
                            const response = await fetch('cart_handler.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'add', product_id: parseInt(productId), quantity: quantity })
                            });

                            const data = await response.json();
                            if (data.success) {
                                this.showNotification('Product added to cart successfully!', 'success');
                                this.updateHeaderCartCount();
                            } else {
                                this.showNotification(data.message || 'Failed to add product to cart', 'error');
                            }

                            this.isAddingToCart = false;
                            return data;
                        } else {
                            // Guest user - add to localStorage
                            this.addToGuestCart(productId, quantity);
                            this.showNotification('Product added to cart successfully!', 'success');
                            this.updateHeaderCartCount();

                            this.isAddingToCart = false;
                            return { success: true, message: 'Added to guest cart' };
                        }
                    }
                } catch (error) {
                    console.error('Error adding to cart:', error);
                    this.showNotification('An error occurred while adding to cart', 'error');
                    this.isAddingToCart = false;
                    return { success: false, message: 'Network error' };
                }
            }

            addToGuestCart(productId, quantity = 1) {
                try {
                    // Get existing cart from localStorage
                    let cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');

                    // Check if product already exists in cart
                    const existingItem = cart.find(item => item.product_id === parseInt(productId));

                    if (existingItem) {
                        // Update quantity
                        existingItem.quantity += quantity;
                    } else {
                        // Add new item
                        cart.push({
                            product_id: parseInt(productId),
                            quantity: quantity,
                            added_date: new Date().toISOString()
                        });
                    }

                    // Save back to localStorage
                    localStorage.setItem('guest_cart', JSON.stringify(cart));

                    console.log('Added to guest cart:', { productId, quantity, cart });
                } catch (error) {
                    console.error('Error adding to guest cart:', error);
                }
            }

            updateHeaderCartCount() {
                try {
                    // Use global cart manager if available (matches shop.php)
                    if (typeof window.cartManager !== 'undefined' && typeof window.cartManager.updateHeaderCart === 'function') {
                        window.cartManager.updateHeaderCart();
                        return;
                    }

                    if (this.isLoggedIn) {
                        // For logged-in users, get count from database
                        fetch('cart_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_count' })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    this.updateCartCountDisplay(data.count || 0);
                                }
                            })
                            .catch(error => {
                                console.error('Error getting cart count:', error);
                            });
                    } else {
                        // For guest users, get count from localStorage
                        const cart = JSON.parse(localStorage.getItem('guest_cart') || '[]');
                        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
                        this.updateCartCountDisplay(totalItems);
                    }
                } catch (error) {
                    console.error('Error updating header cart count:', error);
                }
            }

            updateCartCountDisplay(count) {
                // Update all cart count elements with multiple selectors
                const selectors = [
                    '.cart-count', '#cart-count', '.header-cart-count',
                    '.cart-item-count', '.cart-badge', '.badge-count',
                    '[data-cart-count]', '.w-icon-cart .cart-count'
                ];

                selectors.forEach(selector => {
                    const elements = document.querySelectorAll(selector);
                    elements.forEach(element => {
                        if (count > 0) {
                            element.textContent = count;
                            element.style.display = 'inline';
                            element.style.visibility = 'visible';
                        } else {
                            element.style.display = 'none';
                            element.style.visibility = 'hidden';
                        }
                    });
                });

                // Update sticky footer cart count
                const stickyCartCount = document.getElementById('sticky-cart-count');
                if (stickyCartCount) {
                    if (count > 0) {
                        stickyCartCount.textContent = count;
                        stickyCartCount.style.display = 'inline';
                    } else {
                        stickyCartCount.style.display = 'none';
                    }
                }

                // Update header cart count specifically
                const headerCartCount = document.querySelector('.header-cart .cart-count');
                if (headerCartCount) {
                    if (count > 0) {
                        headerCartCount.textContent = count;
                        headerCartCount.style.display = 'inline';
                    } else {
                        headerCartCount.style.display = 'none';
                    }
                }

                // Call global update function if available
                if (typeof window.updateCartCount === 'function') {
                    window.updateCartCount();
                }

                console.log('Updated cart count display:', count);
            }

            async toggleWishlist(productId, uiButton = null) {
                try {
                    if (this.isLoggedIn) {
                        // Check if in wishlist first
                        const checkResponse = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'check', product_id: parseInt(productId) })
                        });

                        const checkData = await checkResponse.json();

                        if (checkData.success) {
                            const action = checkData.in_wishlist ? 'remove' : 'add';
                            const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: action, product_id: parseInt(productId) })
                            });

                            const data = await response.json();
                            if (data.success) {
                                this.showNotification(data.message, 'success');
                                this.updateHeaderCount();

                                // If removing from wishlist, reload the page
                                if (checkData.in_wishlist) {
                                    setTimeout(() => {
                                        window.location.reload();
                                    }, 500);
                                } else {
                                    // If adding to wishlist, just update button state
                                    if (uiButton) {
                                        this.updateWishlistButtonState(uiButton, !checkData.in_wishlist);
                                    }

                                    // Update all buttons with same product ID
                                    this.updateAllWishlistButtons(productId, !checkData.in_wishlist);
                                }
                            } else {
                                this.showNotification(data.message || 'Failed to update wishlist', 'error');
                            }
                        }
                    } else {
                        // Handle guest wishlist
                        let wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                        const index = wishlist.indexOf(parseInt(productId));
                        let isInWishlist = false;

                        if (index > -1) {
                            wishlist.splice(index, 1);
                            this.showNotification('Removed from wishlist', 'info');

                            // Reload the page after removal from wishlist
                            setTimeout(() => {
                                window.location.reload();
                            }, 500);
                        } else {
                            wishlist.push(parseInt(productId));
                            isInWishlist = true;
                            this.showNotification('Added to wishlist', 'success');

                            // Update button state using shop.php method
                            if (uiButton) {
                                this.updateWishlistButtonState(uiButton, isInWishlist);
                            }

                            // Update all buttons with same product ID
                            this.updateAllWishlistButtons(productId, isInWishlist);
                        }

                        localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));
                        this.updateHeaderCount();
                    }
                } catch (error) {
                    console.error('Error toggling wishlist:', error);
                    this.showNotification('An error occurred. Please try again.', 'error');
                }
            }

            updateWishlistButtonState(button, inWishlist) {
                // Use shop.php method for updating button states - match exactly
                console.log('Updating wishlist button state:', button, 'inWishlist:', inWishlist);

                if (inWishlist) {
                    // In wishlist - filled heart (red)
                    console.log('Setting button to FILLED state');
                    button.classList.add('active', 'in-wishlist', 'w-icon-heart-full');
                    button.classList.remove('w-icon-heart');
                    button.style.setProperty('color', '#ff4757', 'important');
                    button.style.setProperty('background-color', '#fff', 'important');
                    button.style.setProperty('border-color', '#ff4757', 'important');
                } else {
                    // Not in wishlist - unfilled heart (gray)
                    console.log('Setting button to UNFILLED state');
                    button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');
                    button.classList.add('w-icon-heart');
                    button.style.removeProperty('color');
                    button.style.setProperty('background-color', '#fff', 'important');
                    button.style.setProperty('border-color', '#ddd', 'important');
                }

                console.log('Button classes after update:', button.className);
                console.log('Button styles after update:', {
                    color: button.style.color,
                    backgroundColor: button.style.backgroundColor,
                    borderColor: button.style.borderColor
                });
            }

            updateAllWishlistButtons(productId, isInWishlist) {
                // Update all wishlist buttons with the same product ID (matches shop.php)
                const allWishlistButtons = document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`);

                allWishlistButtons.forEach(button => {
                    this.updateWishlistButtonState(button, isInWishlist);
                });

                console.log(`Updated ${allWishlistButtons.length} wishlist buttons for product ID ${productId}`);
            }

            async initializeWishlistButtonStates() {
                // Initialize wishlist button states on page load (matches shop.php)
                console.log('Initializing wishlist button states...');
                const wishlistButtons = document.querySelectorAll('.btn-wishlist, .btn-product-icon.btn-wishlist');
                console.log(`Found ${wishlistButtons.length} wishlist buttons`);

                // First, ensure all buttons have proper icon structure
                wishlistButtons.forEach(button => {
                    this.ensureWishlistButtonIcon(button);
                });

                if (this.isLoggedIn) {
                    console.log('User is logged in, checking database...');
                    // For logged-in users, check database for each product
                    for (const button of wishlistButtons) {
                        const productId = button.getAttribute('data-product-id');
                        if (productId) {
                            try {
                                const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ action: 'check', product_id: parseInt(productId) })
                                });

                                const data = await response.json();
                                console.log(`Product ${productId} wishlist status:`, data);
                                if (data.success) {
                                    // Set default filled state if already in wishlist
                                    console.log(`Setting product ${productId} to ${data.in_wishlist ? 'filled' : 'unfilled'}`);
                                    this.updateWishlistButtonState(button, data.in_wishlist);
                                } else {
                                    // Set default unfilled state
                                    console.log(`Setting product ${productId} to unfilled (no data)`);
                                    this.updateWishlistButtonState(button, false);
                                }
                            } catch (error) {
                                console.error('Error checking wishlist status for product', productId, error);
                                // Set default unfilled state
                                this.updateWishlistButtonState(button, false);
                            }
                        }
                    }
                } else {
                    console.log('User is guest, checking localStorage...');
                    // For guest users, check localStorage
                    const guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                    console.log('Guest wishlist:', guestWishlist);

                    wishlistButtons.forEach(button => {
                        const productId = parseInt(button.getAttribute('data-product-id'));
                        if (productId && guestWishlist.includes(productId)) {
                            // Set default filled state if already in wishlist
                            console.log(`Setting product ${productId} to filled (in guest wishlist)`);
                            this.updateWishlistButtonState(button, true);
                        } else {
                            // Set default unfilled state
                            console.log(`Setting product ${productId} to unfilled (not in guest wishlist)`);
                            this.updateWishlistButtonState(button, false);
                        }
                    });
                }
            }

            ensureWishlistButtonIcon(button) {
                // Ensure button has proper classes like shop.php
                if (!button.classList.contains('w-icon-heart') && !button.classList.contains('w-icon-heart-full')) {
                    button.classList.add('w-icon-heart');
                }

                // Remove conflicting classes
                button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');

                // Ensure w-icon-heart class is present
                if (!button.classList.contains('w-icon-heart')) {
                    button.classList.add('w-icon-heart');
                }
            }

            forceUpdateAllWishlistButtons() {
                // Force update all wishlist buttons to ensure proper display
                console.log('Force updating all wishlist buttons...');
                const wishlistButtons = document.querySelectorAll('.btn-wishlist, .btn-product-icon.btn-wishlist');

                wishlistButtons.forEach(button => {
                    const productId = button.getAttribute('data-product-id');
                    if (productId) {
                        if (this.isLoggedIn) {
                            // For logged-in users, re-check database
                            this.checkWishlistStatusForButton(button, productId);
                        } else {
                            // For guest users, re-check localStorage
                            const guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                            const isInWishlist = guestWishlist.includes(parseInt(productId));
                            this.updateWishlistButtonState(button, isInWishlist);
                        }
                    }
                });
            }

            async checkWishlistStatusForButton(button, productId) {
                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check', product_id: parseInt(productId) })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.updateWishlistButtonState(button, data.in_wishlist);
                    } else {
                        this.updateWishlistButtonState(button, false);
                    }
                } catch (error) {
                    console.error('Error checking wishlist status for product', productId, error);
                    this.updateWishlistButtonState(button, false);
                }
            }

            updateHeaderCount() {
                if (typeof window.updateHeaderWishlistCount === 'function') {
                    window.updateHeaderWishlistCount();
                }
            }


            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `wishlist-notification wishlist-notification-${type}`;

                const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
                notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

                notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; color: white; padding: 16px 24px;
                    z-index: 10000; font-size: 14px; border-radius: 8px; max-width: 350px;
                    background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' :
                        type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' :
                            'linear-gradient(135deg, #3498db, #2980b9)'};
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease;
                `;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }, 4000);
            }

            formatPrice(price) {
                return parseFloat(price).toFixed(2);
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialize wishlist page manager
        const wishlistPageManager = new WishlistPageManager();

        // Make wishlistPageManager globally available
        window.wishlistPageManager = wishlistPageManager;

        // Initialize quickview functionality exactly like shop.php
        document.addEventListener('DOMContentLoaded', function () {
            function waitForWolmart() {
                if (typeof Wolmart !== 'undefined' && Wolmart.$body) {
                    initQuickview();
                } else {
                    setTimeout(waitForWolmart, 100);
                }
            }

            function initQuickview() {
                Wolmart.$body.on('click', '.btn-quickview', function (event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    const quickviewBtn = this;
                    const productId = quickviewBtn.getAttribute('data-product-id');
                    if (!productId) return;
                    quickviewBtn.style.opacity = '0.6';

                    if (typeof Wolmart !== 'undefined' && Wolmart.popup) {
                        Wolmart.popup({
                            items: { src: 'quickview.php?id=' + productId, type: 'ajax' },
                            ajax: {
                                settings: { method: 'GET' },
                                success: function (data) {
                                    // Ensure content is properly loaded
                                    setTimeout(function () {
                                        initModalQuantityButtons();
                                        initModalImageNavigation();
                                        initModalImageNavigationFallback();
                                        initModalWishlistButtons();
                                        initModalCartButtons();
                                    }, 200);
                                }
                            },
                            callbacks: {
                                open: function () {
                                    // Initialize after modal is fully open
                                    setTimeout(function () {
                                        initModalQuantityButtons();
                                        initModalImageNavigation();
                                        initModalImageNavigationFallback();
                                        initModalWishlistButtons();
                                        initModalCartButtons();
                                    }, 300);
                                },
                                close: function () {
                                    quickviewBtn.style.opacity = '';
                                }
                            }
                        }, 'quickview');
                    } else {
                        // Fallback: load content directly
                        loadQuickviewFallback(productId, quickviewBtn);
                    }
                });
            }

            function initModalQuantityButtons() {
                // Initialize quantity buttons in modal
                const quantityInputs = document.querySelectorAll('.mfp-content .quantity input');
                const plusBtns = document.querySelectorAll('.mfp-content .quantity .btn-plus');
                const minusBtns = document.querySelectorAll('.mfp-content .quantity .btn-minus');

                plusBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const input = btn.parentElement.querySelector('input');
                        const currentValue = parseInt(input.value) || 0;
                        input.value = currentValue + 1;
                    });
                });

                minusBtns.forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const input = btn.parentElement.querySelector('input');
                        const currentValue = parseInt(input.value) || 0;
                        if (currentValue > 1) {
                            input.value = currentValue - 1;
                        }
                    });
                });
            }

            function initModalImageNavigation() {
                // Wait a bit for modal to fully load - match shop.php exactly
                setTimeout(function () {
                    // Find elements in the modal
                    const modal = document.querySelector('.mfp-content .product-popup');
                    if (!modal) return;

                    const thumbnailImages = modal.querySelectorAll('.product-thumb img');
                    const mainImages = modal.querySelectorAll('.swiper-wrapper-quickview .swiper-slide img');
                    const mainSlides = modal.querySelectorAll('.swiper-wrapper-quickview .swiper-slide');
                    const nextBtn = modal.querySelector('.swiper-button-next');
                    const prevBtn = modal.querySelector('.swiper-button-prev');

                    let currentIndex = 0;
                    const totalImages = mainSlides.length;

                    // Function to update main image display
                    function updateMainImage(index) {
                        // Hide all slides and show the selected one
                        mainSlides.forEach((slide, i) => {
                            slide.style.display = i === index ? 'block' : 'none';
                        });

                        // Update active thumbnail
                        thumbnailImages.forEach((thumb, i) => {
                            const thumbParent = thumb.parentElement;
                            if (i === index) {
                                thumbParent.classList.add('active');
                                thumbParent.style.borderColor = '#007bff';
                                thumbParent.style.opacity = '1';
                            } else {
                                thumbParent.classList.remove('active');
                                thumbParent.style.borderColor = 'transparent';
                                thumbParent.style.opacity = '0.7';
                            }
                        });

                        console.log('Switched to image', index);
                    }

                    // Next button functionality
                    if (nextBtn) {
                        nextBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            currentIndex = (currentIndex + 1) % totalImages;
                            updateMainImage(currentIndex);
                        });
                    }

                    // Previous button functionality
                    if (prevBtn) {
                        prevBtn.addEventListener('click', function (e) {
                            e.preventDefault();
                            currentIndex = (currentIndex - 1 + totalImages) % totalImages;
                            updateMainImage(currentIndex);
                        });
                    }

                    // Make thumbnails clickable - match shop.php exactly
                    thumbnailImages.forEach(function (thumb, index) {
                        if (!thumb.hasAttribute('data-nav-initialized')) {
                            thumb.setAttribute('data-nav-initialized', 'true');

                            // Style thumbnail as clickable
                            thumb.style.cursor = 'pointer';
                            thumb.parentElement.style.cursor = 'pointer';

                            // Add click event to thumbnail
                            thumb.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();

                                currentIndex = index;
                                updateMainImage(currentIndex);
                            });

                            // Set first thumbnail as active by default
                            if (index === 0) {
                                thumb.parentElement.classList.add('active');
                                thumb.parentElement.style.borderColor = '#007bff';
                                thumb.parentElement.style.opacity = '1';
                            } else {
                                thumb.parentElement.style.opacity = '0.7';
                            }
                        }
                    });

                    // Initialize with first image
                    updateMainImage(0);
                }, 300);
            }

            function initModalWishlistButtons() {
                // Initialize wishlist buttons in modal with proper state
                const wishlistBtns = document.querySelectorAll('.mfp-content .btn-wishlist, .mfp-content .btn-product-icon.btn-wishlist');
                wishlistBtns.forEach(btn => {
                    if (!btn.hasAttribute('data-modal-wishlist-initialized')) {
                        btn.setAttribute('data-modal-wishlist-initialized', 'true');

                        // Ensure proper icon structure
                        if (window.wishlistPageManager) {
                            window.wishlistPageManager.ensureWishlistButtonIcon(btn);
                        }

                        // Set initial state based on wishlist status
                        const productId = btn.getAttribute('data-product-id');
                        if (productId && window.wishlistPageManager) {
                            if (window.wishlistPageManager.isLoggedIn) {
                                // For logged-in users, check database
                                checkWishlistStatusForModalButton(btn, productId);
                            } else {
                                // For guest users, check localStorage
                                const guestWishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                                const isInWishlist = guestWishlist.includes(parseInt(productId));
                                window.wishlistPageManager.updateWishlistButtonState(btn, isInWishlist);
                            }
                        }

                        btn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            if (productId && window.wishlistPageManager) {
                                window.wishlistPageManager.toggleWishlist(productId, btn);
                            }
                        });
                    }
                });
            }

            async function checkWishlistStatusForModalButton(button, productId) {
                try {
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check', product_id: parseInt(productId) })
                    });

                    const data = await response.json();
                    if (data.success && window.wishlistPageManager) {
                        window.wishlistPageManager.updateWishlistButtonState(button, data.in_wishlist);
                    } else if (window.wishlistPageManager) {
                        window.wishlistPageManager.updateWishlistButtonState(button, false);
                    }
                } catch (error) {
                    console.error('Error checking wishlist status for product', productId, error);
                    if (window.wishlistPageManager) {
                        window.wishlistPageManager.updateWishlistButtonState(button, false);
                    }
                }
            }

            function initModalCartButtons() {
                // Initialize cart buttons in modal - prevent duplicates
                const cartBtns = document.querySelectorAll('.mfp-content .btn-cart, .mfp-content .btn-add-cart');
                cartBtns.forEach(btn => {
                    if (!btn.hasAttribute('data-modal-cart-initialized')) {
                        btn.setAttribute('data-modal-cart-initialized', 'true');

                        btn.addEventListener('click', function (e) {
                            e.preventDefault();
                            e.stopImmediatePropagation();

                            const productId = btn.getAttribute('data-product-id');
                            if (!productId) return;

                            // Prevent multiple rapid clicks
                            if (btn.disabled) return;

                            // Get quantity from modal
                            const quantityInput = document.querySelector('.mfp-content .quantity');
                            const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

                            // Use global cart manager if available (matches shop.php)
                            if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                                const originalText = btn.innerHTML;
                                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                btn.disabled = true;

                                window.cartManager.addToCart(productId, quantity).then((result) => {
                                    if (result && result.success) {
                                        showCartNotification('Product added to cart successfully!', 'success');
                                        // Update header cart count
                                        if (window.wishlistPageManager) {
                                            window.wishlistPageManager.updateHeaderCartCount();
                                        }
                                    }
                                }).finally(() => {
                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                });
                            } else {
                                // Fallback implementation for both guest and logged-in users
                                const originalText = btn.innerHTML;
                                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                btn.disabled = true;

                                const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

                                if (isLoggedIn) {
                                    // Logged-in user - add to database
                                    fetch('cart_handler.php', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json' },
                                        body: JSON.stringify({ action: 'add', product_id: parseInt(productId), quantity: quantity })
                                    })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                showCartNotification('Product added to cart successfully!', 'success');
                                                if (window.wishlistPageManager) {
                                                    window.wishlistPageManager.updateHeaderCartCount();
                                                }
                                            } else {
                                                showCartNotification(data.message || 'Failed to add product to cart', 'error');
                                            }
                                        })
                                        .catch(() => {
                                            showCartNotification('An error occurred while adding to cart', 'error');
                                        })
                                        .finally(() => {
                                            btn.innerHTML = originalText;
                                            btn.disabled = false;
                                        });
                                } else {
                                    // Guest user - add to localStorage
                                    if (window.wishlistPageManager) {
                                        window.wishlistPageManager.addToGuestCart(productId, quantity);
                                        showCartNotification('Product added to cart successfully!', 'success');
                                        window.wishlistPageManager.updateHeaderCartCount();
                                    }

                                    btn.innerHTML = originalText;
                                    btn.disabled = false;
                                }
                            }
                        });
                    }
                });
            }

            function showCartNotification(message, type = 'info') {
                // Remove any existing cart notifications to prevent duplicates
                const existingNotifications = document.querySelectorAll('.cart-notification');
                existingNotifications.forEach(notification => notification.remove());

                const notification = document.createElement('div');
                notification.className = `cart-notification cart-notification-${type}`;

                const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
                notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

                notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; color: white; padding: 16px 24px;
                    z-index: 10000; font-size: 14px; border-radius: 8px; max-width: 350px;
                    background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' :
                        type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' :
                            'linear-gradient(135deg, #3498db, #2980b9)'};
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                    animation: slideInRight 0.3s ease;
                    cursor: pointer;
                `;

                document.body.appendChild(notification);

                // Auto remove after 4 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.animation = 'slideOutRight 0.3s ease';
                        setTimeout(() => notification.remove(), 300);
                    }
                }, 4000);

                // Click to close
                notification.addEventListener('click', () => {
                    notification.remove();
                });
            }

            // Start the initialization
            waitForWolmart();
        });

        // Fallback quickview loader
        function loadQuickviewFallback(productId, button) {
            const modal = document.getElementById('quickview-modal');
            if (!modal) return;

            // Show loading
            modal.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';
            modal.style.display = 'block';

            // Fetch quickview content
            fetch('quickview.php?id=' + productId)
                .then(response => response.text())
                .then(html => {
                    modal.innerHTML = html;

                    // Create overlay
                    const overlay = document.createElement('div');
                    overlay.style.cssText = `
                        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                        background: rgba(0,0,0,0.8); z-index: 10000; 
                        display: flex; align-items: center; justify-content: center;
                    `;

                    const modalContainer = document.createElement('div');
                    modalContainer.style.cssText = `
                        background: white; border-radius: 8px; max-width: 90%; max-height: 90%; 
                        overflow-y: auto; position: relative; padding: 20px;
                    `;

                    // Add close button
                    const closeBtn = document.createElement('button');
                    closeBtn.innerHTML = '×';
                    closeBtn.style.cssText = `
                        position: absolute; top: 10px; right: 15px; background: none; 
                        border: none; font-size: 24px; cursor: pointer; z-index: 1;
                    `;

                    modalContainer.appendChild(closeBtn);
                    modalContainer.innerHTML += modal.innerHTML;
                    overlay.appendChild(modalContainer);
                    document.body.appendChild(overlay);

                    // Close handlers
                    const closeModal = () => {
                        if (overlay.parentNode) {
                            document.body.removeChild(overlay);
                        }
                        button.style.opacity = '';
                    };

                    closeBtn.onclick = closeModal;
                    overlay.onclick = (e) => {
                        if (e.target === overlay) closeModal();
                    };

                    // Initialize modal functionality
                    setTimeout(() => {
                        initModalQuantityButtons();
                        initModalImageNavigation();
                        initModalImageNavigationFallback();
                        initModalWishlistButtons();
                        initModalCartButtons();
                    }, 100);

                    button.style.opacity = '';
                })
                .catch(error => {
                    console.error('Error loading quickview:', error);
                    modal.innerHTML = '<div class="text-center p-5 text-danger">Error loading product details</div>';
                    button.style.opacity = '';
                });
        }

        // Additional fallback image navigation - match shop.php exactly
        function initModalImageNavigationFallback() {
            setTimeout(() => {
                const modal = document.querySelector('.mfp-content .product-popup');
                if (!modal) {
                    console.log('Modal not found for image navigation');
                    return;
                }

                const thumbnailImages = modal.querySelectorAll('.product-thumb img');
                const mainImages = modal.querySelectorAll('.swiper-wrapper-quickview .swiper-slide img');
                const mainSlides = modal.querySelectorAll('.swiper-wrapper-quickview .swiper-slide');

                console.log('Found elements:', {
                    modal: !!modal,
                    thumbnails: thumbnailImages.length,
                    mainSlides: mainSlides.length
                });

                if (thumbnailImages.length === 0 || mainSlides.length === 0) {
                    console.log('Not enough elements found for image navigation');
                    return;
                }

                // Initialize first image as active
                mainSlides.forEach((slide, index) => {
                    slide.style.display = index === 0 ? 'block' : 'none';
                });

                thumbnailImages.forEach((thumb, index) => {
                    const thumbParent = thumb.parentElement;

                    // Set first thumbnail as active
                    if (index === 0) {
                        thumbParent.classList.add('active');
                        thumbParent.style.borderColor = '#007bff';
                        thumbParent.style.opacity = '1';
                    } else {
                        thumbParent.style.opacity = '0.7';
                    }

                    // Add click event
                    thumb.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        // Update main images
                        mainSlides.forEach((slide, slideIndex) => {
                            slide.style.display = slideIndex === index ? 'block' : 'none';
                        });

                        // Update thumbnails
                        thumbnailImages.forEach((thumbImg, thumbIndex) => {
                            const thumbParent = thumbImg.parentElement;
                            if (thumbIndex === index) {
                                thumbParent.classList.add('active');
                                thumbParent.style.borderColor = '#007bff';
                                thumbParent.style.opacity = '1';
                            } else {
                                thumbParent.classList.remove('active');
                                thumbParent.style.borderColor = 'transparent';
                                thumbParent.style.opacity = '0.7';
                            }
                        });

                        console.log('Thumbnail clicked, switched to image', index);
                    });
                });
            }, 100);
        }
    </script>

  
</body>

</html>
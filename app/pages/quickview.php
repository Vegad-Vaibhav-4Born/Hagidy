<?php
require_once __DIR__ . '/includes/init.php';

// Set content type to HTML
header('Content-Type: text/html; charset=UTF-8');

// HTML escape function
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="text-center p-5 text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><br>Product ID is required</div>';
    exit;
}

$product_id = (int) $_GET['id'];

// Fetch product data from database
$query = "SELECT * FROM products WHERE id = ? AND status = 'approved'";
$stmt = mysqli_prepare($db_connection, $query);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    echo '<div class="text-center p-5 text-danger"><i class="fa fa-exclamation-triangle fa-2x"></i><br>Product not found</div>';
    exit;
}

$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Process product data
$id = $product['id'];
$name = $product['product_name'];
$mrp = (int) $product['mrp'];
$price = (int) $product['selling_price'];
$discount = (int) $product['discount'];
$description = $product['description'];
$coin = (int) $product['coin'];
$brand = $product['brand'];
$images = $product['images'];
$specifications = $product['specifications'];
$inventory = (int) $product['Inventory'];

// Calculate discount percentage
$discount_percentage = 0;
if ($mrp > $price) {
    $discount_percentage = round((($mrp - $price) / $mrp) * 100);
}

// Process images (same logic as main product page)
$image_array = [];
$default_image = $vendor_baseurl . 'uploads/vendors/no-product.png';
$base_image_url = $vendor_baseurl;

// Parse images JSON (same logic as main product page)
if (!empty($images)) {
    $images_data = json_decode($images, true);
    if (is_array($images_data)) {
        foreach ($images_data as $image_name) {
            if (!empty($image_name)) {
                $image_array[] = $image_name;
            }
        }
    }
}

// Use real images or fallback to default
if (empty($image_array)) {
    // No valid images found, use default
    $image_array[] = $default_image;
}

// Process specifications for color and size variants and all specifications
$color_variants = [];
$size_variants = [];
$all_variants = [];
$all_specifications = [];
$variant_price_by_id = [];
$attribute_variants_indexed = [];

// Get attributes from attributes table (same logic as product-detail.php)
if (!empty($specifications)) {
    $specs = json_decode($specifications, true);
    if (is_array($specs)) {
        foreach ($specs as $spec) {
            if (isset($spec['attribute_id'])) {
                $attribute_id = intval($spec['attribute_id']);
                // keep original variants order for this attribute for index mapping
                if (isset($spec['variants']) && is_array($spec['variants'])) {
                    $attribute_variants_indexed[$attribute_id] = $spec['variants'];
                    foreach ($spec['variants'] as $ve) {
                        if (isset($ve['id'])) {
                            $vid = intval($ve['id']);
                            $mrp_val = isset($ve['mrp']) ? floatval($ve['mrp']) : null;
                            $selling_val = isset($ve['selling_price']) ? floatval($ve['selling_price']) : null;
                            if ($mrp_val !== null || $selling_val !== null) {
                                $variant_price_by_id[$vid] = ['mrp' => $mrp_val, 'selling_price' => $selling_val];
                            }
                        }
                    }
                }

                // Get attribute details
                $attr_query = "SELECT * FROM attributes WHERE id = ? LIMIT 1";
                $attr_stmt = mysqli_prepare($db_connection, $attr_query);
                mysqli_stmt_bind_param($attr_stmt, "i", $attribute_id);
                mysqli_stmt_execute($attr_stmt);
                $attr_result = mysqli_stmt_get_result($attr_stmt);

                if ($attr_result && mysqli_num_rows($attr_result) > 0) {
                    $attr_data = mysqli_fetch_assoc($attr_result);
                    mysqli_stmt_close($attr_stmt);

                    // Parse attribute values from the single table
                    $attr_values = json_decode($attr_data['attribute_values'], true);

                    if (is_array($attr_values)) {
                        $attribute_variants = [];
                        $idx = 0;
                        $totalSpec = count($attribute_variants_indexed[$attribute_id] ?? []);

                        foreach ($attr_values as $value) {
                            if ($totalSpec > 0 && $idx >= $totalSpec)
                                break;
                            if (isset($value['value_name'])) {
                                $specVariant = $attribute_variants_indexed[$attribute_id][$idx] ?? null;
                                $mapped_variant_id = ($specVariant && isset($specVariant['id'])) ? intval($specVariant['id']) : (isset($value['id']) ? intval($value['id']) : null);
                                $mapped_mrp = $specVariant && isset($specVariant['mrp']) ? floatval($specVariant['mrp']) : null;
                                $mapped_selling = $specVariant && isset($specVariant['selling_price']) ? floatval($specVariant['selling_price']) : null;

                                $attribute_variants[] = [
                                    'name' => $value['value_name'],
                                    'variant_id' => $mapped_variant_id,
                                    'mrp' => $mapped_mrp,
                                    'selling_price' => $mapped_selling,
                                    'color_code' => $value['color_code'] ?? $value['code'] ?? $value['hex'] ?? $value['hex_code'] ?? null
                                ];
                            }
                            $idx++;
                        }

                        // Add to all_variants array for dynamic display
                        if (!empty($attribute_variants)) {
                            $all_variants[] = [
                                'attribute_id' => $attribute_id,
                                'attribute_name' => $attr_data['name'],
                                'variants' => $attribute_variants
                            ];
                        }

                        // Separate color and size for backward compatibility
                        if (stripos($attr_data['name'], 'color') !== false) {
                            foreach ($attribute_variants as $variant) {
                                $color_variants[] = [
                                    'name' => $variant['name'],
                                    'code' => $variant['name'],
                                    'variant_id' => $variant['variant_id'],
                                    'mrp' => $variant['mrp'],
                                    'selling_price' => $variant['selling_price']
                                ];
                            }
                        } elseif (stripos($attr_data['name'], 'size') !== false) {
                            foreach ($attribute_variants as $variant) {
                                $size_variants[] = [
                                    'label' => $variant['name'],
                                    'variant_id' => $variant['variant_id'],
                                    'mrp' => $variant['mrp'],
                                    'selling_price' => $variant['selling_price']
                                ];
                            }
                        }

                        // Add all specifications to display
                        $values_list = [];
                        foreach ($attr_values as $value) {
                            if (isset($value['value_name'])) {
                                $values_list[] = $value['value_name'];
                            }
                        }

                        if (!empty($values_list)) {
                            $all_specifications[] = [
                                'name' => $attr_data['name'],
                                'values' => implode(', ', $values_list)
                            ];
                        }
                    }
                }
            }
        }
    }
}

// No default variants; if none, nothing will be shown

// Get reviews for this product (same logic as product-detail.php)
$reviews_query = "SELECT * FROM reviews WHERE product_id = ? ORDER BY created_date DESC";
$reviews_stmt = mysqli_prepare($db_connection, $reviews_query);
mysqli_stmt_bind_param($reviews_stmt, "i", $product_id);
mysqli_stmt_execute($reviews_stmt);
$reviews_result = mysqli_stmt_get_result($reviews_stmt);
$reviews = [];
$total_reviews = 0;
$total_rating = 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

if ($reviews_result) {
    while ($review = mysqli_fetch_assoc($reviews_result)) {
        $reviews[] = $review;
        $total_reviews++;
        $rating = floatval($review['rating']);
        $total_rating += $rating;

        // Count ratings for distribution
        $rating_floor = floor($rating);
        if ($rating_floor >= 1 && $rating_floor <= 5) {
            $rating_counts[$rating_floor]++;
        }
    }
    mysqli_stmt_close($reviews_stmt);
}

// Calculate average rating
$average_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
$average_rating_percentage = $average_rating * 20; // Convert to percentage for display

// Expose variant prices for this modal instance to JS
echo '<script>window.productVariantPrices = Object.assign({}, window.productVariantPrices || {}, ' . json_encode($variant_price_by_id ?: new stdClass()) . ');</script>';

?>
<link style="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/quickview.css" rel="stylesheet">
<div class="product product-single product-popup" data-product-id="<?php echo $id; ?>">
    <div class="row gutter-lg">
        <div class="col-md-6 mb-4 mb-md-0">
            <div class="product-gallery product-gallery-sticky">
                <div
                    class="swiper-container product-single-swiper swiper-theme nav-inner <?php echo count($image_array) <= 1 ? 'single-image' : ''; ?>">
                    <div class="swiper-wrapper swiper-wrapper-quickview">
                        <?php foreach ($image_array as $index => $image_src): ?>
                            <?php
                            $zoom_image = $image_src; // You can create different sizes if needed
                            ?>
                            <div class="swiper-slide">
                                <figure class="product-image">
                                    <img src="<?php echo e($image_src); ?>" data-zoom-image="<?php echo e($zoom_image); ?>"
                                        alt="<?php echo e($name); ?>" width="800" height="900"
                                        onerror="this.src='<?php echo $vendor_baseurl . 'uploads/vendors/no-product.png'; ?>'; this.onerror=null;">
                                </figure>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($image_array) > 1): ?>
                        <button class="swiper-button-next" type="button"></button>
                        <button class="swiper-button-prev" type="button"></button>
                    <?php endif; ?>
                </div>
                <?php if (count($image_array) > 1): ?>
                    <div class="product-thumbs-wrap">
                        <div class="product-thumbs">
                            <?php foreach ($image_array as $index => $thumb_src): ?>
                                <div class="product-thumb swiper-slide <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <img src="<?php echo e($thumb_src); ?>" alt="Product Thumb" width="80" height="80"
                                        data-index="<?php echo $index; ?>"
                                        onerror="this.src='<?php echo $vendor_baseurl . 'uploads/vendors/no-product.png'; ?>'; this.onerror=null;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6 overflow-hidden p-relative">
            <div class="product-details scrollable pl-0">
                <h1 class="product-title"><?php echo e($name); ?></h1>

                <hr class="product-divider">
                <div class="product-price d-flex align-items-center">
                    <ins class="new-price mr-2">
                        <sup style="font-size: 18px;">₹</sup> <?php echo number_format($price, 2); ?>
                    </ins>
                    <?php if ($coin > 0): ?>
                        <span class="d-block product-price1">
                            Earn <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid" alt=""> <?php echo $coin; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="ratings-container">
                    <div class="ratings-full">
                        <span class="ratings" style="width: <?php echo $average_rating_percentage; ?>%;"></span>
                        <span class="tooltiptext tooltip-top"><?php echo $average_rating; ?> Stars</span>
                    </div>
                    <a href="product-detail.php?id=<?php echo $id; ?>#product-tab-reviews"
                        class="rating-reviews scroll-to">(<?php echo $total_reviews; ?> Reviews)</a>
                </div>

                <div class="stock-status mb-3">
                    <?php if ($inventory > 0): ?>
                        <span class="stock-available" style="color: #2ecc71; font-weight: bold;">
                            <i class="fa fa-check-circle"></i> In Stock (<?php echo $inventory; ?> available)
                        </span>
                    <?php else: ?>
                        <span class="stock-unavailable" style="color: #e74c3c; font-weight: bold;">
                            <i class="fa fa-times-circle"></i> Out of Stock
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mrp-price">
                    <h4>
                        M.R.P: <del class="old-price">₹<?php echo number_format($mrp, 2); ?></del>
                        <span
                            style="<?php echo ($discount_percentage > 0) ? '' : 'display:none;'; ?>">(<?php echo max(0, $discount_percentage); ?>%
                            off)</span>
                    </h4>
                </div>

                <?php if (!empty($description)): ?>
                    <div class="product-short-desc">
                        <p><?php echo e($description); ?></p>
                    </div>
                <?php endif; ?>

                <hr class="product-divider">



                <!-- Read More Button -->
                <div class="product-form">
                    <a href="product-detail.php?id=<?php echo $id; ?>" class="btn btn-primary btn-read-more"
                        style="width: 100%; padding: 12px 20px; font-size: 16px; font-weight: 600;">
                        <i class="fa fa-eye"></i>
                        <span>Read More</span>
                    </a>
                </div>

                <div class="social-links-wrapper">
                    <div class="social-links">
                        <div class="social-icons social-no-color border-thin">
                            <a href="#" class="social-icon social-facebook w-icon-facebook"></a>
                            <a href="#" class="social-icon social-twitter w-icon-twitter"></a>
                            <a href="#" class="social-icon social-pinterest fab fa-pinterest-p"></a>
                            <a href="#" class="social-icon social-whatsapp fab fa-whatsapp"></a>
                            <a href="#" class="social-icon social-youtube fab fa-linkedin-in"></a>
                        </div>
                    </div>
                    <span class="divider d-xs-show"></span>
                    <div class="product-link-wrapper d-flex">
                        <a href="#" class="btn-product-icon btn-wishlist w-icon-heart"
                            data-product-id="<?php echo $id; ?>">
                            <span></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End of Quick view -->

<script>
    // Image carousel functionality for quickview modal
    function initImageCarousel(modal) {
        const mainSlider = modal.querySelector('.swiper-wrapper-quickview');
        const thumbnails = modal.querySelectorAll('.product-thumb');
        const nextBtn = modal.querySelector('.swiper-button-next');
        const prevBtn = modal.querySelector('.swiper-button-prev');

        if (!mainSlider || thumbnails.length === 0) return;

        let currentIndex = 0;
        const totalImages = mainSlider.children.length;

        // Function to update main image display
        function updateMainImage(index) {
            // Hide all slides and show the selected one
            Array.from(mainSlider.children).forEach((slide, i) => {
                slide.style.display = i === index ? 'block' : 'none';
            });

            // Update active thumbnail
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === index);
                // Also update thumbnail visual states
                if (i === index) {
                    thumb.style.borderColor = '#007bff';
                    thumb.style.opacity = '1';
                } else {
                    thumb.style.borderColor = 'transparent';
                    thumb.style.opacity = '0.7';
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

        // Thumbnail click functionality
        thumbnails.forEach((thumb, index) => {
            thumb.addEventListener('click', function (e) {
                e.preventDefault();
                currentIndex = index;
                updateMainImage(currentIndex);
            });
        });

        // Initialize with first image
        updateMainImage(0);
    }

    // Initialize quickview modal functionality
    function initQuickviewModal() {
        // Always target the modal content instance if present
        const modal = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
        if (!modal) return;
        // Prevent rebinding on the same modal instance only
        if (modal.getAttribute('data-quickview-initialized') === 'true') return;
        modal.setAttribute('data-quickview-initialized', 'true');

        // Initialize image carousel functionality
        initImageCarousel(modal);

    }

    // Notification function
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `wishlist-notification wishlist-notification-${type}`;

        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
        notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            color: white;
            padding: 16px 24px;
            z-index: 10000;
            font-size: 14px;
            line-height: 1.4;
            max-width: 350px;
            animation: slideInRight 0.3s ease;
            cursor: pointer;
        `;

        document.body.appendChild(notification);

        notification.addEventListener('click', () => {
            dismissNotification(notification);
        });

        setTimeout(() => {
            dismissNotification(notification);
        }, 4000);
    }

    function dismissNotification(notification) {
        if (notification && notification.parentNode) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        // Small delay to ensure modal content is fully loaded
        setTimeout(initQuickviewModal, 100);
    });

    // Also initialize immediately in case DOMContentLoaded already fired
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQuickviewModal);
    } else {
        setTimeout(initQuickviewModal, 100);
    }
</script>




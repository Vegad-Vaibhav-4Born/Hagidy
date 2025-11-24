<?php
session_start();
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;
    $review_text = isset($_POST['review']) ? trim($_POST['review']) : '';
    $author_name = isset($_POST['author']) ? trim($_POST['author']) : '';
    $author_email = isset($_POST['email_1']) ? trim($_POST['email_1']) : '';
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

    // Bound rating between 1 and 5
    if ($rating < 1)
        $rating = 1;
    if ($rating > 5)
        $rating = 5;

    if ($rating > 0 && !empty($review_text) && !empty($author_name) && !empty($author_email)) {
        $rating = mysqli_real_escape_string($db_connection, $rating);
        $review_text = mysqli_real_escape_string($db_connection, $review_text);
        $author_name = mysqli_real_escape_string($db_connection, $author_name);
        $author_email = mysqli_real_escape_string($db_connection, $author_email);
        $product_id_for_review = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Enforce: must be logged in to submit
        if ($user_id <= 0) {
            header("Location: product-detail.php?id=$product_id_for_review&review=login_required#product-tab-reviews");
            exit;
        }

        // Check if user has purchased this product
        $has_purchased = false;
        $order_rs = mysqli_query($db_connection, "SELECT products FROM `order` WHERE user_id = '$user_id'");
        if ($order_rs) {
            while ($row_o = mysqli_fetch_assoc($order_rs)) {
                $prods = json_decode($row_o['products'], true);
                if (is_array($prods)) {
                    foreach ($prods as $op) {
                        if (isset($op['product_id']) && intval($op['product_id']) === $product_id_for_review) {
                            $has_purchased = true;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$has_purchased) {
            header("Location: product-detail.php?id=$product_id_for_review&review=purchase_required#product-tab-reviews");
            exit;
        }

        // Enforce one review per user per product
        $exists_rs = mysqli_query($db_connection, "SELECT 1 FROM reviews WHERE product_id = '$product_id_for_review' AND user_id = '$user_id' LIMIT 1");
        if ($exists_rs && mysqli_num_rows($exists_rs) > 0) {
            header("Location: product-detail.php?id=$product_id_for_review&review=exists#product-tab-reviews");
            exit;
        }

        $insert_review = "INSERT INTO reviews (rating, product_id, user_id, description, user_name, user_email, created_date) 
                         VALUES ('$rating', '$product_id_for_review', '$user_id', '$review_text', '$author_name', '$author_email', NOW())";

        if (mysqli_query($db_connection, $insert_review)) {
            // Redirect to prevent form resubmission and scroll to reviews
            header("Location: product-detail.php?id=$product_id_for_review&review=success#product-tab-reviews");
            exit;
        }
    }
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 1; // Default to product ID 1 for testing

// Initialize variables
$product = null;
$category_name = '';
$images = [];
$video = '';
$color_variants = [];
$size_variants = [];
$discount_percentage = 0;
$base_image_url = $vendor_baseurl;
$default_image = $vendor_baseurl . 'uploads/vendors/no-product.png';

if ($product_id > 0) {
    // Fetch product data with category information
    $product_query = "SELECT p.*, c.name as category_name 
                     FROM products p 
                     LEFT JOIN category c ON p.category_id = c.id 
                     WHERE p.status='approved' AND p.id = '$product_id' LIMIT 1";

    $product_result = mysqli_query($db_connection, $product_query);

    if ($product_result && mysqli_num_rows($product_result) > 0) {
        $product = mysqli_fetch_assoc($product_result);
        $category_name = $product['category_name'] ?: 'Uncategorized';

        // Parse images JSON
        if (!empty($product['images'])) {
            $images_data = json_decode($product['images'], true);
            if (is_array($images_data)) {
                foreach ($images_data as $image_name) {
                    $images[] = $image_name;
                }
            }
        }

        // Set default image if no images found
        if (empty($images)) {
            $images[] = $default_image;
        }

        // Handle video
        if (!empty($product['video'])) {
            $video = $base_image_url . $product['video'];
        }

        // Build variant price map and attribute→variants index map from specifications
        $variant_price_by_id = [];
        $attribute_variants_indexed = [];
        if (!empty($product['specifications'])) {
            $specs_for_prices = json_decode($product['specifications'], true);
            if (is_array($specs_for_prices)) {
                foreach ($specs_for_prices as $spec_block) {
                    $attId = isset($spec_block['attribute_id']) ? intval($spec_block['attribute_id']) : null;
                    if ($attId && isset($spec_block['variants']) && is_array($spec_block['variants'])) {
                        $attribute_variants_indexed[$attId] = $spec_block['variants']; // keep order
                        foreach ($spec_block['variants'] as $variant_entry) {
                            if (isset($variant_entry['id'])) {
                                $vid = intval($variant_entry['id']);
                                $mrp_val = isset($variant_entry['mrp']) ? floatval($variant_entry['mrp']) : null;
                                $selling_val = isset($variant_entry['selling_price']) ? floatval($variant_entry['selling_price']) : null;
                                if ($mrp_val !== null || $selling_val !== null) {
                                    $variant_price_by_id[$vid] = [
                                        'mrp' => $mrp_val,
                                        'selling_price' => $selling_val
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // Get all attributes dynamically from attributes table
        $all_variants = [];
        if (!empty($product['specifications'])) {
            $specs = json_decode($product['specifications'], true);
            if (is_array($specs)) {
                foreach ($specs as $spec) {
                    if (isset($spec['attribute_id'])) {
                        $attribute_id = intval($spec['attribute_id']);

                        // Get attribute details
                        $attr_query = "SELECT * FROM attributes WHERE id = '$attribute_id' LIMIT 1";
                        $attr_result = mysqli_query($db_connection, $attr_query);

                        if ($attr_result && mysqli_num_rows($attr_result) > 0) {
                            $attr_data = mysqli_fetch_assoc($attr_result);

                            // Parse attribute values from the single table
                            $attr_values = json_decode($attr_data['attribute_values'], true);

                            if (is_array($attr_values)) {
                                $attribute_variants = [];
                                $idx = 0;
                                $totalSpecVariants = count($attribute_variants_indexed[$attribute_id] ?? []);

                                foreach ($attr_values as $value) {
                                    if ($totalSpecVariants > 0 && $idx >= $totalSpecVariants) {
                                        break;
                                    }
                                    if (isset($value['value_name'])) {
                                        // Try to map variant id from specifications by attribute_id and index
                                        $mapped_variant_id = null;
                                        $mapped_mrp = null;
                                        $mapped_selling = null;
                                        if (!empty($attribute_variants_indexed[$attribute_id])) {
                                            $specVariant = $attribute_variants_indexed[$attribute_id][$idx] ?? null;
                                            if ($specVariant && isset($specVariant['id'])) {
                                                $mapped_variant_id = intval($specVariant['id']);
                                                if (isset($specVariant['mrp']))
                                                    $mapped_mrp = floatval($specVariant['mrp']);
                                                if (isset($specVariant['selling_price']))
                                                    $mapped_selling = floatval($specVariant['selling_price']);
                                            }
                                        }
                                        // Fallback to id inside value (if stored)
                                        if ($mapped_variant_id === null && isset($value['id'])) {
                                            $mapped_variant_id = intval($value['id']);
                                        }

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

                                if (!empty($attribute_variants)) {
                                    $all_variants[] = [
                                        'attribute_id' => $attribute_id,
                                        'attribute_name' => $attr_data['name'],
                                        'variants' => $attribute_variants
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // Separate color and size variants for backward compatibility
        $color_variants = [];
        $size_variants = [];
        foreach ($all_variants as $attr_group) {
            if (stripos($attr_group['attribute_name'], 'color') !== false) {
                foreach ($attr_group['variants'] as $variant) {
                    $color_variants[] = [
                        'name' => $variant['name'],
                        'code' => $variant['name'],
                        'variant_id' => $variant['variant_id'],
                        'mrp' => $variant['mrp'],
                        'selling_price' => $variant['selling_price']
                    ];
                }
            } elseif (stripos($attr_group['attribute_name'], 'size') !== false) {
                foreach ($attr_group['variants'] as $variant) {
                    $size_variants[] = [
                        'label' => $variant['name'],
                        'variant_id' => $variant['variant_id'],
                        'mrp' => $variant['mrp'],
                        'selling_price' => $variant['selling_price']
                    ];
                }
            }
        }

        if ($product['mrp'] > 0 && $product['selling_price'] < $product['mrp']) {
            $discount_percentage = round((($product['mrp'] - $product['selling_price']) / $product['mrp']) * 100);
        }

        // Get reviews for this product
        $reviews_query = "SELECT * FROM reviews WHERE product_id = '$product_id' ORDER BY created_date DESC";
        $reviews_result = mysqli_query($db_connection, $reviews_query);
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
        }

        // Calculate average rating
        $average_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
        $average_rating_percentage = $average_rating * 20; // Convert to percentage for display

        // Calculate rating distribution percentages
        $rating_percentages = [];
        foreach ($rating_counts as $star => $count) {
            $rating_percentages[$star] = $total_reviews > 0 ? round(($count / $total_reviews) * 100) : 0;
        }

        // Get all specifications for this product
        $all_specifications = [];
        if (!empty($product['specifications'])) {
            $specs = json_decode($product['specifications'], true);
            if (is_array($specs)) {
                foreach ($specs as $spec) {
                    if (isset($spec['attribute_id'])) {
                        $attribute_id = intval($spec['attribute_id']);

                        // Get attribute details
                        $attr_query = "SELECT * FROM attributes WHERE id = '$attribute_id' LIMIT 1";
                        $attr_result = mysqli_query($db_connection, $attr_query);

                        if ($attr_result && mysqli_num_rows($attr_result) > 0) {
                            $attr_data = mysqli_fetch_assoc($attr_result);

                            // Parse attribute values from the single table
                            $attr_values = json_decode($attr_data['attribute_values'], true);

                            if (is_array($attr_values)) {
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

        // Get vendor info and vendor products (same vendor_id, excluding current product)
        $vendor_products = [];
        $vendor_info = null;
        if (!empty($product['vendor_id'])) {
            $vendor_id = intval($product['vendor_id']);

            // Fetch vendor details
            $vendor_info_rs = mysqli_query($db_connection, "SELECT * FROM vendor_registration WHERE id = '$vendor_id' LIMIT 1");
            if ($vendor_info_rs && mysqli_num_rows($vendor_info_rs) > 0) {
                $vendor_info = mysqli_fetch_assoc($vendor_info_rs);

                // Resolve state name from state table if vendor stores a state ID
                if (!empty($vendor_info['state']) && ctype_digit((string) $vendor_info['state'])) {
                    $state_id = intval($vendor_info['state']);
                    $state_rs = mysqli_query($db_connection, "SELECT * FROM state WHERE id = '$state_id' LIMIT 1");
                    if ($state_rs && mysqli_num_rows($state_rs) > 0) {
                        $state_row = mysqli_fetch_assoc($state_rs);
                        // Try common column names for state name
                        $resolved_state_name = '';
                        foreach (['name', 'state_name', 'state'] as $col) {
                            if (!empty($state_row[$col])) {
                                $resolved_state_name = $state_row[$col];
                                break;
                            }
                        }
                        if (!empty($resolved_state_name)) {
                            // Attach a derived field to vendor_info for rendering convenience
                            $vendor_info['state_name_resolved'] = $resolved_state_name;
                        }
                    }
                }
            }
            $vendor_query = "SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN category c ON p.category_id = c.id 
                            WHERE p.status='approved' AND p.vendor_id = '$vendor_id' 
                            AND p.id != '$product_id' 
                            ORDER BY p.id DESC 
                            LIMIT 5";

            $vendor_result = mysqli_query($db_connection, $vendor_query);

            if ($vendor_result) {
                while ($vendor_product = mysqli_fetch_assoc($vendor_result)) {
                    // Parse images for each vendor product
                    $vendor_images = [];
                    if (!empty($vendor_product['images'])) {
                        $images_data = json_decode($vendor_product['images'], true);
                        if (is_array($images_data)) {
                            foreach ($images_data as $image_name) {
                                $vendor_images[] = $image_name;
                            }
                        }
                    }

                    // Set default image if no images found
                    if (empty($vendor_images)) {
                        $vendor_images[] = $default_image;
                    }

                    // Calculate discount percentage for vendor product
                    $vendor_discount = 0;
                    if ($vendor_product['mrp'] > 0 && $vendor_product['selling_price'] < $vendor_product['mrp']) {
                        $vendor_discount = round((($vendor_product['mrp'] - $vendor_product['selling_price']) / $vendor_product['mrp']) * 100);
                    }

                    // Get reviews count and average rating for vendor product
                    $vendor_reviews_query = "SELECT COUNT(*) as review_count, AVG(CAST(rating AS DECIMAL(3,2))) as avg_rating 
                                            FROM reviews WHERE product_id = '" . $vendor_product['id'] . "'";
                    $vendor_reviews_result = mysqli_query($db_connection, $vendor_reviews_query);
                    $vendor_review_data = mysqli_fetch_assoc($vendor_reviews_result);

                    $vendor_product['images_array'] = $vendor_images;
                    $vendor_product['discount_percentage'] = $vendor_discount;
                    $vendor_product['review_count'] = intval($vendor_review_data['review_count']);
                    $vendor_product['avg_rating'] = floatval($vendor_review_data['avg_rating']);
                    $vendor_product['avg_rating_percentage'] = $vendor_product['avg_rating'] * 20;

                    $vendor_products[] = $vendor_product;
                }
            }
        }

        // Get category products (same category_id, excluding current product and vendor products)
        $category_products = [];
        if (!empty($product['category_id'])) {
            $category_id = intval($product['category_id']);

            // Get IDs of vendor products to exclude them
            $exclude_ids = [$product_id];
            foreach ($vendor_products as $vp) {
                $exclude_ids[] = $vp['id'];
            }
            $exclude_ids_str = implode(',', $exclude_ids);

            $category_query = "SELECT p.*, c.name as category_name 
                             FROM products p 
                             LEFT JOIN category c ON p.category_id = c.id 
                             WHERE p.status='approved' AND p.category_id = '$category_id' 
                              AND p.id NOT IN ($exclude_ids_str) 
                              ORDER BY p.id DESC 
                              LIMIT 8";

            $category_result = mysqli_query($db_connection, $category_query);

            if ($category_result) {
                while ($category_product = mysqli_fetch_assoc($category_result)) {
                    // Parse images for each category product
                    $category_images = [];
                    if (!empty($category_product['images'])) {
                        $images_data = json_decode($category_product['images'], true);
                        if (is_array($images_data)) {
                            foreach ($images_data as $image_name) {
                                $category_images[] =   $image_name;
                            }
                        }
                    }

                    // Set default image if no images found
                    if (empty($category_images)) {
                        $category_images[] = $default_image;
                    }

                    // Calculate discount percentage for category product
                    $category_discount = 0;
                    if ($category_product['mrp'] > 0 && $category_product['selling_price'] < $category_product['mrp']) {
                        $category_discount = round((($category_product['mrp'] - $category_product['selling_price']) / $category_product['mrp']) * 100);
                    }

                    // Get reviews count and average rating for category product
                    $category_reviews_query = "SELECT COUNT(*) as review_count, AVG(CAST(rating AS DECIMAL(3,2))) as avg_rating 
                                              FROM reviews WHERE product_id = '" . $category_product['id'] . "'";
                    $category_reviews_result = mysqli_query($db_connection, $category_reviews_query);
                    $category_review_data = mysqli_fetch_assoc($category_reviews_result);

                    $category_product['images_array'] = $category_images;
                    $category_product['discount_percentage'] = $category_discount;
                    $category_product['review_count'] = intval($category_review_data['review_count']);
                    $category_product['avg_rating'] = floatval($category_review_data['avg_rating']);
                    $category_product['avg_rating_percentage'] = $category_product['avg_rating'] * 20;

                    $category_products[] = $category_product;
                }
            }
        }

        // Store in recent views
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            // Logged-in user: Store in recent_views table
            $user_id = intval($_SESSION['user_id']);

            // Check if the product_id already exists for this user
            $check_query = "SELECT COUNT(*) as count FROM recent_views WHERE user_id = '$user_id' AND product_id = '$product_id'";
            $check_result = mysqli_query($db_connection, $check_query);
            $row = mysqli_fetch_assoc($check_result);
            $exists = $row['count'] > 0;

            if ($exists) {
                // Update existing entry's created_date
                $update_query = "UPDATE recent_views SET created_date = NOW() WHERE user_id = '$user_id' AND product_id = '$product_id'";
                mysqli_query($db_connection, $update_query);
            } else {
                // Check if user has 5 or more records
                $count_query = "SELECT COUNT(*) as count FROM recent_views WHERE user_id = '$user_id'";
                $count_result = mysqli_query($db_connection, $count_query);
                $count_row = mysqli_fetch_assoc($count_result);
                $record_count = $count_row['count'];

                if ($record_count >= 4) {
                    // Delete the oldest record for this user
                    $delete_query = "DELETE FROM recent_views WHERE user_id = '$user_id' ORDER BY created_date ASC LIMIT 1";
                    mysqli_query($db_connection, $delete_query);
                }

                // Insert new record
                $insert_query = "INSERT INTO recent_views (user_id, product_id, created_date) VALUES ('$user_id', '$product_id', NOW())";
                mysqli_query($db_connection, $insert_query);
            }
        } else {
            // Non-logged-in user: Store in localStorage via JavaScript
?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const productId = <?php echo json_encode($product_id); ?>;
                    if (productId > 0) {
                        // Get existing recent views from localStorage
                        let recentViews = JSON.parse(localStorage.getItem('recentViews') || '[]');

                        // Remove existing entry to update order or avoid duplicates
                        recentViews = recentViews.filter(id => id !== productId);

                        // Add new product ID at the start
                        recentViews.unshift(productId);

                        // If more than 5 items, remove the oldest (last in array)
                        if (recentViews.length > 4) {
                            recentViews = recentViews.slice(0, 4);
                        }

                        // Store updated array in localStorage
                        try {
                            localStorage.setItem('recentViews', JSON.stringify(recentViews));
                        } catch (e) {
                            console.error('Error saving to localStorage:', e);
                        }
                    }
                });
            </script>
        <?php
        }

        // Add JavaScript for review success handling
        if (isset($_GET['review']) && $_GET['review'] == 'success') {
        ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Wait for page to fully load before scrolling
                    setTimeout(function() {
                        // Find the tab navigation area
                        const tabsContainer = document.querySelector('.nav-tabs');
                        const reviewsTab = document.querySelector('a[href="#product-tab-reviews"]');

                        if (tabsContainer && reviewsTab) {
                            // Scroll to the tabs area first
                            tabsContainer.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });

                            // Then scroll a bit more to show the reviews content
                            setTimeout(function() {
                                const reviewsPane = document.querySelector('#product-tab-reviews');
                                if (reviewsPane) {
                                    reviewsPane.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start'
                                    });
                                }
                            }, 500);
                        }

                        // Remove the success parameter from URL after handling
                        if (window.history && window.history.replaceState) {
                            const url = new URL(window.location);
                            url.searchParams.delete('review');
                            window.history.replaceState({}, document.title, url.toString());
                        }
                    }, 500);
                });
            </script>
<?php
        }
    } else {
        // Product not found, redirect to shop or show error
        header('Location: shop.php');
        exit;
    }
}

// If no product found, redirect
if (!$product) {
    header('Location: shop.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title><?php echo e($product['product_name']); ?> | <?php echo e($category_name); ?> | HAGIDY</title>


    <meta name="keywords" content="" />
    <meta name="description" content="">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">


    <!-- WebFont.js -->
    <script>
        WebFontConfig = {
            google: {
                families: ['Poppins:400,500,600,700']
            }
        };
        (function(d) {
            var wf = d.createElement('script'),
                s = d.scripts[0];
            wf.src = '<?php echo PUBLIC_ASSETS; ?>js/webfont.js';
            wf.async = true;
            s.parentNode.insertBefore(wf, s);
        })(document);
    </script>


    <link rel="preload" href="./assetsvendor/fontawesome-free/webfonts/fa-regular-400.woff" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-solid-900.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-brands-400.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>fonts/wolmart.ttf?png09e" as="font" type="font/ttf" crossorigin="anonymous">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/animate/animate.min.css">

    <!-- Plugin CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/default-skin/default-skin.min.css">

    <!-- Default CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/product-detail.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <script>
        // Expose variant-specific prices to JS (if any)
        window.productVariantPrices = <?php echo json_encode($variant_price_by_id ?: new stdClass()); ?>;
        window.productBasePrice = {
            mrp: <?php echo isset($product['mrp']) ? floatval($product['mrp']) : 0; ?>,
            selling_price: <?php echo isset($product['selling_price']) ? floatval($product['selling_price']) : 0; ?>
        };
    </script>

</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>


        <!-- Start of Main -->
        <main class="main mb-10 pb-1">
            <!-- Start of Breadcrumb -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li><a
                                    href="./shop.php?category=<?php echo $product['category_id']; ?>"><?php echo e($category_name); ?></a>
                            </li>
                            <li><?php echo e($product['product_name']); ?></li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->

            <!-- Start of Page Content -->
            <div class="page-content">
                <div class="container">
                    <div class="row">
                        <div class="main-content">
                            <div class="product product-single row">
                                <div class="col-md-6 mb-6">
                                    <div class="product-gallery product-gallery-sticky product-gallery-vertical">
                                        <div class="swiper-container product-single-swiper swiper-theme nav-inner"
                                            data-swiper-options="{
                                            'navigation': {
                                                'nextEl': '.swiper-button-next',
                                                'prevEl': '.swiper-button-prev'
                                            }
                                        }">
                                            <div class="swiper-wrapper row cols-1 gutter-no">
                                                <?php foreach ($images as $index => $image): ?>
                                                    <div class="swiper-slide">
                                                        <figure class="product-image">
                                                            <img src="<?php echo e($image); ?>"
                                                                data-zoom-image="<?php echo e($image); ?>"
                                                                alt="<?php echo e($product['product_name']); ?>" width="800"
                                                                height="900">
                                                        </figure>
                                                    </div>
                                                <?php endforeach; ?>

                                                <?php if (!empty($video)): ?>
                                                    <div class="swiper-slide">
                                                        <figure class="product-image product-video-container">
                                                            <video width="800" height="900" controls class="product-video">
                                                                <source src="<?php echo e($video); ?>" type="video/mp4">
                                                                Your browser does not support the video tag.
                                                            </video>
                                                        </figure>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="swiper-button-next"></button>
                                            <button class="swiper-button-prev"></button>
                                            <a href="#" class="product-gallery-btn product-image-full"><i
                                                    class="w-icon-zoom"></i></a>
                                        </div>
                                        <div class="product-thumbs-wrap swiper-container" data-swiper-options="{
                                            'navigation': {
                                                'nextEl': '.swiper-button-next',
                                                'prevEl': '.swiper-button-prev'
                                            },
                                            'breakpoints': {
                                                '992': {
                                                    'direction': 'vertical',
                                                    'slidesPerView': 'auto'
                                                }
                                            }
                                        }">
                                            <div class="product-thumbs swiper-wrapper row cols-lg-1 cols-4 gutter-sm">
                                                <?php foreach ($images as $index => $image): ?>
                                                    <div class="product-thumb swiper-slide">
                                                        <img src="<?php echo  e($image); ?>"
                                                            alt="Product Thumb" width="800" height="900">
                                                    </div>
                                                <?php endforeach; ?>

                                                <?php if (!empty($video)): ?>
                                                    <div class="product-thumb swiper-slide product-thumb-video">
                                                        <video width="100" height="50" class="product-thumb-video-element">
                                                            <source src="<?php echo e($video); ?>" type="video/mp4">
                                                        </video>
                                                        <div class="video-play-icon">
                                                            <i class="fas fa-play"></i>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button class="swiper-button-prev"></button>
                                            <button class="swiper-button-next"></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 sticky-sidebar-wrapper mb-4 mb-md-6">
                                    <div class="product-details" data-sticky-options="{'minWidth': 767}">
                                        <h1 class="product-title"><?php echo e($product['product_name']); ?></h1>


                                        <hr class="product-divider">

                                        <div class="product-price d-flex align-items-center"><ins
                                                class="new-price mr-2"> <sup style="font-size: 18px;">₹</sup>
                                                <?php echo number_format($product['selling_price'], 2); ?></ins>
                                            <span class="d-block product-price1"> Earn <img
                                                    src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid" alt="">
                                                <?php echo $product['coin']; ?>
                                            </span>
                                        </div>
                                        <div class="mrp-price">
                                            <h4>
                                                M.R.P: <del
                                                    class="old-price">₹<?php echo number_format($product['mrp'], 2); ?></del>
                                                <?php if ($discount_percentage > 0): ?>
                                                    <span>(<?php echo $discount_percentage; ?>% off)</span>
                                                <?php endif; ?>
                                            </h4>
                                        </div>
                                        <div class="ratings-container">
                                            <div class="ratings-full">
                                                <span class="ratings"
                                                    style="width: <?php echo $average_rating_percentage; ?>%;"></span>
                                                <span class="tooltiptext tooltip-top"><?php echo $average_rating; ?>
                                                    Stars</span>
                                            </div>
                                            <a href="#product-tab-reviews"
                                                class="rating-reviews scroll-to">(<?php echo $total_reviews; ?>
                                                Reviews)</a>
                                        </div>


                                        <div class="product-short-desc">
                                            <?php echo nl2br(e($product['description'])); ?>
                                        </div>

                                        <hr class="product-divider">

                                        <?php if (!empty($all_variants)): ?>
                                            <?php
                                            // Order: multi-variant groups first, then single-variant groups
                                            $multiVariantGroups = [];
                                            $singleVariantGroups = [];
                                            foreach ($all_variants as $g) {
                                                $cnt = isset($g['variants']) && is_array($g['variants']) ? count($g['variants']) : 0;
                                                if ($cnt > 1) $multiVariantGroups[] = $g; else $singleVariantGroups[] = $g;
                                            }
                                            $orderedGroups = array_merge($multiVariantGroups, $singleVariantGroups);
                                            foreach ($orderedGroups as $attr_group): ?>
                                                <?php
                                                $is_color = stripos($attr_group['attribute_name'], 'color') !== false;
                                                $is_size = stripos($attr_group['attribute_name'], 'size') !== false;
                                                $swatch_class = $is_color ? 'product-color-swatch' : ($is_size ? 'product-size-swatch' : 'product-variant-swatch');
                                                ?>
                                                <div class="product-form product-variation-form <?php echo $swatch_class; ?>">
                                                    <label><?php echo e($attr_group['attribute_name']); ?>:</label>
                                                    <?php $count_variants = isset($attr_group['variants']) && is_array($attr_group['variants']) ? count($attr_group['variants']) : 0; ?>
                                                    <?php if ($count_variants > 1): ?>
                                                        <div class="d-flex align-items-center product-variations">
                                                            <?php foreach ($attr_group['variants'] as $variant): ?>
                                                                <?php if ($is_color): ?>
                                                                    <?php
                                                                    // Enhanced color detection and display
                                                                    $colorName = $variant['name'];
                                                                    $swatchColor = '#ccc'; // Default fallback
                                                                    if (!empty($variant['color_code'])) {
                                                                        $swatchColor = $variant['color_code'];
                                                                    } elseif (strpos(trim($colorName), '#') === 0) {
                                                                        $swatchColor = trim($colorName);
                                                                    } else {
                                                                        $colorMap = [
                                                                            'black' => '#000000','white' => '#ffffff','red' => '#ff0000','green' => '#00ff00','blue' => '#0000ff','yellow' => '#ffff00','orange' => '#ffa500','purple' => '#800080','pink' => '#ffc0cb','brown' => '#a52a2a','gray' => '#808080','grey' => '#808080','silver' => '#c0c0c0','gold' => '#ffd700','navy' => '#000080','maroon' => '#800000','lime' => '#00ff00','cyan' => '#00ffff','magenta' => '#ff00ff','olive' => '#808000','teal' => '#008080','indigo' => '#4b0082','violet' => '#8a2be2','coral' => '#ff7f50','salmon' => '#fa8072','turquoise' => '#40e0d0','beige' => '#f5f5dc','ivory' => '#fffff0','khaki' => '#f0e68c','lavender' => '#e6e6fa','mint' => '#f5fffa','peach' => '#ffcba4','rose' => '#ff69b4','tan' => '#d2b48c','wheat' => '#f5deb3'
                                                                        ];
                                                                        $lowerColorName = strtolower(trim($colorName));
                                                                        if (isset($colorMap[$lowerColorName])) { $swatchColor = $colorMap[$lowerColorName]; }
                                                                    }
                                                                    $colorDisplayName = $colorName;
                                                                    ?>
                                                                    <a href="#" class="color" style="background-color: <?php echo e($variant['name']); ?>;" title="<?php echo e($colorDisplayName); ?>" data-color="<?php echo e($variant['name']); ?>" <?php if (!empty($variant['variant_id'])): ?>data-variant-id="<?php echo intval($variant['variant_id']); ?>"<?php endif; ?> <?php if (isset($variant['mrp'])): ?>data-mrp="<?php echo htmlspecialchars($variant['mrp'], ENT_QUOTES); ?>"<?php endif; ?> <?php if (isset($variant['selling_price'])): ?>data-selling="<?php echo htmlspecialchars($variant['selling_price'], ENT_QUOTES); ?>"<?php endif; ?>></a>
                                                                <?php elseif ($is_size): ?>
                                                                    <a href="#" class="size" data-size="<?php echo e($variant['name']); ?>" <?php if (!empty($variant['variant_id'])): ?>data-variant-id="<?php echo intval($variant['variant_id']); ?>"<?php endif; ?> <?php if (isset($variant['mrp'])): ?>data-mrp="<?php echo htmlspecialchars($variant['mrp'], ENT_QUOTES); ?>"<?php endif; ?> <?php if (isset($variant['selling_price'])): ?>data-selling="<?php echo htmlspecialchars($variant['selling_price'], ENT_QUOTES); ?>"<?php endif; ?>><?php echo e($variant['name']); ?></a>
                                                                <?php else: ?>
                                                                    <a href="#" class="variant-option" data-attribute="<?php echo e($attr_group['attribute_name']); ?>" data-value="<?php echo e($variant['name']); ?>" <?php if (!empty($variant['variant_id'])): ?>data-variant-id="<?php echo intval($variant['variant_id']); ?>"<?php endif; ?> <?php if (isset($variant['mrp'])): ?>data-mrp="<?php echo htmlspecialchars($variant['mrp'], ENT_QUOTES); ?>"<?php endif; ?> <?php if (isset($variant['selling_price'])): ?>data-selling="<?php echo htmlspecialchars($variant['selling_price'], ENT_QUOTES); ?>"<?php endif; ?>><?php echo e($variant['name']); ?></a>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center product-variations">
                                                            <?php
                                                            $only = $count_variants === 1 ? $attr_group['variants'][0] : null;
                                                            $label = $only ? $only['name'] : '';
                                                            ?>
                                                            <span class="text-muted"><?php echo e($label); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>


                                        <div class=" product-sticky-content sticky-content">
                                            <div class="product-form container">
                                                <div class="product-qty-form">
                                                    <div class="cart-position1">
                                                        <div class="cart-position">
                                                            <button class="quantity-plus w-icon-plus  button2"></button>
                                                            <input class="quantity form-control text-center "
                                                                type="number" min="1" max="100000">
                                                            <button
                                                                class="quantity-minus w-icon-minus button1"></button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <button
                                                    class="btn <?php echo (isset($product['Inventory']) && $product['Inventory'] > 0) ? 'btn-primary' : 'btn-secondary'; ?> btn-cart"
                                                    data-product-id="<?php echo $product['id']; ?>" <?php echo (isset($product['Inventory']) && $product['Inventory'] <= 0) ? 'disabled' : ''; ?>>
                                                    <i class="w-icon-cart"></i>
                                                    <span><?php echo (isset($product['Inventory']) && $product['Inventory'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class=" product-sticky-content ">
                                            <div class="product-form">
                                                <button class="btn btn-primary btn-buy mb-1">
                                                    <span>Buy Now</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="product-bm-wrapper mt-1">
                                            <div class="product-meta">
                                                <div class="product-categories">
                                                    Category:
                                                    <span class="product-category"><a
                                                            href="shop.php?category=<?php echo $product['category_id']; ?>"><?php echo e($category_name); ?></a></span>
                                                </div>
                                                <div class="product-sku">
                                                    SKU: <span><?php echo e($product['sku_id']); ?></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="social-links-wrapper">
                                            <div class="social-links">
                                                <div class="social-icons social-no-color border-thin">
                                                    <a href="#" class="social-icon social-facebook w-icon-facebook"></a>
                                                    <a href="#" class="social-icon social-twitter w-icon-twitter"></a>
                                                    <a href="#"
                                                        class="social-icon social-pinterest fab fa-pinterest-p"></a>
                                                    <a href="#" class="social-icon social-whatsapp fab fa-whatsapp"></a>
                                                    <a href="#"
                                                        class="social-icon social-youtube fab fa-linkedin-in"></a>
                                                </div>
                                            </div>
                                            <span class="divider d-xs-show"></span>
                                            <div class="product-link-wrapper d-flex">
                                                <a href="#" class="btn-product-icon btn-wishlist w-icon-heart"
                                                    data-product-id="<?php echo $product['id']; ?>"><span></span></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="tab tab-nav-boxed tab-nav-underline product-tabs">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a href="#product-tab-description"
                                            class="nav-link <?php echo (isset($_GET['review']) && $_GET['review'] == 'success') ? '' : 'active'; ?>">Description</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="#product-tab-specification" class="nav-link">Specification</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="#product-tab-reviews"
                                            class="nav-link <?php echo (isset($_GET['review']) && $_GET['review'] == 'success') ? 'active' : ''; ?>">Customer
                                            Reviews (<?php echo $total_reviews; ?>)</a>
                                    </li>
                                    <li class="nav-item">
                                        <a href="#product-tab-vendor" class="nav-link">Vendor Info</a>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane <?php echo (isset($_GET['review']) && $_GET['review'] == 'success') ? '' : 'active'; ?>"
                                        id="product-tab-description">
                                        <div class="row mb-4">
                                            <div class="col-md-12 mb-5">
                                                <div class="product-description">
                                                    <?php echo nl2br(e($product['description'])); ?>
                                                </div>

                                                <?php if (!empty($product['manufacture_details'])): ?>
                                                    <div class="mt-4">
                                                        <h5 class="font-weight-bold">Manufacturing Details:</h5>
                                                        <p><?php echo nl2br(e($product['manufacture_details'])); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($product['packaging_details'])): ?>
                                                    <div class="mt-4">
                                                        <h5 class="font-weight-bold">Packaging Details:</h5>
                                                        <p><?php echo nl2br(e($product['packaging_details'])); ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="row cols-md-3">
                                            <div class="mb-3">
                                                <h5 class="sub-title font-weight-bold"><span class="mr-3">1.</span>Free
                                                    Shipping &amp; Return</h5>
                                                <p class="detail pl-5">We offer free shipping for products on orders
                                                    above 500₹ and offer free delivery for all orders in India.</p>
                                            </div>
                                            <!--<div class="mb-3">
                                                <h5 class="sub-title font-weight-bold"><span>2.</span>Free and Easy
                                                    Returns</h5>
                                                <p class="detail pl-5">We guarantee our products and you could get back
                                                    all of your money anytime you want in 30 days.</p>
                                            </div>-->
                                            <div class="mb-3">
                                                <h5 class="sub-title font-weight-bold"><span>2.</span>Special Financing
                                                </h5>
                                                <p class="detail pl-5">Get 20%-50% off items over 50₹ for a month or
                                                    over 250₹ for a year with our special credit card.</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane" id="product-tab-specification">
                                        <ul class="list-none">
                                            <?php if (!empty($all_specifications)): ?>
                                                <?php foreach ($all_specifications as $spec): ?>
                                                    <li>
                                                        <label><?php echo e($spec['name']); ?></label>
                                                        <p><?php echo e($spec['values']); ?></p>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li>
                                                    <label>No specifications available</label>
                                                    <p>Specifications will be updated soon.</p>
                                                </li>
                                            <?php endif; ?>

                                        </ul>
                                    </div>
                                    <div class="tab-pane" id="product-tab-vendor">
                                        <div class="row mb-3">
                                            <div class="col-md-6 mb-4">
                                                <figure class="vendor-banner br-sm">
                                                    <?php
                                                    $default_vendor_banner = PUBLIC_ASSETS . 'images/vendor-banner.jpg';
                                                    $vendor_banner = $default_vendor_banner;
                                                    if (!empty($vendor_info) && !empty($vendor_info['banner_image'])) {
                                                        $vendor_banner = $vendor_baseurl . $vendor_info['banner_image'];
                                                    }
                                                    ?>
                                                    <img src="<?php echo e($vendor_banner); ?>" alt="Vendor Banner"
                                                        width="610" height="295" style="background-color: #353B55;" />
                                                </figure>
                                            </div>
                                            <div class="col-md-6 pl-2 pl-md-6 mb-4">
                                                <div class="vendor-user">
                                                    <figure class="vendor-logo mr-4">

                                                        <?php
                                                        // Attempt to use vendor logo if available; otherwise show placeholder
                                                        $vendor_logo = PUBLIC_ASSETS . 'images/products/vendor-logo.jpg';
                                                        if (!empty($vendor_info) && !empty($vendor_info['logo'])) {
                                                            $vendor_logo = $vendor_baseurl . $vendor_info['logo'];
                                                        } else { ?>

                                                            <span class="vendor-logo-custom">
                                                                <?php
                                                                $name = $vendor_info['business_name'];
                                                                $words = explode(' ', trim($name));
                                                                $initials = '';
                                                                foreach ($words as $word) {
                                                                    if (!empty($word)) {
                                                                        $initials .= strtoupper(substr($word, 0, 1));
                                                                    }
                                                                }
                                                                echo substr($initials, 0, 2);
                                                                ?>
                                                            </span>
                                                        <?php } ?>

                                                    </figure>
                                                    <div>
                                                        <div class="vendor-name">
                                                            <a
                                                                href="#"><?php echo e(!empty($vendor_info['business_name']) ? $vendor_info['business_name'] : 'Vendor'); ?></a>
                                                        </div>

                                                        <?php
                                                        $get_reviews = mysqli_query($con, "SELECT * FROM reviews WHERE product_id = '$product_id'");
                                                        $total_reviews = mysqli_num_rows($get_reviews);
                                                        //echo $total_reviews;
                                                        $totalReviews = $total_reviews;
                                                        $avgRatingQuery = mysqli_query($con, "SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = '$product_id'");
                                                        $avgRatingData = mysqli_fetch_assoc($avgRatingQuery);
                                                        $avgRating = $avgRatingData['avg_rating'] ?? 0;

                                                        // Get rating distribution - count reviews by rounded rating
                                                        $ratingDistribution = [];
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            // Count reviews where the rating rounds to this star level
                                                            $countQuery = mysqli_query($con, "SELECT COUNT(*) as count FROM reviews WHERE product_id = '$product_id' AND ROUND(rating) = '$i'");
                                                            $countData = mysqli_fetch_assoc($countQuery);
                                                            $ratingDistribution[$i] = $countData['count'] ?? 0;
                                                        }

                                                        ?>

                                                        <div class="star-icon p-0">
                                                            <?php
                                                            for ($i = 1; $i <= 5; $i++) {
                                                                if ($i <= $avgRating) {
                                                                    echo '<i class="fa-solid fa-star star-done"></i>';
                                                                } else {
                                                                    echo '<i class="fa-solid fa-star"></i>';
                                                                }
                                                            }
                                                            ?>
                                                            <span>(<?php echo $total_reviews; ?> Reviews)</span>
                                                        </div>


                                                    </div>
                                                </div>
                                                <ul class="vendor-info list-style-none">
                                                    <li class="store-name">
                                                        <label>Store Name:</label>
                                                        <span
                                                            class="detail"><?php echo e(!empty($vendor_info['business_name']) ? $vendor_info['business_name'] : 'N/A'); ?></span>
                                                    </li>
                                                    <li class="store-address">
                                                        <label>Address:</label>
                                                        <span class="detail">
                                                            <?php
                                                            $addr_parts = [];
                                                            if (!empty($vendor_info['business_address']))
                                                                $addr_parts[] = $vendor_info['business_address'];
                                                            if (!empty($vendor_info['city']))
                                                                $addr_parts[] = $vendor_info['city'];
                                                            // Prefer resolved state name if available; otherwise fall back to state field
                                                            if (!empty($vendor_info['state_name_resolved'])) {
                                                                $addr_parts[] = $vendor_info['state_name_resolved'];
                                                            } elseif (!empty($vendor_info['state'])) {
                                                                $addr_parts[] = $vendor_info['state'];
                                                            }
                                                            if (!empty($vendor_info['pincode']))
                                                                $addr_parts[] = $vendor_info['pincode'];
                                                            $full_addr = trim(implode(', ', $addr_parts));
                                                            echo e(!empty($full_addr) ? $full_addr : 'Address not available');
                                                            ?>
                                                        </span>
                                                    </li>
                                                    <li class="store-phone">
                                                        <label>Phone:</label>
                                                        <?php
                                                        $phone = '';
                                                        if (!empty($vendor_info['mobile_number']))
                                                            $phone = $vendor_info['mobile_number'];
                                                        elseif (!empty($vendor_info['mobile']))
                                                            $phone = $vendor_info['mobile'];
                                                        ?>
                                                        <a href="<?php echo !empty($phone) ? 'tel:' . e($phone) : '#'; ?>"
                                                            class="text-primary"><?php echo e(!empty($phone) ? $phone : 'N/A'); ?></a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="tab-pane <?php echo (isset($_GET['review']) && $_GET['review'] == 'success') ? 'active' : ''; ?>"
                                        id="product-tab-reviews">
                                        <div class="row mb-4">
                                            <div class="col-xl-4 col-lg-5 mb-4">
                                                <div class="ratings-wrapper">
                                                    <div class="avg-rating-container mb-3">
                                                        <h4 class="avg-mark font-weight-bolder ls-50">
                                                            <?php echo $average_rating; ?>
                                                        </h4>
                                                        <div class="avg-rating">
                                                            <p class="text-dark mb-1">Average Rating</p>
                                                            <div class="ratings-container">
                                                                <div class="ratings-full">
                                                                    <span class="ratings"
                                                                        style="width: <?php echo $average_rating_percentage; ?>%;"></span>
                                                                    <span class="tooltiptext tooltip-top"></span>
                                                                </div>
                                                                <a href="#"
                                                                    class="rating-reviews">(<?php echo $total_reviews; ?>
                                                                    Reviews)</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="ratings-list">
                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                            <div class="ratings-container">
                                                                <div class="ratings-full">
                                                                    <span class="ratings"
                                                                        style="width: <?php echo $i * 20; ?>%;"></span>
                                                                    <span class="tooltiptext tooltip-top"><?php echo $i; ?>
                                                                        Stars</span>
                                                                </div>
                                                                <div class="progress-bar progress-bar-sm ">
                                                                    <span
                                                                        style="width: <?php echo $rating_percentages[$i]; ?>%;"></span>
                                                                </div>
                                                                <div class="progress-value">
                                                                    <mark><?php echo $rating_percentages[$i]; ?>%</mark>
                                                                </div>
                                                            </div>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-xl-8 col-lg-7 mb-4">

                                                <?php
                                                // Determine review permission and state
                                                $can_review = false;
                                                $already_reviewed = false;
                                                $login_required = false;
                                                $purchase_required = false;
                                                if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                                                    $uid_tmp = intval($_SESSION['user_id']);
                                                    // Already reviewed?
                                                    $chk_rs = mysqli_query($db_connection, "SELECT 1 FROM reviews WHERE product_id = '" . intval($product_id) . "' AND user_id = '" . $uid_tmp . "' LIMIT 1");
                                                    if ($chk_rs && mysqli_num_rows($chk_rs) > 0) {
                                                        $already_reviewed = true;
                                                    } else {
                                                        // Has purchased?
                                                        $order_rs_tmp = mysqli_query($db_connection, "SELECT products FROM `order` WHERE user_id = '" . $uid_tmp . "'");
                                                        if ($order_rs_tmp) {
                                                            while ($rowx = mysqli_fetch_assoc($order_rs_tmp)) {
                                                                $prodsx = json_decode($rowx['products'], true);
                                                                if (is_array($prodsx)) {
                                                                    foreach ($prodsx as $opx) {
                                                                        if (isset($opx['product_id']) && intval($opx['product_id']) === intval($product_id)) {
                                                                            $can_review = true;
                                                                            break 2;
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        if (!$can_review) {
                                                            $purchase_required = true;
                                                        }
                                                    }
                                                } else {
                                                    $login_required = true;
                                                }
                                                ?>
                                                <div class="review-form-wrapper">



                                                    <?php if (isset($_GET['review']) && $_GET['review'] == 'success'): ?>
                                                        <div class="alert alert-success mb-3" id="review-success-message">
                                                            <strong>🎉 Thank you!</strong> Your review has been submitted
                                                            successfully and is now visible below.
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($can_review && !$already_reviewed): ?>
                                                        <h3 class="title tab-pane-title font-weight-bold mb-1">Submit Your
                                                            Review</h3>
                                                        <p class="mb-3">Your email address will not be published. Required
                                                            fields are marked *</p>
                                                        <form action="product-detail.php?id=<?php echo $product_id; ?>"
                                                            method="POST" class="review-form">
                                                            <div class="rating-form">
                                                                <label for="rating">Your Rating Of This Product :</label>
                                                                <span class="rating-stars">
                                                                    <a class="star-1" href="#">1</a>
                                                                    <a class="star-2" href="#">2</a>
                                                                    <a class="star-3" href="#">3</a>
                                                                    <a class="star-4" href="#">4</a>
                                                                    <a class="star-5" href="#">5</a>
                                                                </span>
                                                                <select name="rating" id="rating" required=""
                                                                    style="display: none;">
                                                                    <option value="">Rate…</option>
                                                                    <option value="5">Perfect</option>
                                                                    <option value="4">Good</option>
                                                                    <option value="3">Average</option>
                                                                    <option value="2">Not that bad</option>
                                                                    <option value="1">Very poor</option>
                                                                </select>
                                                            </div>
                                                            <textarea cols="30" rows="6" name="review"
                                                                placeholder="Write Your Review Here..." class="form-control"
                                                                id="review" required></textarea>
                                                            <div class="row gutter-md">
                                                                <div class="col-md-6">
                                                                    <input type="text" class="form-control" name="author"
                                                                        placeholder="Your Name" id="author" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <input type="email" class="form-control" name="email_1"
                                                                        placeholder="Your Email" id="email_1" required>
                                                                </div>
                                                            </div>
                                                            <div class="form-group mb-3">
                                                                <input type="checkbox" class="custom-checkbox"
                                                                    id="save-checkbox">
                                                                <label for="save-checkbox">Save my name, email, and website
                                                                    in this browser for the next time I comment.</label>
                                                            </div>
                                                            <div class="form-group mb-3">
                                                                <p>You have to login to add images.</p>
                                                            </div>
                                                            <button type="submit" name="submit_review"
                                                                class="btn btn-dark">Submit
                                                                Review</button>
                                                        </form>

                                                    <?php elseif ($login_required): ?>
                                                        <div class="alert alert-info mb-3">
                                                            Please <a href="login.php" class="text-primary">log in</a> to
                                                            review this product.
                                                        </div>
                                                    <?php elseif ($purchase_required): ?>
                                                        <div class="alert alert-info mb-3">
                                                            Only customers who purchased this product can leave a review.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php

                                                ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($reviews)): ?>
                                            <?php foreach ($reviews as $review): ?>
                                                <?php
                                                $review_rating = floatval($review['rating']);
                                                $review_rating_percentage = $review_rating * 20; // Convert to percentage
                                                $review_date = date('M d, Y', strtotime($review['created_date']));
                                                ?>
                                                <div class="comment-body mb-4">
                                                    <figure class="comment-avatar">
                                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/agents/1-100x100.png" alt="Commenter Avatar"
                                                            width="90" height="90">
                                                    </figure>
                                                    <div class="comment-content">
                                                        <h4 class="comment-author">
                                                            <a href="#"><?php echo e($review['user_name']); ?></a>
                                                        </h4>
                                                        <div class="ratings-container comment-rating">
                                                            <div class="ratings-full">
                                                                <span class="ratings"
                                                                    style="width: <?php echo $review_rating_percentage; ?>%;"></span>
                                                                <span
                                                                    class="tooltiptext tooltip-top"><?php echo $review_rating; ?>
                                                                    Stars</span>
                                                            </div>
                                                            <span
                                                                class="review-date ml-2 text-muted"><?php echo $review_date; ?></span>
                                                        </div>
                                                        <p><?php echo nl2br(e($review['description'])); ?></p>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="no-reviews text-center py-5">
                                                <p class="text-muted">No reviews yet. Be the first to review this product!
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <section class="vendor-product-section">
                                <div class="title-link-wrapper mb-4">
                                    <h4 class="title text-left">More Products From This Vendor</h4>
                                    <a href="#" class="btn btn-dark btn-link btn-slide-right btn-icon-right">More
                                        Products<i class="w-icon-long-arrow-right"></i></a>
                                </div>
                                <div class="swiper-container swiper-theme select-product-wrapper shadow-swiper appear-animate pb-2 mb-10"
                                    data-swiper-options="{
                                            'spaceBetween': 20,
                                            'slidesPerView': 2,
                                            'breakpoints': {
                                                '768': {
                                                    'slidesPerView': 3
                                                },
                                                '992': {
                                                    'slidesPerView': 4
                                                },
                                                '1200': {
                                                    'slidesPerView': 5
                                                }
                                            }
                                            }">
                                    <div
                                        class="swiper-wrapper swiper-wrapper-vendor row cols-lg-5 cols-md-4 cols-sm-3 cols-2">
                                        <?php if (!empty($vendor_products)): ?>
                                            <?php foreach ($vendor_products as $vendor): ?>
                                                <div class="swiper-slide product product-image-gap product-simple">
                                                    <figure class="product-media">
                                                        <a href="product-detail.php?id=<?php echo $vendor['id']; ?>">
                                                            <img src="<?php echo   e($vendor['images_array'][0]); ?>"
                                                                alt="<?php echo e($vendor['product_name']); ?>" width="295"
                                                                height="335" />
                                                            <?php if (isset($vendor['images_array'][1])): ?>
                                                                <img src="<?php echo   e($vendor['images_array'][1]); ?>"
                                                                    alt="<?php echo e($vendor['product_name']); ?>" width="295"
                                                                    height="335" />
                                                            <?php endif; ?>
                                                        </a>
                                                        <div class="product-action-vertical visible">
                                                            <a href="#" class="btn-product-icon btn-quickview" title="Quickview"
                                                                data-product-id="<?php echo $vendor['id']; ?>"><i
                                                                    class="fa-regular fa-eye"></i></a>
                                                        </div>
                                                    </figure>
                                                    <div class="product-details">
                                                        <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist"
                                                            data-product-id="<?php echo $vendor['id']; ?>"></a>
                                                        <h4 class="product-name">
                                                            <a
                                                                href="product-detail.php?id=<?php echo $vendor['id']; ?>"><?php echo e($vendor['product_name']); ?></a>
                                                        </h4>
                                                        <div class="ratings-container">
                                                            <div class="ratings-full">
                                                                <span class="ratings"
                                                                    style="width: <?php echo $vendor['avg_rating_percentage']; ?>%;"></span>
                                                                <span
                                                                    class="tooltiptext tooltip-top"><?php echo $vendor['avg_rating']; ?>
                                                                    Stars</span>
                                                            </div>
                                                            <a href="product-detail.php?id=<?php echo $vendor['id']; ?>"
                                                                class="rating-reviews">(<?php echo $vendor['review_count']; ?>
                                                                reviews)</a>
                                                        </div>
                                                        <div class="product-pa-wrapper">
                                                            <?php if ($vendor['coin'] > 0): ?>
                                                                <div class="">
                                                                    <div class="product-price product-flex justify-content-between">
                                                                        <div class="">
                                                                            <ins class="new-price">₹<?php echo number_format($vendor['selling_price'], 2); ?></ins>
                                                                            <?php if ($vendor['discount_percentage'] > 0): ?>
                                                                                <del
                                                                                    class="old-price">₹<?php echo number_format($vendor['mrp'], 2); ?></del>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="coin-img">
                                                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png"
                                                                                class="img-fluid" alt="">
                                                                            <h6><?php echo $vendor['coin']; ?></h6>
                                                                        </div>
                                                                    </div>
                                                                    <div class="product-action">
                                                                        <a href="product-detail.php?id=<?php echo $vendor['id']; ?>"
                                                                            class="btn-cart btn-product btn btn-link btn-underline">View
                                                                            Product</a>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="product-price">
                                                                    <ins
                                                                        class="new-price">₹<?php echo number_format($vendor['selling_price'], 2); ?></ins>
                                                                    <?php if ($vendor['discount_percentage'] > 0): ?>
                                                                        <del
                                                                            class="old-price">₹<?php echo number_format($vendor['mrp'], 2); ?></del>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="product-action">
                                                                    <a href="product-detail.php?id=<?php echo $vendor['id']; ?>"
                                                                        class="btn-product btn btn-link btn-underline"
                                                                        style="color: #336699;">View Product</a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12 text-center py-5">
                                                <p class="text-muted">No products found from this vendor.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="swiper-pagination mt-4"></div>
                                </div>
                            </section>
                            <section class="related-product-section">
                                <div class="title-link-wrapper mb-4">
                                    <h4 class="title">Related Products</h4>
                                    <a href="#" class="btn btn-dark btn-link btn-slide-right btn-icon-right">More
                                        Products<i class="w-icon-long-arrow-right"></i></a>
                                </div>
                                <div class="product-wrapper row cols-xl-4 cols-lg-2 cols-md-2 cols-12">
                                    <?php if (!empty($category_products)): ?>
                                        <?php foreach ($category_products as $cat_product): ?>
                                            <div class="product-wrap">
                                                <div class="product product-image-gap product-simple">
                                                    <figure class="product-media">
                                                        <a href="product-detail.php?id=<?php echo $cat_product['id']; ?>">
                                                            <img src="<?php echo  e($cat_product['images_array'][0]); ?>"
                                                                alt="<?php echo e($cat_product['product_name']); ?>" width="295"
                                                                height="335" />
                                                            <?php if (isset($cat_product['images_array'][1])): ?>
                                                                <img src="<?php echo   e($cat_product['images_array'][1]); ?>"
                                                                    alt="<?php echo e($cat_product['product_name']); ?>" width="295"
                                                                    height="335" />
                                                            <?php endif; ?>
                                                        </a>
                                                        <div class="product-action-vertical visible">
                                                            <a href="#" class="btn-product-icon btn-quickview" title="Quickview"
                                                                data-product-id="<?php echo $cat_product['id']; ?>"><i
                                                                    class="fa-regular fa-eye"></i></a>
                                                        </div>
                                                    </figure>
                                                    <div class="product-details">
                                                        <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist"
                                                            data-product-id="<?php echo $cat_product['id']; ?>"></a>
                                                        <h4 class="product-name">
                                                            <a
                                                                href="product-detail.php?id=<?php echo $cat_product['id']; ?>"><?php echo e($cat_product['product_name']); ?></a>
                                                        </h4>
                                                        <div class="ratings-container">
                                                            <div class="ratings-full">
                                                                <span class="ratings"
                                                                    style="width: <?php echo $cat_product['avg_rating_percentage']; ?>%;"></span>
                                                                <span
                                                                    class="tooltiptext tooltip-top"><?php echo $cat_product['avg_rating']; ?>
                                                                    Stars</span>
                                                            </div>
                                                            <a href="product-detail.php?id=<?php echo $cat_product['id']; ?>"
                                                                class="rating-reviews">(<?php echo $cat_product['review_count']; ?>
                                                                reviews)</a>
                                                        </div>
                                                        <div class="product-pa-wrapper">
                                                            <?php if ($cat_product['coin'] > 0): ?>
                                                                <div class="product-price product-flex justify-content-between">
                                                                    <ins
                                                                        class="new-price">₹<?php echo number_format($cat_product['selling_price'], 2); ?></ins>
                                                                    <?php if ($cat_product['discount_percentage'] > 0): ?>
                                                                        <del
                                                                            class="old-price">₹<?php echo number_format($cat_product['mrp'], 2); ?></del>
                                                                    <?php endif; ?>
                                                                    <div class="coin-img">
                                                                        <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid"
                                                                            alt="">
                                                                        <h6><?php echo $cat_product['coin']; ?></h6>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="product-price">
                                                                    <ins
                                                                        class="new-price">₹<?php echo number_format($cat_product['selling_price'], 2); ?></ins>
                                                                    <?php if ($cat_product['discount_percentage'] > 0): ?>
                                                                        <del
                                                                            class="old-price">₹<?php echo number_format($cat_product['mrp'], 2); ?></del>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="product-action">
                                                                <a href="product-detail.php?id=<?php echo $cat_product['id']; ?>"
                                                                    class=" btn-product btn btn-link btn-underline"
                                                                    style="color: #336699;">View Product</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12 text-center py-5">
                                            <p class="text-muted">No related products found.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </div>
                        <!-- End of Main Content -->

                        <!-- End of Sidebar -->
                    </div>
                </div>
            </div>
            <!-- End of Page Content -->
        </main>
        <!-- End of Main -->

        <!-- Start of Footer -->
        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Out of Stock Modal -->
    <div id="outOfStockModal"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:10000; align-items:center; justify-content:center;">
        <div
            style="background:#fff; width:90%; max-width:420px; border-radius:14px; padding:24px; text-align:center; position:relative; box-shadow:0 20px 60px rgba(0,0,0,.25);">
            <button id="closeOutOfStockModal" aria-label="Close"
                style="position:absolute; top:10px; right:12px; border:none; background:transparent; font-size:22px; cursor:pointer; color:#666;">×</button>
            <div style="font-size:48px; margin-bottom:12px; color:#e74c3c;">
                <i class="fas fa-times-circle"></i>
            </div>
            <h3 style="margin:0 0 8px; font-weight:600; color:#333;">Product is out of stock</n3>
                <p style="margin:0 0 16px; color:#666;">Please check back later or explore similar products.</p>
                <a href="shop.php" class="btn btn-primary" style="padding:10px 18px; border-radius:8px;">Browse
                    Products</a>
        </div>
    </div>

    <!-- Root element of PhotoSwipe. Must have class pswp -->
    <div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">

        <!-- Background of PhotoSwipe. It's a separate element as animating opacity is faster than rgba(). -->
        <div class="pswp__bg"></div>

        <!-- Slides wrapper with overflow:hidden. -->
        <div class="pswp__scroll-wrap">

            <!-- Container that holds slides.
            PhotoSwipe keeps only 3 of them in the DOM to save memory.
            Don't modify these 3 pswp__item elements, data is added later on. -->
            <div class="pswp__container">
                <div class="pswp__item"></div>
                <div class="pswp__item"></div>
                <div class="pswp__item"></div>
            </div>

            <!-- Default (PhotoSwipeUI_Default) interface on top of sliding area. Can be changed. -->
            <div class="pswp__ui pswp__ui--hidden">

                <div class="pswp__top-bar">

                    <!--  Controls are self-explanatory. Order can be changed. -->

                    <div class="pswp__counter"></div>

                    <button class="pswp__button pswp__button--close" aria-label="Close (Esc)"></button>
                    <button class="pswp__button pswp__button--zoom" aria-label="Zoom in/out"></button>

                    <div class="pswp__preloader">
                        <div class="loading-spin"></div>
                    </div>
                </div>

                <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                    <div class="pswp__share-tooltip"></div>
                </div>

                <button class="pswp__button--arrow--left" aria-label="Previous (arrow left)"></button>
                <button class="pswp__button--arrow--right" aria-label="Next (arrow right)"></button>

                <div class="pswp__caption">
                    <div class="pswp__caption__center"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of PhotoSwipe -->

    <!-- Start of Quick View -->
    <div class="product product-single product-popup">
        <div class="row gutter-lg">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="product-gallery product-gallery-sticky">
                    <div class="swiper-container product-single-swiper swiper-theme nav-inner">
                        <div class="swiper-wrapper row cols-1 gutter-no">
                            <div class="swiper-slide">
                                <figure class="product-image">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/1-440x494.jpg"
                                        data-zoom-image="<?php echo PUBLIC_ASSETS; ?>images/products/popup/1-800x900.jpg"
                                        alt="Water Boil Black Utensil" width="800" height="900">
                                </figure>
                            </div>
                            <div class="swiper-slide">
                                <figure class="product-image">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/2-440x494.jpg"
                                        data-zoom-image="<?php echo PUBLIC_ASSETS; ?>images/products/popup/2-800x900.jpg"
                                        alt="Water Boil Black Utensil" width="800" height="900">
                                </figure>
                            </div>
                            <div class="swiper-slide">
                                <figure class="product-image">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/3-440x494.jpg"
                                        data-zoom-image="<?php echo PUBLIC_ASSETS; ?>images/products/popup/3-800x900.jpg"
                                        alt="Water Boil Black Utensil" width="800" height="900">
                                </figure>
                            </div>
                            <div class="swiper-slide">
                                <figure class="product-image">
                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/4-440x494.jpg"
                                        data-zoom-image="<?php echo PUBLIC_ASSETS; ?>images/products/popup/4-800x900.jpg"
                                        alt="Water Boil Black Utensil" width="800" height="900">
                                </figure>
                            </div>
                        </div>
                        <button class="swiper-button-next"></button>
                        <button class="swiper-button-prev"></button>
                    </div>
                    <div class="product-thumbs-wrap swiper-container" data-swiper-options="{
                        'navigation': {
                            'nextEl': '.swiper-button-next',
                            'prevEl': '.swiper-button-prev'
                        }
                    }">
                        <div class="product-thumbs swiper-wrapper row cols-4 gutter-sm">
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/1-103x116.jpg" alt="Product Thumb" width="103"
                                    height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/2-103x116.jpg" alt="Product Thumb" width="103"
                                    height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/3-103x116.jpg" alt="Product Thumb" width="103"
                                    height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/4-103x116.jpg" alt="Product Thumb" width="103"
                                    height="116">
                            </div>
                        </div>
                        <button class="swiper-button-next"></button>
                        <button class="swiper-button-prev"></button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 overflow-hidden p-relative">
                <div class="product-details scrollable pl-0">
                    <h1 class="product-title"><?php echo e($product['product_name']); ?></h1>


                    <hr class="product-divider">
                    <div class="product-price d-flex align-items-center"><ins class="new-price mr-2"> <sup
                                style="font-size: 18px;">₹</sup>
                            <?php echo number_format($product['selling_price'], 2); ?></ins>
                        <span class="d-block product-price1"> Earn <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png"
                                class="img-fluid" alt=""> <?php echo $product['coin']; ?>
                        </span>
                    </div>

                    <div class="ratings-container">
                        <div class="ratings-full">
                            <span class="ratings" style="width: 80%;"></span>
                            <span class="tooltiptext tooltip-top"></span>
                        </div>
                        <a href="#product-tab-reviews" class="rating-reviews scroll-to">(3
                            Reviews)</a>
                    </div>

                    <div class="stock-status mb-3">
                        <?php if (isset($product['Inventory']) && $product['Inventory'] > 0): ?>
                            <span class="stock-available" style="color: #2ecc71; font-weight: bold;">
                                <i class="fa fa-check-circle"></i> In Stock (<?php echo $product['Inventory']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="stock-unavailable" style="color: #e74c3c; font-weight: bold;">
                                <i class="fa fa-times-circle"></i> Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="mrp-price">
                        <h4>
                            M.R.P: <del class="old-price">₹54.69</del> <span>(62% off)</span>
                        </h4>
                    </div>

                    <div class="product-short-desc">
                        <?php echo nl2br(e($product['description'])); ?>
                    </div>


                    <?php if (!empty($all_specifications)): ?>
                        <div class="product-form product-specifications">
                            <!-- <label class="mb-2">Specifications:</label> -->
                            <div class="specifications-list">
                                <?php foreach ($all_specifications as $spec): ?>
                                    <div class="specification-item mb-2">
                                        <span class="spec-name font-weight-bold"><?php echo e($spec['name']); ?>:</span>
                                        <span class="spec-value ml-2"><?php echo e($spec['values']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="product-variation-price">
                        <span></span>
                    </div>

                    <div class="product-form">
                        <div class="product-qty-form">
                            <div class="cart-position">
                                <button class="quantity-plus w-icon-plus  button2"></button>
                                <input class="quantity form-control text-center " type="number" min="1" max="100000">
                                <button class="quantity-minus w-icon-minus button1"></button>
                            </div>
                        </div>
                        <button class="btn btn-primary btn-cart">
                            <i class="w-icon-cart"></i>
                            <span>Add to Cart</span>
                        </button>
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
                            <a href="#" class="btn-product-icon btn-wishlist w-icon-heart"><span></span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End of Quick view -->

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
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const searchSuggestions = document.getElementById('searchSuggestions');

            // Show suggestions when input is focused
            searchInput.addEventListener('focus', function() {
                searchSuggestions.classList.add('show');
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                    searchSuggestions.classList.remove('show');
                }
            });

            // Handle suggestion item clicks
            const suggestionItems = document.querySelectorAll('.suggestion-item');
            suggestionItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const title = this.querySelector('.suggestion-title').textContent;
                    searchInput.value = title;
                    searchSuggestions.classList.remove('show');
                });
            });

            // Handle "Show more" click
            const showMoreLink = document.querySelector('.show-more');
            if (showMoreLink) {
                showMoreLink.addEventListener('click', function(event) {
                    event.preventDefault();
                    // Redirect to search results page or expand suggestions
                    window.location.href = 'shop.php?search=' + encodeURIComponent(searchInput.value);
                });
            }

            // Hide suggestions on form submit
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    searchSuggestions.classList.remove('show');
                });
            }
        });
    </script>

    <!-- Quickview JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for Wolmart to be available
            function waitForWolmart() {
                if (typeof Wolmart !== 'undefined' && Wolmart.$body) {
                    initQuickview();
                } else {
                    setTimeout(waitForWolmart, 100);
                }
            }

            function initQuickview() {
                // Add event listener for quickview buttons
                Wolmart.$body.on('click', '.btn-quickview', function(event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();

                    const quickviewBtn = this;
                    const productId = quickviewBtn.getAttribute('data-product-id');

                    if (productId) {
                        // Show loading state
                        quickviewBtn.style.opacity = '0.6';

                        // Use Wolmart's modal functionality
                        if (typeof Wolmart !== 'undefined' && Wolmart.popup) {
                            Wolmart.popup({
                                items: {
                                    src: 'quickview.php?id=' + productId,
                                    type: 'ajax'
                                },
                                ajax: {
                                    settings: {
                                        method: 'GET'
                                    }
                                },
                                callbacks: {
                                    ajaxContentAdded: function() {
                                        // Remove theme's default demo handler for modal add-to-cart
                                        try {
                                            if (typeof Wolmart !== 'undefined' && Wolmart.$body && Wolmart.$body.off) {
                                                Wolmart.$body.off('click', '.product-popup .btn-cart');
                                            }
                                        } catch (e) {
                                            /* ignore */ }

                                        // Initialize product single functionality
                                        if (typeof Wolmart.initProductSingle === 'function') {
                                            Wolmart.initProductSingle();
                                        }

                                        // Initialize image error handling for dynamically loaded content
                                        initImageErrorHandling();

                                        // Initialize quantity buttons for modal
                                        initModalQuantityButtons();

                                        // Initialize image navigation for modal
                                        initModalImageNavigation();

                                        // Initialize wishlist buttons for modal
                                        if (window.wishlistManager) {
                                            window.wishlistManager.initDynamicWishlistButtons();
                                        }

                                        // Initialize quickview modal functionality from quickview.php
                                        setTimeout(function() {
                                            if (typeof initQuickviewModal === 'function') {
                                                initQuickviewModal();
                                            }
                                        }, 50);

                                        // Reset button state
                                        quickviewBtn.style.opacity = '';
                                    },
                                    close: function() {
                                        // Clean up modal state on close
                                        const modalRoot = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
                                        if (modalRoot) {
                                            // Remove initialization flags to allow re-initialization on next open
                                            modalRoot.removeAttribute('data-quickview-initialized');
                                            modalRoot.removeAttribute('data-modal-cart-initialized');

                                            // Remove listener flags from buttons
                                            const cartBtn = modalRoot.querySelector('.btn-cart');
                                            if (cartBtn) cartBtn.removeAttribute('data-cart-listener-added');

                                            const plusBtns = modalRoot.querySelectorAll('.quantity-plus');
                                            plusBtns.forEach(btn => btn.removeAttribute('data-plus-listener-added'));

                                            const minusBtns = modalRoot.querySelectorAll('.quantity-minus');
                                            minusBtns.forEach(btn => btn.removeAttribute('data-minus-listener-added'));
                                        }

                                        // Reset button state on modal close
                                        quickviewBtn.style.opacity = '';
                                    }
                                }
                            }, 'quickview');
                        } else {
                            // Fallback: create a simple modal
                            createFallbackModal(productId);
                            quickviewBtn.style.opacity = '';
                        }
                    }
                });
            }

            // Image error handling function
            function initImageErrorHandling() {
                // Handle image loading errors in the modal
                const modalImages = document.querySelectorAll('.product-popup img');
                modalImages.forEach(function(img) {
                    if (!img.hasAttribute('data-error-handled')) {
                        img.setAttribute('data-error-handled', 'true');

                        // Set up error handling
                        img.onerror = function() {
                            if (!this.hasAttribute('data-fallback-used')) {
                                this.setAttribute('data-fallback-used', 'true');
                                this.src = 'uploads/vendors/no-product.png';

                                // Add a subtle visual indicator that this is a fallback image
                                this.style.opacity = '0.8';
                                this.title = 'Image not available - showing default image';
                            }
                        };
                    }
                });
            }

            // Initialize quantity buttons for modal
            function initModalQuantityButtons() {
                const modalRoot = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
                if (!modalRoot) return;

                const quantityInputs = modalRoot.querySelectorAll('.quantity');
                quantityInputs.forEach(function(input) {
                    const plusBtn = input.parentNode.querySelector('.quantity-plus');
                    const minusBtn = input.parentNode.querySelector('.quantity-minus');

                    if (plusBtn && !plusBtn.hasAttribute('data-modal-plus-listener-added')) {
                        plusBtn.setAttribute('data-modal-plus-listener-added', 'true');
                        plusBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const currentValue = parseInt(input.value) || 1;
                            const maxValue = parseInt(input.getAttribute('max')) || 100000;
                            if (currentValue < maxValue) {
                                input.value = currentValue + 1;
                            }
                        });
                    }

                    if (minusBtn && !minusBtn.hasAttribute('data-modal-minus-listener-added')) {
                        minusBtn.setAttribute('data-modal-minus-listener-added', 'true');
                        minusBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            const currentValue = parseInt(input.value) || 1;
                            const minValue = parseInt(input.getAttribute('min')) || 1;
                            if (currentValue > minValue) {
                                input.value = currentValue - 1;
                            }
                        });
                    }
                });

                // Initialize add to cart functionality for modal
                initModalCartButtons();
            }

            // Initialize add to cart buttons for modal
            function initModalCartButtons() {
                const modalRoot = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
                if (!modalRoot) return;

                // Check if already initialized to prevent duplicate listeners
                if (modalRoot.hasAttribute('data-modal-cart-initialized')) {
                    return;
                }
                modalRoot.setAttribute('data-modal-cart-initialized', 'true');

                const cartButton = modalRoot.querySelector('.btn-cart');
                if (!cartButton || cartButton.hasAttribute('data-modal-cart-listener-added')) return;

                cartButton.setAttribute('data-modal-cart-listener-added', 'true');
                cartButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    const productId = cartButton.getAttribute('data-product-id');
                    const quantityInput = modalRoot.querySelector('.quantity');
                    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

                    if (!productId) return;

                    // Prevent multiple rapid clicks
                    if (cartButton.disabled) return;

                    if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                        const originalText = cartButton.innerHTML;
                        cartButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                        cartButton.disabled = true;
                        window.cartManager.addToCart(productId, quantity, modalRoot).finally(function() {
                            cartButton.innerHTML = originalText;
                            cartButton.disabled = false;
                        });
                    } else {
                        addToCart(productId, quantity, cartButton);
                    }
                }, {
                    once: false,
                    capture: true
                });
            }

            // Add to cart function
            function addToCart(productId, quantity, button) {
                // Show loading state
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                button.disabled = true;

                // Make AJAX request to add to cart
                fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'add',
                            product_id: productId,
                            quantity: quantity
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            showNotification('Product added to cart successfully!', 'success');

                            // Update cart count if function exists
                            if (typeof window.updateCartCount === 'function') {
                                window.updateCartCount();
                            }
                        } else {
                            showNotification(data.message || 'Failed to add product to cart', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                        showNotification('An error occurred while adding to cart', 'error');
                    })
                    .finally(() => {
                        // Reset button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
            }

            // Show notification function
            function showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `wishlist-notification wishlist-notification-${type}`;

                // Add icon based on type
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

                // Add click to dismiss functionality
                notification.addEventListener('click', () => {
                    dismissNotification(notification);
                });

                // Remove notification after 4 seconds
                setTimeout(() => {
                    dismissNotification(notification);
                }, 4000);
            }

            // Dismiss notification function
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

            // Initialize image navigation for modal
            function initModalImageNavigation() {
                // Wait a bit for modal to fully load
                setTimeout(function() {
                    // Find elements in the modal
                    const modal = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
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
                        mainSlides.forEach(function(slide, i) {
                            slide.style.display = i === index ? 'block' : 'none';
                        });

                        // Update active thumbnail
                        thumbnailImages.forEach(function(thumb, i) {
                            if (i === index) {
                                thumb.parentElement.classList.add('active');
                                thumb.parentElement.style.borderColor = '#007bff';
                                thumb.parentElement.style.opacity = '1';
                            } else {
                                thumb.parentElement.classList.remove('active');
                                thumb.parentElement.style.borderColor = 'transparent';
                                thumb.parentElement.style.opacity = '0.7';
                            }
                        });
                    }

                    // Next button functionality
                    if (nextBtn) {
                        nextBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            currentIndex = (currentIndex + 1) % totalImages;
                            updateMainImage(currentIndex);
                        });
                    }

                    // Previous button functionality
                    if (prevBtn) {
                        prevBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            currentIndex = (currentIndex - 1 + totalImages) % totalImages;
                            updateMainImage(currentIndex);
                        });
                    }

                    // Make thumbnails clickable
                    thumbnailImages.forEach(function(thumb, index) {
                        if (!thumb.hasAttribute('data-nav-initialized')) {
                            thumb.setAttribute('data-nav-initialized', 'true');

                            // Style thumbnail as clickable
                            thumb.style.cursor = 'pointer';
                            thumb.parentElement.style.cursor = 'pointer';

                            // Add click event to thumbnail
                            thumb.addEventListener('click', function(e) {
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

            // Fallback modal creation
            function createFallbackModal(productId) {
                // Create modal overlay
                const overlay = document.createElement('div');
                overlay.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    z-index: 9999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                `;

                // Create modal content
                const modal = document.createElement('div');
                modal.style.cssText = `
                    background: white;
                    border-radius: 8px;
                    max-width: 90%;
                    max-height: 90%;
                    overflow: auto;
                    position: relative;
                `;

                // Create close button
                const closeBtn = document.createElement('button');
                closeBtn.innerHTML = '×';
                closeBtn.style.cssText = `
                    position: absolute;
                    top: 10px;
                    right: 15px;
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    z-index: 10000;
                `;

                closeBtn.onclick = () => {
                    document.body.removeChild(overlay);
                };

                // Load content via AJAX
                fetch('quickview.php?id=' + productId)
                    .then(response => response.text())
                    .then(html => {
                        modal.innerHTML = html;
                        modal.appendChild(closeBtn);

                        // Initialize image error handling, quantity buttons, navigation, and wishlist for the fallback modal
                        setTimeout(function() {
                            initImageErrorHandling();
                            initModalQuantityButtons();
                            initModalImageNavigation();
                            if (window.wishlistManager) {
                                window.wishlistManager.initDynamicWishlistButtons();
                            }
                        }, 100);
                    })
                    .catch(error => {
                        modal.innerHTML = '<div class="p-4 text-center text-danger">Error loading product details</div>';
                        modal.appendChild(closeBtn);
                    });

                overlay.appendChild(modal);
                document.body.appendChild(overlay);

                // Close modal when clicking overlay
                overlay.onclick = (e) => {
                    if (e.target === overlay) {
                        document.body.removeChild(overlay);
                    }
                };
            }

            waitForWolmart();
        });
    </script>


    <!-- Wishlist Functionality -->
    <script>
        // Wishlist Management System
        class WishlistManager {
            constructor() {
                this.init();
            }

            init() {
                // Initialize wishlist buttons on page load
                document.addEventListener('DOMContentLoaded', () => {
                    this.initWishlistButtons();
                    this.checkWishlistStatus();
                });
            }

            // Check if user is logged in
            isUserLoggedIn() {
                // Check if user_id exists in session (set by PHP)
                return typeof window.user_id !== 'undefined' && window.user_id > 0;
            }

            // Initialize all wishlist buttons
            initWishlistButtons() {
                const wishlistButtons = document.querySelectorAll('.btn-wishlist');
                wishlistButtons.forEach(button => {
                    if (!button.hasAttribute('data-wishlist-initialized')) {
                        button.setAttribute('data-wishlist-initialized', 'true');
                        button.addEventListener('click', (e) => this.handleWishlistClick(e));

                        // Set initial class based on current state
                        this.setInitialWishlistClass(button);
                    }
                });
            }

            // Set initial wishlist class for a button
            setInitialWishlistClass(button) {
                // Ensure the button starts with the correct base class
                if (!button.classList.contains('w-icon-heart') && !button.classList.contains('w-icon-heart-full')) {
                    button.classList.add('w-icon-heart');
                }

                // Remove any existing state classes
                button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');

                // Add the base class if not present
                if (!button.classList.contains('w-icon-heart')) {
                    button.classList.add('w-icon-heart');
                }
            }

            // Handle wishlist button clicks
            async handleWishlistClick(event) {
                event.preventDefault();
                event.stopPropagation();

                const button = event.currentTarget;
                const productId = this.getProductId(button);

                if (!productId) {
                    this.showNotification('Unable to identify product. Please refresh the page and try again.', 'error');
                    return;
                }

                // Check if user is logged in
                if (!this.isUserLoggedIn()) {
                    // Show login required modal
                    if (window.loginRequiredModal) {
                        window.loginRequiredModal.show();
                    } else {
                        // Fallback to redirect if modal is not available
                        window.location.href = 'login.php';
                    }
                    return;
                }

                // Show loading state
                this.setButtonLoading(button, true);

                try {
                    // Check current wishlist status
                    const isInWishlist = await this.isInWishlist(productId);

                    // Debug log
                    console.log('Wishlist action:', {
                        productId,
                        isInWishlist,
                        buttonClasses: button.className
                    });

                    if (isInWishlist) {
                        await this.removeFromWishlist(productId, button);
                    } else {
                        await this.addToWishlist(productId, button);
                    }
                } catch (error) {
                    console.error('Wishlist error:', error);
                    this.showNotification('An unexpected error occurred. Please try again later.', 'error');
                } finally {
                    this.setButtonLoading(button, false);
                }
            }

            // Get product ID from button or its context
            getProductId(button) {
                // Try data attribute first
                let productId = button.getAttribute('data-product-id');

                if (!productId) {
                    // Try to find in modal context
                    const modal = button.closest('.product-popup');
                    if (modal) {
                        productId = modal.getAttribute('data-product-id');
                    }
                }

                if (!productId) {
                    // Try to find in product context
                    const productContainer = button.closest('.product, .product-wrap');
                    if (productContainer) {
                        const productLink = productContainer.querySelector('a[href*="product-detail.php?id="]');
                        if (productLink) {
                            const match = productLink.href.match(/id=(\d+)/);
                            if (match) {
                                productId = match[1];
                            }
                        }
                    }
                }

                // For main product page, try URL parameter
                if (!productId && window.location.href.includes('product-detail.php')) {
                    const urlParams = new URLSearchParams(window.location.search);
                    productId = urlParams.get('id');
                }

                return productId ? parseInt(productId) : null;
            }

            // Check if user is logged in
            isUserLoggedIn() {
                // Check PHP session status
                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                    return true;
                <?php else: ?>
                    return false;
                <?php endif; ?>
            }

            // Add product to wishlist
            async addToWishlist(productId, button) {
                if (this.isUserLoggedIn()) {
                    // Logged in user - save to database
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'add',
                            product_id: productId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.updateButtonState(button, true);
                        this.updateAllProductCards(productId, true);
                        this.showNotification('Product added to your wishlist', 'success');
                        this.updateWishlistCount();
                    } else {
                        this.showNotification(data.message || 'Unable to add product to wishlist. Please try again.', 'error');
                    }
                } else {
                    // Guest user - save to localStorage
                    this.addToLocalWishlist(productId);
                    this.updateButtonState(button, true);
                    this.updateAllProductCards(productId, true);
                    this.showNotification('Product added to your wishlist', 'success');
                    this.updateWishlistCount();
                }
            }

            // Remove product from wishlist
            async removeFromWishlist(productId, button) {
                if (this.isUserLoggedIn()) {
                    // Logged in user - remove from database
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'remove',
                            product_id: productId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.updateButtonState(button, false);
                        this.updateAllProductCards(productId, false);
                        this.showNotification('Product removed from your wishlist', 'error');
                        this.updateWishlistCount();
                    } else {
                        this.showNotification(data.message || 'Unable to remove product from wishlist. Please try again.', 'error');
                    }
                } else {
                    // Guest user - remove from localStorage
                    this.removeFromLocalWishlist(productId);
                    this.updateButtonState(button, false);
                    this.updateAllProductCards(productId, false);
                    this.showNotification('Product removed from your wishlist', 'error');
                    this.updateWishlistCount();
                }
            }

            // Check if product is in wishlist
            async isInWishlist(productId) {
                if (this.isUserLoggedIn()) {
                    // Logged in user - check database
                    const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'check',
                            product_id: productId
                        })
                    });

                    const data = await response.json();
                    return data.success ? data.in_wishlist : false;
                } else {
                    // Guest user - check localStorage
                    return this.isInLocalWishlist(productId);
                }
            }

            // LocalStorage methods for guest users
            addToLocalWishlist(productId) {
                let wishlist = this.getLocalWishlist();
                if (!wishlist.includes(productId)) {
                    wishlist.push(productId);
                    localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));
                }
            }

            removeFromLocalWishlist(productId) {
                let wishlist = this.getLocalWishlist();
                wishlist = wishlist.filter(id => id !== productId);
                localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));
            }

            isInLocalWishlist(productId) {
                const wishlist = this.getLocalWishlist();
                return wishlist.includes(productId);
            }

            getLocalWishlist() {
                const wishlist = localStorage.getItem('guest_wishlist');
                return wishlist ? JSON.parse(wishlist) : [];
            }

            // Update button visual state
            updateButtonState(button, isInWishlist) {
                const icon = button.querySelector('i, .w-icon-heart, .w-icon-heart-full') || button;

                // Debug log
                console.log('Updating button state:', {
                    isInWishlist,
                    buttonElement: button,
                    iconElement: icon
                });

                if (isInWishlist) {
                    // Add active classes and change icon to full heart
                    button.classList.add('active', 'in-wishlist');
                    button.classList.remove('w-icon-heart');
                    button.classList.add('w-icon-heart-full');
                    button.style.color = '#ff4757';
                    button.title = 'Remove from wishlist';

                    // Update icon color
                    if (icon && icon !== button) {
                        icon.style.color = '#ff4757';
                    }

                    // Force style application
                    button.style.setProperty('color', '#ff4757', 'important');
                    if (icon && icon !== button) {
                        icon.style.setProperty('color', '#ff4757', 'important');
                    }
                } else {
                    // Remove active classes and change icon to regular heart
                    button.classList.remove('active', 'in-wishlist');
                    button.classList.remove('w-icon-heart-full');
                    button.classList.add('w-icon-heart');
                    button.style.color = '';
                    button.title = 'Add to wishlist';

                    // Reset icon color
                    if (icon && icon !== button) {
                        icon.style.color = '';
                    }

                    // Remove forced styles
                    button.style.removeProperty('color');
                    if (icon && icon !== button) {
                        icon.style.removeProperty('color');
                    }
                }
            }

            // Update all product cards with the same product ID
            updateAllProductCards(productId, isInWishlist) {
                // Find all wishlist buttons with the same product ID
                const allWishlistButtons = document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`);

                allWishlistButtons.forEach(button => {
                    this.updateButtonState(button, isInWishlist);
                });

                console.log(`Updated ${allWishlistButtons.length} product cards for product ID ${productId}`);
            }

            // Set button loading state
            setButtonLoading(button, isLoading) {
                if (isLoading) {
                    button.style.opacity = '0.6';
                    button.style.pointerEvents = 'none';
                } else {
                    button.style.opacity = '';
                    button.style.pointerEvents = '';
                }
            }

            // Check wishlist status for all products on page
            async checkWishlistStatus() {
                const buttons = document.querySelectorAll('.btn-wishlist[data-wishlist-initialized="true"]');

                for (const button of buttons) {
                    const productId = this.getProductId(button);
                    if (productId) {
                        try {
                            const isInWishlist = await this.isInWishlist(productId);
                            this.updateButtonState(button, isInWishlist);
                        } catch (error) {
                            console.error('Error checking wishlist status:', error);
                            // Set to default state on error
                            this.updateButtonState(button, false);
                        }
                    } else {
                        // Set to default state if no product ID
                        this.updateButtonState(button, false);
                    }
                }
            }

            // Update wishlist count in header/navbar
            async updateWishlistCount() {
                try {
                    let count = 0;

                    if (this.isUserLoggedIn()) {
                        const response = await fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'get_count'
                            })
                        });

                        const data = await response.json();
                        count = data.success ? data.count : 0;
                    } else {
                        count = this.getLocalWishlist().length;
                    }

                    // Update count in UI (adjust selector based on your header structure)
                    const countElements = document.querySelectorAll('.wishlist-count, [data-wishlist-count], #wishlist-count');
                    countElements.forEach(element => {
                        element.textContent = count;
                        element.style.display = count > 0 ? 'inline' : 'none';
                    });

                    // Also call header update function if available
                    if (typeof window.updateHeaderWishlistCount === 'function') {
                        window.updateHeaderWishlistCount();
                    }
                } catch (error) {
                    console.error('Error updating wishlist count:', error);
                }
            }

            // Show notification to user
            showNotification(message, type = 'info') {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = `wishlist-notification wishlist-notification-${type}`;

                // Add icon based on type
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

                // Add animation CSS if not exists
                if (!document.querySelector('#wishlist-notification-styles')) {
                    const style = document.createElement('style');
                    style.id = 'wishlist-notification-styles';
                    style.textContent = `
                        @keyframes slideInRight {
                            from { transform: translateX(100%); opacity: 0; }
                            to { transform: translateX(0); opacity: 1; }
                        }
                        @keyframes slideOutRight {
                            from { transform: translateX(0); opacity: 1; }
                            to { transform: translateX(100%); opacity: 0; }
                        }
                    `;
                    document.head.appendChild(style);
                }

                document.body.appendChild(notification);

                // Add click to dismiss functionality
                notification.addEventListener('click', () => {
                    this.dismissNotification(notification);
                });

                // Remove notification after 4 seconds
                setTimeout(() => {
                    this.dismissNotification(notification);
                }, 4000);
            }

            // Dismiss notification with animation
            dismissNotification(notification) {
                if (notification && notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.parentNode.removeChild(notification);
                        }
                    }, 300);
                }
            }

            // Initialize wishlist buttons in dynamically loaded content (like modals)
            initDynamicWishlistButtons() {
                // Re-run initialization for new buttons
                this.initWishlistButtons();

                // Check status for new buttons
                setTimeout(() => {
                    this.checkWishlistStatus();
                }, 100);
            }
        }

        // Initialize wishlist manager
        const wishlistManager = new WishlistManager();

        // Make it globally available for modal initialization
        window.wishlistManager = wishlistManager;
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select buttons from both main product section and quick view modal
            const addToCartButtons = document.querySelectorAll('.btn-cart');
            const buyNowButtons = document.querySelectorAll('.btn-buy');
            const colorOptions = document.querySelectorAll('.product-color-swatch .color');
            const sizeOptions = document.querySelectorAll('.product-size-swatch .size');
            const variantOptions = document.querySelectorAll('.product-variant-swatch .variant-option');
            let selectedColor = null;
            let selectedSize = null;
            let selectedVariants = {};

            // Helpers to update price UI dynamically (without changing UI structure)
            function formatCurrencyINR(value) {
                try {
                    return '₹' + Number(value).toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                } catch (e) {
                    return '₹' + value;
                }
            }

            function updateDisplayedPrice(mrp, selling) {
                // Fallbacks
                const baseMrp = (window.productBasePrice && window.productBasePrice.mrp) ? window.productBasePrice.mrp : 0;
                const baseSell = (window.productBasePrice && window.productBasePrice.selling_price) ? window.productBasePrice.selling_price : 0;
                const finalMrp = (mrp != null) ? mrp : baseMrp;
                const finalSell = (selling != null) ? selling : baseSell;

                // Elements
                const newPriceEl = document.querySelector('.product-price .new-price');
                const mrpEl = document.querySelector('.mrp-price .old-price');
                const discountSpan = document.querySelector('.mrp-price span');

                if (newPriceEl) {
                    // Keep the embedded <sup>₹</sup> structure if present, otherwise render full value
                    const sup = newPriceEl.querySelector('sup');
                    if (sup) {
                        // Update numeric part after the sup
                        const existingSup = sup.outerHTML; // string of sup
                        newPriceEl.innerHTML = existingSup + ' ' + (Number(finalSell).toLocaleString('en-IN', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                    } else {
                        newPriceEl.textContent = formatCurrencyINR(finalSell);
                    }
                }
                if (mrpEl) {
                    mrpEl.textContent = formatCurrencyINR(finalMrp);
                }
                if (discountSpan) {
                    let discount = 0;
                    if (finalMrp > 0 && finalSell < finalMrp) {
                        discount = Math.round(((finalMrp - finalSell) / finalMrp) * 100);
                    }
                    if (discount > 0) {
                        discountSpan.style.display = '';
                        discountSpan.textContent = '(' + discount + '% off)';
                    } else {
                        discountSpan.style.display = 'none';
                    }
                }
            }

            // Track selected color
            colorOptions.forEach(option => {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // Stop other handlers

                    // Add timestamp for price calculation
                    this.setAttribute('data-timestamp', Date.now());

                    colorOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedColor = this.style.backgroundColor;

                    // Dynamic price update if variant id is mapped
                    try {
                        const dataMrp = this.getAttribute('data-mrp');
                        const dataSell = this.getAttribute('data-selling');
                        if (dataMrp !== null || dataSell !== null) {
                            const m = (dataMrp !== null && dataMrp !== '') ? parseFloat(dataMrp) : null;
                            const s = (dataSell !== null && dataSell !== '') ? parseFloat(dataSell) : null;
                            updateDisplayedPrice(m, s);
                            return;
                        }
                        const variantId = this.getAttribute('data-variant-id');
                        if (variantId && window.productVariantPrices && window.productVariantPrices[variantId]) {
                            const vp = window.productVariantPrices[variantId];
                            updateDisplayedPrice(vp.mrp, vp.selling_price);
                        } else {
                            // Revert to base price if no mapping
                            updateDisplayedPrice(null, null);
                        }
                    } catch (err) {
                        /* ignore */ }
                }, {
                    capture: true
                });
            });

            // Track selected size
            sizeOptions.forEach(option => {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // Stop other handlers

                    // Add timestamp for price calculation
                    this.setAttribute('data-timestamp', Date.now());

                    sizeOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedSize = this.textContent;

                    // Dynamic price update if size provides variant pricing
                    try {
                        const dataMrp = this.getAttribute('data-mrp');
                        const dataSell = this.getAttribute('data-selling');
                        if (dataMrp !== null || dataSell !== null) {
                            const m = (dataMrp !== null && dataMrp !== '') ? parseFloat(dataMrp) : null;
                            const s = (dataSell !== null && dataSell !== '') ? parseFloat(dataSell) : null;
                            updateDisplayedPrice(m, s);
                            return;
                        }
                        const variantId = this.getAttribute('data-variant-id');
                        if (variantId && window.productVariantPrices && window.productVariantPrices[variantId]) {
                            const vp = window.productVariantPrices[variantId];
                            updateDisplayedPrice(vp.mrp, vp.selling_price);
                        } else {
                            updateDisplayedPrice(null, null);
                        }
                    } catch (err) {
                        /* ignore */ }
                }, {
                    capture: true
                });
            });

            // Track selected other variants
            variantOptions.forEach(option => {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // Stop other handlers

                    const attribute = this.getAttribute('data-attribute');
                    const value = this.getAttribute('data-value');

                    // Add timestamp for price calculation
                    this.setAttribute('data-timestamp', Date.now());

                    // Remove active class from all options in the same attribute group
                    const parentGroup = this.closest('.product-variant-swatch');
                    if (parentGroup) {
                        parentGroup.querySelectorAll('.variant-option').forEach(opt => opt.classList.remove('selected'));
                    }

                    // Add active class to clicked option
                    this.classList.add('selected');
                    selectedVariants[attribute] = value;

                    // Dynamic price update if variant provides pricing
                    try {
                        const dataMrp = this.getAttribute('data-mrp');
                        const dataSell = this.getAttribute('data-selling');
                        if (dataMrp !== null || dataSell !== null) {
                            const m = (dataMrp !== null && dataMrp !== '') ? parseFloat(dataMrp) : null;
                            const s = (dataSell !== null && dataSell !== '') ? parseFloat(dataSell) : null;
                            updateDisplayedPrice(m, s);
                            return;
                        }
                        const variantId = this.getAttribute('data-variant-id');
                        if (variantId && window.productVariantPrices && window.productVariantPrices[variantId]) {
                            const vp = window.productVariantPrices[variantId];
                            updateDisplayedPrice(vp.mrp, vp.selling_price);
                        } else {
                            updateDisplayedPrice(null, null);
                        }
                    } catch (err) {
                        /* ignore */ }
                }, {
                    capture: true
                });
            });

            // Handle add to cart clicks for all matching buttons
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // Stop other handlers

                    // Block when out of stock (server-rendered state)
                    var isOutOfStock = false;
                    try {
                        var stockEl = document.querySelector('.stock-unavailable');
                        isOutOfStock = !!stockEl || button.disabled || button.textContent.indexOf('Out of Stock') !== -1;
                    } catch (err) {}
                    if (isOutOfStock) {
                        var modal = document.getElementById('outOfStockModal');
                        if (modal) {
                            modal.style.display = 'flex';
                            var closer = document.getElementById('closeOutOfStockModal');
                            if (closer) {
                                closer.onclick = function() {
                                    modal.style.display = 'none';
                                };
                            }
                            modal.onclick = function(ev) {
                                if (ev.target === modal) modal.style.display = 'none';
                            };
                        }
                        return;
                    }

                    const productId = button.getAttribute('data-product-id') || <?php echo $product['id']; ?>;
                    const container = button.closest('.product, .product-single, .product-details, .product-popup') || document;
                    const quantityInput = container.querySelector('.quantity');
                    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

                    // Check if product is in stock
                    if (button.disabled || button.textContent.includes('Out of Stock')) {
                        showNotification('This product is currently out of stock', 'error');
                        return;
                    }

                    // Check if variant selection is required
                    const hasColorOptions = colorOptions.length > 0;
                    const hasSizeOptions = sizeOptions.length > 0;
                    const hasVariantOptions = variantOptions.length > 0;

                    if ((hasColorOptions && !selectedColor) || (hasSizeOptions && !selectedSize)) {
                        showNotification('Please select all required options (Color/Size)', 'error');
                        return;
                    }

                    // Check if other variants are required
                    if (hasVariantOptions) {
                        const variantGroups = document.querySelectorAll('.product-variant-swatch');
                        for (let group of variantGroups) {
                            const selectedOption = group.querySelector('.variant-option.selected');
                            if (!selectedOption) {
                                const attributeName = group.querySelector('label').textContent.replace(':', '');
                                showNotification(`Please select ${attributeName}`, 'error');
                                return;
                            }
                        }
                    }

                    // Capture selected variants and their prices
                    const selectedVariants = {};
                    const variantPrices = {};

                    // Helper function to safely parse float values
                    function safeParseFloat(value) {
                        return value !== null && value !== undefined && value !== '' ? parseFloat(value) : null;
                    }

                    // Get selected color variant
                    const selectedColorElement = document.querySelector('.product-color-swatch .color.selected');
                    if (selectedColorElement) {
                        const colorName = selectedColorElement.getAttribute('data-color');
                        const colorVariantId = selectedColorElement.getAttribute('data-variant-id');
                        const colorMrp = selectedColorElement.getAttribute('data-mrp');
                        const colorSelling = selectedColorElement.getAttribute('data-selling');

                        if (colorName) {
                            selectedVariants.color = colorName;
                        }
                        if (colorVariantId) {
                            selectedVariants.color_variant_id = colorVariantId;
                        }
                        // Store color variant prices separately
                        if (colorMrp !== null) {
                            variantPrices.color_mrp = safeParseFloat(colorMrp);
                        }
                        if (colorSelling !== null) {
                            variantPrices.color_selling_price = safeParseFloat(colorSelling);
                        }
                    }

                    // Get selected size variant
                    const selectedSizeElement = document.querySelector('.product-size-swatch .size.selected');
                    if (selectedSizeElement) {
                        const sizeName = selectedSizeElement.getAttribute('data-size');
                        const sizeVariantId = selectedSizeElement.getAttribute('data-variant-id');
                        const sizeMrp = selectedSizeElement.getAttribute('data-mrp');
                        const sizeSelling = selectedSizeElement.getAttribute('data-selling');

                        if (sizeName) {
                            selectedVariants.size = sizeName;
                        }
                        if (sizeVariantId) {
                            selectedVariants.size_variant_id = sizeVariantId;
                        }
                        // Store size variant prices separately
                        if (sizeMrp !== null) {
                            variantPrices.size_mrp = safeParseFloat(sizeMrp);
                        }
                        if (sizeSelling !== null) {
                            variantPrices.size_selling_price = safeParseFloat(sizeSelling);
                        }
                    }

                    // Get selected other variants
                    const selectedVariantElements = document.querySelectorAll('.product-variant-swatch .variant-option.selected');
                    selectedVariantElements.forEach(element => {
                        const attribute = element.getAttribute('data-attribute');
                        const value = element.getAttribute('data-value');
                        const variantId = element.getAttribute('data-variant-id');
                        const mrp = element.getAttribute('data-mrp');
                        const selling = element.getAttribute('data-selling');

                        if (attribute && value) {
                            selectedVariants[attribute.toLowerCase().replace(/\s+/g, '_')] = value;
                        }
                        if (variantId) {
                            selectedVariants[attribute.toLowerCase().replace(/\s+/g, '_') + '_variant_id'] = variantId;
                        }
                        // Store other variant prices with attribute prefix
                        const attrKey = attribute.toLowerCase().replace(/\s+/g, '_');
                        if (mrp !== null) {
                            variantPrices[attrKey + '_mrp'] = safeParseFloat(mrp);
                        }
                        if (selling !== null) {
                            variantPrices[attrKey + '_selling_price'] = safeParseFloat(selling);
                        }
                    });

                    // Calculate final prices based on the last selected variant
                    // We need to find which variant was selected last and use its prices
                    let finalMrp = null;
                    let finalSellingPrice = null;
                    let lastSelectedVariant = null;

                    // Track all selected variants with their selection order
                    const allSelectedVariants = [];

                    // Add color variant if selected
                    if (selectedColorElement) {
                        const colorMrp = selectedColorElement.getAttribute('data-mrp');
                        const colorSelling = selectedColorElement.getAttribute('data-selling');
                        allSelectedVariants.push({
                            type: 'color',
                            element: selectedColorElement,
                            mrp: colorMrp,
                            selling: colorSelling,
                            timestamp: selectedColorElement.getAttribute('data-timestamp') || Date.now()
                        });
                    }

                    // Add size variant if selected
                    if (selectedSizeElement) {
                        const sizeMrp = selectedSizeElement.getAttribute('data-mrp');
                        const sizeSelling = selectedSizeElement.getAttribute('data-selling');
                        allSelectedVariants.push({
                            type: 'size',
                            element: selectedSizeElement,
                            mrp: sizeMrp,
                            selling: sizeSelling,
                            timestamp: selectedSizeElement.getAttribute('data-timestamp') || Date.now()
                        });
                    }

                    // Add other variants if selected
                    selectedVariantElements.forEach(element => {
                        const mrp = element.getAttribute('data-mrp');
                        const selling = element.getAttribute('data-selling');
                        allSelectedVariants.push({
                            type: 'other',
                            element: element,
                            mrp: mrp,
                            selling: selling,
                            timestamp: element.getAttribute('data-timestamp') || Date.now()
                        });
                    });

                    // Find the variant with the highest timestamp (most recently selected)
                    if (allSelectedVariants.length > 0) {
                        lastSelectedVariant = allSelectedVariants.reduce((latest, current) => {
                            return parseInt(current.timestamp) > parseInt(latest.timestamp) ? current : latest;
                        });

                        // Use the last selected variant's prices
                        if (lastSelectedVariant.selling !== null && lastSelectedVariant.selling !== '') {
                            finalSellingPrice = safeParseFloat(lastSelectedVariant.selling);
                        }
                        if (lastSelectedVariant.mrp !== null && lastSelectedVariant.mrp !== '') {
                            finalMrp = safeParseFloat(lastSelectedVariant.mrp);
                        }
                    }

                    // Set final prices
                    if (finalSellingPrice !== null) {
                        variantPrices.selling_price = finalSellingPrice;
                    }
                    if (finalMrp !== null) {
                        variantPrices.mrp = finalMrp;
                    }

                    // Store information about which variant determined the final price
                    if (lastSelectedVariant) {
                        variantPrices.price_source = lastSelectedVariant.type;
                        variantPrices.price_source_variant = lastSelectedVariant.element.getAttribute('data-value') ||
                            lastSelectedVariant.element.getAttribute('data-color') ||
                            lastSelectedVariant.element.getAttribute('data-size');
                    }

                    // Prepare cart data with variant information
                    const cartData = {
                        action: 'add',
                        product_id: parseInt(productId),
                        quantity: parseInt(quantity),
                        selected_variants: selectedVariants,
                        variant_prices: variantPrices
                    };

                    // Add to cart using the global cart manager; fallback to direct request if unavailable
                    const doFinally = () => {
                        button.disabled = false;
                        button.innerHTML = '<i class="w-icon-cart"></i><span>Add to Cart</span>';
                    };

                    // Disable button temporarily
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

                    if (typeof window.cartManager !== 'undefined' && typeof window.cartManager.addToCart === 'function') {
                        // Update cart manager to handle variants
                        window.cartManager
                            .addToCart(productId, quantity, button.closest('.product, .product-single'), selectedVariants, variantPrices)
                            .then(result => {
                                if (result && result.success) {
                                    //showNotification('Added to cart successfully', 'success');
                                } else {
                                    // Fallback if cartManager returns unexpected result
                                    return fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify(cartData)
                                        })
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data && data.success) {
                                                if (typeof window.updateCartCount === 'function') window.updateCartCount();
                                                //showNotification('Added to cart successfully', 'success');
                                            } else {
                                                throw new Error(data && data.message ? data.message : 'Add to cart failed');
                                            }
                                        });
                                }
                            })
                            .catch(error => {
                                console.error('Error adding to cart:', error);
                                //showNotification('Failed to add to cart. Please try again.', 'error');
                            })
                            .finally(doFinally);
                    } else {
                        // Direct fallback request with variant data
                        fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(cartData)
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data && data.success) {
                                    if (typeof window.updateCartCount === 'function') window.updateCartCount();
                                    showNotification('Added to cart successfully', 'success');
                                } else {
                                    throw new Error(data && data.message ? data.message : 'Add to cart failed');
                                }
                            })
                            .catch(error => {
                                console.error('Error adding to cart (fallback):', error);
                                showNotification('Failed to add to cart. Please try again.', 'error');
                            })
                            .finally(doFinally);
                    }
                }, {
                    capture: true
                });
            });

            // Handle buy now clicks for all matching buttons
            buyNowButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation(); // Stop other handlers
                    // Block when out of stock
                    var isOutOfStock = false;
                    try {
                        var stockEl = document.querySelector('.stock-unavailable');
                        isOutOfStock = !!stockEl || button.disabled || (document.querySelector('.btn-cart') && document.querySelector('.btn-cart').disabled);
                    } catch (err) {}
                    if (isOutOfStock) {
                        var modal = document.getElementById('outOfStockModal');
                        if (modal) {
                            modal.style.display = 'flex';
                            var closer = document.getElementById('closeOutOfStockModal');
                            if (closer) {
                                closer.onclick = function() {
                                    modal.style.display = 'none';
                                };
                            }
                            modal.onclick = function(ev) {
                                if (ev.target === modal) modal.style.display = 'none';
                            };
                        }
                        return;
                    }
                    // Dynamically validate all required variant selections
                    var missing = [];
                    try {
                        var colorOptions = document.querySelectorAll('.product-color-swatch .color');
                        var colorSelected = document.querySelector('.product-color-swatch .color.selected');
                        if (colorOptions.length > 0 && !colorSelected) missing.push('Color');

                        var sizeOptions = document.querySelectorAll('.product-size-swatch .size');
                        var sizeSelected = document.querySelector('.product-size-swatch .size.selected');
                        if (sizeOptions.length > 0 && !sizeSelected) missing.push('Size');

                        var variantGroups = document.querySelectorAll('.product-variant-swatch');
                        variantGroups.forEach(function(group) {
                            var hasOptions = group.querySelectorAll('.variant-option').length > 0;
                            var sel = group.querySelector('.variant-option.selected');
                            if (hasOptions && !sel) {
                                var labelEl = group.querySelector('label');
                                var label = labelEl ? labelEl.textContent.replace(':', '').trim() : 'Option';
                                missing.push(label);
                            }
                        });
                    } catch (e) {}

                    if (missing.length) {
                        var msg = 'Please select: ' + missing.join(', ');
                        if (typeof window.showNotification === 'function') {
                            window.showNotification(msg, 'error');
                        } else if (typeof window.showToast === 'function') {
                            window.showToast(msg, {
                                type: 'error'
                            });
                        } else {
                            console.warn(msg);
                        }
                        return; // stop Buy Now
                    }

                    // All required selections present; proceed with Buy Now -> add to cart then go to cart
                    {
                        const productId = button.getAttribute('data-product-id') || <?php echo $product['id']; ?>;
                        const container = button.closest('.product, .product-single, .product-details, .product-popup') || document;
                        const quantityInput = container.querySelector('.quantity');
                        const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;

                        // Reuse the same selection harvesting as Add to Cart
                        const selectedVariants = {};
                        const variantPrices = {};

                        function safeParseFloat(value) {
                            return value !== null && value !== undefined && value !== '' ? parseFloat(value) : null;
                        }

                        const selectedColorElement = document.querySelector('.product-color-swatch .color.selected');
                        if (selectedColorElement) {
                            const colorName = selectedColorElement.getAttribute('data-color');
                            const colorVariantId = selectedColorElement.getAttribute('data-variant-id');
                            const colorMrp = selectedColorElement.getAttribute('data-mrp');
                            const colorSelling = selectedColorElement.getAttribute('data-selling');
                            if (colorName) selectedVariants.color = colorName;
                            if (colorVariantId) selectedVariants.color_variant_id = colorVariantId;
                            if (colorMrp !== null) variantPrices.color_mrp = safeParseFloat(colorMrp);
                            if (colorSelling !== null) variantPrices.color_selling_price = safeParseFloat(colorSelling);
                        }

                        const selectedSizeElement = document.querySelector('.product-size-swatch .size.selected');
                        if (selectedSizeElement) {
                            const sizeName = selectedSizeElement.getAttribute('data-size');
                            const sizeVariantId = selectedSizeElement.getAttribute('data-variant-id');
                            const sizeMrp = selectedSizeElement.getAttribute('data-mrp');
                            const sizeSelling = selectedSizeElement.getAttribute('data-selling');
                            if (sizeName) selectedVariants.size = sizeName;
                            if (sizeVariantId) selectedVariants.size_variant_id = sizeVariantId;
                            if (sizeMrp !== null) variantPrices.size_mrp = safeParseFloat(sizeMrp);
                            if (sizeSelling !== null) variantPrices.size_selling_price = safeParseFloat(sizeSelling);
                        }

                        const selectedVariantElements = document.querySelectorAll('.product-variant-swatch .variant-option.selected');
                        selectedVariantElements.forEach(element => {
                            const attribute = element.getAttribute('data-attribute');
                            const value = element.getAttribute('data-value');
                            const variantId = element.getAttribute('data-variant-id');
                            const mrp = element.getAttribute('data-mrp');
                            const selling = element.getAttribute('data-selling');
                            if (attribute && value) {
                                const key = attribute.toLowerCase().replace(/\s+/g, '_');
                                selectedVariants[key] = value;
                                if (variantId) selectedVariants[key + '_variant_id'] = variantId;
                                if (mrp !== null) variantPrices[key + '_mrp'] = safeParseFloat(mrp);
                                if (selling !== null) variantPrices[key + '_selling_price'] = safeParseFloat(selling);
                            }
                        });

                        // Determine final price and source exactly like Add to Cart
                        let finalMrp = null;
                        let finalSellingPrice = null;
                        let lastSelectedVariant = null;

                        const allSelectedVariants = [];
                        if (selectedColorElement) {
                            const colorMrp = selectedColorElement.getAttribute('data-mrp');
                            const colorSelling = selectedColorElement.getAttribute('data-selling');
                            allSelectedVariants.push({
                                type: 'color',
                                element: selectedColorElement,
                                mrp: colorMrp,
                                selling: colorSelling,
                                timestamp: selectedColorElement.getAttribute('data-timestamp') || Date.now()
                            });
                        }
                        if (selectedSizeElement) {
                            const sizeMrp = selectedSizeElement.getAttribute('data-mrp');
                            const sizeSelling = selectedSizeElement.getAttribute('data-selling');
                            allSelectedVariants.push({
                                type: 'size',
                                element: selectedSizeElement,
                                mrp: sizeMrp,
                                selling: sizeSelling,
                                timestamp: selectedSizeElement.getAttribute('data-timestamp') || Date.now()
                            });
                        }
                        selectedVariantElements.forEach(element => {
                            const mrp = element.getAttribute('data-mrp');
                            const selling = element.getAttribute('data-selling');
                            allSelectedVariants.push({
                                type: 'other',
                                element: element,
                                mrp: mrp,
                                selling: selling,
                                timestamp: element.getAttribute('data-timestamp') || Date.now()
                            });
                        });

                        if (allSelectedVariants.length > 0) {
                            lastSelectedVariant = allSelectedVariants.reduce((latest, current) => {
                                return parseInt(current.timestamp) > parseInt(latest.timestamp) ? current : latest;
                            });
                            if (lastSelectedVariant.selling !== null && lastSelectedVariant.selling !== '') {
                                finalSellingPrice = safeParseFloat(lastSelectedVariant.selling);
                            }
                            if (lastSelectedVariant.mrp !== null && lastSelectedVariant.mrp !== '') {
                                finalMrp = safeParseFloat(lastSelectedVariant.mrp);
                            }
                        }

                        if (finalSellingPrice !== null) {
                            variantPrices.selling_price = finalSellingPrice;
                        }
                        if (finalMrp !== null) {
                            variantPrices.mrp = finalMrp;
                        }
                        if (lastSelectedVariant) {
                            variantPrices.price_source = lastSelectedVariant.type;
                            variantPrices.price_source_variant = lastSelectedVariant.element.getAttribute('data-value') ||
                                lastSelectedVariant.element.getAttribute('data-color') ||
                                lastSelectedVariant.element.getAttribute('data-size');
                        }

                        const cartData = {
                            action: 'add',
                            product_id: parseInt(productId),
                            quantity: parseInt(quantity),
                            selected_variants: selectedVariants,
                            variant_prices: variantPrices
                        };

                        // Disable button during operation
                        const originalHtml = button.innerHTML;
                        button.disabled = true;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

                        const handleSuccessUI = () => {
                            if (typeof window.updateCartCount === 'function') window.updateCartCount();
                            if (typeof window.showNotification === 'function') {
                                window.showNotification('Added to cart successfully', 'success');
                            }
                        };

                        if (typeof window.cartManager !== 'undefined' && typeof window.cartManager.addToCart === 'function') {
                            window.cartManager.addToCart(productId, quantity, null, selectedVariants, variantPrices)
                                .then(() => handleSuccessUI())
                                .catch(err => {
                                    console.error('Buy Now via cartManager failed:', err);
                                    if (typeof window.showNotification === 'function') {
                                        window.showNotification('Failed to add to cart. Please try again.', 'error');
                                    }
                                })
                                .finally(() => {
                                    button.disabled = false;
                                    button.innerHTML = originalHtml;
                                });
                        } else {
                            fetch('<?php echo USER_BASEURL; ?>/app/handlers/cart_handler.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json'
                                    },
                                    body: JSON.stringify(cartData)
                                })
                                .then(r => r.json())
                                .then(data => {
                                    if (data && data.success) {
                                        handleSuccessUI();
                                    } else {
                                        const msg = (data && data.message) ? data.message : 'Failed to add to cart.';
                                        if (typeof window.showNotification === 'function') {
                                            window.showNotification(msg, 'error');
                                        } else {
                                            console.error(msg);
                                        }
                                    }
                                })
                                .catch(err => {
                                    console.error('Buy Now add-to-cart error:', err);
                                    if (typeof window.showNotification === 'function') {
                                        window.showNotification('Network error. Please try again.', 'error');
                                    }
                                })
                                .finally(() => {
                                    button.disabled = false;
                                    button.innerHTML = originalHtml;
                                });
                        }
                    }
                }, {
                    capture: true
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stickyElement = document.querySelector('.containt-sticy2');

            window.addEventListener('scroll', function() {
                if (window.scrollY >= 60) {
                    stickyElement.classList.add('containt-sticy');
                } else {
                    stickyElement.classList.remove('containt-sticy');
                }
            });
        });

        // Notification function for product detail page
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `product-notification product-notification-${type}`;

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
    </script>

    <!-- Notification animations -->
    <style>
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</body>

</html>
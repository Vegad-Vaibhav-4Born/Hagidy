<?php require_once __DIR__ . '/includes/init.php';

// Function to get review count and average rating for a product
function getProductReviewData($product_id, $db_connection)
{
    $reviews_query = "SELECT * FROM reviews WHERE product_id = ? ORDER BY created_date DESC";
    $stmt = mysqli_prepare($db_connection, $reviews_query);
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $reviews_result = mysqli_stmt_get_result($stmt);
    $total_reviews = 0;
    $total_rating = 0;
    $average_rating = 0;
    $average_rating_percentage = 0;

    if ($reviews_result) {
        while ($review = mysqli_fetch_assoc($reviews_result)) {
            $total_reviews++;
            $rating = floatval($review['rating']);
            $total_rating += $rating;
        }
        mysqli_stmt_close($stmt);
    }

    // Calculate average rating
    if ($total_reviews > 0) {
        $average_rating = round($total_rating / $total_reviews, 1);
        $average_rating_percentage = $average_rating * 20; // Convert to percentage for display
    }

    return [
        'total_reviews' => $total_reviews,
        'average_rating' => $average_rating,
        'average_rating_percentage' => $average_rating_percentage
    ];
}

// Fetch completed orders to calculate top selling products
$orders_result = mysqli_query($db_connection, "SELECT * FROM `order` WHERE order_status = 'Delivered'");

// Accumulate total quantity sold per product
$product_sales = array();
while ($order = mysqli_fetch_assoc($orders_result)) {
    $products_json = $order['products'];
    $order_products = json_decode($products_json, true);
    if (is_array($order_products)) {
        foreach ($order_products as $op) {
            $pid = intval($op['product_id']);
            $qty = intval($op['quantity']);
            if ($pid > 0 && $qty > 0) {
                if (!isset($product_sales[$pid])) {
                    $product_sales[$pid] = 0;
                }
                $product_sales[$pid] += $qty;
            }
        }
    }
}

// Sort by sales descending and get top 5 product IDs
arsort($product_sales);
$top_pids = array_keys(array_slice($product_sales, 0, 5, true));

// Fetch product details for top products
$top_selling_products = null;
if (!empty($top_pids)) {
    $pids_str = implode(',', $top_pids);
    $products_query = "SELECT * FROM products WHERE status='approved' AND id IN ($pids_str) ORDER BY FIELD(id, $pids_str)";
    $top_selling_products = mysqli_query($db_connection, $products_query);
} else {
    // Fallback: fetch any 5 products if no sales data
    $top_selling_products = mysqli_query($db_connection, "SELECT * FROM products WHERE status='approved' LIMIT 5");
}


// just for You
// Fetch 6 products from the products table
$products_query_just_for_you = "SELECT id, product_name, selling_price, mrp, coin, images FROM products WHERE status='approved' ORDER BY created_date DESC LIMIT 6";
$products_result = mysqli_query($db_connection, $products_query_just_for_you);

// Group products into pairs for swiper slides (2 products per slide)
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}
$products_per_slide = 2;
$slides = array_chunk($products, $products_per_slide);




$brand_images = [];
$brand_q = mysqli_query($db_connection, "SELECT image_path FROM brand_images where status='Active' ORDER BY id DESC");
if ($brand_q) {
    while ($bi = mysqli_fetch_assoc($brand_q)) {
        if (!empty($bi['image_path'])) {
            $brand_images[] = $bi['image_path'];
        }
    }
}

// Fetch main banners for intro slider
$main_banners = [];
$banner_q = mysqli_query($db_connection, "SELECT image_path FROM banners WHERE type='main' AND status='Active' ORDER BY id DESC");
if ($banner_q) {
    while ($br = mysqli_fetch_assoc($banner_q)) {
        if (!empty($br['image_path'])) {
            $main_banners[] = $br['image_path'];
        }
    }
}

// Fetch second banners for sidebar small banners
$second_banners = [];
$second_q = mysqli_query($db_connection, "SELECT image_path FROM banners WHERE type='side' AND status='Active' ORDER BY id DESC LIMIT 2");
if ($second_q) {
    while ($sr = mysqli_fetch_assoc($second_q)) {
        if (!empty($sr['image_path'])) {
            $second_banners[] = $sr['image_path'];
        }
    }
}

// Fetch center banners for middle banner section
$center_banners = [];
$center_q = mysqli_query($db_connection, "SELECT image_path FROM banners WHERE type='center' AND status='Active' ORDER BY id DESC LIMIT 2");
if ($center_q) {
    while ($cr = mysqli_fetch_assoc($center_q)) {
        if (!empty($cr['image_path'])) {
            $center_banners[] = $cr['image_path'];
        }
    }
}
$products_recent_views = [];
$product_count = 0;

if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Logged-in user: Fetch recent views from database
    $user_id = intval($_SESSION['user_id']);
    $query = "SELECT p.id, p.product_name, p.selling_price, p.mrp, p.coin, p.images
              FROM recent_views rv
              JOIN products p ON rv.product_id = p.id
              WHERE rv.user_id = ?
              ORDER BY rv.created_date DESC
              LIMIT 4";
    $stmt = mysqli_prepare($db_connection, $query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $products_recent_views[] = $row;
    }
    $product_count = count($products_recent_views);
} else {
    // Non-logged-in user: Fetch from localStorage via JavaScript
    // Placeholder array, populated by JavaScript
    $products_recent_views = [];
}

// Compute Top Weekly Vendors based on delivered orders in the last 7 days
$top_weekly_vendors = [];
$weekly_vendor_sales = [];

$orders_weekly = mysqli_query($db_connection, "SELECT products FROM `order` WHERE order_status = 'Delivered' AND created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($orders_weekly) {
    while ($order = mysqli_fetch_assoc($orders_weekly)) {
        $order_products = json_decode($order['products'], true);
        if (is_array($order_products)) {
            foreach ($order_products as $op) {
                $vendor_id = isset($op['vendor_id']) ? intval($op['vendor_id']) : 0;
                $quantity = isset($op['quantity']) ? intval($op['quantity']) : 0;
                if ($vendor_id > 0 && $quantity > 0) {
                    if (!isset($weekly_vendor_sales[$vendor_id])) {
                        $weekly_vendor_sales[$vendor_id] = 0;
                    }
                    $weekly_vendor_sales[$vendor_id] += $quantity;
                }
            }
        }
    }
}

// Determine top 4 vendors by quantity sold
if (!empty($weekly_vendor_sales)) {
    arsort($weekly_vendor_sales);
    $top_vendor_ids = array_keys(array_slice($weekly_vendor_sales, 0, 4, true));

    if (!empty($top_vendor_ids)) {
        $ids_str = implode(',', array_map('intval', $top_vendor_ids));
        $vendors_rs = mysqli_query($db_connection, "SELECT id, business_name, banner_image FROM vendor_registration WHERE id IN ($ids_str) ORDER BY FIELD(id, $ids_str)");
        if ($vendors_rs) {
            while ($v = mysqli_fetch_assoc($vendors_rs)) {
                $vid = intval($v['id']);

                // Count total products for vendor
                $product_count_rs = mysqli_query($db_connection, "SELECT COUNT(*) as cnt FROM products WHERE status='approved' AND vendor_id = '$vid'");
                $vendor_product_count = 0;
                if ($product_count_rs && ($row = mysqli_fetch_assoc($product_count_rs))) {
                    $vendor_product_count = intval($row['cnt']);
                }

                // Fetch up to 3 product thumbnails for this vendor
                $thumbs = [];
                $thumb_rs = mysqli_query($db_connection, "SELECT images FROM products WHERE status='approved' AND vendor_id = '$vid' ORDER BY id DESC LIMIT 6");
                if ($thumb_rs) {
                    while ($prow = mysqli_fetch_assoc($thumb_rs)) {
                        $imgs = json_decode($prow['images'], true);
                        if (is_array($imgs)) {
                            foreach ($imgs as $img) {
                                if (!empty($img)) {
                                    $thumbs[] = htmlspecialchars($img);
                                    if (count($thumbs) >= 3)
                                        break 2;
                                }
                            }
                        }
                    }
                }

                if (empty($thumbs)) {
                    $thumbs = ['uploads/vendors/no-product.png'];
                }

                $banner_image = isset($v['banner_image']) ? trim($v['banner_image']) : '';
                $logoPath = !empty($banner_image) ? htmlspecialchars($banner_image) : ($thumbs[0] ?? 'uploads/vendors/no-product.png');

                $top_weekly_vendors[] = [
                    'id' => $vid,
                    'name' => $v['business_name'] ?? ('Vendor #' . $vid),
                    'product_count' => $vendor_product_count,
                    'thumbs' => $thumbs,
                    'logo' => $logoPath,
                    'sold_qty' => intval($weekly_vendor_sales[$vid] ?? 0)
                ];
            }
        }
    }
}

// Fallback: show any approved vendors if no weekly data
if (empty($top_weekly_vendors)) {
    $vendors_rs = mysqli_query($db_connection, "SELECT id, business_name, banner_image FROM vendor_registration WHERE status = 'approved' ORDER BY id DESC LIMIT 4");
    if ($vendors_rs) {
        while ($v = mysqli_fetch_assoc($vendors_rs)) {
            $vid = intval($v['id']);
            $product_count_rs = mysqli_query($db_connection, "SELECT COUNT(*) as cnt FROM products WHERE status='approved' AND vendor_id = '$vid'");
            $vendor_product_count = 0;
            if ($product_count_rs && ($row = mysqli_fetch_assoc($product_count_rs))) {
                $vendor_product_count = intval($row['cnt']);
            }
            $thumbs = [];
            $thumb_rs = mysqli_query($db_connection, "SELECT images FROM products WHERE status='approved' AND vendor_id = '$vid' ORDER BY id DESC LIMIT 6");
            if ($thumb_rs) {
                while ($prow = mysqli_fetch_assoc($thumb_rs)) {
                    $imgs = json_decode($prow['images'], true);
                    if (is_array($imgs)) {
                        foreach ($imgs as $img) {
                            if (!empty($img)) {
                                $thumbs[] = htmlspecialchars($img);
                                if (count($thumbs) >= 3)
                                    break 2;
                            }
                        }
                    }
                }
            }
            if (empty($thumbs)) {
                $thumbs = ['uploads/vendors/no-product.png'];
            }
            $banner_image = isset($v['banner_image']) ? trim($v['banner_image']) : '';
            $logoPath = !empty($banner_image) ? htmlspecialchars($banner_image) : ($thumbs[0] ?? 'uploads/vendors/no-product.png');

            $top_weekly_vendors[] = [
                'id' => $vid,
                'name' => $v['business_name'] ?? ('Vendor #' . $vid),
                'product_count' => $vendor_product_count,
                'thumbs' => $thumbs,
                'logo' => $logoPath,
                'sold_qty' => 0
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>HOME | HAGIDY</title>

    <meta name="keywords" content="Marketplace ecommerce responsive HTML5 Template" />
    <meta name="description" content="Wolmart is powerful marketplace &amp; ecommerce responsive Html5 Template.">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">

    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-regular-400.woff"
        as="font" type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-solid-900.woff2" as="font"
        type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-brands-400.woff2"
        as="font" type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>fonts/wolmart.ttf?png09e" as="font" type="font/ttf"
        crossorigin="anonymous">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/css/all.min.css">

    <!-- Plugins CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/animate/animate.min.css">
    <link rel="stylesheet" type="text/css"
        href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">

    <!-- Default CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/index-wishlist.css">



</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <!-- Start of Main -->
        <main class="main">
            <div class="container pb-2">
                <!-- End Of Category Wrapper -->

                <div class="intro-section mb-2">
                    <div class="row">
                        <div class="intro-wrapper col-lg-9 mt-4 mb-4">
                            <div class="swiper-container swiper-theme pg-inner pg-white animation-slider"
                                data-swiper-options="{
                                'spaceBetween': 0,
                                'slidesPerView': 1
                            }">
                                <div class="swiper-wrapper row gutter-no cols-1">
                                    <?php if (!empty($main_banners)) {
                                        foreach ($main_banners as $bn) {
                                            $bg = $superadmin_baseurl . $bn; ?>
                                            <div class="swiper-slide banner banner-fixed intro-slide br-sm"
                                                style="background-image: url(<?php echo htmlspecialchars($bg); ?>); background-color: #EAEAEA;">
                                                <div class="banner-content y-50">
                                                    <a href="shop.php"
                                                        class="btn btn-outline btn-dark btn-rounded btn-icon-right slide-animate"
                                                        data-animation-options="{'name': 'fadeInUpShorter', 'duration': '.5s', 'delay': '.3s'}">
                                                        Shop Now
                                                        <i class="w-icon-long-arrow-right"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php }
                                    } else { ?>
                                        <div class="swiper-slide banner banner-fixed intro-slide intro-slide1 br-sm "
                                            style="background-image: url(<?php echo PUBLIC_ASSETS; ?>images/demos/demo12/slides/intro-3.jpg); background-color: #3F3E3A;">
                                            <div class="banner-content y-50 text-right">
                                                <h3 class="banner-subtitle text-uppercase font-secondary font-weight-bolder slide-animate"
                                                    data-animation-options="{'name': 'fadeInLeftShorter', 'duration': '.5s', 'delay': '.2s'}">
                                                    From Online Store
                                                </h3>
                                                <h2 class="banner-title font-secondary text-capitalize text-white slide-animate"
                                                    data-animation-options="{'name': 'fadeInRightShorter', 'duration': '.5s', 'delay': '.4s'}">
                                                    Men's Lifestyle<br>
                                                    Collection
                                                </h2>
                                                <h4 class="banner-price-info font-weight-normal text-white ls-25 slide-animate"
                                                    data-animation-options="{'name': 'fadeInRightShorter', 'duration': '.5s', 'delay': '.4s'}">
                                                    Discount <span class="text-sky-blue font-weight-bolder">50%
                                                        OFF</span>This Week.
                                                </h4>
                                                <a href="shop.php"
                                                    class="btn btn-outline btn-white btn-rounded btn-icon-right slide-animate"
                                                    data-animation-options="{'name': 'fadeInUpShorter', 'duration': '.5s', 'delay': '.6s'}">
                                                    Shop Now
                                                    <i class="w-icon-long-arrow-right"></i>
                                                </a>
                                            </div>
                                        </div>

                                    <?php } ?>
                                </div>
                                <div class="swiper-pagination"></div>
                            </div>
                        </div>
                        <div class="intro-banner-wrapper col-lg-3 mt-4">
                            <?php if (!empty($second_banners)) {
                                foreach ($second_banners as $sb) {
                                    $src = $superadmin_baseurl . $sb; ?>
                                    <div class="banner banner-fixed intro-banner br-sm mb-4">
                                        <figure class="br-sm h-100">
                                            <img src="<?php echo htmlspecialchars($src); ?>" alt="Category Banner" width="680"
                                                height="180" style="background-color: #565960;" />
                                        </figure>
                                        <div class="banner-content">
                                            <a href="shop.php"
                                                class="btn btn-dark btn-link btn-slide-right btn-icon-right btn-infinite">
                                                Shop Now
                                                <i class="w-icon-long-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                            <?php }
                            } ?>
                        </div>
                    </div>
                </div>
                <!-- End of Intro-wrapper -->

                <div class="swiper-container swiper-theme icon-box-wrapper br-sm mt-0 mb-10 appear-animate"
                    data-swiper-options="{
                    'slidesPerView': 1,
                    'autoplay': { 'delay': 3000, 'disableOnInteraction': false },
                    'loop': true,
                    'breakpoints': {
                        '576': {
                            'slidesPerView': 2
                        },
                        '992': {
                            'slidesPerView': 3
                        },
                        '1200': {
                            'slidesPerView': 4
                        }
                    }}">
                    <div class="swiper-wrapper row cols-md-4 cols-sm-3 cols-1">
                        <div class="swiper-slide icon-box icon-box-side text-dark d-flex gap-2">
                            <span class="icon-box-icon icon-shipping mr-3">
                                <i class="w-icon-truck"></i>
                            </span>
                            <div class="icon-box-content">
                                <h4 class="icon-box-title font-weight-bolder">Free Shipping &amp; Returns</h4>
                                <p class="text-default">For all orders over ₹99</p>
                            </div>
                        </div>
                        <div class="swiper-slide icon-box icon-box-side text-dark">
                            <span class="icon-box-icon icon-payment mr-3 ">
                                <i class="w-icon-bag"></i>
                            </span>
                            <div class="icon-box-content">
                                <h4 class="icon-box-title font-weight-bolder">Secure Payment</h4>
                                <p class="text-default">We ensure secure payment</p>
                            </div>
                        </div>
                        <div class="swiper-slide icon-box icon-box-side text-dark icon-box-money d-flex gap-3">
                            <span class="icon-box-icon icon-money mr-3">
                                <i class="w-icon-money"></i>
                            </span>
                            <div class="icon-box-content">
                                <h4 class="icon-box-title font-weight-bolder">Cash Back Guarantee</h4>
                                <p class="text-default">Any back within 30 days</p>
                            </div>
                        </div>
                        <div class="swiper-slide icon-box icon-box-side text-dark icon-box-chat mt-0">
                            <span class="icon-box-icon icon-chat">
                                <i class="w-icon-chat"></i>
                            </span>
                            <div class="icon-box-content">
                                <h4 class="icon-box-title font-weight-bolder">Customer Support</h4>
                                <p class="text-default"> connect@hagidy.com</p>
                            </div>
                        </div>
                    </div>

                </div>
                <?php $categories_home = mysqli_query($db_connection, "SELECT * FROM category LIMIT 11"); ?>
                <div class="category-wrapper row cols-12 pt-3 pb-3">

                    <?php while ($category = mysqli_fetch_assoc($categories_home)) { ?>
                        <?php
                        // Use default image if category image is empty or null
                        $image = !empty($category['image']) ? htmlspecialchars($category['image']) : 'uploads/categories/no-Categories.png';
                        ?>
                        <div class="category category-ellipse category-img">
                            <figure class="category-media">
                                <a href="shop.php?category=<?php echo htmlspecialchars($category['id']); ?>" class="">
                                    <img src="<?php echo $superadmin_baseurl; ?><?php echo $image; ?>"
                                        alt="<?php echo htmlspecialchars($category['name']); ?>" />
                                </a>
                            </figure>
                            <div class="category-content">
                                <h4 class="category-name">
                                    <a href="shop.php?category=<?php echo htmlspecialchars($category['id']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                </h4>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="category category-ellipse ">
                        <a href="shop.php">
                            <div class="icon-box icon-colored-circle">
                                <span class="icon-box-icon mb-0 text-white">
                                    <i class="w-icon-hamburger"></i>
                                </span>
                            </div>
                        </a>
                        <div class="category-content">
                            <h4 class="category-name text-start">
                                <a href="shop.php">Categories</a>
                            </h4>
                        </div>
                    </div>
                </div>
                <!-- End of Iocn Box Wrapper -->

                <div class="banner-wrapper appear-animate row cols-md-2 mb-7">
                    <?php if (!empty($center_banners)) {
                        foreach ($center_banners as $cb) {
                            $src = $superadmin_baseurl . $cb; ?>
                            <div class="banner banner-fixed overlay-dark br-sm mt-2">
                                <figure class="br-sm">
                                    <img src="<?php echo htmlspecialchars($src); ?>" alt="Category Banner" width="680"
                                        height="180" style="background-color: #565960;" />
                                </figure>
                                <div class="banner-content y-50">
                                    <a href="shop.php"
                                        class="btn btn-sm btn-outline btn-dark btn-rounded btn-icon-right slide-animate">
                                        Shop Now
                                        <i class="w-icon-long-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                    <?php }
                    } ?>
                </div>
                <!-- End of Banner-wrapper -->

                <div class="title-link-wrapper title-select after-none appear-animate">
                    <h2 class="title font-secondary font-weight-bolder">Just For You</h2>
                    <a href="shop.php" class="font-weight-bold ls-25">
                        More Products
                        <i class="w-icon-long-arrow-right"></i>
                    </a>
                </div>

                <?php if (!empty($slides)) { ?>
                    <div class="product-wrapper row cols-xl-4 cols-lg-2 cols-md-2 cols-12">
                        <?php foreach ($slides as $slide_products) { ?>
                            <?php foreach ($slide_products as $product) {
                                // Handle images: assume 'images' is a JSON array of image paths
                                $images = json_decode($product['images'], true);
                                $primary_image = (is_array($images) && isset($images[0])) ? htmlspecialchars($images[0]) : 'uploads/vendors/no-product.png';
                                $hover_image = (is_array($images) && isset($images[1])) ? htmlspecialchars($images[1]) : 'uploads/vendors/no-product.png';

                                // Price formatting
                                // $selling_price = number_format($product['selling_price'], 2);
                                // $mrp = number_format($product['mrp'], 2);
                                $selling_price = $product['selling_price'];
                                $mrp = $product['mrp'];
                                $show_old_price = ($product['mrp'] > $product['selling_price']);

                                // Coin display
                                // $coin_display = !empty($product['coin']) ? '<div class="coin-img"><img src="' . PUBLIC_ASSETS . 'images/coin-hagidy.png" class="img-fluid" alt=""><h6>' . intval($product['coin']) . '</h6></div>' : '';
                                $coin_display = !empty($product['coin']) ? '<div class="coin-img"><img src="' . PUBLIC_ASSETS . 'images/coin-hagidy.png" class="img-fluid" alt=""><h6>' . number_format($product['coin'], 2) . '</h6></div>' : '';
                                $price_class = !empty($product['coin']) ? 'product-flex justify-content-between' : '';

                                // Get review data for this product
                                $review_data = getProductReviewData($product['id'], $db_connection);
                                $total_reviews = $review_data['total_reviews'];
                                $average_rating = $review_data['average_rating'];
                                $average_rating_percentage = $review_data['average_rating_percentage'];
                            ?>
                                <div class="product-wrap">

                                    <div class="product product-image-gap product-simple">
                                        <figure class="product-media">
                                            <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                                                <img src="<?php echo  $primary_image; ?>" alt="Product" width="295"
                                                    height="295" />
                                                <img src="<?php echo  $hover_image; ?>" alt="Product" width="295"
                                                    height="295" />
                                            </a>
                                            <div class="product-action-vertical visible">
                                                <a href="#" class="btn-product-icon btn-quickview" title="Quickview"
                                                    data-product-id="<?php echo $product['id']; ?>"><i
                                                        class="fa-regular fa-eye"></i></a>
                                            </div>
                                        </figure>
                                        <div class="product-details">
                                            <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist"
                                                data-product-id="<?php echo $product['id']; ?>"></a>
                                            <h4 class="product-name">
                                                <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </a>
                                            </h4>
                                            <div class="ratings-container">
                                                <div class="ratings-full">
                                                    <span class="ratings"
                                                        style="width: <?php echo $average_rating_percentage; ?>%;"></span>
                                                    <span class="tooltiptext tooltip-top"><?php echo $average_rating; ?>
                                                        Stars</span>
                                                </div>
                                                <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>"
                                                    class="rating-reviews">(<?php echo $total_reviews; ?> reviews)</a>
                                            </div>
                                            <div class="product-pa-wrapper">
                                                <div class="product-price <?php echo $price_class; ?>">
                                                    <div class="">
                                                        <ins class="new-price">₹<?php echo $selling_price; ?></ins>
                                                        <?php if ($show_old_price) { ?>
                                                            <del class="old-price">₹<?php echo $mrp; ?></del>
                                                        <?php } ?>
                                                    </div>
                                                    <?php echo $coin_display; ?>
                                                </div>
                                                <div class="product-action">
                                                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                                                    <a href="#" class="btn-cart btn-product btn btn-link btn-underline"
                                                        data-product-id="<?php echo $product['id']; ?>">Add To Cart</a>
                                                    <?php else: ?>
                                                    <a href="<?php echo PUBLIC_ASSETS; ?>ajax/login.html" class="btn-car btn btn-link btn-underline login sign-in">Add To
                                                        Cart</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>

                    </div>
                <?php } else {
                ?>
                    <div class="text-center">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/products/no-products.png" alt="No Product" width="200"
                            height="150" />
                        <h4 class="product-name">
                            <a href="shop.php">No Just For You Products</a>
                        </h4>
                    </div>
                <?php } ?>
                <div id="recent-views-section" style="<?php echo $product_count > 0 ? '' : 'display: none;'; ?>">
                    <div class="title-link-wrapper title-select after-none appear-animate">
                        <h2 class="title font-secondary font-weight-bolder">Your Recent Views</h2>
                    </div>

                    <div class="product-wrapper row cols-xl-4 cols-lg-2 cols-md-2 cols-12">


                        <?php if ($product_count > 0) { ?>
                            <?php foreach ($products_recent_views as $product) {
                                $images = json_decode($product['images'], true);
                                $primary_image = (is_array($images) && isset($images[0])) ? htmlspecialchars($images[0]) : 'uploads/vendors/no-product.png';
                                $hover_image = (is_array($images) && isset($images[1])) ? htmlspecialchars($images[1]) : 'uploads/vendors/no-product.png';
                                // $selling_price = number_format($product['selling_price'], 2);
                                // $mrp = number_format($product['mrp'], 2);
                                $selling_price = $product['selling_price'];
                                $mrp = $product['mrp'];
                                $show_old_price = ($product['mrp'] > $product['selling_price']);
                                // $coin_display = !empty($product['coin']) ? '<div class="coin-img"><img src="' . PUBLIC_ASSETS . 'images/coin-hagidy.png" class="img-fluid" alt=""><h6>' . intval($product['coin']) . '</h6></div>' : '';
                                $coin_display = !empty($product['coin']) ? '<div class="coin-img"><img src="' . PUBLIC_ASSETS . 'images/coin-hagidy.png" class="img-fluid" alt=""><h6>' . number_format($product['coin'], 2) . '</h6></div>' : '';
                                $price_class = !empty($product['coin']) ? 'product-flex justify-content-between' : '';

                                // Get review data for this product
                                $review_data = getProductReviewData($product['id'], $db_connection);
                                $total_reviews = $review_data['total_reviews'];
                                $average_rating = $review_data['average_rating'];
                                $average_rating_percentage = $review_data['average_rating_percentage'];
                            ?>
                                <div class="product-wrap">
                                    <div class="product product-image-gap product-simple">
                                        <figure class="product-media">
                                            <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                                                <img src="<?php echo $primary_image; ?>" alt="Product"
                                                    width="295" height="335" />
                                                <img src="<?php echo $hover_image; ?>" alt="Product" width="295"
                                                    height="335" />
                                            </a>
                                            <div class="product-action-vertical visible">
                                                <a href="#" class="btn-product-icon btn-quickview" title="Quickview"
                                                    data-product-id="<?php echo $product['id']; ?>"><i
                                                        class="fa-regular fa-eye"></i></a>
                                            </div>
                                        </figure>
                                        <div class="product-details">
                                            <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist"
                                                data-product-id="<?php echo $product['id']; ?>"></a>
                                            <h4 class="product-name">
                                                <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </a>
                                            </h4>
                                            <div class="ratings-container">
                                                <div class="ratings-full">
                                                    <span class="ratings"
                                                        style="width: <?php echo $average_rating_percentage; ?>%;"></span>
                                                    <span class="tooltiptext tooltip-top"><?php echo $average_rating; ?>
                                                        Stars</span>
                                                </div>
                                                <a href="product-detail.php?id=<?php echo htmlspecialchars($product['id']); ?>"
                                                    class="rating-reviews">(<?php echo $total_reviews; ?> reviews)</a>
                                            </div>
                                            <div class="product-pa-wrapper">
                                                <div class="product-price <?php echo $price_class; ?>">
                                                    <div class="">
                                                        <ins class="new-price">₹<?php echo $selling_price; ?></ins>
                                                        <?php if ($show_old_price) { ?>
                                                            <del class="old-price">₹<?php echo $mrp; ?></del>
                                                        <?php } ?>
                                                    </div>
                                                    <?php echo $coin_display; ?>
                                                </div>
                                                <div class="product-action">
                                                    <a href="#" class="btn-cart btn-product btn btn-link btn-underline"
                                                        data-product-id="<?php echo $product['id']; ?>">Add To Cart</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                        <div id="recent-views-container"
                            style="<?php echo $product_count > 0 ? 'display: none;' : ''; ?>"></div>
                    </div>
                </div>

                <!-- </div> -->

            </div>

        </main>
        <!-- End of Main -->

        <?php include __DIR__ . '/../includes/footer.php'; ?>
    </div>
    <!-- End of Page-wrapper -->



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
            <h3 style="margin:0 0 8px; font-weight:600; color:#333;">Product is out of stock</h3>
            <p style="margin:0 0 16px; color:#666;">Please check back later or explore similar products.</p>
            <a href="shop.php" class="btn btn-primary" style="padding:10px 18px; border-radius:8px;">Browse Products</a>
        </div>
    </div>


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
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/1-103x116.jpg"
                                    alt="Product Thumb" width="103" height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/2-103x116.jpg"
                                    alt="Product Thumb" width="103" height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/3-103x116.jpg"
                                    alt="Product Thumb" width="103" height="116">
                            </div>
                            <div class="product-thumb swiper-slide">
                                <img src="<?php echo PUBLIC_ASSETS; ?>images/products/popup/4-103x116.jpg"
                                    alt="Product Thumb" width="103" height="116">
                            </div>
                        </div>
                        <button class="swiper-button-next"></button>
                        <button class="swiper-button-prev"></button>
                    </div>
                </div>
            </div>
            <div class="col-md-6 overflow-hidden p-relative">
                <div class="product-details scrollable pl-0">
                    <h1 class="product-title">Electronics Black Wrist Watch</h1>


                    <hr class="product-divider">
                    <div class="product-price d-flex align-items-center"><ins class="new-price mr-2"> <sup
                                style="font-size: 18px;">₹</sup> 40.00</ins>
                        <span class="d-block product-price1"> Earn <img
                                src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid" alt=""> 3
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
                    <div class="mrp-price">
                        <h4>
                            M.R.P: <del class="old-price">₹54.69</del> <span>(62% off)</span>
                        </h4>
                    </div>

                    <div class="product-short-desc">
                        <ul class="list-type-check list-style-none">
                            <li>Ultrices eros in cursus turpis massa cursus mattis.</li>
                            <li>Volutpat ac tincidunt vitae semper quis lectus.</li>
                            <li>Aliquam id diam maecenas ultricies mi eget mauris.</li>
                        </ul>
                    </div>

                    <hr class="product-divider">

                    <div class="product-form product-variation-form product-color-swatch">
                        <label>Color:</label>
                        <div class="d-flex align-items-center product-variations">
                            <a href="#" class="color" style="background-color: #ffcc01"></a>
                            <a href="#" class="color" style="background-color: #ca6d00;"></a>
                            <a href="#" class="color" style="background-color: #1c93cb;"></a>
                            <a href="#" class="color" style="background-color: #ccc;"></a>
                            <a href="#" class="color" style="background-color: #333;"></a>
                        </div>
                    </div>
                    <div class="product-form product-variation-form product-size-swatch">
                        <label class="mb-1">Size:</label>
                        <div class="flex-wrap d-flex align-items-center product-variations">
                            <a href="#" class="size">Small</a>
                            <a href="#" class="size">Medium</a>
                            <a href="#" class="size">Large</a>
                            <a href="#" class="size">Extra Large</a>
                        </div>
                        <a href="#" class="product-variation-clean">Clean All</a>
                    </div>

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
                            <a href="#" class="btn-product-icon btn-wishlist w-icon-heart"
                                data-product-id=""><span></span></a>
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
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/parallax/parallax.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery.plugin/jquery.plugin.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/imagesloaded/imagesloaded.pkgd.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/skrollr/skrollr.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/zoom/jquery.zoom.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery.countdown/jquery.countdown.min.js"></script>

    <!-- Main JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>

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
                    // console.log('Wishlist action:', { productId, isInWishlist, buttonClasses: button.className });

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
                // console.log('Updating button state:', { isInWishlist, buttonElement: button, iconElement: icon });

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

                // console.log(`Updated ${allWishlistButtons.length} product cards for product ID ${productId}`);
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
                                            /* ignore */
                                        }

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
                fetch('<?php echo USER_BASEURL; ?>app/handlers/cart_handler.php', {
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

    <!-- Add to Cart Functionality for Product Cards -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize add to cart functionality for all product cards
            initProductCardAddToCart();

            // Re-initialize when new content is loaded (for dynamic content like recent views)
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        // Check if any new product cards were added
                        const hasNewProducts = Array.from(mutation.addedNodes).some(node =>
                            node.nodeType === 1 && (
                                node.classList?.contains('product') ||
                                node.querySelector?.('.product')
                            )
                        );
                        if (hasNewProducts) {
                            setTimeout(initProductCardAddToCart, 100);
                        }
                    }
                });
            });

            // Start observing
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });

        function initProductCardAddToCart() {
            // Find all add to cart buttons in product cards
            const addToCartButtons = document.querySelectorAll('.product .btn-cart, .product-wrap .btn-cart');

            addToCartButtons.forEach(button => {
                // Skip if already initialized
                if (button.hasAttribute('data-cart-initialized')) {
                    return;
                }

                button.setAttribute('data-cart-initialized', 'true');

                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const productId = this.getAttribute('data-product-id');
                    if (!productId) {
                        console.error('No product ID found for add to cart button');
                        return;
                    }

                    // Check stock via API before attempting add-to-cart
                    fetch('<?php echo USER_BASEURL; ?>app/Helpers/get_products.php?ids=' + encodeURIComponent(productId))
                        .then(r => r.json())
                        .then(data => {
                            const product = data && data.products && data.products.find(p => String(p.id) === String(productId));
                            const inventory = product && typeof product.Inventory !== 'undefined' ? parseInt(product.Inventory) : null;
                            if (inventory !== null && inventory <= 0) {
                                // Show Out of Stock modal
                                const oos = document.getElementById('outOfStockModal');
                                if (oos) {
                                    oos.style.display = 'flex';
                                    const closer = document.getElementById('closeOutOfStockModal');
                                    if (closer) closer.onclick = function() {
                                        oos.style.display = 'none';
                                    };
                                    oos.onclick = function(ev) {
                                        if (ev.target === oos) oos.style.display = 'none';
                                    };
                                }
                                return Promise.reject('OOS');
                            }
                            return Promise.resolve();
                        })
                        .then(() => {
                            // Get product context for notifications
                            const productCard = this.closest('.product, .product-wrap');
                            if (!productCard) {
                                console.error('No product card found for context');
                                return;
                            }

                            // Use global CartManager if available
                            if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                                // Show loading state
                                const originalText = this.innerHTML;
                                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                this.style.pointerEvents = 'none';

                                window.cartManager.addToCart(productId, 1, productCard)
                                    .then(result => {
                                        if (result && result.success) {
                                            // console.log('Successfully added to cart:', productId);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error adding to cart:', error);
                                        showFallbackNotification('Failed to add to cart. Please try again.', 'error');
                                    })
                                    .finally(() => {
                                        // Reset button state
                                        this.innerHTML = originalText;
                                        this.style.pointerEvents = '';
                                    });
                            } else {
                                // Fallback: direct AJAX request
                                addToCartFallback(productId, this, productCard);
                            }
                        })
                        .catch(err => {
                            if (String(err) !== 'OOS') {
                                console.warn('Stock check failed or other error:', err);
                            }
                        });
                });
            });
        }

        function addToCartFallback(productId, button, productCard) {
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
            button.style.pointerEvents = 'none';

            fetch('<?php echo USER_BASEURL; ?>app/handlers/cart_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'add',
                        product_id: parseInt(productId),
                        quantity: 1
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showFallbackNotification('Product added to cart successfully!', 'success');

                        // Update cart count if function exists
                        if (typeof window.updateCartCount === 'function') {
                            window.updateCartCount();
                        }
                    } else {
                        showFallbackNotification(data.message || 'Failed to add product to cart', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error adding to cart:', error);
                    showFallbackNotification('An error occurred while adding to cart', 'error');
                })
                .finally(() => {
                    // Reset button state
                    button.innerHTML = originalText;
                    button.style.pointerEvents = '';
                });
        }

        function showFallbackNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `cart-notification cart-notification-${type}`;

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
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                animation: slideInRight 0.3s ease;
                cursor: pointer;
                background: ${type === 'success' ? 'linear-gradient(135deg, #2ecc71, #27ae60)' :
                    type === 'error' ? 'linear-gradient(135deg, #e74c3c, #c0392b)' :
                        'linear-gradient(135deg, #3498db, #2980b9)'};
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
    </script>



    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // For non-logged-in users, fetch recent views from localStorage
            <?php if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) { ?>
                // console.log('Non-logged-in user');
                const recentViews = JSON.parse(localStorage.getItem('recentViews') || '[]');
                // console.log('Recent Views:', recentViews);
                if (recentViews.length > 0) {
                    // Fetch product details via AJAX
                    fetch('<?php echo USER_BASEURL; ?>/app/api/recent-views-section.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                product_ids: recentViews.slice(0, 4)
                            })
                        })
                        .then(response => {
                            // console.log('API Response Status:', response.status);
                            if (!response.ok) {
                                throw new Error('Network response was not ok: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // console.log('API Response Data:', data);
                            const container = document.getElementById('recent-views-container');
                            container.innerHTML = '';
                            if (data.products && data.products.length > 0) {
                                data.products.forEach(product => {
                                    const images = product.images || [];
                                    const primaryImage = images[0] || 'uploads/vendors/no-product.png';
                                    const hoverImage = images[1] || 'uploads/vendors/no-product.png';
                                    const priceClass = product.coin ? 'product-flex justify-content-between' : '';
                                    const coinDisplay = product.coin ? `<div class="coin-img"><img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid" alt=""><h6>${parseInt(product.coin)}</h6></div>` : '';
                                    const oldPrice = product.mrp > product.selling_price ? `<del class="old-price">₹${parseFloat(product.mrp).toFixed(2)}</del>` : '';

                                    // Review data (will be populated by API)
                                    const totalReviews = product.total_reviews || 0;
                                    const averageRating = product.average_rating || 0;
                                    const averageRatingPercentage = product.average_rating_percentage || 0;
                                    const html = `
                    <div class="product-wrap mr-3">
                        <div class="product product-image-gap product-simple">
                            <figure class="product-media">
                                        <a href="product-detail.php?id=${product.id}">
                                            <img src="<?php echo ${primaryImage} ?>" alt="Product" width="295" height="335" />
                                            <img src="<?php echo ${hoverImage} ?>" alt="Product" width="295" height="335" />
                                </a>
                                <div class="product-action-vertical visible">
                                            <a href="#" class="btn-product-icon btn-quickview" title="Quickview" data-product-id="${product.id}"><i class="fa-regular fa-eye"></i></a>
                                </div>
                            </figure>
                            <div class="product-details">
                                <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist" data-product-id="${product.id}"></a>
                                <h4 class="product-name">
                                            <a href="product-detail.php?id=${product.id}">${product.product_name}</a>
                                </h4>
                                <div class="ratings-container">
                                    <div class="ratings-full">
                                                <span class="ratings" style="width: ${averageRatingPercentage}%;"></span>
                                        <span class="tooltiptext tooltip-top">${averageRating} Stars</span>
                                    </div>
                                            <a href="product-detail.php?id=${product.id}" class="rating-reviews">(${totalReviews} reviews)</a>
                                </div>
                                <div class="product-pa-wrapper">
                                            <div class="product-price ${priceClass}">
                                                <ins class="new-price">₹${parseFloat(product.selling_price).toFixed(2)}</ins>
                                                ${oldPrice}
                                                ${coinDisplay}
                                    </div>
                                    <div class="product-action">
                                        <a href="#" class="btn-cart btn-product btn btn-link btn-underline" data-product-id="${product.id}">Add To Cart</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                        `;
                                    container.innerHTML += html;
                                });
                                container.style.display = 'flex';
                                const sectionEl = document.getElementById('recent-views-section');
                                if (sectionEl) sectionEl.style.display = '';

                                // Initialize wishlist functionality for dynamically loaded content
                                if (window.wishlistManager) {
                                    window.wishlistManager.initDynamicWishlistButtons();
                                }
                            } else {
                                container.style.display = 'none';
                                const sectionEl = document.getElementById('recent-views-section');
                                if (sectionEl) sectionEl.style.display = 'none';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching recent views:', error);
                            const container = document.getElementById('recent-views-container');
                            if (container) container.style.display = 'none';
                            const sectionEl = document.getElementById('recent-views-section');
                            if (sectionEl) sectionEl.style.display = 'none';
                        });
                } else {
                    const container = document.getElementById('recent-views-container');
                    if (container) container.style.display = 'none';
                    const sectionEl = document.getElementById('recent-views-section');
                    if (sectionEl) sectionEl.style.display = 'none';
                }
            <?php } ?>
        });
    </script>
</body>

</html>
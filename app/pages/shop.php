<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/../Helpers/shop_functions.php';

// HTML escape helper (ensure available on this page)
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// Get URL parameters
$category_id = isset($_GET['category']) ? (int) $_GET['category'] : null;
$sub_category_id = isset($_GET['subcategory']) ? (int) $_GET['subcategory'] : null;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['count']) ? (int) $_GET['count'] : 12; // Products per page
$allowed_counts = [9, 12, 24, 36];
if (!in_array($limit, $allowed_counts, true)) {
    $limit = 12;
}
$offset = ($page - 1) * $limit;
$sort_by = isset($_GET['orderby']) ? $_GET['orderby'] : 'default';
// sanitize orderby
$allowed_sort = ['default', 'popularity', 'rating', 'date', 'price-low', 'price-high'];
if (!in_array($sort_by, $allowed_sort, true)) {
    $sort_by = 'default';
}

// Get filter parameters
$filters = [];
if (isset($_GET['min_price']) && $_GET['min_price'] !== '' && $_GET['min_price'] > 0) {
    $filters['min_price'] = (float) $_GET['min_price'];
}
if (isset($_GET['max_price']) && $_GET['max_price'] !== '' && $_GET['max_price'] > 0) {
    $filters['max_price'] = (float) $_GET['max_price'];
}
if (isset($_GET['min_coins']) && $_GET['min_coins'] !== '' && $_GET['min_coins'] >= 0) {
    $filters['min_coins'] = (int) $_GET['min_coins'];
}
if (isset($_GET['max_coins']) && $_GET['max_coins'] !== '' && $_GET['max_coins'] > 0) {
    $filters['max_coins'] = (int) $_GET['max_coins'];
}
// New minimum rating filter (e.g., 3.5 and above)
if (isset($_GET['min_rating']) && $_GET['min_rating'] !== '') {
    $filters['min_rating'] = (float) $_GET['min_rating'];
}

// Get data from database
$categories = getAllCategories($db_connection);
$products = getProductsByCategory($db_connection, $category_id, $sub_category_id, $limit, $offset, $filters, $sort_by);
$total_products = getProductCount($db_connection, $category_id, $sub_category_id, $filters);
$total_pages = ceil($total_products / $limit);

// Get subcategories for selected category
$subcategories = [];
if ($category_id) {
    $subcategories = getSubcategoriesByCategory($db_connection, $category_id);
}

// Get filter data
$price_ranges = getPriceFilterRanges($db_connection, $category_id, $sub_category_id);
$coin_ranges = getCoinFilterRanges($db_connection, $category_id, $sub_category_id);
$rating_ranges = getRatingFilterData($db_connection, $category_id, $sub_category_id);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>SHOP | HAGIDY</title>

    <meta name="keywords" content="" />
    <meta name="description" content="">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">

    <link rel="preload" href="./assetsvendor/fontawesome-free/webfonts/fa-regular-400.woff" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-solid-900.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/webfonts/fa-brands-400.woff2" as="font" type="font/woff2"
        crossorigin="anonymous">
    <link rel="preload" href="<?php echo PUBLIC_ASSETS; ?>fonts/wolmart.ttf?png09e" as="font" type="font/ttf" crossorigin="anonymous">

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/fontawesome-free/css/all.min.css">

    <!-- Plugins CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/animate/animate.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">

    <!-- Default CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/shop.css">

</head>

<body>
    <div class="page-wrapper">
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <!-- Start of Main -->
        <main class="main">
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li>Shop</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb-nav -->
            <div class="container">
                <div class="shop-content row gutter-lg">
                    <!-- Start of Sidebar, Shop Sidebar -->
                    <aside class="sidebar shop-sidebar sticky-sidebar-wrapper sidebar-fixed">
                        <div class="sidebar-overlay"></div>
                        <a class="sidebar-close" href="#"><i class="close-icon"></i></a>
                        <div class="sidebar-content scrollable">
                            <div class="sticky-sidebar">
                                <div class="filter-actions">
                                    <label>Filter :</label>
                                    <a href="shop.php" class="btn btn-dark btn-link " id="clear-all-filters">Clear
                                        All</a>
                                </div>
                                <div class="widget widget-collapsible">
                                    <h3 class="widget-title"><label>All Categories</label></h3>
                                    <div class="category-scroll-container">
                                        <ul class="widget-body filter-items search-ul">
                                            <?php if (!empty($categories)): ?>
                                                <?php foreach ($categories as $index => $category): ?>
                                                    <?php
                                                    $category_product_count = getCategoryProductCount($db_connection, $category['id']);
                                                    $category_subcategories = getSubcategoriesByCategory($db_connection, $category['id']);
                                                    $is_active = ($category_id == $category['id']);
                                                    ?>
                                                    <li>
                                                        <div
                                                            class="accordion Accordion <?php echo $is_active ? '__active' : ''; ?>">
                                                            <button
                                                                class="w-100 accordion-head AccordionTrigger d-flex justify-content-between align-items-center"
                                                                id="accordion-button-<?php echo $category['id']; ?>"
                                                                aria-controls="accordion-panel-<?php echo $category['id']; ?>"
                                                                data-category-id="<?php echo $category['id']; ?>">
                                                                <a href="?category=<?php echo $category['id']; ?>"
                                                                    class="category-link <?php echo $is_active ? 'active' : ''; ?>">
                                                                    <?php echo e($category['name']); ?>
                                                                    <!-- <span class="product-count">(<?php echo $category_product_count; ?>)</span> -->
                                                                </a>
                                                                <i class="fas fa-chevron-down accordion-icon"></i>
                                                            </button>
                                                            <?php if (!empty($category_subcategories)): ?>
                                                                <div class="accordion-content AccordionContent"
                                                                    id="accordion-panel-<?php echo $category['id']; ?>"
                                                                    aria-labelledby="accordion-button-<?php echo $category['id']; ?>">
                                                                    <div class="usercontent">
                                                                        <ul class="d-block subcategory-list">
                                                                            <?php foreach ($category_subcategories as $subcategory): ?>
                                                                                <?php
                                                                                $subcategory_product_count = getSubcategoryProductCount($db_connection, $subcategory['id'], $category['id']);
                                                                                $is_subcategory_active = ($sub_category_id == $subcategory['id']);
                                                                                ?>
                                                                                <li>
                                                                                    <a href="?category=<?php echo $category['id']; ?>&subcategory=<?php echo $subcategory['id']; ?>"
                                                                                        class="subcategory-link <?php echo $is_subcategory_active ? 'active' : ''; ?>">
                                                                                        <?php echo e($subcategory['name']); ?>
                                                                                        <span
                                                                                            class="product-count">(<?php echo $subcategory_product_count; ?>)</span>
                                                                                    </a>
                                                                                </li>
                                                                            <?php endforeach; ?>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li>
                                                    <p class="text-muted">No categories found</p>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <div class="widget widget-collapsible">
                                    <h3 class="widget-title"><label>Price</label></h3>
                                    <div class="widget-body">
                                        <ul class="filter-items search-ul">
                                            <?php if (!empty($price_ranges)): ?>
                                                <?php foreach ($price_ranges as $range): ?>
                                                    <?php
                                                    $range_parts = explode('-', $range['price_range']);
                                                    $min_price = $range_parts[0];
                                                    $max_price = isset($range_parts[1]) ? $range_parts[1] : '';

                                                    $filter_url = '?' . http_build_query(array_merge($_GET, [
                                                        'min_price' => $min_price,
                                                        'max_price' => $max_price,
                                                        'page' => 1
                                                    ]));
                                                    ?>
                                                    <li class="filter-item">
                                                        <a href="<?php echo $filter_url; ?>">
                                                            ₹<?php echo $range['price_range']; ?>
                                                        </a>
                                                        <span class="filter-count"><?php echo $range['count']; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li>
                                                    <p class="text-muted">No price ranges found</p>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                        <form class="price-range" method="GET">
                                            <?php if ($category_id): ?>
                                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                            <?php endif; ?>
                                            <?php if ($sub_category_id): ?>
                                                <input type="hidden" name="subcategory"
                                                    value="<?php echo $sub_category_id; ?>">
                                            <?php endif; ?>
                                            <input type="number" name="min_price" class="min_price text-center"
                                                placeholder="₹min"
                                                value="<?php echo isset($filters['min_price']) ? $filters['min_price'] : ''; ?>">
                                            <span class="delimiter">-</span>
                                            <input type="number" name="max_price" class="max_price text-center"
                                                placeholder="₹max"
                                                value="<?php echo isset($filters['max_price']) ? $filters['max_price'] : ''; ?>">
                                            <button type="submit" class="btn btn-primary btn-rounded">Go</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="widget widget-collapsible">
                                    <h3 class="widget-title"><label>Purchase coins</label></h3>
                                    <div class="widget-body">
                                        <ul class="filter-items search-ul">
                                            <?php if (!empty($coin_ranges)): ?>
                                                <?php foreach ($coin_ranges as $range): ?>
                                                    <?php
                                                    $coin_range_str = $range['coin_range'] ?? ''; // fallback to empty string
                                                    if (!empty($coin_range_str)) {
                                                        $range_parts = explode('-', $coin_range_str);
                                                        $min_coins = $range_parts[0] ?? 0;
                                                        $max_coins = $range_parts[1] ?? $min_coins;
                                                    } else {
                                                        $min_coins = 0;
                                                        $max_coins = 0;
                                                    }


                                                    if ($min_coins == 0 && $max_coins == 0) {
                                                        continue;
                                                    }
                                                    $filter_url = '?' . http_build_query(array_merge($_GET, [
                                                        'min_coins' => $min_coins,
                                                        'max_coins' => $max_coins,
                                                        'page' => 1
                                                    ]));
                                                    ?>
                                                    <li class="filter-item">
                                                        <a href="<?php echo $filter_url; ?>">
                                                            <?php echo !empty($coin_range_str) ? $coin_range_str : "0"; ?> Coins
                                                        </a>
                                                        <span class="filter-count"><?php echo $range['count']; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li>
                                                    <p class="text-muted">No coin ranges found</p>
                                                </li>
                                            <?php endif; ?>

                                        </ul>
                                        <form class="price-range" method="GET">
                                            <?php if ($category_id): ?>
                                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                            <?php endif; ?>
                                            <?php if ($sub_category_id): ?>
                                                <input type="hidden" name="subcategory"
                                                    value="<?php echo $sub_category_id; ?>">
                                            <?php endif; ?>
                                            <input type="number" name="min_coins" class="min_price text-center"
                                                placeholder="min"
                                                value="<?php echo isset($filters['min_coins']) ? $filters['min_coins'] : ''; ?>">
                                            <span class="delimiter">-</span>
                                            <input type="number" name="max_coins" class="max_price text-center"
                                                placeholder="max"
                                                value="<?php echo isset($filters['max_coins']) ? $filters['max_coins'] : ''; ?>">
                                            <button type="submit" class="btn btn-primary btn-rounded">Go</button>
                                        </form>
                                    </div>
                                </div>
                                <!--<div class="widget widget-collapsible">
                                <h3 class="widget-title"><label>Rating</label></h3>
                                    <div class="widget-body">
                                        <?php
                                        // Checklist options: X and above
                                        $rating_checklist = [
                                            '4.5' => '4.5 and above',
                                            '4.0' => '4.0 and above',
                                            '3.5' => '3.5 and above',
                                            '3.0' => '3.0 and above',
                                            '2.0' => '2.0 and above'
                                        ];
                                        $current_min_rating = isset($_GET['min_rating']) ? (string) $_GET['min_rating'] : '';
                                        ?>
                                        <form class="rating-range" method="GET">
                                            <?php if ($category_id): ?>
                                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                                            <?php endif; ?>
                                            <?php if ($sub_category_id): ?>
                                                <input type="hidden" name="subcategory" value="<?php echo $sub_category_id; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($filters['min_price'])): ?>
                                                <input type="hidden" name="min_price" value="<?php echo (int) $filters['min_price']; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($filters['max_price'])): ?>
                                                <input type="hidden" name="max_price" value="<?php echo (int) $filters['max_price']; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($filters['min_coins'])): ?>
                                                <input type="hidden" name="min_coins" value="<?php echo (int) $filters['min_coins']; ?>">
                                            <?php endif; ?>
                                            <?php if (isset($filters['max_coins'])): ?>
                                                <input type="hidden" name="max_coins" value="<?php echo (int) $filters['max_coins']; ?>">
                                            <?php endif; ?>
                                            <ul class="filter-items rating-checklist mt-1">
                                                <?php foreach ($rating_checklist as $value => $label): ?>
                                            <li class="filter-item">
                                                    <label>
                                                        <input type="radio" name="min_rating" value="<?php echo $value; ?>" <?php echo ($current_min_rating === (string) $value ? 'checked' : ''); ?>>
                                                        <span class="rating-label"><?php echo $label; ?></span>
                                                    </label>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                            <div class="mt-2 d-flex align-items-center gap-2">
                                                <button type="submit" class="btn btn-primary btn-rounded">Go</button>
                                                <?php if (!empty($current_min_rating)): ?>
                                                    <a href="?<?php echo http_build_query(array_diff_key($_GET, ['min_rating' => ''])); ?>" class="btn btn-dark btn-link ml-2">Clear</a>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>-->
                            </div>
                        </div>
                    </aside>
                    <!-- End of Shop Sidebar -->
                    <div class="main-content">
                        <nav class="toolbox sticky-toolbox sticky-content fix-top">
                            <div class="toolbox-left">
                                <a href="#"
                                    class="btn btn-primary btn-outline btn-rounded left-sidebar-toggle btn-icon-left d-block d-lg-none"><i
                                        class="w-icon-category"></i><span>Filters</span></a>
                                <div class="toolbox-item toolbox-sort select-box text-dark">
                                    <label>Sort By :</label>
                                    <form method="GET" id="sort-form">
                                        <?php if ($category_id): ?><input type="hidden" name="category"
                                                value="<?php echo $category_id; ?>"><?php endif; ?>
                                        <?php if ($sub_category_id): ?><input type="hidden" name="subcategory"
                                                value="<?php echo $sub_category_id; ?>"><?php endif; ?>
                                        <?php if (isset($filters['min_price'])): ?><input type="hidden" name="min_price"
                                                value="<?php echo (int) $filters['min_price']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['max_price'])): ?><input type="hidden" name="max_price"
                                                value="<?php echo (int) $filters['max_price']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['min_coins'])): ?><input type="hidden" name="min_coins"
                                                value="<?php echo (int) $filters['min_coins']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['max_coins'])): ?><input type="hidden" name="max_coins"
                                                value="<?php echo (int) $filters['max_coins']; ?>"><?php endif; ?>
                                        <?php if (isset($_GET['min_rating']) && $_GET['min_rating'] !== ''): ?><input
                                                type="hidden" name="min_rating"
                                                value="<?php echo htmlspecialchars((string) $_GET['min_rating']); ?>"><?php endif; ?>
                                        <?php if ($limit): ?><input type="hidden" name="count"
                                                value="<?php echo (int) $limit; ?>"><?php endif; ?>
                                        <select name="orderby" class="form-control" id="sort-select"
                                            onchange="this.form.submit()">
                                            <option value="default" <?php echo $sort_by == 'default' ? 'selected="selected"' : ''; ?>>Default sorting</option>
                                            <option value="popularity" <?php echo $sort_by == 'popularity' ? 'selected="selected"' : ''; ?>>Sort by popularity</option>
                                            <option value="rating" <?php echo $sort_by == 'rating' ? 'selected="selected"' : ''; ?>>Sort by average rating</option>
                                            <option value="date" <?php echo $sort_by == 'date' ? 'selected="selected"' : ''; ?>>Sort by latest</option>
                                            <option value="price-low" <?php echo $sort_by == 'price-low' ? 'selected="selected"' : ''; ?>>Sort by price: low to high</option>
                                            <option value="price-high" <?php echo $sort_by == 'price-high' ? 'selected="selected"' : ''; ?>>Sort by price: high to low</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            <div class="toolbox-right">
                                <div class="toolbox-item toolbox-show select-box mr-0">
                                    <form method="GET" id="count-form">
                                        <?php if ($category_id): ?><input type="hidden" name="category"
                                                value="<?php echo $category_id; ?>"><?php endif; ?>
                                        <?php if ($sub_category_id): ?><input type="hidden" name="subcategory"
                                                value="<?php echo $sub_category_id; ?>"><?php endif; ?>
                                        <?php if ($sort_by): ?><input type="hidden" name="orderby"
                                                value="<?php echo htmlspecialchars($sort_by); ?>"><?php endif; ?>
                                        <?php if (isset($filters['min_price'])): ?><input type="hidden" name="min_price"
                                                value="<?php echo (int) $filters['min_price']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['max_price'])): ?><input type="hidden" name="max_price"
                                                value="<?php echo (int) $filters['max_price']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['min_coins'])): ?><input type="hidden" name="min_coins"
                                                value="<?php echo (int) $filters['min_coins']; ?>"><?php endif; ?>
                                        <?php if (isset($filters['max_coins'])): ?><input type="hidden" name="max_coins"
                                                value="<?php echo (int) $filters['max_coins']; ?>"><?php endif; ?>
                                        <?php if (isset($_GET['min_rating']) && $_GET['min_rating'] !== ''): ?><input
                                                type="hidden" name="min_rating"
                                                value="<?php echo htmlspecialchars((string) $_GET['min_rating']); ?>"><?php endif; ?>
                                        <select name="count" class="form-control" id="show-select"
                                            onchange="this.form.submit()">
                                            <option value="9" <?php echo $limit == 9 ? 'selected="selected"' : ''; ?>>Show
                                                9</option>
                                            <option value="12" <?php echo $limit == 12 ? 'selected="selected"' : ''; ?>>
                                                Show 12</option>
                                            <option value="24" <?php echo $limit == 24 ? 'selected="selected"' : ''; ?>>
                                                Show 24</option>
                                            <option value="36" <?php echo $limit == 36 ? 'selected="selected"' : ''; ?>>
                                                Show 36</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                        </nav>

                        <!-- Debug Information (remove in production) -->
                        <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                            <div class="alert alert-info mb-3">
                                <strong>Debug Info:</strong><br>
                                Sort By: <?php echo htmlspecialchars($sort_by); ?><br>
                                Filters: <?php echo htmlspecialchars(json_encode($filters)); ?><br>
                                Category ID: <?php echo $category_id; ?><br>
                                Sub Category ID: <?php echo $sub_category_id; ?><br>
                                Total Products: <?php echo $total_products; ?><br>
                                Page: <?php echo $page; ?><br>
                                Limit: <?php echo $limit; ?><br>
                                Offset: <?php echo $offset; ?>
                            </div>
                        <?php endif; ?>

                        <div class="product-wrapper row cols-xl-4 cols-lg-2 cols-md-2 cols-12">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $product_images = getProductImages($product['images']);
                                    $main_image = getProductImageUrl($product['images'], 0);
                                    $second_image = getProductImageUrl($product['images'], 1);
                                    $discount_percentage = calculateDiscountPercentage($product['mrp'], $product['selling_price']);
                                    $product_link = getProductLink($product['id']);

                                    // Debug: Log product data for troubleshooting
                                    // error_log("Product ID: " . $product['id'] . ", Price: " . $product['selling_price'] . ", Sort: " . $sort_by);
                                    ?>
                                    <div class="product-wrap">
                                        <div class="product product-image-gap product-simple">
                                            <figure class="product-media">
                                                <a href="<?php echo $product_link; ?>">
                                                    <img src="<?php echo  $main_image; ?>"
                                                        alt="<?php echo e($product['product_name']); ?>" width="295"
                                                        height="335" />
                                                    <?php if ($second_image && $second_image !== $main_image): ?>
                                                        <img src="<?php echo  $second_image; ?>"
                                                            alt="<?php echo e($product['product_name']); ?>" width="295"
                                                            height="335" />
                                                    <?php endif; ?>
                                                </a>

                                                <div class="product-action-vertical visible">
                                                    <a href="#" class="btn-product-icon btn-quickview" title="Quickview"
                                                        data-product-id="<?php echo $product['id']; ?>">
                                                        <i class="fa-regular fa-eye"></i>
                                                    </a>
                                                </div>
                                            </figure>
                                            <div class="product-details">
                                                <a href="#" class="btn-wishlist w-icon-heart" title="Add to wishlist"
                                                    data-product-id="<?php echo $product['id']; ?>"></a>
                                                <h4 class="product-name">
                                                    <a
                                                        href="<?php echo $product_link; ?>"><?php echo e($product['product_name']); ?></a>
                                                </h4>
                                                <?php
                                                $product_rating = getProductRating($db_connection, $product['id']);
                                                ?>
                                                <div class="ratings-container">
                                                    <div class="ratings-full">
                                                        <span class="ratings"
                                                            style="width: <?php echo $product_rating['rating_percentage']; ?>%;"></span>
                                                        <span class="tooltiptext tooltip-top"></span>
                                                    </div>
                                                    <a href="<?php echo $product_link; ?>"
                                                        class="rating-reviews">(<?php echo $product_rating['review_count']; ?>
                                                        reviews)</a>
                                                </div>
                                                <div class="product-pa-wrapper">
                                                    <div
                                                        class="product-price <?php echo $product['coin'] > 0 ? 'product-flex justify-content-between' : ''; ?>">
                                                        <!-- <ins class="new-price"><?php echo formatPrice($product['selling_price']); ?></ins> -->
                                                        <?php //if ($product['mrp'] > $product['selling_price']): 
                                                        ?>
                                                        <!-- <del class="old-price"><?php // echo formatPrice($product['mrp']); 
                                                                                    ?></del> -->
                                                        <?php // endif; 
                                                        ?>
                                                        <div class="">
                                                            <ins class="new-price"><?php echo $product['selling_price']; ?></ins>
                                                            <?php if ($product['mrp'] > $product['selling_price']): ?>
                                                                <del class="old-price"><?php echo $product['mrp']; ?></del>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($product['coin'] > 0): ?>
                                                            <div class="coin-img">
                                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="img-fluid" alt="">
                                                                <h6><?php echo $product['coin']; ?></h6>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="product-action">
                                                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                                                            <a href="#" class="btn-cart btn-product btn btn-link btn-underline" data-product-id="<?php echo $product['id']; ?>">
                                                                Add To Cart
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="<?php echo PUBLIC_ASSETS; ?>ajax/login.html" class="btn-car btn btn-link btn-underline login sign-in">Add To Cart</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12 text-center py-5">
                                    <div class="no-products-found">
                                        <img src="<?php echo $vendor_baseurl . 'uploads/vendors/product_not_found.jpeg'; ?>"
                                            alt="No products found" width="200" height="200" class="mb-3">
                                        <h4>No Products Found</h4>
                                        <p class="text-muted">Sorry, no products found for the selected category.</p>
                                        <a href="shop.php" class="btn btn-primary">View All Products</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($total_products > 0): ?>
                            <div class="toolbox toolbox-pagination justify-content-between">
                                <p class="showing-info mb-2 mb-sm-0">
                                    Showing<span><?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total_products); ?>
                                        of <?php echo $total_products; ?></span>Products
                                </p>
                                <?php if ($total_pages > 1): ?>
                                    <ul class="pagination">
                                        <li class="prev <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <?php if ($page > 1): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                                    aria-label="Previous">
                                                    <i class="w-icon-long-arrow-left"></i>Prev
                                                </a>
                                            <?php else: ?>
                                                <a href="#" aria-label="Previous" tabindex="-1" aria-disabled="true">
                                                    <i class="w-icon-long-arrow-left"></i>Prev
                                                </a>
                                            <?php endif; ?>
                                        </li>

                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);

                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link"
                                                    href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="next <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                                    aria-label="Next">
                                                    Next<i class="w-icon-long-arrow-right"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="#" aria-label="Next" tabindex="-1" aria-disabled="true">
                                                    Next<i class="w-icon-long-arrow-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
        <!-- End of Main -->

        <!-- Wishlist Functionality (copied from index reference) -->
        <script>
            // Wishlist Management System
            (function() {
                if (window.wishlistManager) return; // avoid re-defining
                class WishlistManager {
                    constructor() {
                        this.init();
                    }
                    init() {
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
                    initWishlistButtons() {
                        const wishlistButtons = document.querySelectorAll('.btn-wishlist');
                        wishlistButtons.forEach(button => {
                            if (!button.hasAttribute('data-wishlist-initialized')) {
                                button.setAttribute('data-wishlist-initialized', 'true');
                                button.addEventListener('click', (e) => this.handleWishlistClick(e));
                                this.setInitialWishlistClass(button);
                            }
                        });
                    }
                    setInitialWishlistClass(button) {
                        if (!button.classList.contains('w-icon-heart') && !button.classList.contains('w-icon-heart-full')) {
                            button.classList.add('w-icon-heart');
                        }
                        button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');
                        if (!button.classList.contains('w-icon-heart')) {
                            button.classList.add('w-icon-heart');
                        }
                    }
                    async handleWishlistClick(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        const button = event.currentTarget;
                        // Prevent double-submit
                        if (button.getAttribute('data-busy') === 'true') return;
                        const productId = this.getProductId(button);
                        if (!productId) {
                            this.showNotification('Unable to identify product.', 'error');
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

                        button.setAttribute('data-busy', 'true');
                        this.setButtonLoading(button, true);
                        try {
                            const isInWishlist = await this.safeIsInWishlist(productId);
                            if (isInWishlist) {
                                await this.safeRemoveFromWishlist(productId, button);
                            } else {
                                await this.safeAddToWishlist(productId, button);
                            }
                        } catch (e) {
                            console.error('Wishlist error:', e);
                            console.error('Product ID:', productId);
                            console.error('User logged in:', this.isUserLoggedIn());
                            console.error('Error stack:', e.stack);
                            console.error('Button element:', button);
                            this.showNotification('We hit a temporary issue. Please try again.', 'error');
                        } finally {
                            this.setButtonLoading(button, false);
                            button.removeAttribute('data-busy');
                        }
                    }
                    getProductId(button) {
                        let productId = button.getAttribute('data-product-id');
                        if (!productId) {
                            const productContainer = button.closest('.product, .product-wrap');
                            if (productContainer) {
                                const productLink = productContainer.querySelector('a[href*="product-detail.php?id="]');
                                if (productLink) {
                                    const match = productLink.href.match(/id=(\d+)/);
                                    if (match) productId = match[1];
                                }
                            }
                        }
                        return productId ? parseInt(productId) : null;
                    }
                    isUserLoggedIn() {
                        return <?php echo isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 ? 'true' : 'false'; ?>;
                    }
                    async safeAddToWishlist(productId, button) {
                        try {
                            if (this.isUserLoggedIn()) {
                                const {
                                    ok,
                                    data,
                                    message
                                } = await this.requestJson('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                    action: 'add',
                                    product_id: productId
                                });
                                if (ok && data && data.success) {
                                    this.updateButtonState(button, true);
                                    this.updateAllProductCards(productId, true);
                                    this.showNotification('Product added to wishlist', 'success');
                                    this.updateWishlistCount();
                                } else {
                                    this.showNotification((data && data.message) || message || 'Unable to add to wishlist', 'error');
                                }
                            } else {
                                this.addToLocalWishlist(productId);
                                this.updateButtonState(button, true);
                                this.updateAllProductCards(productId, true);
                                this.showNotification('Product added to wishlist', 'success');
                                this.updateWishlistCount();
                            }
                        } catch (error) {
                            console.error('Error adding to wishlist:', error);
                            this.showNotification('Failed to add to wishlist. Please try again.', 'error');
                        }
                    }
                    async safeRemoveFromWishlist(productId, button) {
                        try {
                            if (this.isUserLoggedIn()) {
                                const {
                                    ok,
                                    data,
                                    message
                                } = await this.requestJson('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                    action: 'remove',
                                    product_id: productId
                                });
                                if (ok && data && data.success) {
                                    this.updateButtonState(button, false);
                                    this.updateAllProductCards(productId, false);
                                    this.showNotification('Product removed from your wishlist', 'error');
                                    this.updateWishlistCount();
                                } else {
                                    this.showNotification((data && data.message) || message || 'Unable to remove from wishlist', 'error');
                                }
                            } else {
                                this.removeFromLocalWishlist(productId);
                                this.updateButtonState(button, false);
                                this.updateAllProductCards(productId, false);
                                this.showNotification('Product removed from your wishlist', 'error');
                                this.updateWishlistCount();
                            }
                        } catch (error) {
                            console.error('Error removing from wishlist:', error);
                            this.showNotification('Failed to remove from wishlist. Please try again.', 'error');
                        }
                    }
                    async safeIsInWishlist(productId) {
                        try {
                            if (this.isUserLoggedIn()) {
                                const {
                                    ok,
                                    data
                                } = await this.requestJson('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                    action: 'check',
                                    product_id: productId
                                });
                                return ok && data && data.success ? !!data.in_wishlist : false;
                            } else {
                                return this.isInLocalWishlist(productId);
                            }
                        } catch (error) {
                            console.error('Error checking wishlist status:', error);
                            return false;
                        }
                    }
                    async requestJson(url, payload) {
                        try {
                            const res = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify(payload)
                            });
                            const text = await res.text();
                            let data = null;
                            let message = '';
                            try {
                                data = text ? JSON.parse(text) : null;
                            } catch (parseErr) {
                                message = 'Invalid server response';
                            }
                            if (!res.ok) {
                                return {
                                    ok: false,
                                    data,
                                    message: message || ('HTTP ' + res.status)
                                };
                            }
                            return {
                                ok: true,
                                data,
                                message
                            };
                        } catch (err) {
                            return {
                                ok: false,
                                data: null,
                                message: 'Network error'
                            };
                        }
                    }
                    addToLocalWishlist(productId) {
                        try {
                            let w = this.getLocalWishlist();
                            if (!w.includes(productId)) {
                                w.push(productId);
                                localStorage.setItem('guest_wishlist', JSON.stringify(w));
                            }
                        } catch (error) {
                            console.error('Error adding to local wishlist:', error);
                        }
                    }
                    removeFromLocalWishlist(productId) {
                        try {
                            let w = this.getLocalWishlist().filter(id => id !== productId);
                            localStorage.setItem('guest_wishlist', JSON.stringify(w));
                        } catch (error) {
                            console.error('Error removing from local wishlist:', error);
                        }
                    }
                    isInLocalWishlist(productId) {
                        try {
                            return this.getLocalWishlist().includes(productId);
                        } catch (error) {
                            console.error('Error checking local wishlist:', error);
                            return false;
                        }
                    }
                    getLocalWishlist() {
                        try {
                            const w = localStorage.getItem('guest_wishlist');
                            return w ? JSON.parse(w) : [];
                        } catch (error) {
                            console.error('Error getting local wishlist:', error);
                            return [];
                        }
                    }
                    updateButtonState(button, inWl) {
                        const icon = button.querySelector('i, .w-icon-heart, .w-icon-heart-full') || button;
                        if (inWl) {
                            button.classList.add('active', 'in-wishlist');
                            button.classList.remove('w-icon-heart');
                            button.classList.add('w-icon-heart-full');
                            button.style.setProperty('color', '#ff4757', 'important');
                            if (icon && icon !== button) icon.style.setProperty('color', '#ff4757', 'important');
                        } else {
                            button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');
                            button.classList.add('w-icon-heart');
                            button.style.removeProperty('color');
                            if (icon && icon !== button) icon.style.removeProperty('color');
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
                    setButtonLoading(button, isLoading) {
                        button.style.opacity = isLoading ? '0.6' : '';
                        button.style.pointerEvents = isLoading ? 'none' : '';
                    }
                    async checkWishlistStatus() {
                        const buttons = document.querySelectorAll('.btn-wishlist[data-wishlist-initialized="true"]');
                        for (const button of buttons) {
                            const productId = this.getProductId(button);
                            if (!productId) {
                                this.updateButtonState(button, false);
                                continue;
                            }
                            try {
                                const inWl = await this.safeIsInWishlist(productId);
                                this.updateButtonState(button, inWl);
                            } catch {
                                this.updateButtonState(button, false);
                            }
                        }
                    }
                    showNotification(message, type = 'info') {
                        const n = document.createElement('div');
                        n.className = `wishlist-notification wishlist-notification-${type}`;
                        const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
                        n.innerHTML = `<span style="margin-right:8px;font-weight:bold;">${icon}</span>${message}`;
                        n.style.cssText = 'position:fixed;top:20px;right:20px;color:#fff;padding:16px 24px;z-index:10000;font-size:14px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation: slideInRight .3s ease;cursor:pointer;background:' + (type === 'success' ? 'linear-gradient(135deg,#2ecc71,#27ae60)' : type === 'error' ? 'linear-gradient(135deg,#e74c3c,#c0392b)' : 'linear-gradient(135deg,#3498db,#2980b9)');
                        document.body.appendChild(n);
                        n.addEventListener('click', () => {
                            n.remove();
                        });
                        setTimeout(() => {
                            if (n.parentNode) {
                                n.style.animation = 'slideOutRight .3s ease';
                                setTimeout(() => n.remove(), 300);
                            }
                        }, 4000);
                    }
                    initDynamicWishlistButtons() {
                        this.initWishlistButtons();
                        setTimeout(() => this.checkWishlistStatus(), 100);
                    }

                    // Update wishlist count method
                    updateWishlistCount() {
                        const countElement = document.getElementById('wishlist-count');
                        if (!countElement) return;

                        // Check if user is logged in
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                            // Logged in user - get count from database
                            fetch('<?php echo USER_BASEURL; ?>/app/handlers/wishlist_handler.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                    },
                                    body: JSON.stringify({
                                        action: 'get_count'
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const count = data.count || 0;
                                        countElement.textContent = count;
                                        countElement.style.display = count > 0 ? 'inline' : 'none';

                                        // Call global header update function if available
                                        if (typeof window.updateHeaderWishlistCount === 'function') {
                                            window.updateHeaderWishlistCount();
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error getting wishlist count:', error);
                                });
                        <?php else: ?>
                            // Guest user - get count from localStorage
                            const wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                            const count = wishlist.length;
                            countElement.textContent = count;
                            countElement.style.display = count > 0 ? 'inline' : 'none';

                            // Call global header update function if available
                            if (typeof window.updateHeaderWishlistCount === 'function') {
                                window.updateHeaderWishlistCount();
                            }
                        <?php endif; ?>
                    }
                }
                window.wishlistManager = new WishlistManager();

                // Debug: Log wishlist manager initialization
                console.log('WishlistManager initialized:', window.wishlistManager);
            })();
        </script>

        <!-- Quickview JavaScript (copied from index reference) -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function waitForWolmart() {
                    if (typeof Wolmart !== 'undefined' && Wolmart.$body) {
                        initQuickview();
                    } else {
                        setTimeout(waitForWolmart, 100);
                    }
                }

                function initQuickview() {
                    Wolmart.$body.on('click', '.btn-quickview', function(event) {
                        event.preventDefault();
                        event.stopImmediatePropagation();
                        const quickviewBtn = this;
                        const productId = quickviewBtn.getAttribute('data-product-id');
                        if (!productId) return;
                        quickviewBtn.style.opacity = '0.6';
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
                                        try {
                                            if (typeof Wolmart !== 'undefined' && Wolmart.$body && Wolmart.$body.off) {
                                                Wolmart.$body.off('click', '.product-popup .btn-cart');
                                            }
                                        } catch (e) {}

                                        if (typeof Wolmart.initProductSingle === 'function') {
                                            Wolmart.initProductSingle();
                                        }

                                        initImageErrorHandling();
                                        initModalQuantityButtons();
                                        initModalImageNavigation();

                                        if (window.wishlistManager) {
                                            window.wishlistManager.initDynamicWishlistButtons();
                                        }

                                        // Initialize quickview modal functionality from quickview.php
                                        setTimeout(function() {
                                            if (typeof initQuickviewModal === 'function') {
                                                initQuickviewModal();
                                            }
                                        }, 50);

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

                                        quickviewBtn.style.opacity = '';
                                    }
                                }
                            }, 'quickview');
                        } else {
                            createFallbackModal(productId);
                            quickviewBtn.style.opacity = '';
                        }
                    });
                }

                function initImageErrorHandling() {
                    const modalImages = document.querySelectorAll('.product-popup img');
                    modalImages.forEach(function(img) {
                        if (!img.hasAttribute('data-error-handled')) {
                            img.setAttribute('data-error-handled', 'true');
                            img.onerror = function() {
                                if (!this.hasAttribute('data-fallback-used')) {
                                    this.setAttribute('data-fallback-used', 'true');
                                    this.src = 'uploads/vendors/no-product.png';
                                    this.style.opacity = '0.8';
                                    this.title = 'Image not available - showing default image';
                                }
                            };
                        }
                    });
                }

                function initModalQuantityButtons() {
                    const modalRoot = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
                    if (!modalRoot) return;

                    const quantityInputs = modalRoot.querySelectorAll('.quantity');
                    quantityInputs.forEach(function(input) {
                        const plus = input.parentNode.querySelector('.quantity-plus');
                        const minus = input.parentNode.querySelector('.quantity-minus');

                        if (plus && !plus.hasAttribute('data-modal-plus-listener-added')) {
                            plus.setAttribute('data-modal-plus-listener-added', 'true');
                            plus.addEventListener('click', function(e) {
                                e.preventDefault();
                                const val = parseInt(input.value) || 1;
                                const max = parseInt(input.getAttribute('max')) || 100000;
                                if (val < max) input.value = val + 1;
                            });
                        }

                        if (minus && !minus.hasAttribute('data-modal-minus-listener-added')) {
                            minus.setAttribute('data-modal-minus-listener-added', 'true');
                            minus.addEventListener('click', function(e) {
                                e.preventDefault();
                                const val = parseInt(input.value) || 1;
                                const min = parseInt(input.getAttribute('min')) || 1;
                                if (val > min) input.value = val - 1;
                            });
                        }
                    });
                    initModalCartButtons();
                }

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
                            addToCartFallback(productId, quantity, cartButton);
                        }
                    }, {
                        once: false,
                        capture: true
                    });
                }

                function addToCartFallback(productId, quantity, button) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                    button.disabled = true;
                    fetch('cart_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'add',
                                product_id: parseInt(productId),
                                quantity: quantity
                            })
                        })
                        .then(r => r.json()).then(d => {
                            if (d.success) {
                                showFallbackNotification('Product added to cart successfully!', 'success');
                                if (typeof window.updateCartCount === 'function') {
                                    window.updateCartCount();
                                }
                            } else {
                                showFallbackNotification(d.message || 'Failed to add product to cart', 'error');
                            }
                        })
                        .catch(() => showFallbackNotification('An error occurred while adding to cart', 'error'))
                        .finally(() => {
                            button.innerHTML = originalText;
                            button.disabled = false;
                        });
                }

                function showFallbackNotification(message, type = 'info') {
                    const n = document.createElement('div');
                    n.className = `cart-notification cart-notification-${type}`;
                    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
                    n.innerHTML = `<span style="margin-right:8px;font-weight:bold;">${icon}</span>${message}`;
                    n.style.cssText = 'position:fixed;top:20px;right:20px;color:#fff;padding:16px 24px;z-index:10000;font-size:14px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation: slideInRight .3s ease;cursor:pointer;background:' + (type === 'success' ? 'linear-gradient(135deg,#2ecc71,#27ae60)' : 'linear-gradient(135deg,#e74c3c,#c0392b)');
                    document.body.appendChild(n);
                    n.addEventListener('click', () => {
                        n.remove();
                    });
                    setTimeout(() => {
                        if (n.parentNode) {
                            n.style.animation = 'slideOutRight .3s ease';
                            setTimeout(() => n.remove(), 300);
                        }
                    }, 4000);
                }

                function initModalImageNavigation() {
                    // Wait a bit for modal to fully load
                    setTimeout(function() {
                        // Find elements in the modal
                        const modal = document.querySelector('.product-popup');
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

                function createFallbackModal(productId) {
                    const overlay = document.createElement('div');
                    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;';

                    const modal = document.createElement('div');
                    modal.style.cssText = 'background:#fff;border-radius:8px;max-width:90%;max-height:90%;overflow:auto;position:relative;';

                    const closeBtn = document.createElement('button');
                    closeBtn.innerHTML = '×';
                    closeBtn.style.cssText = 'position:absolute;top:10px;right:15px;background:none;border:none;font-size:24px;cursor:pointer;z-index:10000;';

                    closeBtn.onclick = () => {
                        // Clean up modal state before removing
                        const modalRoot = modal.querySelector('.product-popup');
                        if (modalRoot) {
                            modalRoot.removeAttribute('data-quickview-initialized');
                            modalRoot.removeAttribute('data-modal-cart-initialized');

                            const cartBtn = modalRoot.querySelector('.btn-cart');
                            if (cartBtn) cartBtn.removeAttribute('data-cart-listener-added');

                            const plusBtns = modalRoot.querySelectorAll('.quantity-plus');
                            plusBtns.forEach(btn => btn.removeAttribute('data-plus-listener-added'));

                            const minusBtns = modalRoot.querySelectorAll('.quantity-minus');
                            minusBtns.forEach(btn => btn.removeAttribute('data-minus-listener-added'));
                        }
                        document.body.removeChild(overlay);
                    };

                    fetch('quickview.php?id=' + productId)
                        .then(r => r.text())
                        .then(html => {
                            modal.innerHTML = html;
                            modal.appendChild(closeBtn);
                            setTimeout(function() {
                                initImageErrorHandling();
                                initModalQuantityButtons();
                                initModalImageNavigation();
                                if (window.wishlistManager) {
                                    window.wishlistManager.initDynamicWishlistButtons();
                                }
                                // Initialize quickview modal functionality from quickview.php
                                if (typeof initQuickviewModal === 'function') {
                                    initQuickviewModal();
                                }
                            }, 100);
                        })
                        .catch(() => {
                            modal.innerHTML = '<div class="p-4 text-center text-danger">Error loading product details</div>';
                            modal.appendChild(closeBtn);
                        });

                    overlay.appendChild(modal);
                    document.body.appendChild(overlay);

                    overlay.onclick = (e) => {
                        if (e.target === overlay) {
                            // Clean up modal state before removing
                            const modalRoot = modal.querySelector('.product-popup');
                            if (modalRoot) {
                                modalRoot.removeAttribute('data-quickview-initialized');
                                modalRoot.removeAttribute('data-modal-cart-initialized');

                                const cartBtn = modalRoot.querySelector('.btn-cart');
                                if (cartBtn) cartBtn.removeAttribute('data-cart-listener-added');

                                const plusBtns = modalRoot.querySelectorAll('.quantity-plus');
                                plusBtns.forEach(btn => btn.removeAttribute('data-plus-listener-added'));

                                const minusBtns = modalRoot.querySelectorAll('.quantity-minus');
                                minusBtns.forEach(btn => btn.removeAttribute('data-minus-listener-added'));
                            }
                            document.body.removeChild(overlay);
                        }
                    };
                }
                waitForWolmart();
            });
        </script>

        <!-- Product Card Add-To-Cart (consistent with index) -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function initProductCardAddToCart() {
                    const buttons = document.querySelectorAll('.product .btn-cart, .product-wrap .btn-cart');
                    buttons.forEach(button => {
                        if (button.hasAttribute('data-cart-initialized')) return;
                        button.setAttribute('data-cart-initialized', 'true');
                        button.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            const productId = this.getAttribute('data-product-id');
                            if (!productId) return;
                            const productCard = this.closest('.product, .product-wrap');
                            // Stock check before adding
                            fetch('<?php echo USER_BASEURL; ?>app/Helpers/get_products.php?ids=' + encodeURIComponent(productId))
                                .then(r => r.json())
                                .then(data => {
                                    const p = data && data.products && data.products.find(pp => String(pp.id) === String(productId));
                                    const inv = p && typeof p.Inventory !== 'undefined' ? parseInt(p.Inventory) : null;
                                    if (inv !== null && inv <= 0) {
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
                                    if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                                        const originalText = this.innerHTML;
                                        this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                        this.style.pointerEvents = 'none';
                                        window.cartManager.addToCart(productId, 1, productCard).finally(() => {
                                            this.innerHTML = originalText;
                                            this.style.pointerEvents = '';
                                        });
                                    } else {
                                        addToCartFallback(productId, this, productCard);
                                    }
                                })
                                .catch(err => {
                                    if (String(err) !== 'OOS') {
                                        console.warn('Stock check failed:', err);
                                    }
                                });
                        }, {
                            capture: true
                        });
                    });
                }

                function addToCartFallback(productId, button, productCard) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                    button.style.pointerEvents = 'none';
                    fetch('cart_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'add',
                                product_id: parseInt(productId),
                                quantity: 1
                            })
                        })
                        .then(r => r.json()).then(d => {
                            if (d.success) {
                                showFallbackNotification('Product added to cart successfully!', 'success');
                                if (typeof window.updateCartCount === 'function') {
                                    window.updateCartCount();
                                }
                            } else {
                                showFallbackNotification(d.message || 'Failed to add product to cart', 'error');
                            }
                        })
                        .catch(() => showFallbackNotification('An error occurred while adding to cart', 'error'))
                        .finally(() => {
                            button.innerHTML = originalText;
                            button.style.pointerEvents = '';
                        });
                }

                function showFallbackNotification(message, type = 'info') {
                    const n = document.createElement('div');
                    n.className = `cart-notification cart-notification-${type}`;
                    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
                    n.innerHTML = `<span style="margin-right:8px;font-weight:bold;">${icon}</span>${message}`;
                    n.style.cssText = 'position:fixed;top:20px;right:20px;color:#fff;padding:16px 24px;z-index:10000;font-size:14px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);animation: slideInRight .3s ease;cursor:pointer;background:' + (type === 'success' ? 'linear-gradient(135deg,#2ecc71,#27ae60)' : 'linear-gradient(135deg,#e74c3c,#c0392b)');
                    document.body.appendChild(n);
                    n.addEventListener('click', () => {
                        n.remove();
                    });
                    setTimeout(() => {
                        if (n.parentNode) {
                            n.style.animation = 'slideOutRight .3s ease';
                            setTimeout(() => n.remove(), 300);
                        }
                    }, 4000);
                }
                initProductCardAddToCart();
                // Re-init on dynamic content if needed
                const observer = new MutationObserver(() => {
                    setTimeout(initProductCardAddToCart, 100);
                });
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        </script>
        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <!-- End of Footer -->
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
    <div class="product product-single product-popup" id="quickview-modal">
        <!-- Dynamic content will be loaded here -->
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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const accordions = document.querySelectorAll('.Accordion');

            accordions.forEach((accordion) => {
                const accordionTrigger = accordion.querySelector('.AccordionTrigger');
                const accordionContent = accordion.querySelector('.AccordionContent');

                // Apply ARIA and display values on load
                if (accordionTrigger.classList.contains('__active')) {
                    accordionTrigger.setAttribute('aria-expanded', 'true');
                    accordionContent.style.display = 'block';
                    accordionContent.setAttribute('aria-hidden', 'false');
                    accordionContent.style.height = `${accordionContent.scrollHeight}px`;
                } else {
                    accordionTrigger.setAttribute('aria-expanded', 'false');
                    accordionContent.style.display = 'none';
                    accordionContent.setAttribute('aria-hidden', 'true');
                }

                // On click of accordion trigger
                accordionTrigger.addEventListener('click', (e) => {
                    e.preventDefault();

                    if (accordionTrigger.classList.contains('__active')) {
                        closeAccordion(accordionTrigger, accordionContent);
                    } else {
                        openAccordion(accordionTrigger, accordionContent);

                        // Close sibling accordions
                        const siblingAccordions = Array.from(
                            accordion.parentNode.querySelectorAll('.Accordion')
                        ).filter((sibling) => {
                            const siblingTrigger = sibling.querySelector('.AccordionTrigger');
                            return (
                                sibling !== accordion &&
                                !sibling.classList.contains('AccordionManualClose') &&
                                siblingTrigger &&
                                siblingTrigger.getAttribute('aria-expanded') === 'true'
                            );
                        });

                        siblingAccordions.forEach((sibling) => {
                            const siblingTrigger = sibling.querySelector('.AccordionTrigger');
                            const siblingContent = sibling.querySelector('.AccordionContent');
                            closeAccordion(siblingTrigger, siblingContent);
                        });
                    }
                });
            });

            // Slide down animation
            function openAccordion(trigger, content) {
                trigger.setAttribute('aria-expanded', 'true');
                trigger.classList.add('__active');
                content.setAttribute('aria-hidden', 'false');
                content.style.display = 'block';

                const height = content.scrollHeight;
                content.style.height = '0';
                content.style.overflow = 'hidden';
                requestAnimationFrame(() => {
                    content.style.height = `${height}px`;
                    content.style.opacity = '1';
                });

                content.addEventListener(
                    'transitionend',
                    () => {
                        content.style.removeProperty('height');
                        content.style.removeProperty('overflow');
                        content.style.removeProperty('transition');
                    }, {
                        once: true
                    }
                );
            }

            // Slide up animation
            function closeAccordion(trigger, content) {
                trigger.setAttribute('aria-expanded', 'false');
                trigger.classList.remove('__active');
                content.setAttribute('aria-hidden', 'true');

                content.style.height = `${content.scrollHeight}px`;
                requestAnimationFrame(() => {
                    content.style.height = '0';
                    content.style.opacity = '0';
                });

                content.style.overflow = 'hidden';

                content.addEventListener(
                    'transitionend',
                    () => {
                        content.style.display = 'none';
                        content.style.removeProperty('height');
                        content.style.removeProperty('overflow');
                        content.style.removeProperty('transition');
                    }, {
                        once: true
                    }
                );
            }

            // Category and subcategory link handling
            const categoryLinks = document.querySelectorAll('.category-link, .subcategory-link');
            categoryLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add loading state
                    const wrapper = document.querySelector('.product-wrapper');
                    if (wrapper) {
                        wrapper.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';
                    }
                });
            });

            // Auto-open accordion for active category
            const activeCategory = document.querySelector('.category-link.active');
            if (activeCategory) {
                const accordion = activeCategory.closest('.Accordion');
                if (accordion) {
                    const trigger = accordion.querySelector('.AccordionTrigger');
                    const content = accordion.querySelector('.AccordionContent');
                    if (trigger && content) {
                        openAccordion(trigger, content);
                    }
                }
            }

            // Filter form handling
            const filterForms = document.querySelectorAll('.price-range, .rating-range');
            filterForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Show loading state
                    const wrapper = document.querySelector('.product-wrapper');
                    if (wrapper) {
                        wrapper.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';
                    }

                    const formData = new FormData(this);
                    const params = new URLSearchParams();

                    // Add existing URL parameters
                    const urlParams = new URLSearchParams(window.location.search);
                    for (const [key, value] of urlParams) {
                        if (!formData.has(key) && key !== 'page') {
                            params.append(key, value);
                        }
                    }

                    // Add form data with validation
                    for (const [key, value] of formData) {
                        if (value && value.trim() !== '') {
                            // Validate numeric inputs
                            if ((key === 'min_price' || key === 'max_price' || key === 'min_coins' || key === 'max_coins') && isNaN(value)) {
                                alert('Please enter valid numbers for ' + key.replace('_', ' '));
                                return;
                            }
                            params.append(key, value);
                        }
                    }

                    // Reset page to 1 when filtering
                    params.set('page', '1');

                    // Navigate to filtered URL
                    window.location.href = '?' + params.toString();
                });
            });

            // Filter link handling
            const filterLinks = document.querySelectorAll('.filter-item a');
            filterLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add loading state
                    const wrapper = document.querySelector('.product-wrapper');
                    if (wrapper) {
                        wrapper.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';
                    }
                });
            });

            // Active filter highlighting
            const currentUrl = new URLSearchParams(window.location.search);
            const activeFilters = document.querySelectorAll('.filter-item a');

            activeFilters.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('?')) {
                    const linkParams = new URLSearchParams(href.split('?')[1]);
                    let isActive = true;

                    // Check if all filter parameters match
                    for (const [key, value] of linkParams) {
                        if (key !== 'page' && currentUrl.get(key) !== value) {
                            isActive = false;
                            break;
                        }
                    }

                    if (isActive) {
                        link.closest('.filter-item').classList.add('active');
                    }
                }
            });

            // Quickview functionality
            initQuickview();

            // Clear all filters functionality
            const clearAllBtn = document.getElementById('clear-all-filters');
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Clear all filter inputs
                    const filterInputs = document.querySelectorAll('.price-range input[type="number"]');
                    filterInputs.forEach(input => {
                        input.value = '';
                    });

                    // Navigate to clean shop page
                    window.location.href = 'shop.php';
                });
            }

            // Sort By functionality
            const sortSelect = document.getElementById('sort-select');
            if (sortSelect) {
                sortSelect.addEventListener('change', function() {
                    // Show loading state
                    const wrapper = document.querySelector('.product-wrapper');
                    if (wrapper) {
                        wrapper.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';
                    }

                    const currentUrl = new URLSearchParams(window.location.search);
                    currentUrl.set('orderby', this.value);
                    currentUrl.set('page', '1'); // Reset to first page when sorting
                    window.location.href = '?' + currentUrl.toString();
                });
            }

            // Show items per page functionality
            const showSelect = document.getElementById('show-select');
            if (showSelect) {
                showSelect.addEventListener('change', function() {
                    const currentUrl = new URLSearchParams(window.location.search);
                    currentUrl.set('count', this.value);
                    currentUrl.set('page', '1'); // Reset to first page when changing items per page
                    window.location.href = '?' + currentUrl.toString();
                });
            }
        });

        // Quickview initialization
        function initQuickview() {
            // Add event listener for quickview buttons
            document.addEventListener('click', function(event) {
                if (event.target.closest('.btn-quickview')) {
                    event.preventDefault();
                    event.stopPropagation();

                    const quickviewBtn = event.target.closest('.btn-quickview');
                    const productId = quickviewBtn.getAttribute('data-product-id');

                    if (productId) {
                        // Add loading state
                        quickviewBtn.style.opacity = '0.6';

                        // Load quickview content
                        loadQuickview(productId, quickviewBtn);
                    }
                }
            });
        }

        // Load quickview content
        function loadQuickview(productId, button) {
            const modal = document.getElementById('quickview-modal');

            // Show loading
            modal.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>';

            // Fetch quickview content
            fetch('quickview.php?id=' + productId)
                .then(response => response.text())
                .then(html => {
                    modal.innerHTML = html;

                    // Initialize modal functionality
                    if (typeof Wolmart !== 'undefined' && Wolmart.popup) {
                        Wolmart.popup({
                            items: {
                                src: modal.outerHTML
                            },
                            callbacks: {
                                open: function() {
                                    // Initialize quantity buttons
                                    initModalQuantityButtons();
                                    // Initialize image navigation
                                    initModalImageNavigation();
                                    // Initialize wishlist buttons
                                    initModalWishlistButtons();
                                },
                                close: function() {
                                    // Clean up when modal closes
                                    button.style.opacity = '';
                                }
                            }
                        }, 'quickview');
                    } else {
                        // Fallback: create simple modal
                        createFallbackModal(productId, modal);
                    }

                    button.style.opacity = '';
                })
                .catch(error => {
                    console.error('Error loading quickview:', error);
                    modal.innerHTML = '<div class="text-center p-5 text-danger">Error loading product details</div>';
                    button.style.opacity = '';
                });
        }

        // Initialize quantity buttons in modal
        function initModalQuantityButtons() {
            const modalRoot = document.querySelector('.mfp-content .product-popup') || document.querySelector('.product-popup');
            if (!modalRoot) return;

            const quantityInputs = modalRoot.querySelectorAll('.quantity');
            const plusButtons = modalRoot.querySelectorAll('.quantity-plus');
            const minusButtons = modalRoot.querySelectorAll('.quantity-minus');

            plusButtons.forEach(button => {
                if (!button.hasAttribute('data-modal-initialized')) {
                    button.setAttribute('data-modal-initialized', 'true');
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const input = this.parentNode.querySelector('.quantity');
                        if (input) {
                            let value = parseInt(input.value) || 1;
                            input.value = value + 1;
                        }
                    });
                }
            });

            minusButtons.forEach(button => {
                if (!button.hasAttribute('data-modal-initialized')) {
                    button.setAttribute('data-modal-initialized', 'true');
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        const input = this.parentNode.querySelector('.quantity');
                        if (input) {
                            let value = parseInt(input.value) || 1;
                            if (value > 1) {
                                input.value = value - 1;
                            }
                        }
                    });
                }
            });
        }

        // Initialize image navigation in modal
        function initModalImageNavigation() {
            setTimeout(() => {
                const modal = document.querySelector('.product-popup');
                if (!modal) return;

                const thumbnailImages = modal.querySelectorAll('.product-thumb img');
                const mainImages = modal.querySelectorAll('.swiper-wrapper .swiper-slide img');

                thumbnailImages.forEach((thumb, index) => {
                    thumb.addEventListener('click', function() {
                        // Update main image
                        mainImages.forEach((img, imgIndex) => {
                            if (imgIndex === index) {
                                img.style.display = 'block';
                            } else {
                                img.style.display = 'none';
                            }
                        });
                    });
                });
            }, 100);
        }

        // Initialize wishlist buttons in modal
        function initModalWishlistButtons() {
            const wishlistButtons = document.querySelectorAll('.product-popup .btn-wishlist');
            wishlistButtons.forEach(button => {
                if (!button.hasAttribute('data-modal-initialized')) {
                    button.setAttribute('data-modal-initialized', 'true');
                    button.addEventListener('click', function(e) {
                        e.preventDefault();
                        // Add wishlist functionality here
                        console.log('Wishlist clicked in modal');
                    });
                }
            });
        }

        // Fallback modal function
        function createFallbackModal(productId, modal) {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;';

            // Create close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '×';
            closeBtn.style.cssText = 'position:absolute;top:10px;right:15px;background:none;border:none;font-size:30px;color:#fff;cursor:pointer;z-index:10000;';

            // Style modal content
            modal.style.cssText = 'background:white;max-width:90%;max-height:90%;overflow:auto;border-radius:8px;position:relative;';

            // Close modal functionality
            closeBtn.addEventListener('click', function() {
                // Clean up modal state before removing
                const modalRoot = modal.querySelector('.product-popup');
                if (modalRoot) {
                    modalRoot.removeAttribute('data-quickview-initialized');
                    modalRoot.removeAttribute('data-modal-cart-initialized');

                    const cartBtn = modalRoot.querySelector('.btn-cart');
                    if (cartBtn) cartBtn.removeAttribute('data-cart-listener-added');

                    const plusBtns = modalRoot.querySelectorAll('.quantity-plus');
                    plusBtns.forEach(btn => btn.removeAttribute('data-plus-listener-added'));

                    const minusBtns = modalRoot.querySelectorAll('.quantity-minus');
                    minusBtns.forEach(btn => btn.removeAttribute('data-minus-listener-added'));
                }
                document.body.removeChild(overlay);
            });

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    // Clean up modal state before removing
                    const modalRoot = modal.querySelector('.product-popup');
                    if (modalRoot) {
                        modalRoot.removeAttribute('data-quickview-initialized');
                        modalRoot.removeAttribute('data-modal-cart-initialized');

                        const cartBtn = modalRoot.querySelector('.btn-cart');
                        if (cartBtn) cartBtn.removeAttribute('data-cart-listener-added');

                        const plusBtns = modalRoot.querySelectorAll('.quantity-plus');
                        plusBtns.forEach(btn => btn.removeAttribute('data-plus-listener-added'));

                        const minusBtns = modalRoot.querySelectorAll('.quantity-minus');
                        minusBtns.forEach(btn => btn.removeAttribute('data-minus-listener-added'));
                    }
                    document.body.removeChild(overlay);
                }
            });

            modal.appendChild(closeBtn);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            // Initialize modal functionality
            setTimeout(() => {
                initModalQuantityButtons();
                initModalImageNavigation();
                initModalWishlistButtons();
                // Initialize quickview modal functionality from quickview.php
                if (typeof initQuickviewModal === 'function') {
                    initQuickviewModal();
                }
            }, 100);
        }
    </script>

    <!-- Product card cart + wishlist wiring for Shop page -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initShopProductInteractions();
            // Update wishlist count on page load
            if (window.wishlistManager && typeof window.wishlistManager.updateWishlistCount === 'function') {
                window.wishlistManager.updateWishlistCount();
            }
        });

        function initShopProductInteractions() {
            // Initialize wishlist via global manager if available
            if (window.wishlistManager && typeof window.wishlistManager.initWishlistButtons === 'function') {
                window.wishlistManager.initWishlistButtons();
                if (typeof window.wishlistManager.checkWishlistStatus === 'function') {
                    window.wishlistManager.checkWishlistStatus();
                }
                // The global wishlist manager already has updateWishlistCount method
                // No need to override, it will automatically update the count
            } else {
                // Fallback wishlist handling (guests/local)
                document.querySelectorAll('.product .btn-wishlist, .product-wrap .btn-wishlist').forEach(function(btn) {
                    if (btn.getAttribute('data-wishlist-initialized') === 'true') return;
                    btn.setAttribute('data-wishlist-initialized', 'true');
                    // set initial state from localStorage
                    try {
                        const pidInit = parseInt(btn.getAttribute('data-product-id'));
                        let w = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                        if (pidInit && w.includes(pidInit)) {
                            btn.classList.add('in-wishlist', 'w-icon-heart-full');
                            btn.classList.remove('w-icon-heart');
                        }
                    } catch (_) {}
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const productId = parseInt(this.getAttribute('data-product-id'));
                        if (!productId) return;
                        try {
                            let wishlist = JSON.parse(localStorage.getItem('guest_wishlist') || '[]');
                            if (wishlist.includes(productId)) {
                                wishlist = wishlist.filter(id => id !== productId);
                                this.classList.remove('in-wishlist', 'w-icon-heart-full');
                                this.classList.add('w-icon-heart');
                                showCartFallbackNote('Removed from wishlist', 'info');
                            } else {
                                wishlist.push(productId);
                                this.classList.add('in-wishlist', 'w-icon-heart-full');
                                this.classList.remove('w-icon-heart');
                                showCartFallbackNote('Added to wishlist', 'success');
                            }
                            localStorage.setItem('guest_wishlist', JSON.stringify(wishlist));
                            // Update wishlist count after change
                            if (window.wishlistManager && typeof window.wishlistManager.updateWishlistCount === 'function') {
                                window.wishlistManager.updateWishlistCount();
                            }
                        } catch (err) {
                            console.error(err);
                        }
                    });
                });
            }

            // Add to cart buttons with Out-of-Stock check
            const addToCartButtons = document.querySelectorAll('.product .btn-cart, .product-wrap .btn-cart');
            addToCartButtons.forEach(function(button) {
                if (button.getAttribute('data-cart-initialized') === 'true') return;
                button.setAttribute('data-cart-initialized', 'true');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const productId = this.getAttribute('data-product-id');
                    if (!productId) return;
                    const productCard = this.closest('.product, .product-wrap');

                    // Check stock before proceeding
                    fetch('get_products.php?ids=' + encodeURIComponent(productId))
                        .then(r => r.json())
                        .then(data => {
                            console.log(data);
                            const product = data && data.products && data.products.find(p => String(p.id) === String(productId));
                            const inventory = product && typeof product.Inventory !== 'undefined' ? parseInt(product.Inventory) : null;
                            if (inventory !== null && inventory <= 0) {
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
                            if (window.cartManager && typeof window.cartManager.addToCart === 'function') {
                                const original = this.innerHTML;
                                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                this.style.pointerEvents = 'none';
                                window.cartManager.addToCart(productId, 1, productCard)
                                    .catch(() => showCartFallbackNote('Failed to add to cart', 'error'))
                                    .finally(() => {
                                        this.innerHTML = original;
                                        this.style.pointerEvents = '';
                                    });
                            } else {
                                // Fallback direct POST
                                const original = this.innerHTML;
                                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
                                this.style.pointerEvents = 'none';
                                fetch('cart_handler.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json'
                                        },
                                        body: JSON.stringify({
                                            action: 'add',
                                            product_id: parseInt(productId),
                                            quantity: 1
                                        })
                                    }).then(r => r.json())
                                    .then(d => {
                                        if (d.success) {
                                            showCartFallbackNote('Product added to cart', 'success');
                                            if (typeof window.updateCartCount === 'function') window.updateCartCount();
                                        } else {
                                            showCartFallbackNote(d.message || 'Failed to add to cart', 'error');
                                        }
                                    })
                                    .catch(() => showCartFallbackNote('Error adding to cart', 'error'))
                                    .finally(() => {
                                        this.innerHTML = original;
                                        this.style.pointerEvents = '';
                                    });
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

        function showCartFallbackNote(message, type) {
            const note = document.createElement('div');
            note.textContent = message;
            note.style.cssText = 'position:fixed;top:20px;right:20px;color:#fff;padding:12px 16px;border-radius:8px;z-index:10000;font-size:13px;box-shadow:0 4px 12px rgba(0,0,0,.15)';
            note.style.background = type === 'success' ? '#27ae60' : (type === 'error' ? '#e74c3c' : '#2980b9');
            document.body.appendChild(note);
            setTimeout(() => {
                note.style.opacity = '0';
                setTimeout(() => note.remove(), 300);
            }, 2000);
        }

        // Global wishlist count update function - now handled by WishlistManager class
    </script>

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

            // Hide suggestions on form submit
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    searchSuggestions.classList.remove('show');
                });
            }
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
    </script>
</body>

</html>
<?php

include __DIR__ . '/../includes/init.php';  
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
}
$superadmin_id = $_SESSION['superadmin_id'];

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;


$existing_product = null;
if (!empty($product_id)) {
    $rs_prefill = mysqli_query($con, "SELECT * FROM products WHERE id={$product_id} LIMIT 1");

    if ($rs_prefill && mysqli_num_rows($rs_prefill) > 0) {
        $existing_product = mysqli_fetch_assoc($rs_prefill);
    }
}

$vendor_reg_id = $existing_product['vendor_id'];

if ($product_id <= 0) {
    header('Location: ./product-management.php');
    exit;
}

// Fetch product details
$product = null;
$productQuery = "SELECT p.*, c.name as category_name, sc.name as sub_category_name 
                    FROM products p 
                    LEFT JOIN category c ON p.category_id = c.id 
                    LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
                    WHERE p.id = '" . mysqli_real_escape_string($con, $product_id) . "' 
                    AND p.vendor_id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "' 
                    LIMIT 1";

$result = mysqli_query($con, $productQuery);
if ($result && mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
} else {
    header('Location: ./product-management.php');
    exit;
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $deleteQuery = "DELETE FROM products WHERE id = '" . mysqli_real_escape_string($con, $product_id) . "' 
                        AND vendor_id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "'";

    if (mysqli_query($con, $deleteQuery)) {
        header('Location: ./product-management.php?deleted=1');
        exit;
    } else {
        $delete_error = "Failed to delete product. Please try again.";
    }
}

// Parse product specifications from database
$productVariants = [];
$attributeNames = [];
$variantNames = [];

// Helper function to get variant name (handles both regular and text variants)
function getVariantDisplayName($variantItem, $variantNames) {
    // Check if this is a text variant
    if (isset($variantItem['is_text_variant']) && $variantItem['is_text_variant'] === true) {
        // Text variant - use the name property directly
        return isset($variantItem['name']) ? $variantItem['name'] : 'Text Variant';
    }
    // Regular variant - lookup from variantNames array
    return isset($variantItem['id']) && isset($variantNames[$variantItem['id']]) 
        ? $variantNames[$variantItem['id']] 
        : 'Variant';
}

if (!empty($product['specifications'])) {
    $specifications = json_decode($product['specifications'], true);
    if (is_array($specifications)) {
        $productVariants = $specifications;

        // Fetch attribute names and values from database
        $attributeIds = array_column($specifications, 'attribute_id');
        if (!empty($attributeIds)) {
            $attributeIdsStr = implode(',', array_map('intval', $attributeIds));
            $attrQuery = "SELECT id, name, attribute_values FROM attributes WHERE id IN ($attributeIdsStr)";
            $attrResult = mysqli_query($con, $attrQuery);
            if ($attrResult) {
                while ($attr = mysqli_fetch_assoc($attrResult)) {
                    $attributeNames[$attr['id']] = $attr['name'];

                    // Parse attribute values JSON
                    if (!empty($attr['attribute_values'])) {
                        $attributeValues = json_decode($attr['attribute_values'], true);
                        if (is_array($attributeValues)) {
                            foreach ($attributeValues as $value) {
                                if (isset($value['value_id']) && isset($value['value_name'])) {
                                    $variantNames[$value['value_id']] = $value['value_name'];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}



?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>PRODUCT - DETAILS | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template setting, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, setting, setting template bootstrap, admin setting bootstrap.">

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
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/product-details.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


</head>

<body>
   

    <!-- Loader -->
    <div id="loader">
        <img src="./assets/images/media/loader.svg" alt="">
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
            <!-- Start:: row-2 -->
            <div class="row">
                <div class="col-1"></div>
                <div class="col-12 col-xl-10 col-lg-10 col-md-12 col-sm-12">
                    <div
                        class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-3 flex-wrap">
                        <h1 class="page-title fw-semibold fs-18 mb-0">Product Details</h1>
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="./product-management.php">Product
                                            Management</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Product Details</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="card custom-card p-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-5 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="thumbnail-flex gap-3">
                                        <div class="thumbnail-container">
                                            <button class="btn-nav btn-up" id="thumbUp"><i
                                                    class="fa-solid fa-chevron-up"></i></button>
                                            <div class="thumbnail-wrapper">
                                                <?php
                                                $images = [];
                                                $videos = [];
                                                if (!empty($product['images'])) {
                                                    $images = json_decode($product['images'], true);
                                                }
                                                // Parse videos using regex to handle commas within folder names
                                                if (!empty($product['video'])) {
                                                    $videoString = (string) $product['video'];
                                                    // Match video file paths ending with common video extensions
                                                    // Pattern: matches everything (including commas) until video extension, then comma or end
                                                    preg_match_all('/(.*?\.(?:mp4|mov|avi|webm|mkv|flv|wmv|m4v)(?:\?.*)?)(?:,|$)/i', $videoString, $matches);
                                                    if (!empty($matches[1])) {
                                                        foreach ($matches[1] as $vp) {
                                                            $vp = trim($vp);
                                                            if (!empty($vp)) {
                                                                $videos[] = './'.ltrim($vp, './');
                                                            }
                                                        }
                                                    } else {
                                                        // Fallback: if no video extension found, treat entire string as single video
                                                        $vp = trim($videoString);
                                                        if (!empty($vp)) {
                                                            $videos[] = './'.ltrim($vp, './');
                                                        }
                                                    }
                                                }

                                                if (empty($images) || !is_array($images)) {
                                                    $images = ['./assets/images/Detail1s.png'];
                                                }

                                                $thumbIndex = 0;
                                                foreach ($images as $index => $image) {
                                                    $imagePath = './' . ltrim($image, './');
                                                    $isSelected = $thumbIndex === 0 ? 'selected' : '';
                                                    $imageSrc = $imagePath !== '' ? $vendor_baseurl . htmlspecialchars($imagePath) : $vendor_baseurl . '/assets/images/default.png';
                                                    echo '<div class="bright-shot-img">
                                                                <img src="' . $imageSrc . '" alt="Thumbnail ' . ($thumbIndex + 1) . '" class="thumbnail-img ' . $isSelected . '" data-src="' . $imageSrc . '" data-type="image">
                                                            </div>';
                                                    $thumbIndex++;
                                                }
                                                foreach ($videos as $vIndex => $videoPath) {
                                                    $isSelected = $thumbIndex === 0 ? 'selected' : '';
                                                    $videoSrc = $vendor_baseurl . htmlspecialchars($videoPath);
                                                    echo '<div class="bright-shot-img position-relative">
                                                                <video src="' . $videoSrc . '#t=0.1" class="thumbnail-img ' . $isSelected . '" data-src="' . $videoSrc . '" data-type="video" muted preload="metadata" style="max-height:70px;object-fit:cover"></video>
                                                                <span class="position-absolute" style="right:6px;bottom:6px;background:rgba(0,0,0,.6);color:#fff;border-radius:10px;padding:2px 6px;font-size:10px;">Video</span>
                                                            </div>';
                                                    $thumbIndex++;
                                                }
                                                ?>
                                            </div>
                                            <button class="btn-nav btn-down" id="thumbDown"><i
                                                    class="fa-solid fa-chevron-down"></i></button>
                                        </div>
                                        <div>
                                            <div class="bright-img">
                                                <button class="btn-main-nav btn-main-left" id="mainLeft"><i
                                                        class="fa-solid fa-chevron-left"></i></button>
                                                <img src="<?php echo htmlspecialchars($images[0] ? './' . ltrim($images[0], './') : './assets/images/Detail1s.png'); ?>"
                                                    alt="Main Image" class="main-img">
                                                <video class="main-video d-none" controls
                                                    style="width:100%;height:auto;border-radius:8px"></video>
                                                <button class="btn-main-nav btn-main-right" id="mainRight"><i
                                                        class="fa-solid fa-chevron-right"></i></button>
                                                <button class="btn-zoom-icon">
                                                    <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/zoom-icon.png" alt="Zoom Icon">
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-7 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div>
                                        <div class="product-detsail-heading">
                                            <h2><?php echo htmlspecialchars($product['product_name'] ?? 'Product Name'); ?>
                                            </h2>
                                        </div>
                                        <div class="border-details"></div>
                                        <div>
                                            <div class="detail-price">
                                                <sup>₹</sup>
                                                <?php echo number_format((float) ($product['selling_price'] ?? 0), 2); ?>
                                            </div>
                                            <div class="del-price mb-2">
                                                M.R.P:
                                                <del>₹<?php echo number_format((float) ($product['mrp'] ?? 0), 2); ?></del>
                                                <?php
                                                $mrp = (float) ($product['mrp'] ?? 0);
                                                $selling = (float) ($product['selling_price'] ?? 0);
                                                if ($mrp > $selling && $mrp > 0) {
                                                    $discount = round((($mrp - $selling) / $mrp) * 100);
                                                    echo '<span class="off-price">(' . $discount . '% off)</span>';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="star-icon">
                                            <?php
                                            $avgRatingQuery = mysqli_query($con, "SELECT AVG(rating) as avg_rating FROM reviews WHERE product_id = '$product_id'");
                                            $avgRatingData = mysqli_fetch_assoc($avgRatingQuery);
                                            $avgRating = $avgRatingData['avg_rating'] ?? 0;

                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= floor($avgRating)) {
                                                    // Full star
                                                    echo '<i class="fa-solid fa-star star-done"></i>';
                                                } elseif ($i == ceil($avgRating) && $avgRating - floor($avgRating) >= 0.5) {
                                                    // Half star for decimal ratings >= 0.5
                                                    echo '<i class="fa-solid fa-star-half-stroke star-done"></i>';
                                                } else {
                                                    // Empty star
                                                    echo '<i class="fa-solid fa-star"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="product-description-section">
                                            <?php
                                            $description = !empty($product['description']) ? $product['description'] : 'No description available for this product.';
                                            $descriptionText = htmlspecialchars($description);

                                            if (strlen($descriptionText) > 300) {
                                                echo '<div class="description-preview">';
                                                echo substr($descriptionText, 0, 300) . '...';
                                                echo '</div>';
                                                echo '<div class="description-full" style="display: none;">';
                                                echo $descriptionText;
                                                echo '</div>';
                                                echo '<button class="btn btn-sm btn-outline-primary mt-2 read-more-btn" onclick="toggleDescription()">Read More</button>';
                                            } else {
                                                echo $descriptionText;
                                            }
                                            ?>
                                        </div>
                                        <div class="border-details"></div>
                                        <?php 
                                        // Split attributes into multi-variant first, then single-variant
                                        $multiVariantGroups = [];
                                        $singleVariantGroups = [];
                                        foreach ($productVariants as $v) {
                                            $cnt = 0;
                                            if (isset($v['variants']) && is_array($v['variants'])) {
                                                $cnt = count($v['variants']);
                                            }
                                            if ($cnt > 1) { $multiVariantGroups[] = $v; } else { $singleVariantGroups[] = $v; }
                                        }
                                        $orderedGroups = array_merge($multiVariantGroups, $singleVariantGroups);
                                        foreach ($orderedGroups as $variant): ?>
                                            <?php
                                            $attributeName = $attributeNames[$variant['attribute_id']] ?? 'Attribute';
                                            $attributeNameLower = strtolower($attributeName);

                                            // Check if this variant has pricing data
                                            $hasPricing = false;
                                            if (isset($variant['variants']) && is_array($variant['variants'])) {
                                                foreach ($variant['variants'] as $variantItem) {
                                                    if (isset($variantItem['mrp']) || isset($variantItem['selling_price'])) {
                                                        $hasPricing = true;
                                                        break;
                                                    }
                                                }
                                            }
                                            ?>

                                            <!-- Color Attribute -->
                                            <?php if ($attributeNameLower == 'color'): ?>
                                                <div class="round-flex">
                                                    <div class="round-color-text">
                                                        <h5><?php echo htmlspecialchars($attributeName); ?>:</h5>
                                                    </div>
                                                    <?php 
                                                    $count = isset($variant['variants']) && is_array($variant['variants']) ? count($variant['variants']) : 0;
                                                    if ($count > 1): ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php foreach ($variant['variants'] as $colorVariant): 
                                                                $colorName = getVariantDisplayName($colorVariant, $variantNames);
                                                                ?>
                                                                <div class="color-one"
                                                                    data-variant-id="<?php echo $colorVariant['id']; ?>"
                                                                    style="background-color: <?php echo strtolower($colorName); ?>;"
                                                                    title="<?php echo htmlspecialchars($colorName); ?>">
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php 
                                                            $only = $count === 1 ? $variant['variants'][0] : null;
                                                            $label = $only ? getVariantDisplayName($only, $variantNames) : '';
                                                            ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($label); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Size Attribute -->
                                            <?php elseif ($attributeNameLower == 'size'): ?>
                                                <div class="round-flex pt-2">
                                                    <div class="round-color-text">
                                                        <h5><?php echo htmlspecialchars($attributeName); ?>:</h5>
                                                    </div>
                                                    <?php 
                                                    $count = isset($variant['variants']) && is_array($variant['variants']) ? count($variant['variants']) : 0;
                                                    if ($count > 1): ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php foreach ($variant['variants'] as $sizeVariant): 
                                                                $sizeName = getVariantDisplayName($sizeVariant, $variantNames);
                                                                ?>
                                                                <button class="btn-small"
                                                                    data-variant-id="<?php echo $sizeVariant['id']; ?>">
                                                                    <?php echo htmlspecialchars($sizeName); ?>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php 
                                                            $only = $count === 1 ? $variant['variants'][0] : null;
                                                            $label = $only ? getVariantDisplayName($only, $variantNames) : '';
                                                            ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($label); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- All Other Attributes -->
                                            <?php else: ?>
                                                <div class="round-flex pt-2">
                                                    <div class="round-color-text">
                                                        <h5><?php echo htmlspecialchars($attributeName); ?>:</h5>
                                                    </div>
                                                    <?php 
                                                    $count = isset($variant['variants']) && is_array($variant['variants']) ? count($variant['variants']) : 0;
                                                    if ($count > 1): ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php foreach ($variant['variants'] as $otherVariant): 
                                                                $variantName = getVariantDisplayName($otherVariant, $variantNames);
                                                                ?>
                                                                <button class="btn-small"
                                                                    data-variant-id="<?php echo $otherVariant['id']; ?>">
                                                                    <?php echo htmlspecialchars($variantName); ?>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                                            <?php 
                                                            $only = $count === 1 ? $variant['variants'][0] : null;
                                                            $label = $only ? getVariantDisplayName($only, $variantNames) : '';
                                                            ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($label); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <div>
                                            <div class="cataegory-product">
                                                <h4>Category:
                                                    <span><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?>
                                                        |
                                                        <?php echo htmlspecialchars($product['sub_category_name'] ?? 'N/A'); ?></span>
                                                </h4>
                                            </div>

                                            <div class="cataegory-product">
                                                <h4 class="pt-0">SKU:
                                                    <span><?php echo htmlspecialchars($product['sku_id'] ?? 'N/A'); ?></span>
                                                </h4>
                                            </div>

                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="brand-icon">
                                                <i class="fa-brands fa-facebook-f"></i>
                                            </div>
                                            <div class="brand-icon">
                                                <i class="fa-brands fa-x-twitter"></i>
                                            </div>
                                            <div class="brand-icon">
                                                <i class="fa-brands fa-pinterest-p"></i>
                                            </div>
                                            <div class="brand-icon">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </div>
                                            <div class="brand-icon">
                                                <i class="fa-brands fa-linkedin-in"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="product-details-tab">
                                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="pills-home-tab"
                                                    data-bs-toggle="pill" data-bs-target="#pills-home" type="button"
                                                    role="tab" aria-controls="pills-home"
                                                    aria-selected="true">Description</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="pills-profile-tab" data-bs-toggle="pill"
                                                    data-bs-target="#pills-profile" type="button" role="tab"
                                                    aria-controls="pills-profile"
                                                    aria-selected="false">Specification</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="pills-contact-tab" data-bs-toggle="pill"
                                                    data-bs-target="#pills-contact" type="button" role="tab"
                                                    aria-controls="pills-contact" aria-selected="false">Customer Reviews
                                                    (<?php
                                                    $get_reviews = mysqli_query($con, "SELECT * FROM reviews WHERE product_id = '$product_id'");
                                                    $total_reviews = mysqli_num_rows($get_reviews);
                                                    echo $total_reviews;
                                                    //echo rand(5, 25); 
                                                    ?>)</button>
                                            </li>
                                        </ul>
                                        <div class="tab-content" id="pills-tabContent">
                                            <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                                                aria-labelledby="pills-home-tab" tabindex="0">
                                                <div class="nav-prodcut-detail">
                                                    <p>
                                                        <?php echo !empty($product['description']) ? htmlspecialchars($product['description']) : 'No description available for this product.'; ?>
                                                    </p>
                                                 
                                                </div>
                                            </div>
                                            <div class="tab-pane fade" id="pills-profile" role="tabpanel"
                                                aria-labelledby="pills-profile-tab" tabindex="0">

                                                <?php foreach ($productVariants as $variant): ?>
                                                    <div class="modal-product-details">
                                                        <h4><?php echo htmlspecialchars($attributeNames[$variant['attribute_id']] ?? 'Attribute'); ?>
                                                        </h4>
                                                        <span>
                                                            <?php
                                                            $variantNamesList = [];
                                                            if (isset($variant['variants']) && is_array($variant['variants'])) {
                                                                foreach ($variant['variants'] as $variantItem) {
                                                                    $variantNamesList[] = getVariantDisplayName($variantItem, $variantNames);
                                                                }
                                                            }
                                                            echo htmlspecialchars(implode(', ', $variantNamesList));
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>

                                                <div class="border-details mb-3"></div>
                                                <?php if (!empty($product['manufacture_details'])): ?>
                                                    <div class="manufacture-details-section mb-3">
                                                        <h4 class="mb-2">Manufacture Details</h4>
                                                        <div class="manufacture-content">
                                                            <?php
                                                            $manufactureText = htmlspecialchars($product['manufacture_details']);

                                                            echo $manufactureText;

                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="border-details mb-3"></div>
                                                <?php if (!empty($product['packaging_details'])): ?>
                                                    <div class="packaging-details-section mb-3">
                                                        <h4 class="mb-2">Packaging Details</h4>
                                                        <div class="packaging-content">
                                                            <?php
                                                            $packagingText = htmlspecialchars($product['packaging_details']);

                                                            echo $packagingText;

                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                            </div>
                                            <div class="tab-pane fade" id="pills-contact" role="tabpanel"
                                                aria-labelledby="pills-contact-tab" tabindex="0">
                                                <div class="row g-3">
                                                    <div class="col-xl-5 col-lg-12 col-md-12 col-sm-12 col-12">
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

                                                        // Debug output (remove this after testing)
                                                        echo "<!-- Debug: Total reviews: " . $total_reviews . ", Avg: " . $avgRating . ", Distribution: " . implode(',', $ratingDistribution) . " -->";
                                                        ?>
                                                        <div class="d-flex align-items-center gap-3 mb-2">
                                                            <div class="rating">
                                                                <h3><?php echo number_format($avgRating, 1); ?></h3>
                                                            </div>
                                                            <div class="rating-text">
                                                                <h5>Average Rating</h5>
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
                                                        <div>
                                                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="star-icon p-0">
                                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                            <?php if ($i <= $star): ?>
                                                                                <i class="fa-solid fa-star star-done"></i>
                                                                            <?php else: ?>
                                                                                <i class="fa-solid fa-star"></i>
                                                                            <?php endif; ?>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                    <div class="progress">
                                                                        <?php
                                                                        $percentage = $total_reviews > 0 ? round(($ratingDistribution[$star] / $total_reviews) * 100) : 0;
                                                                        ?>
                                                                        <div class="progress-bar" role="progressbar"
                                                                            aria-label="Basic example"
                                                                            style="width: <?php echo $percentage; ?>%"
                                                                            aria-valuenow="<?php echo $percentage; ?>"
                                                                            aria-valuemin="0" aria-valuemax="100"></div>
                                                                    </div>
                                                                    <div>
                                                                        <span><?php echo $percentage; ?>%</span>
                                                                    </div>
                                                                </div>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    // $get_reviews = mysqli_query($con, "SELECT * FROM reviews WHERE product_id = '$product_id'");
                                                    foreach ($get_reviews as $review) {
                                                        // print_r($review);   
                                                        $user_id = $review['user_id'];
                                                        $user_query = mysqli_query($con, "SELECT * FROM users WHERE id = '$user_id'");
                                                        $user_data = mysqli_fetch_assoc($user_query);

                                                        // Check if user data exists
                                                        if ($user_data) {
                                                            $user_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
                                                        } else {
                                                            $user_name = 'Anonymous User';
                                                        }
                                                        ?>
                                                        <div class="col-xl-7 col-lg-12 col-md-12 col-sm-12 col-12">
                                                            <div class="user-detail-flex">
                                                                <div class="user-detail">
                                                                    <span
                                                                        class="avatar avatar-md avatar-rounded bg-primary text-white d-flex align-items-center justify-content-center">
                                                                        <?php
                                                                        $name = $user_name;
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
                                                                </div>
                                                                <div class="user-name">
                                                                    <h4><?php echo $user_name; ?></h4>
                                                                    <div class="star-icon p-0">
                                                                        <?php
                                                                        $rating = $review['rating'] ?? 0;
                                                                        for ($i = 1; $i <= 5; $i++) {
                                                                            if ($i <= floor($rating)) {
                                                                                // Full star
                                                                                echo '<i class="fa-solid fa-star star-done"></i>';
                                                                            } elseif ($i == ceil($rating) && $rating - floor($rating) >= 0.5) {
                                                                                // Half star for decimal ratings >= 0.5
                                                                                echo '<i class="fa-solid fa-star-half-stroke star-done"></i>';
                                                                            } else {
                                                                                // Empty star
                                                                                echo '<i class="fa-solid fa-star"></i>';
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                    <p><?php echo $review['description']; ?></p>
                                                                </div>
                                                            </div>

                                                        <?php } ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button class="btn btn-delete-dtsil" data-bs-toggle="modal"
                                            data-bs-target="#cancelOrderModal">Delete</button>
                                        <a href="./first-edit-product.php?id=<?php echo $product_id; ?>"><button
                                                class="btn btn-edit-dtsil">Edit</button></a>
                                    </div>

                                    <!-- Hidden form for delete -->
                                    <form id="deleteForm" method="POST" style="display: none;">
                                        <input type="hidden" name="delete_product" value="1">
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-1"></div>
            </div>
            <!-- End:: row-2 -->
        </div>
        <!-- End::app-content -->

        <!-- Cancel Order Confirmation Modal -->
        <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content"
                    style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <div class="modal-body text-center p-4">
                        <!-- Icon -->
                        <div class="mb-3">
                            <div
                                style="width: 60px; height: 60px; background-color: #F45B4B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                <i class="fas fa-info" style="color: white; font-size: 24px;"></i>
                            </div>
                        </div>

                        <!-- Message -->
                        <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                            Are you sure, you want to Delete <br> the Product ?
                        </h5>

                        <!-- Buttons -->
                        <div class="d-flex gap-3 justify-content-center">
                            <button type="button" class="btn btn-outline-danger" id="cancelNoBtn"
                                style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                No
                            </button>
                            <button type="button" class="btn btn-primary" id="cancelYesBtn"
                                style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                Yes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal for Zoom -->
        <div class="modal fade modal-fullscreen" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="imageModalLabel">Image Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner" id="carouselImages">
                                <!-- Images will be dynamically added here -->
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel"
                                data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel"
                                data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Start -->
        <footer class="footer mt-auto py-3 bg-white text-center">
            <div class="container">
                <span class="text-muted">Copyright © 2025 <span id="year"></span> <a href="#"
                        class="text-primary fw-semibold">Hagidy</a>.
                    Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                        <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology</span>
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

    <!-- Image Click and Zoom Script -->
    <script>
        // Get all thumbnails (images or videos)
        const thumbnails = document.querySelectorAll('.thumbnail-img');
        // Main media elements
        const mainImage = document.querySelector('.main-img');
        const mainVideo = document.querySelector('.main-video');
        // Get the zoom button
        const zoomButton = document.querySelector('.btn-zoom-icon');
        // Get the modal and carousel container
        const modal = document.getElementById('imageModal');
        const carouselImages = document.getElementById('carouselImages');
        // Get navigation buttons
        const thumbUp = document.getElementById('thumbUp');
        const thumbDown = document.getElementById('thumbDown');
        const mainLeft = document.getElementById('mainLeft');
        const mainRight = document.getElementById('mainRight');
        // Get the thumbnail wrapper
        const thumbnailWrapper = document.querySelector('.thumbnail-wrapper');

        let currentIndex = 0; // Track the currently selected thumbnail index

        // Function to populate carousel with images/videos
        function populateCarousel(activeIndex) {
            carouselImages.innerHTML = ''; // Clear existing carousel items
            thumbnails.forEach((thumbnail, index) => {
                const div = document.createElement('div');
                div.className = `carousel-item ${index === activeIndex ? 'active' : ''}`;
                const type = thumbnail.dataset.type || 'image';
                if (type === 'video') {
                    const vid = document.createElement('video');
                    vid.controls = true;
                    vid.className = 'd-block w-100 rounded';
                    vid.src = thumbnail.dataset.src || thumbnail.src;
                    div.appendChild(vid);
                } else {
                    const img = document.createElement('img');
                    img.src = thumbnail.dataset.src || thumbnail.src;
                    img.className = 'd-block w-100 img-fluid';
                    img.alt = `Slide ${index + 1}`;
                    div.appendChild(img);
                }
                carouselImages.appendChild(div);
            });
        }

        // Function to update selected thumbnail
        function updateSelectedThumbnail(index) {
            thumbnails.forEach(thumbnail => thumbnail.classList.remove('selected'));
            const th = thumbnails[index];
            th.classList.add('selected');
            const type = th.dataset.type || 'image';
            if (type === 'video') {
                mainImage.classList.add('d-none');
                mainVideo.classList.remove('d-none');
                mainVideo.src = th.dataset.src || th.currentSrc || th.src;
                try {
                    mainVideo.load();
                } catch (e) { }
            } else {
                mainVideo.classList.add('d-none');
                mainImage.classList.remove('d-none');
                mainImage.src = th.dataset.src || th.currentSrc || th.src;
            }
            populateCarousel(index);
        }

        // Function to update button states
        function updateButtonStates() {
            thumbUp.disabled = currentIndex === 0;
            thumbDown.disabled = currentIndex === thumbnails.length - 1;
            mainLeft.disabled = currentIndex === 0;
            mainRight.disabled = currentIndex === thumbnails.length - 1;
        }

        // Function to scroll to a specific thumbnail
        function scrollToThumbnail(index) {
            const thumbnail = thumbnails[index];
            const scrollPosition = thumbnail.offsetTop - thumbnailWrapper.offsetTop;

            thumbnailWrapper.scrollTo({
                top: scrollPosition,
                behavior: 'smooth'
            });

            currentIndex = index;
            updateSelectedThumbnail(currentIndex);
            updateButtonStates();
        }

        // Add click event to thumbnails
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', function () {
                currentIndex = index;
                updateSelectedThumbnail(currentIndex);
                updateButtonStates();
                // Scroll to the clicked thumbnail
                scrollToThumbnail(currentIndex);
            });
        });

        // Add click event to zoom button
        zoomButton.addEventListener('click', function () {
            populateCarousel(currentIndex); // Populate carousel with the current main image as active
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show(); // Show the modal
        });

        // Add click events to thumbnail navigation buttons
        thumbUp.addEventListener('click', function () {
            if (currentIndex > 0) {
                scrollToThumbnail(currentIndex - 1);
            }
        });

        thumbDown.addEventListener('click', function () {
            if (currentIndex < thumbnails.length - 1) {
                scrollToThumbnail(currentIndex + 1);
            }
        });

        // Add click events to main image navigation buttons
        mainLeft.addEventListener('click', function () {
            if (currentIndex > 0) {
                scrollToThumbnail(currentIndex - 1);
            }
        });

        mainRight.addEventListener('click', function () {
            if (currentIndex < thumbnails.length - 1) {
                scrollToThumbnail(currentIndex + 1);
            }
        });

        // Initialize the first thumbnail as selected
        updateSelectedThumbnail(currentIndex);
        updateButtonStates();
    </script>
    <script>
        // Toggle functions for read more/less functionality - must be global for onclick handlers
        function toggleDescription() {
            var preview = document.querySelector('.description-preview');
            var full = document.querySelector('.description-full');
            var btn = event.target;

            if (preview && full) {
                if (preview.style.display !== 'none') {
                    preview.style.display = 'none';
                    full.style.display = 'block';
                    btn.textContent = 'Read Less';
                } else {
                    preview.style.display = 'block';
                    full.style.display = 'none';
                    btn.textContent = 'Read More';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            var cancelNoBtn = document.getElementById('cancelNoBtn');
            var cancelYesBtn = document.getElementById('cancelYesBtn');
            var cancelOrderModal = document.getElementById('cancelOrderModal');
            var deleteForm = document.getElementById('deleteForm');

            if (cancelNoBtn && cancelOrderModal) {
                cancelNoBtn.addEventListener('click', function () {
                    var modalInstance = bootstrap.Modal.getOrCreateInstance(cancelOrderModal);
                    modalInstance.hide();
                });
            }

            if (cancelYesBtn && deleteForm) {
                cancelYesBtn.addEventListener('click', function () {
                    deleteForm.submit();
                });
            }

            // Product variants data
            var productVariants = <?php echo json_encode($productVariants); ?>;
            var variantNames = <?php echo json_encode($variantNames); ?>;

            // Debug: Log the variant data
// Handle variant selection for all attributes
            document.querySelectorAll('[data-variant-id]').forEach(function (element) {
                element.addEventListener('click', function () {
                    // Ignore clicks when single-variant lock is active
                    if (this.getAttribute('data-single-locked') === '1') {
                        return;
                    }
                    var variantId = this.getAttribute('data-variant-id');

                    // Remove active class from siblings in the same attribute group
                    var siblings = this.parentNode.querySelectorAll('[data-variant-id]');
                    siblings.forEach(function (sibling) {
                        sibling.classList.remove('active', 'selected');
                    });

                    // Add active class to clicked element
                    this.classList.add('active', 'selected');

                    // Update price if variant has pricing
                    updatePriceForVariant(variantId);
                });
            });

            function updatePriceForVariant(variantId) {
                var foundVariant = false;
                var variantHasPricing = false;
                var selectedVariant = null;

                // Find variant in data
                for (var i = 0; i < productVariants.length; i++) {
                    if (productVariants[i].variants && Array.isArray(productVariants[i].variants)) {
                        for (var j = 0; j < productVariants[i].variants.length; j++) {
                            if (productVariants[i].variants[j].id == variantId) {
                                foundVariant = true;
                                selectedVariant = productVariants[i].variants[j];

                                // Check if this variant has pricing data
                                if (selectedVariant.mrp && selectedVariant.selling_price &&
                                    parseFloat(selectedVariant.mrp) > 0 && parseFloat(selectedVariant.selling_price) > 0) {
                                    variantHasPricing = true;

                                    // Update price display
                                    var priceElement = document.querySelector('.detail-price sup');
                                    if (priceElement) {
                                        var priceContainer = priceElement.parentNode;
                                        priceContainer.innerHTML = '<sup>₹</sup> ' + parseFloat(selectedVariant.selling_price).toFixed(2);
                                    }

                                    // Update MRP
                                    var mrpElement = document.querySelector('.del-price del');
                                    if (mrpElement) {
                                        mrpElement.textContent = '₹' + parseFloat(selectedVariant.mrp).toFixed(2);
                                    }

                                    // Update discount
                                    var mrp = parseFloat(selectedVariant.mrp);
                                    var selling = parseFloat(selectedVariant.selling_price);
                                    if (mrp > selling && mrp > 0) {
                                        var discount = Math.round(((mrp - selling) / mrp) * 100);
                                        var discountElement = document.querySelector('.off-price');
                                        if (discountElement) {
                                            discountElement.textContent = '(' + discount + '% off)';
                                        }
                                    }
}
                                break;
                            }
                        }
                    }
                }

                // If variant found but has no pricing, revert to main product price
                if (foundVariant && !variantHasPricing) {
revertToMainPrice();
                } else if (!foundVariant) {
}
            }

            // Auto-select and lock when an attribute has only a single variant
            (function autoSelectAndLockSingleVariants() {
                // For each attribute block (color/size/others sections), find variant containers
                var groups = document.querySelectorAll('.round-flex');
                groups.forEach(function (group) {
                    var options = group.querySelectorAll('[data-variant-id]');
                    if (options.length === 1) {
                        var only = options[0];
                        // Mark selected
                        only.classList.add('active', 'selected');
                        // Visually/interaction disable
                        only.setAttribute('data-single-locked', '1');
                        try { only.style.pointerEvents = 'none'; } catch (e) {}
                        only.setAttribute('aria-disabled', 'true');
                        // Apply pricing if available for this variant
                        var vid = only.getAttribute('data-variant-id');
                        if (vid) { updatePriceForVariant(vid); }
                    }
                });
            })();

            function revertToMainPrice() {
                // Get main product prices from PHP
                var mainSellingPrice = <?php echo json_encode($product['selling_price'] ?? 0); ?>;
                var mainMrp = <?php echo json_encode($product['mrp'] ?? 0); ?>;

                // Update price display
                var priceElement = document.querySelector('.detail-price sup');
                if (priceElement) {
                    var priceContainer = priceElement.parentNode;
                    priceContainer.innerHTML = '<sup>₹</sup> ' + parseFloat(mainSellingPrice).toFixed(2);
                }

                // Update MRP
                var mrpElement = document.querySelector('.del-price del');
                if (mrpElement) {
                    mrpElement.textContent = '₹' + parseFloat(mainMrp).toFixed(2);
                }

                // Update discount
                if (mainMrp > mainSellingPrice && mainMrp > 0) {
                    var discount = Math.round(((parseFloat(mainMrp) - parseFloat(mainSellingPrice)) / parseFloat(mainMrp)) * 100);
                    var discountElement = document.querySelector('.off-price');
                    if (discountElement) {
                        discountElement.textContent = '(' + discount + '% off)';
                    }
                }
            }

        });
    </script>
</body>

</html>
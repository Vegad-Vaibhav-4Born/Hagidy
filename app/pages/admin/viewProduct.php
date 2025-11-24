<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}


$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['single_action'])) {
    $singleAction = trim((string) ($_POST['single_action']));
    $allowedStatuses = ['approved', 'hold', 'rejected', 'pending'];
    $postProductId = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
    if (in_array($singleAction, $allowedStatuses, true) && $postProductId > 0) {
        $statusEsc = mysqli_real_escape_string($con, $singleAction);
        $comment = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
        $coinAmount = isset($_POST['coin_amount']) ? (int) $_POST['coin_amount'] : 0;
        $platformFee = isset($_POST['platform_fee']) ? (float) $_POST['platform_fee'] : 0;
        $commentEsc = mysqli_real_escape_string($con, $comment);
        $commentSql = ($singleAction === 'hold' || $singleAction === 'rejected') ? ", status_note = '" . $commentEsc . "'" : '';
        $updateSql = "UPDATE products SET seen = 1, status = '" . $statusEsc . "'" . $commentSql . ", updated_date = NOW() WHERE id = " . $postProductId;
        mysqli_query($con, $updateSql);
        if ($singleAction === 'approved' && $postProductId > 0) {
            // credit coins to vendor and product; set platform fee
            $resVendor = mysqli_query($con, "SELECT vendor_id FROM products WHERE id = " . $postProductId);
            if ($resVendor && ($vr = mysqli_fetch_assoc($resVendor))) {
                $vendorId = (int) $vr['vendor_id'];

            }
            if ($coinAmount > 0) {
                @mysqli_query($con, "UPDATE products SET coin = " . $coinAmount . " WHERE id = " . $postProductId);
            }
            @mysqli_query($con, "UPDATE products SET platform_fee = " . $platformFee . " WHERE id = " . $postProductId);
        }
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_type'] = ($singleAction === 'approved') ? 'success' : (($singleAction === 'rejected') ? 'danger' : 'warning');
    $_SESSION['flash_text'] = ($singleAction === 'approved') ? 'Product approved successfully.' : (($singleAction === 'rejected') ? 'Product rejected.' : 'Product updated.');
    $_SESSION['flash_refresh'] = true;
    header('Location: productRequest.php');
    exit;
}

$productId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = null;
$productVariants = [];
$attributeNames = [];
$variantNames = [];

if ($productId > 0) {

    $seen = mysqli_query($con, "UPDATE products SET seen = 1 WHERE id = " . $productId);
    $sql = "SELECT p.*, v.business_name AS vendor_name, c.name AS category_name, sc.name AS sub_category_name
            FROM products p
            LEFT JOIN vendor_registration v ON v.id = p.vendor_id
            LEFT JOIN category c ON c.id = p.category_id
            LEFT JOIN sub_category sc ON sc.id = p.sub_category_id
            WHERE p.id = " . $productId;
    $res = mysqli_query($con, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $product = mysqli_fetch_assoc($res);
    }
}

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

// Parse product specifications from database
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
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title> VIEW - PRODUCT | HADIDY</title>
    <meta name="Description" content="Hagidy-Super-Admin">
    <meta name="Author" content="Hagidy-Super-Admin">
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

    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


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

            <!-- Start:: row-2 -->
            <div class="row g-3">
                <div class="col-12 col-xl-1 col-lg-1 col-md-12 col-sm-12"></div>
                <div class="col-12 col-xl-10 col-lg-10 col-md-12 col-sm-12">
                    <div
                        class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap g-3">
                        <h1 class="page-title fw-semibold fs-18 mb-0">View Product</h1>
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="product-management.php">Accessories Product
                                        </a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">View Product</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="row g-3">
                                        <div class="col-12 ">
                                            <label for="Light-blue" class="form-label text-default">Product Name
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-blue"
                                                value="<?php echo htmlspecialchars($product['product_name'] ?? ''); ?>"
                                                disabled>
                                            <div class="product-add-text">
                                                <h3>*Product Name should not exceed 30 characters</h3>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default">Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-Category" name="Light-Category"
                                                class="form-select form-select-lg selecton-order w-100" disabled>
                                                <option selected="">
                                                    <?php echo htmlspecialchars($product['category_name'] ?? ''); ?>
                                                </option>
                                                <option value="Accesories1">Accesories 1</option>
                                                <option value="Accesories2">Accesories 2</option>
                                                <option value="Accesories3">Accesories 3</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default" disabled>Sub
                                                Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-Category" name="Light-Category"
                                                class="form-select form-select-lg selecton-order w-100" disabled>
                                                <option selected="">
                                                    <?php echo htmlspecialchars($product['sub_category_name'] ?? ''); ?>
                                                </option>
                                                <option value="sub-category-1">Sub Category 1</option>
                                                <option value="sub-category-2">Sub Category 2</option>
                                                <option value="sub-category-3">Sub Category 3</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-MRP" class="form-label text-default">MRP
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-MRP"
                                                value="<?php echo isset($product['mrp']) ? '₹' . number_format((float) $product['mrp'], 2) : ''; ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Selling" class="form-label text-default">Selling Price
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Selling"
                                                value="<?php echo isset($product['selling_price']) ? '₹' . number_format((float) $product['selling_price'], 2) : ''; ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Discount" class="form-label text-default">Discount (%)
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Discount"
                                                value="<?php echo isset($product['discount']) ? rtrim(rtrim(number_format((float) $product['discount'], 2), '0'), '.') . ' %' : ''; ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Product Description
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <ul class="product-description-bg">
                                                    <li><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">Brand Name
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Brand"
                                                value="<?php echo htmlspecialchars($product['brand'] ?? $product['product_brand'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">GST (%)
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Brand"
                                                value="<?php echo isset($product['gst']) ? rtrim(rtrim(number_format((float) $product['gst'], 2), '0'), '.') . ' %' : ''; ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">HSN ID
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Brand"
                                                value="<?php echo htmlspecialchars($product['hsn_id'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Manufacturer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <ul class="product-description-bg">
                                                    <li><?php echo nl2br(htmlspecialchars($product['manufacture_details'] ?? '')); ?>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Packer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <ul class="product-description-bg">
                                                    <li><?php echo nl2br(htmlspecialchars($product['packaging_details'] ?? '')); ?>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-12 ">
                                            <label for="Light-SKU" class="form-label text-default">SKU ID
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-SKU"
                                                value="<?php echo htmlspecialchars($product['sku_id'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Group" class="form-label text-default">Group ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Group"
                                                value="<?php echo htmlspecialchars($product['group_id'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Style" class="form-label text-default">Product ID / Style
                                                ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Style"
                                                value="<?php echo htmlspecialchars($product['style_id'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Inventory" class="form-label text-default">Inventory
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Inventory"
                                                value="<?php echo htmlspecialchars($product['Inventory'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Inventory" class="form-label text-default">Product Brand
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Inventory"
                                                value="<?php echo htmlspecialchars($product['product_brand'] ?? ''); ?>"
                                                disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="row g-3">
                                        <div class="col-12  ">
                                            <label for="Light-Images" class="form-label text-default">Product Images
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                                <?php
                                                $images = [];
                                                if (!empty($product['images'])) {
                                                    $decoded = @json_decode($product['images'], true);
                                                    if (is_array($decoded)) {
                                                        foreach ($decoded as $img) {
                                                            if (is_string($img) && $img !== '') {
                                                                $images[] = preg_match('/^(https?:)?\/\//i', $img) || str_starts_with($img, '/') ? $img : $vendor_baseurl . $img;
                                                            } elseif (is_array($img)) {
                                                                $candidate = $img['url'] ?? $img['path'] ?? '';
                                                                if ($candidate !== '') {
                                                                    $images[] = (preg_match('/^(https?:)?\/\//i', $candidate) || str_starts_with($candidate, '/')) ? $candidate : $vendor_baseurl . $candidate;
                                                                }
                                                            }
                                                        }
                                                    } else {
                                                        $parts = explode(',', (string) $product['images']);
                                                        foreach ($parts as $p) {
                                                            $p = trim($p);
                                                            if ($p !== '') {
                                                                $images[] = preg_match('/^(https?:)?\/\//i', $p) || str_starts_with($p, '/') ? $p : $vendor_baseurl . $p;
                                                            }
                                                        }
                                                    }
                                                }
                                                if (empty($images)) {
                                                    $images[] = $vendor_baseurl . 'uploads/vendors/no-product.png';
                                                }
                                                foreach ($images as $imgSrc) {
                                                    ?>
                                                    <div class="add-productbu">
                                                        <div class="add-product-img">
                                                            <img src="<?php if ($imgSrc !== '') {
                                                                echo htmlspecialchars($imgSrc);
                                                            } else {
                                                                echo $vendor_baseurl . '/assets/images/default.png';
                                                            } ?>" alt=""
                                                                style="width:100px;height:100px;object-fit:cover;border-radius:8px;">
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-12  ">
                                            <label for="Light-Images" class="form-label text-default">Product Video
                                            </label>
                                            <?php
                                            $videos = [];
                                            if (!empty($product['video'])) {
                                                $rawVideo = trim((string) $product['video']);
                                                // Try to decode as JSON first
                                                $decodedVideo = @json_decode($rawVideo, true);
                                                if (is_array($decodedVideo)) {
                                                    // Handle JSON array of videos
                                                    foreach ($decodedVideo as $vid) {
                                                        if (is_string($vid) && $vid !== '') {
                                                            $videos[] = $vid;
                                                        } elseif (is_array($vid)) {
                                                            $vidUrl = $vid['url'] ?? $vid['path'] ?? '';
                                                            if ($vidUrl !== '') {
                                                                $videos[] = $vidUrl;
                                                            }
                                                        }
                                                    }
                                                } else {
                                                    // Handle comma-separated videos or single video
                                                    // Use regex to handle commas within folder names
                                                    preg_match_all('/(.*?\.(?:mp4|mov|avi|webm|mkv|flv|wmv|m4v|ogg|ogv)(?:\?.*)?)(?:,|$)/i', $rawVideo, $matches);
                                                    if (!empty($matches[1])) {
                                                        foreach ($matches[1] as $vp) {
                                                            $vp = trim($vp);
                                                            if ($vp !== '') {
                                                                $videos[] = $vp;
                                                            }
                                                        }
                                                    } else {
                                                        // Fallback: treat as single video
                                                        $vp = trim($rawVideo);
                                                        if ($vp !== '') {
                                                            $videos[] = $vp;
                                                        }
                                                    }
                                                }

                                                // Process each video to build full URLs
                                                foreach ($videos as $idx => $vid) {
                                                    if (!(preg_match('/^(https?:)?\/\//i', $vid) || str_starts_with($vid, '/'))) {
                                                        $videos[$idx] = $vendor_baseurl . $vid;
                                                    }
                                                }
                                            }
                                            // Store videos array for JavaScript access
                                            $videosJson = json_encode($videos);
                                            ?>
                                            <?php if (!empty($videos)): ?>
                                                <div class="d-flex gap-3" style="max-width:560px;">
                                                    <!-- Left: Vertical Thumbnail Slider -->
                                                    <div class="thumbnail-container"
                                                        style="display:flex;flex-direction:column;align-items:center;">
                                                        <button class="btn-nav btn-up" id="videoThumbUp"
                                                            style="background:#fff;border:1px solid #ddd;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-bottom:5px;opacity:0.6;"
                                                            disabled>
                                                            <i class="fa-solid fa-chevron-up" style="font-size:12px;"></i>
                                                        </button>
                                                        <div class="thumbnail-wrapper"
                                                            style="max-height:400px;overflow-y:auto;overflow-x:hidden;scrollbar-width:thin;display:flex;flex-direction:column;gap:8px;">
                                                            <?php foreach ($videos as $idx => $video): ?>
                                                                <?php
                                                                $isSelected = $idx === 0 ? 'selected' : '';
                                                                $lower = strtolower($video);
                                                                $isYoutube = (strpos($lower, 'youtube.com') !== false || strpos($lower, 'youtu.be') !== false);
                                                                ?>
                                                                <div class="bright-shot-img position-relative video-thumbnail"
                                                                    data-video-index="<?php echo $idx; ?>"
                                                                    data-video-url="<?php echo htmlspecialchars($video); ?>"
                                                                    style="width:70px;height:70px;border:2px solid <?php echo $idx === 0 ? '#007bff' : '#ddd'; ?>;border-radius:8px;overflow:hidden;cursor:pointer;transition:all 0.3s;">
                                                                    <?php if ($isYoutube): ?>
                                                                        <?php
                                                                        $youtubeId = '';
                                                                        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $video, $m)) {
                                                                            $youtubeId = $m[1];
                                                                        } elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $video, $m)) {
                                                                            $youtubeId = $m[1];
                                                                        }
                                                                        ?>
                                                                        <?php if ($youtubeId): ?>
                                                                            <img src="https://img.youtube.com/vi/<?php echo htmlspecialchars($youtubeId); ?>/mqdefault.jpg"
                                                                                alt="Video Thumbnail"
                                                                                style="width:100%;height:100%;object-fit:cover;"
                                                                                onerror="this.src='<?php echo PUBLIC_ASSETS; ?>images/vendor/no-video.png';">
                                                                        <?php else: ?>
                                                                            <div
                                                                                style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;">
                                                                                <i class="fa-solid fa-play" style="color:#999;"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php elseif (preg_match('/\.(mp4|webm|ogg|ogv|mov|avi|mkv|flv|wmv|m4v)(\?.*)?$/i', $lower)): ?>
                                                                        <video src="<?php echo htmlspecialchars($video); ?>#t=0.1"
                                                                            muted preload="metadata"
                                                                            style="width:100%;height:100%;object-fit:cover;"
                                                                            onerror="this.parentElement.innerHTML='<div style=\'width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;\'><i class=\'fa-solid fa-play\' style=\'color:#999;\'></i></div>';"></video>
                                                                    <?php else: ?>
                                                                        <div
                                                                            style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;">
                                                                            <i class="fa-solid fa-play" style="color:#999;"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <span class="position-absolute"
                                                                        style="right:4px;bottom:4px;background:rgba(0,0,0,0.7);color:#fff;border-radius:6px;padding:2px 5px;font-size:9px;font-weight:600;">Video</span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <button class="btn-nav btn-down" id="videoThumbDown"
                                                            style="background:#fff;border:1px solid #ddd;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-top:5px;opacity:0.6;"
                                                            <?php echo count($videos) <= 1 ? 'disabled' : ''; ?>>
                                                            <i class="fa-solid fa-chevron-down" style="font-size:12px;"></i>
                                                        </button>
                                                    </div>

                                                    <!-- Right: Main Video Player -->
                                                    <div style="flex:1;position:relative;">
                                                        <div class="bright-img"
                                                            style="position:relative;border-radius:8px;overflow:hidden;">
                                                            <button class="btn-main-nav btn-main-left" id="videoMainLeft"
                                                                style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.8);border:none;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;opacity:0.7;"
                                                                disabled>
                                                                <i class="fa-solid fa-chevron-left"></i>
                                                            </button>
                                                            <div id="mainVideoContainer"
                                                                style="width:100%;aspect-ratio:16/9;background:#000;border-radius:8px;overflow:hidden;">
                                                                <?php
                                                                $firstVideo = $videos[0];
                                                                $firstLower = strtolower($firstVideo);
                                                                if (strpos($firstLower, 'youtube.com') !== false || strpos($firstLower, 'youtu.be') !== false) {
                                                                    $embedUrl = $firstVideo;
                                                                    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $firstVideo, $m)) {
                                                                        $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
                                                                    } elseif (preg_match('/v=([a-zA-Z0-9_-]+)/', $firstVideo, $m)) {
                                                                        $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
                                                                    }
                                                                    echo '<div class="ratio ratio-16x9"><iframe id="mainVideoPlayer" src="' . htmlspecialchars($embedUrl) . '" allowfullscreen loading="lazy" referrerpolicy="no-referrer" style="border:0;"></iframe></div>';
                                                                } elseif (preg_match('/\.(mp4|webm|ogg|ogv|mov|avi|mkv|flv|wmv|m4v)(\?.*)?$/i', $firstLower)) {
                                                                    $type = 'video/mp4';
                                                                    if (str_ends_with($firstLower, '.webm')) {
                                                                        $type = 'video/webm';
                                                                    } elseif (str_ends_with($firstLower, '.ogg') || str_ends_with($firstLower, '.ogv')) {
                                                                        $type = 'video/ogg';
                                                                    }
                                                                    echo '<video id="mainVideoPlayer" controls style="width:100%;height:100%;object-fit:contain;border:0;outline:none;display:block;">
                                                                            <source src="' . htmlspecialchars($firstVideo) . '" type="' . $type . '">
                                                                            Your browser does not support the video tag.
                                                                          </video>';
                                                                } else {
                                                                    echo '<div class="ratio ratio-16x9 d-flex align-items-center justify-content-center bg-light">
                                                                            <a href="' . htmlspecialchars($firstVideo) . '" target="_blank" rel="noopener" class="btn btn-primary">View Video</a>
                                                                          </div>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <button class="btn-main-nav btn-main-right" id="videoMainRight"
                                                                style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,0.8);border:none;border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:10;opacity:0.7;"
                                                                <?php echo count($videos) <= 1 ? 'disabled' : ''; ?>>
                                                                <i class="fa-solid fa-chevron-right"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="add-product-video"
                                                    style="max-width:560px;width:100%;aspect-ratio:16/9;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#f5f5f5;color:#999;">
                                                    No video found
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card custom-card p-5">
                                            <div class="card-header justify-content-between">
                                                <div class="card-title1">
                                                    Product Specifications
                                                </div>
                                            </div>

                                            <?php if (!empty($productVariants)): ?>
                                                <?php foreach ($productVariants as $index => $spec): ?>
                                                    <?php
                                                    $attributeId = $spec['attribute_id'];
                                                    $attributeName = $attributeNames[$attributeId] ?? 'Unknown Attribute';
                                                    $variants = $spec['variants'] ?? [];
                                                    ?>

                                                    <div class="specification-section mb-4">
                                                        <div class="col-12 d-flex justify-content-between mb-4">
                                                            <div class="col-md-6 mb-1 px-2">
                                                                <label class="form-label">Attribute<span
                                                                        class="text-danger">*</span></label>
                                                                <input type="text" class="form-control form-control-lg"
                                                                    value="<?php echo htmlspecialchars($attributeName); ?>"
                                                                    disabled>
                                                            </div>
                                                            <div class="col-md-6 mb-1 px-2">
                                                                <label class="form-label">Variations<span
                                                                        class="text-danger">*</span></label>
                                                                <div class="form-control form-control-lg"
                                                                    style="min-height: 45px; display: flex; align-items: center; flex-wrap: wrap; gap: 5px;">
                                                                    <?php foreach ($variants as $variant): ?>
                                                                        <?php
                                                                        $variantName = getVariantDisplayName($variant, $variantNames);
                                                                        ?>
                                                                        <span
                                                                            class="badge bg-primary me-1"><?php echo htmlspecialchars($variantName); ?></span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <?php if (!empty($variants)): ?>
                                                            <?php foreach ($variants as $variantIndex => $variant): ?>
                                                                <?php
                                                                $variantName = getVariantDisplayName($variant, $variantNames);
                                                                $mrp = isset($variant['mrp']) ? $variant['mrp'] : '';
                                                                $sellingPrice = isset($variant['selling_price']) ? $variant['selling_price'] : '';
                                                                ?>
                                                                <div class="row g-2 mb-3">
                                                                    <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12">
                                                                        <label class="form-label mb-1">Variant</label>
                                                                        <input type="text" class="form-control"
                                                                            value="<?php echo htmlspecialchars($variantName); ?>"
                                                                            disabled>
                                                                    </div>
                                                                    <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12">
                                                                        <label class="form-label mb-1">MRP<span
                                                                                class="text-danger">*</span></label>
                                                                        <input type="text" class="form-control"
                                                                            value="<?php echo !empty($mrp) ? '₹' . number_format((float) $mrp, 2) : ''; ?>"
                                                                            disabled>
                                                                    </div>
                                                                    <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12">
                                                                        <label class="form-label mb-1">Selling Price<span
                                                                                class="text-danger">*</span></label>
                                                                        <input type="text" class="form-control"
                                                                            value="<?php echo !empty($sellingPrice) ? '₹' . number_format((float) $sellingPrice, 2) : ''; ?>"
                                                                            disabled>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>

                                                        <?php if ($index < count($productVariants) - 1): ?>
                                                            <hr class="mt-0"
                                                                style="border-top: 1px dashed #d1d5db; margin: 22px 0;">
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center py-4">
                                                    <div class="text-muted">No specifications available for this product.
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">

                                    <div class="text-end d-flex justify-content-end flex-wrap gap-2">
                                        <form method="post" id="singleActionForm"
                                            class="d-flex align-items-center gap-2">
                                            <input type="hidden" name="single_action" id="single_action" value="">
                                            <input type="hidden" name="comment" id="single_comment" value="">
                                            <input type="hidden" name="coin_amount" id="single_coin_amount" value="0">
                                            <input type="hidden" name="platform_fee" id="single_platform_fee" value="0">
                                            <input type="hidden" name="product_id"
                                                value="<?php echo (int) $productId; ?>">
                                            <!-- <button type="button" class="btn btn-edit-dtsil reject-btn me-2"
                                                id="singleApprove">Approve</button> -->
                                                
                                            <button type="button" class="btn btn-edit-dtsil reject-btn me-2"
                                                id="singleApprove"
                                                data-coin="<?php echo htmlspecialchars($product['coin'] ?? 0); ?>"
                                                data-fee="<?php echo htmlspecialchars($product['platform_fee'] ?? 0); ?>">
                                                Approve
                                            </button>

                                            <button type="button"
                                                class="btn btn-outline-danger btn-wave waves-effect waves-light reject-btn me-2"
                                                id="singleHold" data-bs-toggle="modal"
                                                data-bs-target="#addAttributeModal">On Hold</button>
                                            <button type="button" class="btn btn-delete-dtsil  reject-btn me-2"
                                                id="singleReject" data-bs-toggle="modal"
                                                data-bs-target="#addAttributeModal">Reject</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-1 col-lg-1 col-md-12 col-sm-12"></div>
            </div>

        </div>
    </div>
    <!-- End::app-content -->

    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div class="modal-body text-center p-4">
                    <!-- Icon -->
                    <div class="mb-3">
                        <div
                            style="width: 60px; height: 60px;border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/order-shipping2.png" alt="Approve Icon"
                                style="width:60px; height:60px;">
                        </div>
                    </div>

                    <!-- Coins Input -->
                    <!-- Message -->
                    <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                        Are you sure, you want to Approve <br> the Product ?
                    </h5>

                    <div class="mb-3">
                        <div class="row g-2 text-start">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Coin</label>
                                <input type="number" min="0" step="1" value="" class="form-control"
                                    id="singleApproveCoins" placeholder="Enter coin">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold">Platform Fee</label>
                                <input type="number" min="0" step="0.01" value="" class="form-control"
                                    id="singleApproveFee" placeholder="Enter platform fee">
                            </div>
                        </div>
                    </div>
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
    <!-- Footer Start -->
    <footer class="footer mt-auto py-3 bg-white text-center">
        <div class="container">
            <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                    class="text-primary fw-semibold"> Hagidy </a>.
                Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                    <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology </span>
                </a>
            </span>
        </div>
    </footer>
    <!-- Footer End -->

    </div>

    <!-- Add Comment Modal -->

    <div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content add-attribute-modal-content">
                <div class="modal-header border-0 pb-0">
                    <h4 class="modal-title w-100 text-center fw-bold" id="addAttributeModalLabel">Add Comment</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <form id="attributeForm">
                        <div class="mb-3">
                            <label for="variant" class="form-label fw-semibold">Comment <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control form-control-lg" id="variant"
                                placeholder=" Enter Comment"></textarea>
                        </div>
                        <button type="submit" class="btn save-attribute-btn w-100">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>ś


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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const approveBtn = document.getElementById('singleApprove');
            const holdBtn = document.getElementById('singleHold');
            const rejectBtn = document.getElementById('singleReject');
            const form = document.getElementById('singleActionForm');
            const actionInput = document.getElementById('single_action');
            const commentInput = document.getElementById('single_comment');
            const commentTextarea = document.getElementById('variant');
            const commentModalEl = document.getElementById('addAttributeModal');
            const commentModal = new bootstrap.Modal(commentModalEl);

            // Use modal to confirm single approve
            const approveModalEl = document.getElementById('approveModal');
            const approveModal = new bootstrap.Modal(approveModalEl);
            const approveNoBtn = document.getElementById('cancelNoBtn');
            const approveYesBtn = document.getElementById('cancelYesBtn');

            approveBtn?.addEventListener('click', function () {
                const coin = approveBtn.getAttribute('data-coin');
                const fee = approveBtn.getAttribute('data-fee');
                document.getElementById('singleApproveCoins').value = coin;
                document.getElementById('singleApproveFee').value = fee;
                approveModal.show();
            });
            approveNoBtn?.addEventListener('click', function () { approveModal.hide(); });
            approveYesBtn?.addEventListener('click', function () {
                // Read coin input
                const coinInput = document.getElementById('singleApproveCoins');
                const hiddenCoin = document.getElementById('single_coin_amount');
                if (coinInput && hiddenCoin) {
                    const v = parseInt(coinInput.value || '0', 10);
                    hiddenCoin.value = isNaN(v) || v < 0 ? 0 : v;
                }

                // ✅ Read Platform Fee input
                const feeInput = document.getElementById('singleApproveFee');
                const hiddenFee = document.getElementById('single_platform_fee');
                if (feeInput && hiddenFee) {
                    const f = parseFloat(feeInput.value || '0');
                    hiddenFee.value = isNaN(f) || f < 0 ? 0 : f.toFixed(2);
                }

                actionInput.value = 'approved';
                form.submit();
            });


            let pendingAction = '';
            holdBtn?.addEventListener('click', function () {
                pendingAction = 'hold';
                if (commentTextarea) commentTextarea.value = '';
            });
            rejectBtn?.addEventListener('click', function () {
                pendingAction = 'rejected';
                if (commentTextarea) commentTextarea.value = '';
            });

            const attributeForm = document.getElementById('attributeForm');
            attributeForm?.addEventListener('submit', function (e) {
                e.preventDefault();
                const text = (commentTextarea?.value || '').trim();
                if (text === '') {
                    alert('Please enter a comment.');
                    return;
                }
                commentInput.value = text;
                actionInput.value = pendingAction || 'hold';
                commentModal.hide();
                form.submit();
            });
        });
    </script>

    <script>
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });

            inputElement.addEventListener("change", (e) => {
                if (inputElement.files.length) {
                    updateThumbnail(dropZoneElement, inputElement.files[0]);
                }
            });

            dropZoneElement.addEventListener("dragover", (e) => {
                e.preventDefault();
                dropZoneElement.classList.add("drop-zone--over");
            });

            ["dragleave", "dragend"].forEach((type) => {
                dropZoneElement.addEventListener(type, (e) => {
                    dropZoneElement.classList.remove("drop-zone--over");
                });
            });

            dropZoneElement.addEventListener("drop", (e) => {
                e.preventDefault();

                if (e.dataTransfer.files.length) {
                    inputElement.files = e.dataTransfer.files;
                    updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                }

                dropZoneElement.classList.remove("drop-zone--over");
            });
        });
    </script>

    <script>
        // Video Slider Functionality
        document.addEventListener('DOMContentLoaded', function () {
            const videoThumbnails = document.querySelectorAll('.video-thumbnail');
            const videoThumbUp = document.getElementById('videoThumbUp');
            const videoThumbDown = document.getElementById('videoThumbDown');
            const videoMainLeft = document.getElementById('videoMainLeft');
            const videoMainRight = document.getElementById('videoMainRight');
            const thumbnailWrapper = document.querySelector('.thumbnail-wrapper');
            const mainVideoContainer = document.getElementById('mainVideoContainer');

            if (videoThumbnails.length > 0 && mainVideoContainer) {
                let currentVideoIndex = 0;
                const videos = <?php echo isset($videosJson) ? $videosJson : '[]'; ?>;

                console.log('Videos loaded:', videos);
                console.log('Video thumbnails found:', videoThumbnails.length);

                // Function to update main video player
                function updateMainVideo(index) {
                    if (index < 0 || index >= videos.length) {
                        console.error('Invalid video index:', index);
                        return;
                    }

                    const video = videos[index];
                    const lower = video.toLowerCase();
                    let html = '';

                    if (lower.includes('youtube.com') || lower.includes('youtu.be')) {
                        let embedUrl = video;
                        const youtuBeMatch = video.match(/youtu\.be\/([a-zA-Z0-9_-]+)/);
                        const vMatch = video.match(/[?&]v=([a-zA-Z0-9_-]+)/);
                        if (youtuBeMatch) {
                            embedUrl = 'https://www.youtube.com/embed/' + youtuBeMatch[1];
                        } else if (vMatch) {
                            embedUrl = 'https://www.youtube.com/embed/' + vMatch[1];
                        }
                        html = '<div class="ratio ratio-16x9"><iframe src="' + embedUrl + '" allowfullscreen loading="lazy" referrerpolicy="no-referrer" style="border:0;width:100%;height:100%;"></iframe></div>';
                    } else if (/\.(mp4|webm|ogg|ogv|mov|avi|mkv|flv|wmv|m4v)(\?.*)?$/i.test(lower)) {
                        let type = 'video/mp4';
                        if (lower.endsWith('.webm')) {
                            type = 'video/webm';
                        } else if (lower.endsWith('.ogg') || lower.endsWith('.ogv')) {
                            type = 'video/ogg';
                        }
                        html = '<video controls style="width:100%;height:100%;object-fit:contain;border:0;outline:none;display:block;"><source src="' + video + '" type="' + type + '">Your browser does not support the video tag.</video>';
                    } else {
                        html = '<div class="ratio ratio-16x9 d-flex align-items-center justify-content-center bg-light"><a href="' + video + '" target="_blank" rel="noopener" class="btn btn-primary">View Video</a></div>';
                    }

                    if (mainVideoContainer) {
                        mainVideoContainer.innerHTML = html;
                        currentVideoIndex = index;
                        updateVideoButtons();
                        updateThumbnailSelection();
                    }
                }

                // Function to update thumbnail selection
                function updateThumbnailSelection() {
                    videoThumbnails.forEach((thumb, idx) => {
                        if (idx === currentVideoIndex) {
                            thumb.style.borderColor = '#007bff';
                            thumb.style.borderWidth = '2px';
                        } else {
                            thumb.style.borderColor = '#ddd';
                            thumb.style.borderWidth = '2px';
                        }
                    });
                }

                // Function to update button states
                function updateVideoButtons() {
                    if (videoThumbUp) {
                        videoThumbUp.disabled = currentVideoIndex === 0;
                        videoThumbUp.style.opacity = currentVideoIndex === 0 ? '0.3' : '0.6';
                    }
                    if (videoThumbDown) {
                        videoThumbDown.disabled = currentVideoIndex === videos.length - 1;
                        videoThumbDown.style.opacity = currentVideoIndex === videos.length - 1 ? '0.3' : '0.6';
                    }
                    if (videoMainLeft) {
                        videoMainLeft.disabled = currentVideoIndex === 0;
                        videoMainLeft.style.opacity = currentVideoIndex === 0 ? '0.3' : '0.7';
                    }
                    if (videoMainRight) {
                        videoMainRight.disabled = currentVideoIndex === videos.length - 1;
                        videoMainRight.style.opacity = currentVideoIndex === videos.length - 1 ? '0.3' : '0.7';
                    }
                }

                // Function to scroll thumbnail into view
                function scrollToThumbnail(index) {
                    const thumbnail = videoThumbnails[index];
                    if (thumbnail && thumbnailWrapper) {
                        const scrollPosition = thumbnail.offsetTop - thumbnailWrapper.offsetTop - (thumbnailWrapper.offsetHeight / 2) + (thumbnail.offsetHeight / 2);
                        thumbnailWrapper.scrollTo({
                            top: scrollPosition,
                            behavior: 'smooth'
                        });
                    }
                }

                // Click event on thumbnails
                videoThumbnails.forEach((thumb, index) => {
                    thumb.addEventListener('click', function () {
                        updateMainVideo(index);
                        scrollToThumbnail(index);
                    });
                });

                // Thumbnail navigation buttons
                if (videoThumbUp) {
                    videoThumbUp.addEventListener('click', function () {
                        if (currentVideoIndex > 0) {
                            updateMainVideo(currentVideoIndex - 1);
                            scrollToThumbnail(currentVideoIndex);
                        }
                    });
                }

                if (videoThumbDown) {
                    videoThumbDown.addEventListener('click', function () {
                        if (currentVideoIndex < videos.length - 1) {
                            updateMainVideo(currentVideoIndex + 1);
                            scrollToThumbnail(currentVideoIndex);
                        }
                    });
                }

                // Main video navigation buttons
                if (videoMainLeft) {
                    videoMainLeft.addEventListener('click', function () {
                        if (currentVideoIndex > 0) {
                            updateMainVideo(currentVideoIndex - 1);
                            scrollToThumbnail(currentVideoIndex);
                        }
                    });
                }

                if (videoMainRight) {
                    videoMainRight.addEventListener('click', function () {
                        if (currentVideoIndex < videos.length - 1) {
                            updateMainVideo(currentVideoIndex + 1);
                            scrollToThumbnail(currentVideoIndex);
                        }
                    });
                }

                // Initialize
                updateVideoButtons();
                updateThumbnailSelection();
            }
        });
    </script>
</body>

</html>
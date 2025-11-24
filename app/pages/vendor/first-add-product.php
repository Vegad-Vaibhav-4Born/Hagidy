<?php
session_start();
include '../../pages/includes/init.php';
require_once __DIR__ . '/../../handlers/acess_guard.php';
$vendor_reg_id = $_SESSION['vendor_reg_id'];
// Inline AJAX: load subcategories without page refresh
if (isset($_GET['ajax']) && $_GET['ajax'] === 'subcategories') {
    header('Content-Type: application/json');
    $catId = isset($_GET['category_id']) ? mysqli_real_escape_string($con, $_GET['category_id']) : '';
    $items = [];
    if (!empty($catId)) {
        $rs = mysqli_query($con, "SELECT id, name FROM sub_category WHERE category_id = '$catId' ORDER BY name");
        if ($rs) {
            while ($r = mysqli_fetch_assoc($rs)) {
                $items[] = $r;
            }
        }
    }
    echo json_encode(['success' => true, 'subcategories' => $items]);
    exit;
}
// Fetch vendor business name (for folder path)
$vendor_business_name = isset($_SESSION['vendor_business_name']) ? $_SESSION['vendor_business_name'] : '';
if (empty($vendor_business_name)) {
    $vd = mysqli_query($con, "SELECT business_name FROM vendor_registration WHERE id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "'");
    if ($vd && mysqli_num_rows($vd) > 0) {
        $vendor_business_name = mysqli_fetch_assoc($vd)['business_name'];
    }
}
// Check if we're adding a NEW product (not editing)
// Clear session data from previous edits when accessing the add product page directly
$is_new_product = true;

// If editing_product_id exists in session, it means we came from an edit page, so clear all session data
if (isset($_SESSION['editing_product_id']) && !empty($_SESSION['editing_product_id'])) {
    // We're coming from an edit page - clear all product-related session data for a fresh start
    unset($_SESSION['pending_product_images']);
    unset($_SESSION['pending_product_videos']);
    unset($_SESSION['pending_product_video']);
    unset($_SESSION['pending_product_path']);
    unset($_SESSION['pending_product_payload']);
    unset($_SESSION['editing_product_id']);
    unset($_SESSION['existing_specifications']);
    $is_new_product = true;
}

// Additional safety check: If we have images in session but no payload, or payload has editing_product_id,
// it means these are leftover from a previous edit - clear them
if (isset($_SESSION['pending_product_images']) && (!isset($_SESSION['pending_product_payload']) || empty($_SESSION['pending_product_payload']))) {
    // No payload but images exist - likely leftover from edit, clear them
    unset($_SESSION['pending_product_images']);
    unset($_SESSION['pending_product_videos']);
    unset($_SESSION['pending_product_video']);
    unset($_SESSION['pending_product_path']);
}
if (isset($_SESSION['pending_product_payload'])) {
    $payload_check = json_decode($_SESSION['pending_product_payload'], true);
    if (is_array($payload_check) && isset($payload_check['editing_product_id']) && !empty($payload_check['editing_product_id'])) {
        // Payload contains editing_product_id - this is from an edit, clear everything
        unset($_SESSION['pending_product_images']);
        unset($_SESSION['pending_product_videos']);
        unset($_SESSION['pending_product_video']);
        unset($_SESSION['pending_product_path']);
        unset($_SESSION['pending_product_payload']);
        unset($_SESSION['editing_product_id']);
        unset($_SESSION['existing_specifications']);
    }
}

// Selected filters from URL for dependent dropdowns
$selected_category = isset($_GET['category']) ? $_GET['category'] : '';
$selected_subcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : '';
// Load form data from session if coming back from specifications page
$form_data = [];
if (isset($_SESSION['pending_product_payload'])) {
    $payload = json_decode($_SESSION['pending_product_payload'], true);
    if (is_array($payload)) {
        // Only use session data if it's NOT for an edited product (no editing_product_id)
        if (!isset($payload['editing_product_id']) || empty($payload['editing_product_id'])) {
        $form_data = $payload;
        // Set selected category and subcategory from session data
        if (!empty($payload['category_id'])) {
            $selected_category = $payload['category_id'];
        }
        if (!empty($payload['sub_category_id'])) {
            $selected_subcategory = $payload['sub_category_id'];
            }
        } else {
            // This payload is for an edited product, clear it
            unset($_SESSION['pending_product_payload']);
        }
    }
}
// Handle image upload (store to disk now, save to DB later in next step)
$upload_errors = [];
// Only load images from session if we're coming back from specifications page for a NEW product
// Check if the session images match the current product being added
$uploaded_file_names = [];
if (isset($_SESSION['pending_product_images']) && is_array($_SESSION['pending_product_images'])) {
    // Only use session images if we have a matching product name in the payload
    // This ensures we're not showing images from a previously edited product
    if (isset($_SESSION['pending_product_payload'])) {
        $payload_check = json_decode($_SESSION['pending_product_payload'], true);
        // Only use if no editing_product_id (it's a new product being added)
        if (is_array($payload_check) && (!isset($payload_check['editing_product_id']) || empty($payload_check['editing_product_id']))) {
            $uploaded_file_names = $_SESSION['pending_product_images'];
        } else {
            // Clear session images if they're from an edited product
            unset($_SESSION['pending_product_images']);
            $uploaded_file_names = [];
        }
    } else {
        // No payload means we're starting fresh - clear old images
        unset($_SESSION['pending_product_images']);
        $uploaded_file_names = [];
    }
}
// Only load videos from session if we're coming back from specifications page for a NEW product
$uploaded_video_name = '';
$uploaded_video_names = [];
if (isset($_SESSION['pending_product_payload'])) {
    $payload_check = json_decode($_SESSION['pending_product_payload'], true);
    // Only use videos if no editing_product_id (it's a new product being added)
    if (is_array($payload_check) && (!isset($payload_check['editing_product_id']) || empty($payload_check['editing_product_id']))) {
$uploaded_video_name = isset($_SESSION['pending_product_video']) ? $_SESSION['pending_product_video'] : '';
// Support multiple pending videos in session
$uploaded_video_names = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
    } else {
        // Clear session videos if they're from an edited product
        unset($_SESSION['pending_product_video']);
        unset($_SESSION['pending_product_videos']);
    }
} else {
    // No payload means we're starting fresh - clear old videos
    unset($_SESSION['pending_product_video']);
    unset($_SESSION['pending_product_videos']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_images') {
    // Basic server-side validations
    $product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
    if (strlen($product_name) === 0 || strlen($product_name) > 100) {
        $upload_errors[] = 'Product name is required and must be 100 characters or fewer.';
    }
    // Validate pricing from payload (Selling Price must be less than or equal to MRP)
    if (isset($_POST['form_payload']) && $_POST['form_payload'] !== '') {
        $payload_for_validation = json_decode($_POST['form_payload'], true);
        if (is_array($payload_for_validation)) {
            $mrp_raw = isset($payload_for_validation['mrp']) ? $payload_for_validation['mrp'] : '';
            $sp_raw = isset($payload_for_validation['selling_price']) ? $payload_for_validation['selling_price'] : '';
            $mrp_val = (float)preg_replace('/[^0-9.]/', '', (string)$mrp_raw);
            $sp_val = (float)preg_replace('/[^0-9.]/', '', (string)$sp_raw);
            if ($mrp_val > 0 && $sp_val > 0 && $sp_val > $mrp_val) {
                $upload_errors[] = 'Selling Price must be less than MRP.';
            }
        }
    }
    // Capture other form fields payload into session for next step
    if (isset($_POST['form_payload']) && $_POST['form_payload'] !== '') {
        $_SESSION['pending_product_payload'] = $_POST['form_payload'];
    }
    // Count existing images from session and new files chosen this submit
    $existing_count = is_array($uploaded_file_names) ? count($uploaded_file_names) : 0;
    $new_file_count = 0;
    if (isset($_FILES['product_images']) && is_array($_FILES['product_images']['name'])) {
        foreach ($_FILES['product_images']['name'] as $n) {
            if (!empty($n)) { $new_file_count++; }
        }
    }
    if ($new_file_count === 0) {
        if ($existing_count < 3) {
            $upload_errors[] = 'Minimum of 3 images must be uploaded.';
        }
        } else {
        if (($existing_count + $new_file_count) < 3) {
            $upload_errors[] = 'Minimum of 3 images must be uploaded.';
        }
    }
    if (empty($upload_errors)) {
        // Prepare folder path: uploads/vendors/{vendorname}/{productname}
          $safe_vendor = $vendor_business_name ?: ('vendor-' . $vendor_reg_id);
$safe_product = strtolower($product_name);

        // Store under public/uploads/vendors/{vendor}/{product}
        $public_root = realpath(__DIR__ . '/../../../public');
        if ($public_root === false) { $public_root = __DIR__ . '/../../../public'; }
        $base_dir = rtrim($public_root, '/\\') . '/uploads/vendors/' . $safe_vendor . '/' . $safe_product;
        if (!is_dir($base_dir)) {
            @mkdir($base_dir, 0777, true);
        }
        // Uniform dimensions check removed by request
        // Generate a batch token for filenames
        $batch = date('YmdHis');
        $new_files = [];
        if ($new_file_count > 0) {
        for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
            $name = $_FILES['product_images']['name'][$i];
            $tmp = $_FILES['product_images']['tmp_name'][$i];
            $size = $_FILES['product_images']['size'][$i];
            $err = $_FILES['product_images']['error'][$i];
            if ($err !== UPLOAD_ERR_OK || empty($name)) {
                continue;
            }
            // Validate type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                $upload_errors[] = 'Allowed formats: JPEG, PNG.';
                break;
            }
            // Validate size
            if ($size > 2 * 1024 * 1024) {
                $upload_errors[] = 'Maximum file size is 2 MB per image.';
                break;
            }
            // Validate dimensions and uniformity (ratio check removed as requested)
            $dim = @getimagesize($tmp);
            if (!$dim) {
                $upload_errors[] = 'Invalid image file.';
                break;
            }
            list($w, $h) = $dim;
            if ($w <= 0 || $h <= 0) {
                $upload_errors[] = 'Invalid image dimensions.';
                break;
            }
            // Build filename pattern like "{vendorId}-{batch}-{index}.ext"
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === 'jpeg') { $ext = 'jpg'; }
            $new_name = $vendor_reg_id . '-' . $batch . '-' . ($i + 1) . '.' . $ext;
            $dest = $base_dir . '/' . $new_name;
            if (!@move_uploaded_file($tmp, $dest)) {
                $upload_errors[] = 'Failed to store uploaded image.';
                break;
            }
            $new_files[] = $new_name;
            }
        }
        if (empty($upload_errors)) {
            // Merge newly uploaded files with existing session images (preserve existing)
            if (!empty($new_files)) {
                $merged = array_values(array_merge(is_array($uploaded_file_names) ? $uploaded_file_names : [], $new_files));
                $_SESSION['pending_product_images'] = $uploaded_file_names = $merged;
            }
            // Always ensure path is set for subsequent steps
            $_SESSION['pending_product_path'] = 'uploads/vendors/' . $safe_vendor . '/' . $safe_product;
            // Handle multiple video uploads
            $uploaded_videos = [];
            if (isset($_FILES['product_videos']) && is_array($_FILES['product_videos']['name'])) {
                $allowed_video_types = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/webm'];
                for ($i = 0; $i < count($_FILES['product_videos']['name']); $i++) {
                    $vname = $_FILES['product_videos']['name'][$i];
                    $vtmp = $_FILES['product_videos']['tmp_name'][$i];
                    $vsize = $_FILES['product_videos']['size'][$i];
                    $verr = $_FILES['product_videos']['error'][$i];
                    if ($verr !== UPLOAD_ERR_OK || empty($vname)) {
                        continue;
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $vmime = finfo_file($finfo, $vtmp);
                    finfo_close($finfo);
                    if (!in_array($vmime, $allowed_video_types)) {
                        $upload_errors[] = 'Invalid video format. Allowed: MP4, AVI, MOV, WMV, WebM.';
                        break;
                    }
                    if ($vsize > 50 * 1024 * 1024) {
                        $upload_errors[] = 'Video file size must be less than 50 MB.';
                        break;
                    }
                    $vext = strtolower(pathinfo($vname, PATHINFO_EXTENSION));
                    $video_new_name = $vendor_reg_id . '-' . time() . '-' . ($i + 1) . '-video.' . $vext;
                    $video_dest = $base_dir . '/' . $video_new_name;
                    if (!@move_uploaded_file($vtmp, $video_dest)) {
                        $upload_errors[] = 'Failed to upload a video file.';
                        break;
                    }
                    $uploaded_videos[] = $video_new_name;
                }
                if (empty($upload_errors) && !empty($uploaded_videos)) {
                    // Merge with any existing session videos instead of overwriting
                    $existing_videos = isset($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : [];
                    $_SESSION['pending_product_videos'] = array_values(array_merge($existing_videos, $uploaded_videos));
                }
            }
            // Redirect to second page immediately after successful upload
            // header('Location: addProduct.php');
            header('Location: add-products-sp.php');
            exit;
        } else {
            // Cleanup partial files when an error occurs
            if (!empty($base_dir) && !empty($new_files)) {
                foreach ($new_files as $f) {
                    @unlink($base_dir . '/' . $f);
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
    <title> ADD - PRODUCT | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template setting, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, setting, setting template bootstrap, admin setting bootstrap.">
    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">
    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/main.js"></script>
    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">
    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.css" rel="stylesheet">
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
        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/media/loader.svg" alt="">
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
            <div class="row g-3 p-3">
                <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                    <div
                        class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap g-3">
                        <h1 class="page-title fw-semibold fs-18 mb-0">Add Product</h1>
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="product-management.php">Accessories Product </a>
                                    </li>
                                    <li class="breadcrumb-item active" aria-current="page">Add Product</li>
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
                                                name="product_name" maxlength="100" placeholder="Light Blue Sweat Shirt"
                                                value="<?php echo htmlspecialchars($form_data['product_name'] ?? ''); ?>"
                                                required>
                                            <div class="product-add-text">
                                                <h3>*Product Name should not exceed 100 characters</h3>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default">Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-Category" name="category"
                                                class="form-select form-select-lg selecton-order w-100" required
                                                onchange="onCategoryChange(this.value)">
                                                <option value="">Category</option>
                                                <?php
                                                $vendorQuery = mysqli_query($con, "SELECT product_categories FROM vendor_registration WHERE vendor_id = '$vendor_id'");
                                                $vendorData = mysqli_fetch_assoc($vendorQuery);
                                                $selectedCategories = [];
                                                if (!empty($vendorData['product_categories'])) {
                                                    $selectedCategories = explode(',', $vendorData['product_categories']); // array of ids
                                                }
                                                // Build SQL only if categories exist
                                                if (!empty($selectedCategories)) {
                                                    $ids = implode(',', array_map('intval', $selectedCategories)); // sanitize to int
                                                    $categories = mysqli_query($con, "SELECT * FROM category WHERE id IN ($ids) ORDER BY name");
                                                } else {
                                                    $categories = mysqli_query($con, "SELECT * FROM category ORDER BY name");
                                                }
                                                while ($category = mysqli_fetch_assoc($categories)) {
                                                    $sel = ($selected_category == $category['id']) ? 'selected' : '';
                                                    echo "<option value='" . $category['id'] . "' $sel>" . $category['name'] . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Category" class="form-label text-default">Sub Category
                                                <span class="text-danger">*</span></label>
                                            <select id="Light-SubCategory" name="subcategory" required
                                                class="form-select form-select-lg selecton-order w-100" required
                                                onchange="onSubCategoryChange(this.value)">
                                                <option value="">Sub Category</option>
                                                <?php
                                                if (!empty($selected_category)) {
                                                    $subcategories = mysqli_query($con, "SELECT * FROM sub_category WHERE category_id='" . mysqli_real_escape_string($con, $selected_category) . "' ORDER BY name");
                                                    while ($subcategory = mysqli_fetch_assoc($subcategories)) {
                                                        $sel = ($selected_subcategory == $subcategory['id']) ? 'selected' : '';
                                                        echo "<option value='" . $subcategory['id'] . "' $sel>" . $subcategory['name'] . "</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-MRP" class="form-label text-default">MRP
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-MRP"
                                                placeholder="₹1,499.90"
                                                value="<?php echo htmlspecialchars($form_data['mrp'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Selling" class="form-label text-default">Selling Price
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Selling"
                                                placeholder="₹1,299.99"
                                                value="<?php echo htmlspecialchars($form_data['selling_price'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-4 col-lg-4 col-md-6 col-sm-12 ">
                                            <label for="Light-Discount" class="form-label text-default">Discount (%)
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Discount"
                                                disabled placeholder="5.0 %"
                                                value="<?php echo htmlspecialchars($form_data['discount'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Product Description
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Description"
                                                    class="form-control form-control-lg" required
                                                    placeholder="Enter Product Description "><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">Brand Name
                                            </label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Brand"
                                                placeholder="Enter Brand Name "
                                                value="<?php echo htmlspecialchars($form_data['brand'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">GST (%)
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg" id="Light-GST"
                                                placeholder="1.50%"
                                                value="<?php echo htmlspecialchars($form_data['gst'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">HSN ID
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg" id="Light-HSN"
                                                placeholder="Enter HSN ID "
                                                value="<?php echo htmlspecialchars($form_data['hsn_id'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Manufacturer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Manufacturer"
                                                    class="form-control form-control-lg" required
                                                    placeholder="Enter Manufacturer Details "><?php echo htmlspecialchars($form_data['manufacture_details'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label for="Light-MRP" class="form-label text-default">Packer Details
                                                <span class="text-danger">*</span></label>
                                            <div class="cart-product-li">
                                                <textarea name="" id="Light-Packer" class="form-control form-control-lg"
                                                    required
                                                    placeholder="Enter Packer Details "><?php echo htmlspecialchars($form_data['packaging_details'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-xl-6 col-lg-12 col-md-12 col-sm-12 col-12">
                                    <div class="row g-3">
                                        <div class="col-12 ">
                                            <label for="Light-SKU" class="form-label text-default">SKU ID
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="Light-SKU"
                                                placeholder="Enter SKU ID  "
                                                value="<?php echo htmlspecialchars($form_data['sku_id'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Group" class="form-label text-default">Group ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Group"
                                                placeholder="Enter Group ID "
                                                value="<?php echo htmlspecialchars($form_data['group_id'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Style" class="form-label text-default">Product ID / Style
                                                ID</label>
                                            <input type="text" class="form-control form-control-lg" id="Light-Style"
                                                placeholder="Enter Product ID / Style ID "
                                                value="<?php echo htmlspecialchars($form_data['style_id'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Inventory" class="form-label text-default">Inventory
                                                <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control form-control-lg"
                                                id="Light-Inventory" placeholder="Enter Inventory   "
                                                value="<?php echo htmlspecialchars($form_data['inventory'] ?? ''); ?>"
                                                required>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 ">
                                            <label for="Light-Brand" class="form-label text-default">Product Brand
                                            </label>
                                            <input type="text" class="form-control form-control-lg"
                                                id="Light-ProductBrand" placeholder="Enter Product Brand  "
                                                value="<?php echo htmlspecialchars($form_data['product_brand'] ?? ''); ?>">
                                        </div>
                                        <div class="col-12  ">
                                            <label for="Light-Images" class="form-label text-default">Product Images
                                                <span class="text-danger">*</span></label>
                                            <div class="drop-zone">
                                                <span class="drop-zone__prompt">Drag &amp; Drop your files or
                                                    Browse</span>
                                                <form id="imageUploadForm" method="POST" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="upload_images">
                                                    <input type="text" name="product_name" class="d-none"
                                                        id="hiddenProductName">
                                                    <input type="hidden" name="form_payload" id="form_payload">
                                                    <input type="file" id="product_images" name="product_images[]"
                                                        class="drop-zone__input" accept="image/jpeg,image/png" multiple
                                                        required>
                                                    <input type="file" id="product_videos" name="product_videos[]"
                                                        class="d-none"
                                                        accept="video/mp4,video/avi,video/mov,video/wmv,video/webm"
                                                        multiple>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-12  ">
                                            <div class="minimum-contaiten">
                                                <ul>
                                                    <li>Minimum of 3 images must be uploaded.</li>
                                                    <li>Image dimensions: 30 cm (Height) × 60 cm (Width).</li>
                                                    <li>Allowed formats: JPEG, PNG.</li>
                                                    <li>Maximum file size: 2 MB per image.</li>
                                                    <li>Graphic, inverted, or pixelated images are not accepted.</li>
                                                    <li>Images with text or watermark are not acceptable in primary
                                                        images.</li>
                                                    <li>Blurry or cluttered images are not accepted.</li>
                                                    <li>Images must not contain price or brand logos.</li>
                                                    <li>Product images must not be shrunk, elongated, or stretched.</li>
                                                    <li>Partial product images are not allowed.</li>
                                                    <li>Offensive or objectionable images/products are not acceptable.
                                                    </li>
                                                    <li>Once uploaded, to change an image you must wait a minimum of 24
                                                        hours.</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="col-12  ">
                                            <?php if (!empty($upload_errors)): ?>
                                                <div class="alert alert-danger py-2">
                                                    <?php foreach ($upload_errors as $ue): ?>
                                                        <div><?php echo htmlspecialchars($ue); ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12  ">
                                            <label for="product_video_display" class="form-label text-default">Product
                                                Video
                                            </label>
                                            <div class="drop-zone">
                                                <span class="drop-zone__prompt">Drag &amp; Drop your video files or
                                                    Browse</span>
                                                <input type="file" id="product_video_display" class="drop-zone__input"
                                                    accept="video/mp4,video/avi,video/mov,video/wmv,video/webm"
                                                    multiple>
                                            </div>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fe fe-info me-1"></i>
                                                    Supported formats: MP4, AVI, MOV, WMV, WebM<br>
                                                    Maximum file size: 50 MB
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="text-end mt-5">
                                        <button type="button" id="saveNextBtn"
                                            class="btn btn-light btn-wave waves-effect waves-light"
                                            style="color: white; background-color: #3B4B6B; width: fit-content;">
                                            Save & Next
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <!-- End::app-content -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="input-group">
                        <a href="javascript:void(0);" class="input-group-text" id="Search-Grid"><i
                                class="fe fe-search header-link-icon fs-18"></i></a>
                        <input type="search" class="form-control border-0 px-2" placeholder="Search"
                            aria-label="Username">
                        <a href="javascript:void(0);" class="input-group-text" id="voice-search"><i
                                class="fe fe-mic header-link-icon"></i></a>
                        <a href="javascript:void(0);" class="btn btn-light btn-icon" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="fe fe-more-vertical"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);">Another action</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                        <span class="search-tags"><i class="fe fe-user me-2"></i>People<a href="javascript:void(0)"
                                class="tag-addon"><i class="fe fe-x"></i></a></span>
                        <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a href="javascript:void(0)"
                                class="tag-addon"><i class="fe fe-x"></i></a></span>
                        <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                        <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a href="javascript:void(0)"
                                class="tag-addon"><i class="fe fe-x"></i></a></span>
                    </div>
                    <div class="my-4">
                        <p class="font-weight-semibold text-muted mb-2">Recent Search :</p>
                        <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                            <a href="notifications.php"><span>Notifications</span></a>
                            <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                        </div>
                        <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                            <a href="alerts.php"><span>Alerts</span></a>
                            <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                        </div>
                        <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                            <a href="mail.php"><span>Mail</span></a>
                            <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="btn-group ms-auto">
                        <button class="btn btn-sm btn-primary-light">Search</button>
                        <button class="btn btn-sm btn-primary">Clear Recents</button>
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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/defaultmenu.min.js"></script>
    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>
    <!-- Sticky JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>
    <!-- Simplebar JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>
    <!-- Color Picker JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>
    <!-- Apex Charts JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>
    <!-- Ecommerce-Dashboard JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/ecommerce-dashboard.js"></script>
    <!-- Custom-Switcher JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>
    <script>
        // Render previously uploaded images (stored in session) into the preview list
        function loadExistingImages() {
            try {
                // Session-provided names and base path
                const fileNames = <?php echo json_encode($uploaded_file_names ?? []); ?>;
                const basePath = <?php echo json_encode($_SESSION['pending_product_path'] ?? ''); ?>;
                if (!Array.isArray(fileNames) || fileNames.length === 0 || !basePath) return;
                // Find the images drop zone
                const imgInput = document.getElementById('product_images');
                if (!imgInput) return;
                const dropZoneElement = imgInput.closest('.drop-zone');
                if (!dropZoneElement) return;
                // Ensure preview wrapper exists (aligns with runtime-created wrapper in uploader)
                let previewWrap = document.querySelector('.variant-previews');
                if (!previewWrap) {
                    previewWrap = document.createElement('div');
                    previewWrap.className = 'variant-previews d-flex align-items-center gap-3 flex-wrap mt-4';
                    dropZoneElement.parentElement.appendChild(previewWrap);
                }
                // Render each existing image
                fileNames.forEach(function(name){
                    if (!name) return;
                    // Build absolute URL from web root (uploads/...)
                    const baseurl = '<?php echo USER_BASEURL; ?>' + 'public/';
                    const url = baseurl + '/' + String(basePath).replace(/^\/+/, '') + '/' + String(name).replace(/^\/+/, '');
                    const item = document.createElement('div');
                    item.className = 'add-productbu position-relative';
                    item.style.position = 'relative';
                    item.dataset.fileType = 'existing';
                    item.innerHTML = `
                        <div class="add-product-img"><img src="${url}" style="max-height:80px;object-fit:cover"></div>
                        <div class="small text-muted text-center mt-1" style="width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${name}</div>
                    `;
                    previewWrap.appendChild(item);
                });
            } catch (_) {}
        }
        // Render previously uploaded videos (stored in session) into the preview list
        function loadExistingVideos() {
            try {
                const videoNames = <?php echo json_encode(isset($_SESSION['pending_product_videos']) ? $_SESSION['pending_product_videos'] : []); ?>;
                const basePath = <?php echo json_encode($_SESSION['pending_product_path'] ?? ''); ?>;
                if (!Array.isArray(videoNames) || videoNames.length === 0 || !basePath) return;
                const videoInput = document.getElementById('product_video_display');
                if (!videoInput) return;
                const colWrap = videoInput.closest('.col-12');
                if (!colWrap) return;
                let preview = document.querySelector('.video-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'video-preview mt-2';
                    colWrap.appendChild(preview);
                }
                const baseurl = '<?php echo USER_BASEURL; ?>' + 'public/';
                let html = '';
                videoNames.forEach(function(name){
                    if (!name) return;
                    const url = baseurl + '/' + String(basePath).replace(/^\/+/, '') + '/' + String(name).replace(/^\/+/, '');
                    html += `
                    <div class="card mb-2" data-file-type="existing">
                        <div class="card-body d-flex align-items-center gap-3">
                            <video controls style="width:180px;height:auto" class="rounded">
                                <source src="${url}">
                            </video>
                            <div class="small text-muted">
                                <div><strong>File:</strong> ${name}</div>
                            </div>
                        </div>
                    </div>`;
                });
                preview.innerHTML = html;
            } catch (_) {}
        }
        async function onCategoryChange(value) {
            const subSel = document.getElementById('Light-SubCategory');
            subSel.innerHTML = '<option value="">Loading...</option>';
            if (!value) {
                subSel.innerHTML = '<option value="">Sub Category</option>';
                // Clear localStorage when category is cleared
                clearProductSpecsFromLocalStorage();
                return;
            }
            try {
                // Clear product specifications from localStorage when category changes
                // (since subcategory will also change/reset)
                clearProductSpecsFromLocalStorage();
                
                const res = await fetch('?ajax=subcategories&category_id=' + encodeURIComponent(value), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                subSel.innerHTML = '<option value="">Sub Category</option>';
                if (data && data.success) {
                    data.subcategories.forEach(sc => {
                        const opt = document.createElement('option');
                        opt.value = sc.id;
                        opt.textContent = sc.name;
                        subSel.appendChild(opt);
                    });
                }
                // Load mandatory attributes for the selected category and show a notice if needed
                // Note: This will be overridden when subcategory is selected
                await loadMandatoryAttributes(value);
                // Trigger mandatory sections creation in the next step (addProduct.php)
                // Store the category ID in session for the next step
                try {
                    await fetch('<?php echo USER_BASEURL; ?>app/api/store_category_for_mandatory.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'category_id=' + encodeURIComponent(value)
                    });
                } catch (e) {
}
            } catch (e) {
                subSel.innerHTML = '<option value="">Sub Category</option>';
            }
        }
        // Clear product specifications from localStorage when category/subcategory changes
        function clearProductSpecsFromLocalStorage() {
            try {
                // Get product name from input field
                const productNameInput = document.getElementById('Light-blue');
                const productName = productNameInput ? (productNameInput.value || '').trim() : '';
                
                if (productName) {
                    // Create the same safe key format used in add-products-sp.php
                    const safeKey = productName.toLowerCase().replace(/[^a-z0-9]/g, '_');
                    const localStorageKey = 'product_specs_new_' + safeKey;
                    
                    // Clear the specific key for this product
                    localStorage.removeItem(localStorageKey);
                    console.log('Cleared localStorage for product:', localStorageKey);
                } else {
                    // If no product name, clear all product_specs_new_ keys
                    const allKeys = Object.keys(localStorage);
                    allKeys.forEach(key => {
                        if (key.startsWith('product_specs_new_')) {
                            localStorage.removeItem(key);
                            console.log('Cleared localStorage key:', key);
                        }
                    });
                }
            } catch (e) {
                console.error('Error clearing product specs from localStorage:', e);
            }
        }
        
        // Handle subcategory change to load mandatory attributes based on subcategory
        async function onSubCategoryChange(subcategoryId) {
            if (!subcategoryId) {
                return;
            }
            try {
                // Clear product specifications from localStorage when subcategory changes
                clearProductSpecsFromLocalStorage();
                
                // Load mandatory attributes for the selected subcategory
                await loadMandatoryAttributesForSubcategory(subcategoryId);
                // Store the subcategory ID in session for the next step
                try {
                    await fetch('<?php echo USER_BASEURL; ?>app/api/store_category_for_mandatory.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'sub_category_id=' + encodeURIComponent(subcategoryId)
                    });
                } catch (e) {
}
            } catch (e) {
                console.error('Error loading mandatory attributes for subcategory:', e);
            }
        }
        // Fetch mandatory attribute ids for subcategory and store in window for validation
        async function loadMandatoryAttributesForSubcategory(subcategoryId) {
            try {
                const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?sub_category_id=' + encodeURIComponent(subcategoryId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const json = await resp.json();
                window.__MANDATORY_ATTR_IDS__ = Array.isArray(json.mandatory) ? json.mandatory : [];
            } catch (_) {
                window.__MANDATORY_ATTR_IDS__ = [];
            }
        }
        // Fetch mandatory attribute ids for category and store in window for validation
        async function loadMandatoryAttributes(categoryId) {
            try {
                const resp = await fetch('<?php echo USER_BASEURL; ?>app/api/get_category_mandatories.php?category_id=' + encodeURIComponent(categoryId), {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const json = await resp.json();
                window.__MANDATORY_ATTR_IDS__ = Array.isArray(json.mandatory) ? json.mandatory : [];
            } catch (_) {
                window.__MANDATORY_ATTR_IDS__ = [];
            }
        }
        // Function to calculate discount dynamically
        function calculateDiscount() {
            const mrpInput = document.getElementById('Light-MRP');
            const sellingInput = document.getElementById('Light-Selling');
            const discountInput = document.getElementById('Light-Discount');
            if (mrpInput && sellingInput && discountInput) {
                const mrp = parseFloat(mrpInput.value.replace(/[^0-9.]/g, '')) || 0;
                const selling = parseFloat(sellingInput.value.replace(/[^0-9.]/g, '')) || 0;
                if (mrp > 0 && selling > 0) {
                    // Flag invalid when selling > mrp (allow equal)
                    if (selling > mrp) {
                        discountInput.value = '0%';
                        sellingInput.classList.add('is-invalid');
                    } else {
                        sellingInput.classList.remove('is-invalid');
                        const discount = ((mrp - selling) / mrp) * 100;
                        const roundedDiscount = Math.round(discount);
                        discountInput.value = roundedDiscount + '%';
                    }
                } else {
                    discountInput.value = '0%';
                }
            }
        }
        // Initialize discount calculation when page loads
        document.addEventListener('DOMContentLoaded', function () {
            // Calculate initial discount
            calculateDiscount();
            // Load existing images from session if coming back from specifications page
            <?php if (!empty($uploaded_file_names)): ?>
                loadExistingImages();
            <?php endif; ?>
            // Load existing videos from session if returning
            <?php if (!empty($_SESSION['pending_product_videos']) && is_array($_SESSION['pending_product_videos'])): ?>
                loadExistingVideos();
            <?php endif; ?>
            // Add event listeners for MRP and Selling Price to calculate discount
            const mrpInput = document.getElementById('Light-MRP');
            const sellingInput = document.getElementById('Light-Selling');
            if (mrpInput) {
                mrpInput.addEventListener('input', calculateDiscount);
                mrpInput.addEventListener('blur', calculateDiscount);
            }
            if (sellingInput) {
                sellingInput.addEventListener('input', calculateDiscount);
                sellingInput.addEventListener('blur', calculateDiscount);
            }
        });
        // Hook Save & Next with inline validation messages
        document.getElementById('saveNextBtn')?.addEventListener('click', function (e) {
            e.preventDefault();
            function ensureFeedback(el) {
                let fb = el.parentElement?.querySelector('.invalid-feedback');
                if (!fb) {
                    fb = document.createElement('div');
                    fb.className = 'invalid-feedback';
                    if (el.nextSibling) {
                        el.parentElement.insertBefore(fb, el.nextSibling);
                    } else {
                        el.parentElement.appendChild(fb);
                    }
                }
                return fb;
            }
            function setError(id, msg) {
                const el = document.getElementById(id);
                if (!el) return null;
                el.classList.add('is-invalid');
                const fb = ensureFeedback(el);
                fb.textContent = msg;
                return el;
            }
            function clearErrors() {
                document.querySelectorAll('.is-invalid').forEach(x => x.classList.remove('is-invalid'));
                document.querySelectorAll('.invalid-feedback').forEach(x => x.textContent = '');
            }
            clearErrors();
            const requiredFields = [{
                id: 'Light-blue',
                name: 'Product Name'
            },
            {
                id: 'Light-Category',
                name: 'Category'
            },
            {
                id: 'Light-SubCategory',
                name: 'Sub Category'
            },
            {
                id: 'Light-MRP',
                name: 'MRP'
            },
            {
                id: 'Light-Selling',
                name: 'Selling Price'
            },
            {
                id: 'Light-Description',
                name: 'Description'
            },
            {
                id: 'Light-GST',
                name: 'GST'
            },
            {
                id: 'Light-HSN',
                name: 'HSN ID'
            },
            {
                id: 'Light-Manufacturer',
                name: 'Manufacturer Details'
            },
            {
                id: 'Light-Packer',
                name: 'Packer Details'
            },
            {
                id: 'Light-SKU',
                name: 'SKU ID'
            },
            {
                id: 'Light-Inventory',
                name: 'Inventory'
            }
            ];
            let firstError = null;
            requiredFields.forEach(f => {
                const el = document.getElementById(f.id);
                if (!el || !String(el.value || '').trim()) {
                    const ref = setError(f.id, `${f.name} is required.`);
                    if (!firstError && ref) firstError = ref;
                }
            });
            // Price relationship validation: Selling must be less than or equal to MRP
            (function () {
                const mrpEl = document.getElementById('Light-MRP');
                const spEl = document.getElementById('Light-Selling');
                const mrp = parseFloat((mrpEl?.value || '').replace(/[^0-9.]/g, '')) || 0;
                const sp = parseFloat((spEl?.value || '').replace(/[^0-9.]/g, '')) || 0;
                if (mrp > 0 && sp > 0 && sp > mrp) {
                    const ref = setError('Light-Selling', 'Selling Price must be less than MRP.');
                    if (!firstError && ref) firstError = ref;
                }
            })();
            const nameInput = document.getElementById('Light-blue');
            const productName = (nameInput?.value || '').trim();
            if (productName && productName.length > 100) {
                const ref = setError('Light-blue', 'Must be 100 characters or fewer.');
                if (!firstError && ref) firstError = ref;
            }
            const files = document.getElementById('product_images')?.files || [];
            const existingImages = document.querySelectorAll('.variant-previews .add-productbu[data-file-type="existing"]');
            const totalImages = files.length + existingImages.length;
            if (totalImages < 3) {
                const imgInput = document.getElementById('product_images');
                if (imgInput) {
                    imgInput.classList.add('is-invalid');
                    const fb = ensureFeedback(imgInput);
                    fb.textContent = 'Please ensure you have at least 3 images (existing + new).';
                    if (!firstError) firstError = imgInput;
                }
            }
            if (firstError) {
                firstError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                firstError.focus();
                return;
            }
            // Validate mandatory attributes selection (specs)
            try {
                const requiredAttrIds = (window.__MANDATORY_ATTR_IDS__ || []).map(id => parseInt(id, 10)).filter(n => !isNaN(n));
                if (requiredAttrIds.length) {
                    // Read current selection from UI previews (built earlier)
                    // We store attribute_id and selected variant ids into payload below
                }
            } catch (_) { }
            // collect key fields to persist in session for next step
            const payload = {
                product_name: productName,
                category_id: document.getElementById('Light-Category')?.value || '',
                sub_category_id: document.getElementById('Light-SubCategory')?.value || '',
                mrp: document.getElementById('Light-MRP')?.value || '',
                selling_price: document.getElementById('Light-Selling')?.value || '',
                discount: document.getElementById('Light-Discount')?.value || '',
                description: document.getElementById('Light-Description')?.value || '',
                brand: document.getElementById('Light-Brand')?.value || '',
                gst: document.getElementById('Light-GST')?.value || '',
                hsn_id: document.getElementById('Light-HSN')?.value || '',
                manufacture_details: document.getElementById('Light-Manufacturer')?.value || '',
                packaging_details: document.getElementById('Light-Packer')?.value || '',
                sku_id: document.getElementById('Light-SKU')?.value || '',
                group_id: document.getElementById('Light-Group')?.value || '',
                style_id: document.getElementById('Light-Style')?.value || '',
                inventory: document.getElementById('Light-Inventory')?.value || '',
                product_brand: document.getElementById('Light-ProductBrand')?.value || '',
                video: document.getElementById('product_video_display')?.files?.[0]?.name || '',
            };
            // Build lightweight specs to validate mandatory attributes on server
            // Expect a global array window.existingPriceData for price info; variants collected from Choices control if present
            const specsForValidation = [];
            try {
                const specSections = document.querySelectorAll('.spec-section');
                specSections.forEach(section => {
                    const attrSelect = (function () {
                        const lab = Array.from(section.querySelectorAll('label')).find(l => (l.textContent || '').toLowerCase().includes('attribute'));
                        const wrap = lab ? lab.closest('.col-12, .col-xl-6, .col-lg-6, .col-md-6, .col-sm-6, .col-12.mb-1') : null;
                        return wrap ? wrap.querySelector('select') : null;
                    })();
                    const variantsSelect = section.querySelector('select[multiple]');
                    const attributeId = attrSelect ? parseInt(attrSelect.value, 10) : 0;
                    const variants = variantsSelect ? Array.from(variantsSelect.selectedOptions).map(o => ({
                        id: parseInt(o.value, 10)
                    })) : [];
                    if (attributeId && variants.length) {
                        specsForValidation.push({
                            attribute_id: attributeId,
                            variants
                        });
                    }
                });
            } catch (_) { }
            document.getElementById('form_payload').value = JSON.stringify(payload);
            // set hidden product name and submit upload form
            document.getElementById('hiddenProductName').value = productName;
            document.getElementById('imageUploadForm').submit();
        });
        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");
            // Only apply image preview logic to image inputs, not video inputs
            if (inputElement.id === 'product_video_display') {
                // This is the video input - skip image preview logic
                return;
            }
            // Maintain a mutable selection of files before upload
            let selectedFiles = [];
            function syncInputFiles() {
                const dt = new DataTransfer();
                selectedFiles.forEach(f => dt.items.add(f));
                inputElement.files = dt.files;
            }
            function ensurePreviewWrap() {
                let previewWrap = document.querySelector('.variant-previews');
                if (!previewWrap) {
                    previewWrap = document.createElement('div');
                    previewWrap.className = 'variant-previews d-flex align-items-center gap-3 flex-wrap mt-4';
                    dropZoneElement.parentElement.appendChild(previewWrap);
                }
                return previewWrap;
            }
            function renderPreviews() {
                const previewWrap = ensurePreviewWrap();
                previewWrap.innerHTML = '';
                // Filter only image files for preview
                const imageFiles = selectedFiles.filter(file => file.type.startsWith('image/'));
                imageFiles.forEach((file, idx) => {
                    // Find the actual index in the original selectedFiles array
                    const actualIndex = selectedFiles.indexOf(file);
                    const url = URL.createObjectURL(file);
                    const item = document.createElement('div');
                    item.className = 'add-productbu position-relative';
                    item.style.position = 'relative';
                    item.draggable = true;
                    item.dataset.index = String(actualIndex);
                    item.innerHTML = `
                        <button type="button" class="btn-delet-icon" style="position:absolute;top:-10px;right:-10px;background:#fff;border:0;">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/delet-icon.png" alt="delete">
                        </button>
                        <div class="add-product-img"><img src="${url}" style="max-height:80px;object-fit:cover"></div>
                        <div class="small text-muted text-center mt-1" style="width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${file.name}</div>
                    `;
                    item.querySelector('.btn-delet-icon').addEventListener('click', () => {
                        // Remove this file and re-render
                        selectedFiles.splice(actualIndex, 1);
                        syncInputFiles();
                        renderPreviews();
                    });
                    // Drag & drop reorder handlers
                    item.addEventListener('dragstart', (ev) => {
                        item.classList.add('dragging');
                        ev.dataTransfer.effectAllowed = 'move';
                        ev.dataTransfer.setData('text/plain', item.dataset.index);
                    });
                    item.addEventListener('dragend', () => {
                        item.classList.remove('dragging');
                        previewWrap.querySelectorAll('.drag-over').forEach(el => el.classList.remove('drag-over'));
                    });
                    item.addEventListener('dragover', (ev) => {
                        ev.preventDefault();
                        ev.dataTransfer.dropEffect = 'move';
                        item.classList.add('drag-over');
                    });
                    item.addEventListener('dragleave', () => {
                        item.classList.remove('drag-over');
                    });
                    item.addEventListener('drop', (ev) => {
                        ev.preventDefault();
                        item.classList.remove('drag-over');
                        const fromIdx = Number(ev.dataTransfer.getData('text/plain'));
                        const toIdx = Number(item.dataset.index);
                        if (!Number.isNaN(fromIdx) && !Number.isNaN(toIdx) && fromIdx !== toIdx) {
                            const moved = selectedFiles.splice(fromIdx, 1)[0];
                            selectedFiles.splice(toIdx, 0, moved);
                            syncInputFiles();
                            renderPreviews();
                        }
                    });
                    previewWrap.appendChild(item);
                });
            }
            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });
            inputElement.addEventListener("change", (e) => {
                const newFiles = Array.from(inputElement.files || []);
                // Append new files to existing selection (avoid duplicates by name+size)
                newFiles.forEach(f => {
                    if (!selectedFiles.some(sf => sf.name === f.name && sf.size === f.size)) {
                        selectedFiles.push(f);
                    }
                });
                syncInputFiles();
                renderPreviews();
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
                const dropped = Array.from(e.dataTransfer.files || []);
                if (dropped.length) {
                    dropped.forEach(f => {
                        if (!selectedFiles.some(sf => sf.name === f.name && sf.size === f.size)) {
                            selectedFiles.push(f);
                        }
                    });
                    syncInputFiles();
                    renderPreviews();
                }
                dropZoneElement.classList.remove("drop-zone--over");
            });
        });
        // Video drop zone click functionality
        document.getElementById('product_video_display')?.closest('.drop-zone')?.addEventListener('click', function (e) {
            document.getElementById('product_video_display').click();
        });
        // Multiple video preview + sync to hidden multi file input
        document.getElementById('product_video_display')?.addEventListener('change', function (e) {
            const files = Array.from(e.target.files || []);
            const hiddenInput = document.getElementById('product_videos');
            if (hiddenInput) {
                const dt = new DataTransfer();
                files.forEach(f => dt.items.add(f));
                hiddenInput.files = dt.files;
            }
            const videoContainer = e.target.closest('.col-12');
            let preview = document.querySelector('.video-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'video-preview mt-2';
                videoContainer.appendChild(preview);
            }
            if (!files.length) {
                preview.innerHTML = '';
                return;
            }
            let html = '';
            files.forEach((file, idx) => {
                const url = URL.createObjectURL(file);
                html += `
                    <div class="card mb-2">
                        <div class="card-body d-flex align-items-center gap-3">
                            <video controls style="width:180px;height:auto" class="rounded">
                                <source src="${url}" type="${file.type}">
                            </video>
                            <div class="small text-muted">
                                <div><strong>File:</strong> ${file.name}</div>
                                <div><strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                            </div>
                        </div>
                    </div>`;
            });
            preview.innerHTML = html;
        });
    </script>
</body>

</html>
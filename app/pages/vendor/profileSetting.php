<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['vendor_reg_id'])) {
    header('Location: login.php');
}
$vendor_reg_id = isset($_SESSION['vendor_reg_id']) ? $_SESSION['vendor_reg_id'] : '';
$vendor_mobile = isset($_SESSION['vendor_mobile']) ? $_SESSION['vendor_mobile'] : '';
$vendor_business_name = isset($_SESSION['vendor_business_name']) ? $_SESSION['vendor_business_name'] : '';

$vendor_details = mysqli_query($con, "SELECT * FROM vendor_registration WHERE id = $vendor_reg_id");
$vendor_details = mysqli_fetch_assoc($vendor_details);
$vendor_status = $vendor_details['status'];

// Fetch dynamic data for dropdowns
$businessTypes = mysqli_query($con, "SELECT * FROM business_types ORDER BY type_name");
$states = mysqli_query($con, "SELECT * FROM state ORDER BY name");
$categories = mysqli_query($con, "SELECT * FROM category ORDER BY name");

// Check if fields should be readonly
// Allow editing when rejected, but keep readonly for pending status
$is_readonly = ($vendor_status == 'pending' || $vendor_status == 'approved');
$readonly_attr = $is_readonly ? 'readonly' : '';
$disabled_attr = $is_readonly ? 'disabled' : '';

// Handle Send For Review action (allowed even if fields are readonly)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_review') {
    $rs = mysqli_query($con, "SELECT status FROM vendor_registration WHERE id = $vendor_reg_id");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    $current_status = $row ? $row['status'] : $vendor_status;
    if ($current_status === 'pending') {
        $_SESSION['flash_success'] = 'Already submitted for review.';
        header('Location: profileSetting.php');
        exit;
    }
    if ($current_status === 'hold' || $current_status === 'rejected' || $current_status === 'approved') {
        $res = mysqli_query($con, "UPDATE vendor_registration SET status='pending', status_note=NULL, updated_at=NOW() WHERE id=$vendor_reg_id");
        if ($res) {
            $_SESSION['flash_success'] = 'Your profile has been submitted for review.';
        } else {
            $_SESSION['flash_error'] = 'Unable to submit for review. Please try again.';
        }
        header('Location: profileSetting.php');
        exit;
    }
    $_SESSION['flash_error'] = 'Invalid status for resubmission.';
    header('Location: profileSetting.php');
    exit;
}

// Handle form submission for profile updates
// Allow updates when status is rejected or when not readonly
if (($vendor_status == 'rejected' || !$is_readonly) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $section = $_POST['section'] ?? '';

    if ($section == 'business') {
        // Handle business details section
        $business_name = mysqli_real_escape_string($con, $_POST['business_name'] ?? '');
        $business_type = mysqli_real_escape_string($con, $_POST['business_type'] ?? '');
        $business_type_other = mysqli_real_escape_string($con, $_POST['business_type_other'] ?? '');
        $business_address = mysqli_real_escape_string($con, $_POST['business_address'] ?? '');
        $state = mysqli_real_escape_string($con, $_POST['state'] ?? '');
        $city = mysqli_real_escape_string($con, $_POST['city'] ?? '');
        $pincode = mysqli_real_escape_string($con, $_POST['pincode'] ?? '');
        $owner_name = mysqli_real_escape_string($con, $_POST['owner_name'] ?? '');
        $mobile_number = mysqli_real_escape_string($con, $_POST['mobile_number'] ?? '');
        $whatsapp_number = mysqli_real_escape_string($con, $_POST['whatsapp_number'] ?? '');
        $email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
        $website_social = mysqli_real_escape_string($con, $_POST['website_social'] ?? '');
        $product_categories = implode(',', $_POST['product_categories'] ?? []);

        // Handle file uploads for business section
        $profile_image_path = $vendor_details['profile_image'] ?? '';
        $banner_image_path = $vendor_details['banner_image'] ?? '';

        // Create upload directory (filesystem path) and public path
        $safe_vendor_folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $business_name);
        if ($safe_vendor_folder === '' && !empty($vendor_details['business_name'])) {
            $safe_vendor_folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $vendor_details['business_name']);
        }
        if ($safe_vendor_folder === '') {
            $safe_vendor_folder = 'vendor_' . (int)$vendor_reg_id;
        }

        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            $publicRoot = __DIR__ . '/../../../public';
        }
        $uploadDir = rtrim($publicRoot, '/\\') . '/uploads/vendors/' . $safe_vendor_folder;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        // Public URL base for storing references
        $uploadUrlBase =  '/uploads/vendors/' . $safe_vendor_folder;

        // Handle profile image upload
        if (isset($_FILES['profile_image']) && isset($_FILES['profile_image']['tmp_name']) && $_FILES['profile_image']['error'] === 0 && is_uploaded_file($_FILES['profile_image']['tmp_name'])) {
            $profile_image_name = 'profile_' . time() . '_' . basename($_FILES['profile_image']['name']);
            $fs_target = $uploadDir . $profile_image_name;
            if (@move_uploaded_file($_FILES['profile_image']['tmp_name'], $fs_target)) {
                $profile_image_path = $uploadUrlBase . $profile_image_name;
            }
        }

        // Handle banner image upload
        if (isset($_FILES['banner_image']) && isset($_FILES['banner_image']['tmp_name']) && $_FILES['banner_image']['error'] === 0 && is_uploaded_file($_FILES['banner_image']['tmp_name'])) {
            $banner_image_name = 'banner_' . time() . '_' . basename($_FILES['banner_image']['name']);
            $fs_target = $uploadDir . $banner_image_name;
            if (@move_uploaded_file($_FILES['banner_image']['tmp_name'], $fs_target)) {
                $banner_image_path = $uploadUrlBase . $banner_image_name;
            }
        }

        // Update business details
        $update_query = "UPDATE vendor_registration SET 
            business_name = '$business_name',
            business_type = '$business_type',
            business_type_other = '$business_type_other',
            business_address = '$business_address',
            state = '$state',
            city = '$city',
            pincode = '$pincode',
            owner_name = '$owner_name',
            mobile_number = '$mobile_number',
            whatsapp_number = '$whatsapp_number',
            email = '$email',
            website_social = '$website_social',
            product_categories = '$product_categories',
            profile_image = '$profile_image_path',
            banner_image = '$banner_image_path',
            updated_at = NOW()
            WHERE id = $vendor_reg_id";

        if (mysqli_query($con, $update_query)) {
            $_SESSION['flash_success'] = "Business details updated successfully!";
            header('Location: profileSetting.php');
            exit;
        } else {
            $_SESSION['flash_error'] = "Error updating business details: " . mysqli_error($con);
            header('Location: profileSetting.php');
            exit;
        }
    } elseif ($section == 'bank') {
        // Handle bank details section
        $account_name = mysqli_real_escape_string($con, $_POST['account_name'] ?? '');
        $account_type = mysqli_real_escape_string($con, $_POST['account_type'] ?? '');
        $account_number = mysqli_real_escape_string($con, $_POST['account_number'] ?? '');
        $confirm_account_number = mysqli_real_escape_string($con, $_POST['confirm_account_number'] ?? '');
        $bank_name = mysqli_real_escape_string($con, $_POST['bank_name'] ?? '');
        $ifsc_code = mysqli_real_escape_string($con, $_POST['ifsc_code'] ?? '');

        // Handle cancelled cheque upload
        $cancelled_cheque_path = $vendor_details['cancelled_cheque'] ?? '';
        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            $publicRoot = __DIR__ . '/../../../public';
        }
        $uploadDir = rtrim($publicRoot, '/\\') . '/uploads/vendors/' . $safe_vendor_folder;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        // Public URL base for storing references
        $uploadUrlBase =  '/uploads/vendors/' . $safe_vendor_folder;

        if (isset($_FILES['cancelled_cheque']) && isset($_FILES['cancelled_cheque']['tmp_name']) && $_FILES['cancelled_cheque']['error'] === 0 && is_uploaded_file($_FILES['cancelled_cheque']['tmp_name'])) {
            $cheque_name = 'cheque_' . time() . '_' . basename($_FILES['cancelled_cheque']['name']);
            $fs_target = $uploadDir . $cheque_name;
            if (@move_uploaded_file($_FILES['cancelled_cheque']['tmp_name'], $fs_target)) {
                $cancelled_cheque_path = $uploadUrlBase . $cheque_name;
            }
        }

        // Update bank details
        $update_query = "UPDATE vendor_registration SET 
            account_name = '$account_name',
            account_type = '$account_type',
            account_number = '$account_number',
            confirm_account_number = '$confirm_account_number',
            bank_name = '$bank_name',
            ifsc_code = '$ifsc_code',
            cancelled_cheque = '$cancelled_cheque_path',
            updated_at = NOW()
            WHERE id = $vendor_reg_id";

        if (mysqli_query($con, $update_query)) {
            $_SESSION['flash_success'] = "Bank details updated successfully!";
            header('Location: profileSetting.php');
            exit;
        } else {
            $_SESSION['flash_error'] = "Error updating bank details: " . mysqli_error($con);
            header('Location: profileSetting.php');
            exit;
        }
    } elseif ($section == 'docs') {
        // Handle business documents section
        $gst_number = mysqli_real_escape_string($con, $_POST['gst_number'] ?? '');

        // Handle GST certificate upload
        $gst_certificate_path = $vendor_details['gst_certificate'] ?? '';
        $safe_vendor_folder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $vendor_details['business_name'] ?? '');
        if ($safe_vendor_folder === '') {
            $safe_vendor_folder = 'vendor_' . (int)$vendor_reg_id;
        }
        $publicRoot = realpath(__DIR__ . '/../../../public');
        if ($publicRoot === false) {
            $publicRoot = __DIR__ . '/../../../public';
        }
        $uploadDir = rtrim($publicRoot, '/\\') . '/uploads/vendors/' . $safe_vendor_folder;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        // Public URL base for storing references
        $uploadUrlBase =  '/uploads/vendors/' . $safe_vendor_folder;

        if (isset($_FILES['gst_certificate']) && isset($_FILES['gst_certificate']['tmp_name']) && $_FILES['gst_certificate']['error'] === 0 && is_uploaded_file($_FILES['gst_certificate']['tmp_name'])) {
            $gst_cert_name = 'gst_' . time() . '_' . basename($_FILES['gst_certificate']['name']);
            $fs_target = $uploadDir . $gst_cert_name;
            if (@move_uploaded_file($_FILES['gst_certificate']['tmp_name'], $fs_target)) {
                $gst_certificate_path = $uploadUrlBase . $gst_cert_name;
            }
        }

        // Update business documents
        $update_query = "UPDATE vendor_registration SET 
            gst_number = '$gst_number',
            gst_certificate = '$gst_certificate_path',
            updated_at = NOW()
            WHERE id = $vendor_reg_id";

        if (mysqli_query($con, $update_query)) {
            $_SESSION['flash_success'] = "Business documents updated successfully!";
            header('Location: profileSetting.php');
            exit;
        } else {
            $_SESSION['flash_error'] = "Error updating business documents: " . mysqli_error($con);
            header('Location: profileSetting.php');
            exit;
        }
    }
}

// Generate initials from owner name
function getInitials($name)
{
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2); // Max 2 characters
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
    <title>PROFILE-SETTING | HADIDY</title>
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

            <div class="row">
                <?php //print_r($_SESSION); 
                ?>
                <div class="col-1"></div>
                <div class="col-12 col-xl-10 col-lg-10 col-md-12 col-sm-12">
                    <div
                        class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb gap-2 flex-wrap">
                        <h1 class="page-title fw-semibold fs-18 mb-0">Profile Setting</h1>
                        <div class="ms-md-1 ms-0">
                            <nav>
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboards</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Profile Setting</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                    <?php if (isset($_SESSION['flash_success'])): $success_message = $_SESSION['flash_success'];
                        unset($_SESSION['flash_success']); ?>
                        <div class="alert alert-success alert-dismissible flash-alert fade show" role="alert">
                            <strong>Success!</strong> <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['flash_error'])): $error_message = $_SESSION['flash_error'];
                        unset($_SESSION['flash_error']); ?>
                        <div class="alert alert-danger alert-dismissible flash-alert fade show" role="alert">
                            <strong>Error!</strong> <?php echo $error_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($vendor_status === 'pending'): ?>
                        <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center justify-content-between" role="alert">
                            <div>
                                <strong>Account Status: <?php echo ucfirst($vendor_status); ?></strong>
                                <span class="ms-2">Your profile is under review. Please ensure all details are correct.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($vendor_status === 'hold'): ?>
                        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center justify-content-between" role="alert">
                            <div>
                                <strong>Account Status: <?php echo ucfirst($vendor_status); ?></strong>
                                <?php if (!empty($vendor_details['status_note'])): ?>
                                    <span class="ms-2">- <?php echo htmlspecialchars($vendor_details['status_note']); ?></span>
                                <?php else: ?>
                                    <span class="ms-2">Your profile is under review. Please ensure all details are correct.</span>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="ms-3 mb-0">
                                <input type="hidden" name="action" value="send_review">
                                <button type="submit" class="btn btn-sm btn-primary">Send For Review</button>
                            </form>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($vendor_status === 'rejected'): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center justify-content-between" role="alert">
                            <div>
                                <strong>Account Status: <?php echo ucfirst($vendor_status); ?></strong>
                                <?php if (!empty($vendor_details['status_note'])): ?>
                                    <span class="ms-2">- <?php echo htmlspecialchars($vendor_details['status_note']); ?></span>
                                <?php else: ?>
                                    <span class="ms-2">Your application was rejected. Please update your profile and resubmit for review.</span>
                                <?php endif; ?>
                            </div>
                            <form method="post" class="ms-3 mb-0">
                                <input type="hidden" name="action" value="send_review">
                                <button type="submit" class="btn btn-sm btn-primary">Send For Review</button>
                            </form>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <div class="card custom-card">
                        <div class="card-header justify-content-between gap-3 remove-border-profile">
                            <!-- <div class="card-title1">
                                  Order Request
                                </div> -->
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="business-tab" data-bs-toggle="tab"
                                        data-bs-target="#business" type="button" role="tab">Business Details</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank"
                                        type="button" role="tab">Bank Detail</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs"
                                        type="button" role="tab">Business Docs</button>
                                </li>
                            </ul>
                            <div class="gap-10 ">
                                <label for="kyc" class="kyc">KYC Status :</label>
                                <?php if ($vendor_status == 'pending'): ?>
                                    <span class="badge rounded-pill bg-outline-warning">Pending</span>
                                <?php elseif ($vendor_status == 'hold'): ?>
                                    <span class="badge rounded-pill bg-outline-info">Hold</span>
                                <?php elseif ($vendor_status == 'approved'): ?>
                                    <span class="badge rounded-pill bg-outline-success">Approved</span>
                                <?php elseif ($vendor_status == 'rejected'): ?>
                                    <span class="badge rounded-pill bg-outline-danger">Rejected</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tab-content mt-3" id="profileTabsContent">

                            <!-- Business Details -->
                            <div class="tab-pane fade show active border-0" id="business" role="tabpanel">

                                <form method="post" enctype="multipart/form-data" id="businessForm">
                                    <input type="hidden" name="section" value="business">
                                    <div>

                                    </div>
                                    <div class="row g-3 border-table-profile pt-0">
                                        <!-- Business Logo -->
                                        <div class="col-md-6">
                                            <label class="form-label">Business Logo <span
                                                    class="text-danger">*</span></label>
                                            <div class="d-flex  flex-wrap gap-3">
                                                <div class="business-logo-wrapper">
                                                    <?php
                                                    $profile_image = $vendor_details['profile_image'] ?? '';
                                                    $owner_name = $vendor_details['business_name'] ?? '';
                                                    $initials = '';
                                                    if ($owner_name) {
                                                        $name_parts = explode(' ', trim($owner_name));
                                                        $initials = strtoupper(substr($name_parts[0], 0, 1));
                                                        if (count($name_parts) > 1) {
                                                            $initials .= strtoupper(substr($name_parts[1], 0, 1));
                                                        }
                                                    }
                                                    ?>
                                                    <?php
                                                    $profile_image_path = $vendor_details['profile_image'] ?? '';
                                                    $profile_image_exists = false;
                                                    if ($profile_image_path) {
                                                        // Check if it's a relative path or absolute path
                                                        if (strpos($profile_image_path, './') === 0) {
                                                            $profile_image_exists = file_exists($profile_image_path);
                                                        } else {
                                                            $profile_image_exists = file_exists('./' . $profile_image_path);
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($profile_image_exists): ?>
                                                        <img src="<?php echo htmlspecialchars($profile_image_path); ?>" alt="Logo" class="business-logo-img" id="logoPreview" />
                                                    <?php else: ?>
                                                        <div class="business-logo-img initials-display" id="logoPreview" style="background: #3a4a68; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 24px;">
                                                            <?php echo $initials ?: 'LO'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                                        <label for="logoInput" class="business-logo-camera">
                                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/Vector.svg" alt="Camera Icon"
                                                                class="business-logo-camera-icon" />
                                                            <input type="file" id="logoInput" name="profile_image" accept="image/*" style="display:none;" />
                                                        </label>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                                    <div class="btn-group ms-4 d-flex align-items-center" role="group"
                                                        aria-label="Logo actions">
                                                        <button type="button" class="btn btn-primary btn-sm px-4" id="triggerLogoSelect"
                                                            style="border-top-right-radius:0;border-bottom-right-radius:0;background: #3a4a68 !important; height: 40px;">
                                                            Change
                                                        </button>
                                                        <button type="button" class="btn btn-light btn-sm px-4" id="removeLogo"
                                                            style="border-top-left-radius:0;border-bottom-left-radius:0;color:#222;border-left:0; height: 40px;">Remove</button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Banner Upload -->
                                        <div class="file-upload col-md-6 ">
                                            <label class="form-label">Banner :<span class="text-danger"></span></label>
                                            <?php
                                            $banner_image_path = $vendor_details['banner_image'] ?? '';
                                            $banner_image_exists = false;
                                            if ($banner_image_path) {
                                                // Check if it's a relative path or absolute path
                                                if (strpos($banner_image_path, './') === 0) {
                                                    $banner_image_exists = file_exists($banner_image_path);
                                                } else {
                                                    $banner_image_exists = file_exists('./' . $banner_image_path);
                                                }
                                            }
                                            ?>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($banner_image_exists): ?>
                                                    <div class="banner-preview mb-2">
                                                        <img src="<?php echo htmlspecialchars($banner_image_path); ?>" alt="Banner" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="bannerPreview" />
                                                    </div>
                                                <?php endif; ?>
                                                <div class="w-100">

                                                    <label for="bannerInput" class="file-label">
                                                        Drag & Drop your files or <span>Browse</span>
                                                    </label>
                                                    <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                                        <input type="file" id="bannerInput" name="banner_image" accept="image/*" <?php echo $disabled_attr; ?>>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Business Info -->
                                        <div class="col-md-6 ">
                                            <label class="form-label">Business/Store Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="business_name" placeholder="Store Name" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['business_name'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Business Type <span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="business_type" <?php echo $disabled_attr; ?>>
                                                <option value="">Select Business Type</option>
                                                <?php while ($bt = mysqli_fetch_assoc($businessTypes)): ?>
                                                    <option value="<?php echo $bt['id']; ?>" <?php echo ($vendor_details['business_type'] == $bt['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($bt['type_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                        </div>
                                        <div class="col-12 ">
                                            <label class="form-label">Business Address <span
                                                    class="text-danger">*</span></label>
                                            <textarea class="form-control" name="business_address" rows="2"
                                                placeholder="2nd Floor Room no 8, Sai niwas C.H.S Near T.M.C Office, Majiwada, Thane W , 364001" <?php echo $disabled_attr; ?>><?php echo htmlspecialchars($vendor_details['business_address'] ?? ''); ?></textarea>
                                        </div>

                                        <!-- Location -->
                                        <div class="col-md-6 ">
                                            <label class="form-label">State <span class="text-danger">*</span></label>
                                            <select class="form-select" name="state" <?php echo $disabled_attr; ?>>
                                                <?php
                                                $selected_state = $vendor_details['state'];
                                                $states = mysqli_query($con, "SELECT * FROM state WHERE country_id = 99 AND status = 1");
                                                while ($state = mysqli_fetch_assoc($states)) {
                                                    echo '<option value="' . $state['id'] . '" ' . ($selected_state == $state['id'] ? 'selected' : '') . '>' . $state['name'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">City <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="city" placeholder="Bhavnagar" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['city'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Pincode <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="pincode" placeholder="364001" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['pincode'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Owner / Contact Person Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="owner_name" placeholder="" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['owner_name'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>

                                        <!-- Contact -->
                                        <div class="col-md-6">
                                            <label class="form-label">Mobile Number <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="mobile_number" placeholder="+91 99999 99999" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['mobile_number'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">WhatsApp Number <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="whatsapp_number" placeholder="+91 99999 99999" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['whatsapp_number'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Email Address <span
                                                    class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" placeholder="name@domain.com" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['email'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Website Links / Social Media </label>
                                            <input type="text" class="form-control" name="website_social" placeholder="" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['website_social'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>

                                        <!-- Categories -->
                                        <div class="col-12 ">
                                            <label class="form-label">Categories of Products You Plan to Sell<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-control" name="product_categories[]" id="product-s" multiple <?php echo $disabled_attr; ?>>
                                                <?php
                                                $selected_categories = explode(',', $vendor_details['product_categories'] ?? '');
                                                while ($category = mysqli_fetch_assoc($categories)):
                                                ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $selected_categories) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                        </div>

                                        <!-- Save Button -->
                                    </div>
                                </form>
                                <div class="col-12 text-end border-top pt-3">
                                    <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                        <button type="submit" form="businessForm" class="btn btn-primary">Save Business Details</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Bank Detail -->
                            <div class="tab-pane fade border-0" id="bank" role="tabpanel">
                                <form method="post" enctype="multipart/form-data" id="bankForm">
                                    <input type="hidden" name="section" value="bank">
                                    <div class="row g-3 border-table-profile pt-0">
                                        <div class="col-md-6">
                                            <label class="form-label">Account Name <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="account_name" <?php echo $disabled_attr; ?>
                                                placeholder="Enter Account holder name" value="<?php echo htmlspecialchars($vendor_details['account_name'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Account Type <span
                                                    class="text-danger">*</span></label>
                                            <select name="account_type" id="account_type" class="form-select" <?php echo $disabled_attr; ?>>
                                                <option value="Current">Current Account</option>
                                                <option value="Savings">Savings Account</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Account Number<span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="account_number" placeholder="123456789101" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['account_number'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Confirm Account Number<span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="confirm_account_number" placeholder="123456789101" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['confirm_account_number'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">Bank Name<span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="bank_name" placeholder="State Bank Of India" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['bank_name'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="col-md-6 ">
                                            <label class="form-label">IFSC Code<span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="ifsc_code" placeholder="SBIN0001234" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['ifsc_code'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="file-upload ">
                                            <label class="form-label">Upload Cancelled Cheque / Bank Passbook<span
                                                    class="text-danger">*</span></label>
                                            <?php
                                            $cancelled_cheque_path = $vendor_details['cancelled_cheque'] ?? '';
                                            $cancelled_cheque_exists = false;
                                            if ($cancelled_cheque_path) {
                                                if (strpos($cancelled_cheque_path, './') === 0) {
                                                    $cancelled_cheque_exists = file_exists($cancelled_cheque_path);
                                                    $cancelled_cheque_path = PUBLIC_ASSETS . $cancelled_cheque_path;
                                                } else {
                                                    $cancelled_cheque_exists = file_exists('./' . $cancelled_cheque_path);
                                                    $cancelled_cheque_path = PUBLIC_ASSETS . $cancelled_cheque_path;
                                                }
                                            }
                                            ?>
                                            <div class="d-flex  gap-3 flex-column">
                                               
                                                <div class="w-100">
                                                    <label for="chequeInput" class="file-label">
                                                        Drag & Drop your files or <span>Browse</span>
                                                    </label>
                                                    <input type="file" id="chequeInput" name="cancelled_cheque" accept="image/*" <?php echo $disabled_attr; ?>>
                                                </div>
                                                <?php if ($cancelled_cheque_path): ?>
                                                    <div class="document-preview mb-2">
                                                        <img src="<?php echo htmlspecialchars($cancelled_cheque_path); ?>" alt="Cancelled Cheque" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="chequePreview" />
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <div class="col-12 text-end border-top pt-3">
                                    <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                        <button type="submit" form="bankForm" class="btn btn-primary">Save Bank Details</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Business Docs -->
                            <div class="tab-pane fade border-0" id="docs" role="tabpanel">
                                <form method="post" enctype="multipart/form-data" id="docsForm">
                                    <input type="hidden" name="section" value="docs">
                                    <div class="row g-3 border-table-profile pt-0">
                                        <div class="col-md-12 ">
                                            <label class="form-label">GST Number <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="gst_number" placeholder="9899898989" <?php echo $disabled_attr; ?> value="<?php echo htmlspecialchars($vendor_details['gst_number'] ?? ''); ?>" <?php echo $readonly_attr; ?>>
                                        </div>
                                        <div class="file-upload">
                                            <label class="form-label">GST Certificate <span
                                                    class="text-danger">*</span></label>
                                            <?php
                                            $gst_certificate_path = $vendor_details['gst_certificate'] ?? '';
                                           
                                            $gst_certificate_exists = file_exists(PUBLIC_ASSETS . $gst_certificate_path);

                                            

                                            // print_r($gst_certificate_exists);
                                            //print_r($gst_certificate_path);
                                            ?>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if ($gst_certificate_path): ?>
                                                    <div class="document-preview mb-2">
                                                        <img src="<?php echo PUBLIC_ASSETS . $gst_certificate_path; ?>" alt="GST Certificate" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="gstPreview" />
                                                    </div>
                                                <?php endif; ?>
                                                <div class="w-100">
                                                    <label for="gstInput" class="file-label">
                                                        Drag & Drop your files or <span>Browse</span>
                                                    </label>
                                                    <input type="file" id="gstInput" name="gst_certificate" accept="image/*" <?php echo $disabled_attr; ?>>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                                <div class="col-12 text-end border-top pt-3">
                                    <?php if (!$is_readonly || $vendor_status == 'rejected'): ?>
                                        <button type="submit" form="docsForm" class="btn btn-primary">Save Business Documents</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="col-1"></div>
            </div>


        </div>
    </div>
    <!-- End::app-content -->

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
    <script src="./assets/js/simplebar.js"></script>

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
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss flash alerts after 5 seconds
            setTimeout(function() {
                try {
                    var alertEls = document.querySelectorAll('.flash-alert');
                    alertEls.forEach(function(el) {
                        if (window.bootstrap && bootstrap.Alert) {
                            var instance = bootstrap.Alert.getOrCreateInstance(el);
                            instance.close();
                        } else {
                            el.style.display = 'none';
                        }
                    });
                } catch (e) {}
            }, 5000);

            // Clean URL: remove query parameters if any
            try {
                if (window.location.search && window.history && window.history.replaceState) {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            } catch (e) {}
            new Choices('#product-s', {
                removeItemButton: true,
                placeholder: true,
                placeholderValue: '',
                searchEnabled: true,
                allowHTML: true
            });

            // Logo image preview
            const logoInput = document.getElementById('logoInput');
            const logoPreview = document.getElementById('logoPreview');
            const removeLogoBtn = document.getElementById('removeLogo');

            if (logoInput) {
                logoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            logoPreview.innerHTML = `<img src="${e.target.result}" alt="Logo" class="business-logo-img" />`;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            const triggerLogoSelect = document.getElementById('triggerLogoSelect');
            if (triggerLogoSelect && logoInput) {
                triggerLogoSelect.addEventListener('click', function() {
                    logoInput.click();
                });
            }

            if (removeLogoBtn) {
                removeLogoBtn.addEventListener('click', function() {
                    const modalEl = document.getElementById('removeLogoModal');
                    if (!modalEl) return;
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                    const confirmBtn = modalEl.querySelector('#confirmRemoveLogoBtn');
                    const cancelBtn = modalEl.querySelector('[data-bs-dismiss="modal"]');
                    const onConfirm = function() {
                        logoPreview.innerHTML = '<div class="business-logo-img initials-display" style="background: #3a4a68; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 24px;"><?php echo $initials ?: "LO"; ?></div>';
                        if (logoInput) {
                            logoInput.value = '';
                        }
                        modal.hide();
                        confirmBtn.removeEventListener('click', onConfirm);
                    };
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', onConfirm);
                    }
                    // Clean up listener if modal closed without confirming
                    modalEl.addEventListener('hidden.bs.modal', function cleanup() {
                        if (confirmBtn) confirmBtn.removeEventListener('click', onConfirm);
                        modalEl.removeEventListener('hidden.bs.modal', cleanup);
                    });
                });
            }

            // Banner image preview
            const bannerInput = document.getElementById('bannerInput');
            const bannerPreview = document.getElementById('bannerPreview');

            if (bannerInput) {
                bannerInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (bannerPreview) {
                                bannerPreview.src = e.target.result;
                            } else {
                                const previewDiv = document.createElement('div');
                                previewDiv.className = 'banner-preview mb-2';
                                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Banner" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="bannerPreview" />`;
                                bannerInput.parentNode.insertBefore(previewDiv, bannerInput);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // Cancelled cheque image preview
            const chequeInput = document.getElementById('chequeInput');
            const chequePreview = document.getElementById('chequePreview');

            if (chequeInput) {
                chequeInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (chequePreview) {
                                chequePreview.src = e.target.result;
                            } else {
                                const previewDiv = document.createElement('div');
                                previewDiv.className = 'document-preview mb-2';
                                previewDiv.innerHTML = `<img src="${e.target.result}" alt="Cancelled Cheque" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="chequePreview" />`;
                                chequeInput.parentNode.insertBefore(previewDiv, chequeInput);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            // GST certificate image preview
            const gstInput = document.getElementById('gstInput');
            const gstPreview = document.getElementById('gstPreview');

            if (gstInput) {
                gstInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (gstPreview) {
                                gstPreview.src = e.target.result;
                            } else {
                                const previewDiv = document.createElement('div');
                                previewDiv.className = 'document-preview mb-2';
                                previewDiv.innerHTML = `<img src="${e.target.result}" alt="GST Certificate" class="img-fluid" style="max-height: 150px; border-radius: 8px;" id="gstPreview" />`;
                                gstInput.parentNode.insertBefore(previewDiv, gstInput);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        });
    </script>

    <!-- Remove Logo Confirmation Modal -->
    <div class="modal fade" id="removeLogoModal" tabindex="-1" aria-labelledby="removeLogoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-black fw-bold" id="removeLogoModalLabel">Remove Logo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-column align-items-center justify-content-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="ti ti-alert-triangle text-warning me-2 fs-1"></i>
                        </div>
                        <div class="d-flex align-items-center justify-content-center">
                            <p class="text-black fs-6 ">Are you sure you want to remove the logo?</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmRemoveLogoBtn"><i class="ti ti-trash me-1"></i>Remove</button>
                </div>
            </div>
        </div>
    </div>

</body>

</html>
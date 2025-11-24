<?php
session_start();

// Prevent access if registration is already completed
if (isset($_SESSION['registration_completed']) && $_SESSION['registration_completed'] === true) {
    header("Location: ./index.php");
    exit;
}

require_once '../includes/init.php';

// Fetch dynamic dropdown data
$businessTypes = [];
$states = [];
$categories = [];

// business_types
$btQuery = "SELECT id, type_name FROM business_types WHERE is_active = 1 ORDER BY type_name";
if ($btResult = mysqli_query($con, $btQuery)) {
    while ($row = mysqli_fetch_assoc($btResult)) {
        $businessTypes[] = $row;
    }
}

// state
$stateQuery = "SELECT id, name FROM state WHERE status = 1 AND country_id = 99 ORDER BY name";
if ($stateResult = mysqli_query($con, $stateQuery)) {
    while ($row = mysqli_fetch_assoc($stateResult)) {
        $states[] = $row;
    }
}

// categories
$catQuery = "SELECT id, name FROM category ORDER BY name";
if ($catResult = mysqli_query($con, $catQuery)) {
    while ($row = mysqli_fetch_assoc($catResult)) {
        $categories[] = $row;
    }
}

$avg_products = [];
$avg_productsQuery = "SELECT id, number FROM productno_list ORDER BY id ASC";
if ($avg_productsResult = mysqli_query($con, $avg_productsQuery)) {
    while ($row = mysqli_fetch_assoc($avg_productsResult)) {
        $avg_products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light"
    data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title> REGISTRATION | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="hagidy website">

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
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>icon-fonts/RemixIcons/fonts/remixicon.css">
    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">

    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/quill/quill.snow.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/quill/quill.bubble.css">

    <!-- Filepond CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/filepond/filepond.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-preview/filepond-plugin-image-preview.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-edit/filepond-plugin-image-edit.min.css">

    <!-- Date & Time Picker CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    
    <!-- Custom CSS for validation -->
    <style>
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: block;
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
        
            <div class="col-xxl-10 col-xl-10 col-lg-10 col-md-10 col-sm-12 col-12">
                <div class="mb-2 mt-5 d-flex justify-content-center">
                    <a href="index.php">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/login-logo.png" alt="logo" class="desktop-logo">
                    </a>
                </div>
                <div>
                    <p class="h5 fw-semibold mb-2 text-center">Seller Registration Form</p>
                    <p class="mb-4 text-muted op-7 fw-normal text-center">Welcome to Hagidy Marketplace! Please fill out
                        the details below to register as a seller. We’ll review and get back to you shortly.</p>
                </div>
                <div class="card custom-card">
                    <div class="card-body p-4">
                        <?php if (isset($_SESSION['registration_error']) && $_SESSION['registration_error']): ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px;">
                            <?php echo htmlspecialchars($_SESSION['registration_error']); unset($_SESSION['registration_error']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?php echo USER_BASEURL; ?>app/handlers/registration_handler.php" enctype="multipart/form-data" id="registration-form" novalidate>
                        <div class="row gy-3">
                            <div class=" col-12">
                                <label for="signin-Business" class="form-label text-default">Business/Store Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Business" name="business_name"
                                    placeholder="Enter your business or store name" maxlength="100" required>
                                <span class="text-danger" id="business_name_error"></span>
                            </div>
                            <div class=" col-12">
                                <label for="signin-Address" class="form-label text-default">Business Address <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Address" name="business_address"
                                    placeholder="Enter your business address" maxlength="200" required>
                                <span class="text-danger" id="business_address_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <select class="form-control" name="business_type_id" id="product-color-add" required>
<?php foreach ($businessTypes as $bt): ?>
                                    <option value="<?php echo htmlspecialchars($bt['id']); ?>"><?php echo htmlspecialchars($bt['type_name']); ?></option>
<?php endforeach; ?>
                                </select>
                                <span class="text-danger" id="business_type_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12 ">
                                <input type="text" class="form-control form-control-lg" id="signin-type" name="business_type_text"
                                    placeholder="Enter business type (e.g. Retail, Wholesale)" maxlength="50" disabled>
                                <span class="text-danger" id="business_type_text_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="product-Gujrat" class="form-label">State <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="state_id" id="product-color-add1" required>
<?php foreach ($states as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st['id']); ?>"><?php echo htmlspecialchars($st['name']); ?></option>
<?php endforeach; ?>
                                </select>
                                <span class="text-danger" id="state_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-City" class="form-label text-default">City<span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-City" name="city"
                                    placeholder="Enter city name" maxlength="50" required>
                                <span class="text-danger" id="city_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Pincode" class="form-label text-default">Pincode<span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Pincode" name="pincode"
                                    placeholder="Enter pincode (e.g. 400001)" maxlength="6" pattern="[1-9][0-9]{5}" required>
                                <span class="text-danger" id="pincode_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Owner" class="form-label text-default">Owner / Contact Person
                                    Name<span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Owner" name="owner_name"
                                    placeholder="Enter owner or contact person name" maxlength="100" required>
                                <span class="text-danger" id="owner_name_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Mobile" class="form-label text-default">Mobile Number <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control form-control-lg" id="signin-Mobile" name="mobile"
                                    placeholder="Enter mobile number (e.g. +91 1234567890)" maxlength="15" pattern="[0-9+\-\s()]{10,15}" required>
                                <span class="text-danger" id="mobile_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-WhatsApp" class="form-label text-default">WhatsApp Number <span
                                        class="text-danger">*</span></label>
                                <input type="tel" class="form-control form-control-lg" id="signin-WhatsApp" name="whatsapp"
                                    placeholder="Enter WhatsApp number (e.g. +91 1234567890)" maxlength="15" pattern="[0-9+\-\s()]{10,15}" required>
                                <span class="text-danger" id="whatsapp_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Email" class="form-label text-default">Email Address <span
                                        class="text-danger">*</span></label>
                                <input type="email" class="form-control form-control-lg" id="signin-Email" name="email"
                                    placeholder="Enter email address (e.g. example@domain.com)" maxlength="100" required>
                                <span class="text-danger" id="email_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Social" class="form-label text-default">Website Links / Social Media
                                    </label>
                                <input type="url" class="form-control form-control-lg" id="signin-Social" name="website"
                                    placeholder="Enter website or social media URL" maxlength="200">
                                <span class="text-danger" id="website_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-GST" class="form-label text-default">GST Number <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-GST" name="gst_number"
                                    placeholder="Enter GST number (e.g. 22AAAAA0000A1Z5, 07AABCU9603R1ZX, etc.)" maxlength="15" required>
                                <span class="text-danger" id="gst_number_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12 ">
                                <label for="signin-GST" class="form-label text-default">GST Certificate <span
                                        class="text-danger">*</span></label>
                                <div class="drop-zone">
                                    <span class="drop-zone__prompt">Drag & Drop your files or Browse</span>
                                    <input type="file" name="gst_certificate" class="drop-zone__input" accept="image/*,.pdf" required>
                                </div>
                                <small class="text-muted" id="gst_status">No file selected</small>
                                <span class="text-danger" id="gst_certificate_error"></span>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center mt-2 gap-2">
                                    <div class="border-registration"></div>
                                    <h2 class="h5 fw-semibold bank-details">Bank Details</h2>
                                    <div class="border-registration"></div>
                                </div>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Account" class="form-label text-default">Account Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Account" name="account_name"
                                    placeholder="Enter account holder name" maxlength="100" required>
                                <span class="text-danger" id="account_name_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Account-type" class="form-label text-default">Account Type <span
                                        class="text-danger">*</span></label>
                                <select class="form-control" name="account_type" id="product-Account-type" required>
                                    <option value="Current">Current Account</option>
                                    <option value="Savings">Savings Account</option>
                                </select>
                                <span class="text-danger" id="account_type_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Account-number" class="form-label text-default">Account Number <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Account-number" name="account_number"
                                    placeholder="Enter bank account number" maxlength="18" pattern="[0-9]{9,18}" required>
                                <span class="text-danger" id="account_number_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Account-Confirm" class="form-label text-default">Confirm Account
                                    Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Account-Confirm" name="account_number_confirm"
                                    placeholder="Re-enter bank account number" maxlength="18" pattern="[0-9]{9,18}" required>
                                <span class="text-danger" id="account_number_confirm_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Bank" class="form-label text-default">Bank Name <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Bank" name="bank_name"
                                    placeholder="Enter bank name (e.g. HDFC Bank)" maxlength="100" required>
                                <span class="text-danger" id="bank_name_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-IFSC" class="form-label text-default">IFSC Code <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-IFSC" name="ifsc"
                                    placeholder="Enter IFSC code (e.g. HDFC0001234)" maxlength="11" pattern="[A-Z]{4}0[A-Z0-9]{6}" required>
                                <span class="text-danger" id="ifsc_error"></span>
                            </div>
                            <div class="col-12">
                                <label for="signin-Cheque" class="form-label text-default">Upload Cancelled Cheque /
                                    Bank Passbook <span class="text-danger">*</span></label>
                                <div class="drop-zone drop-zone1">
                                    <span class="drop-zone__prompt">Drag & Drop your files or Browse</span>
                                    <input type="file" name="bank_proof" class="drop-zone__input" accept="image/*,.pdf" required>
                                </div>
                                <small class="text-muted" id="bank_status">No file selected</small>
                                <span class="text-danger" id="bank_proof_error"></span>
                            </div>
                             <div class="col-12">
                                <div class="d-flex align-items-center mt-2 gap-2">
                                    <div class="border-registration"></div>
                                    <h2 class="h5 fw-semibold bank-details">Other Information</h2>
                                    <div class="border-registration"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="signin-Products" class="form-label text-default">Categories of Products You
                                    Plan to Sell<span class="text-danger">*</span></label>
                                <select class="form-control" name="category_ids[]" id="product-Plain" multiple required>
<?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
<?php endforeach; ?>
                                </select>
                                <span class="text-danger" id="category_ids_error"></span>
                                <!-- <select class="form-control" name="signin-Products" id="product-Plain1" multiple>
                                    <option value="Plain">Plain</option>
                                    <option value="Relaxed">Relaxed</option>
                                </select> -->
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Intend" class="form-label text-default">Average Number of Products
                                    You Intend to List <span class="text-danger">*</span></label>
                                <select class="form-control" name="avg_products" id="signin-Intend" required>
<?php foreach ($avg_products as $avg): ?>
                                    <option value="<?php echo htmlspecialchars($avg['id']); ?>"><?php echo htmlspecialchars($avg['number']); ?></option>
<?php endforeach; ?>
                                </select>
                                <span class="text-danger" id="avg_products_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-manufacture" class="form-label text-default">Do you manufacture your
                                    products? * <span class="text-danger">*</span></label>
                                <select class="form-control" name="is_manufacturer" id="signin-manufacture" required>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                                <span class="text-danger" id="is_manufacturer_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Signature" class="form-label text-default">Name (as Signature)<span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Signature" name="signature_name"
                                    placeholder="Enter your name for signature" maxlength="100" required>
                                <span class="text-danger" id="signature_name_error"></span>
                            </div>
                            <div class="col-xxl-6 col-xl-6 col-lg-6 col-md-6 col-sm-12 col-12">
                                <label for="signin-Date" class="form-label text-default">Date <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="signin-Date" name="signature_date"
                                    placeholder="Auto-filled date (e.g. 09/05/2025)" disabled>
                            </div>
                            <div class="col-12">
                                <div class="form-check mt-3 mb-4">
                                    <input class="form-check-input" type="checkbox" value="1" id="defaultCheck1" name="agree_terms" style="border : 1px solid black" required>
                                    <label class="form-check-label text-muted fw-normal" for="defaultCheck1">
                                        I confirm that the information provided is accurate and agree to comply with
                                        Hagidy's seller <a href="../terms_conditions.php" target="_blank" class="text-sky-blue"><u>Terms &
                                                Conditions</u></a>
                                    </label>
                                </div>
                                <span class="text-danger" id="agree_terms_error"></span>
                            </div>
                            <div class="col-12 d-grid mt-3 d-flex justify-content-end g-3 gap-2">
                                <button type="reset" class="btn btn-danger claear-btn-re ">Clear</button>
                                <button type="submit" class="btn btn-primary claear-btn-re">Submit</button>
                            </div>
                        </div>
                        </form>
                    </div>
                </div>
                <div class="text-center">
                    <p class="fs-12 text-muted mt-3 mb-5">Already have an account? <a href="./login.php"
                            class="text-primary">Sign In</a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scroll To Top -->
    <div class="scrollToTop">
        <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
    </div>
    <div id="responsive-overlay"></div>
    
    <!-- Message Modal removed as per requirement (errors shown inline under fields) -->
    
    <!-- Scroll To Top -->
    <!-- Removed unused fileupload/Dropzone to prevent console errors on this page -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/fileupload.js"></script>
    <!-- Popper JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Defaultmenu JS (disabled on this page to avoid null element errors) -->
    <!-- <script src="./assets/js/defaultmenu.min.js"></script> -->

    <!-- Node Waves JS-->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS (disabled here) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/sticky.js"></script>

    <!-- Simplebar JS (disabled here) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/simplebar.js"></script>

    <!-- Color Picker JS (not used on this page) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>

    <!-- Custom-Switcher JS (disabled) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom-switcher.min.js"></script>

    <!-- Date & Time Picker JS (not used on this page) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>

    <!-- Quill Editor JS (not used on this page) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/quill/quill.min.js"></script>

    <!-- Filepond & Dropzone (not used on this page) -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond/filepond.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-preview/filepond-plugin-image-preview.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-exif-orientation/filepond-plugin-image-exif-orientation.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-file-validate-size/filepond-plugin-file-validate-size.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-file-encode/filepond-plugin-file-encode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-edit/filepond-plugin-image-edit.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-file-validate-type/filepond-plugin-file-validate-type.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-crop/filepond-plugin-image-crop.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-resize/filepond-plugin-image-resize.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/filepond-plugin-image-transform/filepond-plugin-image-transform.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/dropzone/dropzone-min.js"></script>

    <!-- Internal Add Products JS (not needed here; causes Choices warnings) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/add-products.js"></script>

    <!-- Custom JS (disabled here due to DOM assumptions) -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/custom.js"></script>
    <script>
        // Auto-fill today's date in dd/mm/yyyy format
        (function(){
            var dateInput = document.getElementById('signin-Date');
            if (dateInput) {
                var d = new Date();
                var dd = String(d.getDate()).padStart(2, '0');
                var mm = String(d.getMonth() + 1).padStart(2, '0');
                var yyyy = d.getFullYear();
                dateInput.value = dd + '/' + mm + '/' + yyyy;
            }
        })();

        function setFileStatus(inputElement, file){
            if (!inputElement) return;
            var name = (file && file.name) ? file.name : '';
            // Also update prompt text inside the same drop zone for clear visibility
            try {
                var dz = inputElement.closest('.drop-zone');
                var prompt = dz ? dz.querySelector('.drop-zone__prompt') : null;
                if (prompt) {
                    prompt.textContent = name ? name : 'Drag & Drop your files or Browse';
                }
            } catch(e) {}
            if (inputElement.name === 'gst_certificate') {
                var gst = document.getElementById('gst_status');
                if (gst) { gst.textContent = name ? ('Selected: ' + name) : 'No file selected'; }
            }
            if (inputElement.name === 'bank_proof') {
                var bank = document.getElementById('bank_status');
                if (bank) { bank.textContent = name ? ('Selected: ' + name) : 'No file selected'; }
            }
        }

        document.querySelectorAll(".drop-zone__input").forEach((inputElement) => {
            const dropZoneElement = inputElement.closest(".drop-zone");

            dropZoneElement.addEventListener("click", (e) => {
                inputElement.click();
            });

            inputElement.addEventListener("change", (e) => {
                if (inputElement.files.length) {
                    if (typeof updateThumbnail === 'function') {
                        updateThumbnail(dropZoneElement, inputElement.files[0]);
                    }
                    setFileStatus(inputElement, inputElement.files[0]);
                } else {
                    setFileStatus(inputElement, null);
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
                    if (typeof updateThumbnail === 'function') {
                        updateThumbnail(dropZoneElement, e.dataTransfer.files[0]);
                    }
                    setFileStatus(inputElement, e.dataTransfer.files[0]);
                }

                dropZoneElement.classList.remove("drop-zone--over");
            });
        });

        // This handler is removed to prevent conflicts with the main validation handler
    </script>
    
    <!-- Registration Validation and Modal Script -->
    <script>
        window.__registrationScriptParsed = true;
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            try { window.__registrationInitStarted = true; } catch(e) {}
            
            // Modal disabled on this page per requirement; skip server messages
            // (We no longer inject PHP into JS here to avoid syntax issues)
            
            // Get form and inputs
            const form = document.getElementById('registration-form');
            if (!form) { return; }
            
            // Business type change handler
            const businessTypeSelect = document.getElementById('product-color-add');
            const businessTypeText = document.getElementById('signin-type');
            
            if (businessTypeSelect && businessTypeText) {
                businessTypeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const optionText = selectedOption.textContent.toLowerCase();
                    
                    // Enable text input only if "Other" is selected
                    if (optionText.includes('other') || optionText.includes('अन्य')) {
                        businessTypeText.disabled = false;
                        businessTypeText.required = true;
                        businessTypeText.placeholder = 'Enter your business type';
                    } else {
                        businessTypeText.disabled = true;
                        businessTypeText.required = false;
                        businessTypeText.value = '';
                        businessTypeText.placeholder = 'Enter business type (e.g. Retail, Wholesale)';
                        // Clear any error when switching away from "Other"
                        clearError('business_type_text');
                        businessTypeText.classList.remove('is-invalid');
                    }
                });
                
                // Clear error when user starts typing
                businessTypeText.addEventListener('input', function() {
                    if (this.required && this.value.trim()) {
                        clearError('business_type_text');
                        this.classList.remove('is-invalid');
                    }
                });
            }
            
            // Form submission handler
            form.addEventListener('submit', function(e) {
                // Block any other submit listeners from interfering
                e.preventDefault();
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
                
                // Clear previous errors
                clearAllErrors();
                
                // Validate form
                const isValid = validateForm();
                
                if (isValid) {
                    // Keep novalidate to avoid native blocking on hidden/third-party widgets
                    form.noValidate = true;
                    // Remove native required on hidden/complex controls to avoid browser focus errors
                    try {
                        var f1 = form.querySelector('input[name="gst_certificate"]');
                        var f2 = form.querySelector('input[name="bank_proof"]');
                        var s1 = form.querySelector('select[name="category_ids[]"]');
                        if (f1) f1.removeAttribute('required');
                        if (f2) f2.removeAttribute('required');
                        if (s1) s1.removeAttribute('required');
                    } catch(_) {}
                    // Bypass onsubmit handlers by calling the native submit
                    try { HTMLFormElement.prototype.submit.call(form); } catch(_) { form.submit(); }
                } else {
                    showModal('❌', 'Please fix the validation errors before submitting.', 'text-danger');
                    
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                }
            });
            
            // Input restrictions
            setupInputRestrictions();
            
            // Phone number duplicate check
            setupPhoneValidation();
            
            // Validation function
            function validateForm() {
                let isValid = true;
                
                // Required text inputs
                const requiredInputs = [
                    { id: 'signin-Business', name: 'business_name', label: 'Business Name' },
                    { id: 'signin-Address', name: 'business_address', label: 'Business Address' },
                    { id: 'signin-City', name: 'city', label: 'City' },
                    { id: 'signin-Pincode', name: 'pincode', label: 'Pincode' },
                    { id: 'signin-Owner', name: 'owner_name', label: 'Owner Name' },
                    { id: 'signin-Mobile', name: 'mobile', label: 'Mobile Number' },
                    { id: 'signin-WhatsApp', name: 'whatsapp', label: 'WhatsApp Number' },
                    { id: 'signin-Email', name: 'email', label: 'Email Address' },
                    { id: 'signin-GST', name: 'gst_number', label: 'GST Number' },
                    { id: 'signin-Account', name: 'account_name', label: 'Account Name' },
                    { id: 'signin-Account-number', name: 'account_number', label: 'Account Number' },
                    { id: 'signin-Account-Confirm', name: 'account_number_confirm', label: 'Confirm Account Number' },
                    { id: 'signin-Bank', name: 'bank_name', label: 'Bank Name' },
                    { id: 'signin-IFSC', name: 'ifsc', label: 'IFSC Code' },
                    { id: 'signin-Cheque', name: 'bank_proof', label: 'Bank Proof' },
                    { id: 'signin-Signature', name: 'signature_name', label: 'Signature Name' }
                ];
                
                // Validate required inputs
                requiredInputs.forEach(input => {
                    const element = document.getElementById(input.id);
                    if (element) {
                        const value = element.value.trim();
                        if (!value) {
                            showError(input.name, input.label + ' is required');
                            element.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            clearError(input.name);
                            element.classList.remove('is-invalid');
                        }
                    }
                });
                
                // Validate selects
                const requiredSelects = [
                    { id: 'product-color-add', name: 'business_type_id', label: 'Business Type' },
                    { id: 'product-color-add1', name: 'state_id', label: 'State' },
                    { id: 'product-Account-type', name: 'account_type', label: 'Account Type' },
                    { id: 'signin-Intend', name: 'avg_products', label: 'Average Products' },
                    { id: 'signin-manufacture', name: 'is_manufacturer', label: 'Manufacturer' },
                    { id: 'signin-Cheque', name: 'bank_proof', label: 'Bank Proof' }
                ];
                
                requiredSelects.forEach(select => {
                    const element = document.getElementById(select.id);
                    if (element) {
                        const value = element.value;
                        if (!value || value === '') {
                            showError(select.name, select.label + ' is required');
                            element.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            clearError(select.name);
                            element.classList.remove('is-invalid');
                        }
                    }
                });
                
                // Validate product categories
                const categorySelect = document.getElementById('product-Plain');
                if (categorySelect) {
                    const selectedOptions = categorySelect.selectedOptions;
                    if (!selectedOptions || selectedOptions.length === 0) {
                        showError('category_ids', 'Please select at least one product category');
                        categorySelect.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        clearError('category_ids');
                        categorySelect.classList.remove('is-invalid');
                    }
                }
                
                // Validate files
                const gstFile = document.querySelector('input[name="gst_certificate"]');
                if (gstFile && (!gstFile.files || gstFile.files.length === 0)) {
                    showError('gst_certificate', 'GST Certificate is required');
                    isValid = false;
                } else {
                    clearError('gst_certificate');
                }
                
                const bankFile = document.querySelector('input[name="bank_proof"]');
                if (bankFile && (!bankFile.files || bankFile.files.length === 0)) {
                    showError('bank_proof', 'Cancelled Cheque / Bank Passbook is required');
                    isValid = false;
                } else {
                    clearError('bank_proof');
                }
                
                // Validate terms
                const terms = document.getElementById('defaultCheck1');
                if (terms && !terms.checked) {
                    showError('agree_terms', 'You must agree to the Terms and Conditions');
                    isValid = false;
                } else {
                    clearError('agree_terms');
                }
                
                const businessTypeSelect = document.getElementById('product-color-add');
                const businessTypeText = document.getElementById('signin-type');
                
                if (businessTypeSelect && businessTypeText) {
                    const selectedOption = businessTypeSelect.options[businessTypeSelect.selectedIndex];
                    const optionText = selectedOption.textContent.toLowerCase();
                    
                    if ((optionText.includes('other') || optionText.includes('अन्य')) && businessTypeText.required) {
                        if (!businessTypeText.value.trim()) {
                            showError('business_type_text', 'Please enter your business type');
                            businessTypeText.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            clearError('business_type_text');
                            businessTypeText.classList.remove('is-invalid');
                        }
                    }
                }
                
                
                // Validate specific formats
                const formatValid = validateFormats();
                if (!formatValid) {
                    isValid = false;
                }
                
                // Always validate account number matching regardless of other errors
                const accountMatchValid = validateAccountNumberMatch();
                if (!accountMatchValid) {
                    isValid = false;
                }
                
                return isValid;
            }
            
            // Flexible GST validation function
            function isValidGST(gstNumber) {
                // Remove any spaces or special characters
                const cleanGST = gstNumber.replace(/[^0-9A-Z]/g, '');
                
                // Check if it's exactly 15 characters
                if (cleanGST.length !== 15) {
                    return false;
                }
                
                // GST format: 2 digits + 5 letters + 4 digits + 1 letter + 1 letter/digit + Z + 1 letter/digit
                // More flexible patterns to accept various valid GST formats
                const patterns = [
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9A-Z]{1}Z[0-9A-Z]{1}$/, // Standard format
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/, // Original strict format
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[0-9]{1}Z[0-9A-Z]{1}$/,   // With digit in 5th position
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z]{1}Z[0-9A-Z]{1}$/    // With letter in 5th position
                ];
                
                // Check against all valid patterns
                return patterns.some(pattern => pattern.test(cleanGST));
            }
            
            function validateFormats() {
                let isValid = true;
                
                // Phone validation
                const mobile = document.getElementById('signin-Mobile');
                if (mobile && mobile.value) {
                    const cleanPhone = mobile.value.replace(/[^0-9]/g, '');
                    if (!/^[0-9]{10,15}$/.test(cleanPhone)) {
                        showError('mobile', 'Please enter a valid mobile number (10-15 digits)');
                        mobile.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        // Check for duplicate phone number
                        if (!checkPhoneDuplicateSync(cleanPhone)) {
                            isValid = false;
                        }
                    }
                }
                
                const whatsapp = document.getElementById('signin-WhatsApp');
                if (whatsapp && whatsapp.value) {
                    const cleanPhone = whatsapp.value.replace(/[^0-9]/g, '');
                    if (!/^[0-9]{10,15}$/.test(cleanPhone)) {
                        showError('whatsapp', 'Please enter a valid WhatsApp number (10-15 digits)');
                        whatsapp.classList.add('is-invalid');
                    }
                }
                
                // Email validation
                const email = document.getElementById('signin-Email');
                if (email && email.value) {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                        showError('email', 'Please enter a valid email address');
                        email.classList.add('is-invalid');
                    }
                }
                
                // GST validation - flexible format
                const gst = document.getElementById('signin-GST');
                if (gst) {
                    const gstValue = gst.value.trim().toUpperCase();
                    if (!gstValue) {
                        showError('gst_number', 'GST Number is required');
                        gst.classList.add('is-invalid');
                    } else if (!isValidGST(gstValue)) {
                        showError('gst_number', 'Please enter a valid GST number (15 characters: 2 digits + 5 letters + 4 digits + 1 letter + 1 letter/digit + Z + 1 letter/digit)');
                        gst.classList.add('is-invalid');
                    } else {
                        clearError('gst_number');
                        gst.classList.remove('is-invalid');
                    }
                }
                
                // IFSC validation
                const ifsc = document.querySelector('input[name="ifsc"]');
                if (ifsc) {
                    if (!ifsc.value || !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.value)) {
                        showError('ifsc', 'Please enter a valid IFSC code (format: ABCD0123456)');
                        ifsc.classList.add('is-invalid');
                    } else {
                        clearError('ifsc');
                        ifsc.classList.remove('is-invalid');
                    }
                }
                
                // Pincode validation
                const pincode = document.getElementById('signin-Pincode');
                if (pincode) {
                    if (!pincode.value || !/^[1-9][0-9]{5}$/.test(pincode.value)) {
                        showError('pincode', 'Please enter a valid 6-digit pincode');
                        pincode.classList.add('is-invalid');
                    } else {
                        clearError('pincode');
                        pincode.classList.remove('is-invalid');
                    }
                }
                
                // Account number validation
                const accountNumber = document.getElementById('signin-Account-number');
                if (accountNumber) {
                    if (!accountNumber.value || !/^[0-9]{9,18}$/.test(accountNumber.value)) {
                        showError('account_number', 'Please enter a valid account number (9-18 digits)');
                        accountNumber.classList.add('is-invalid');
                    } else {
                        clearError('account_number');
                        accountNumber.classList.remove('is-invalid');
                    }
                }
                
                return isValid;
            }
            
            function validateAccountNumberMatch() {
                const accountNumber = document.getElementById('signin-Account-number');
                const confirmAccountNumber = document.getElementById('signin-Account-Confirm');
                
                if (accountNumber && confirmAccountNumber) {
                    const accountValue = accountNumber.value.trim();
                    const confirmValue = confirmAccountNumber.value.trim();
                    
                    // Only validate if both fields have values
                    if (accountValue && confirmValue) {
                        if (accountValue !== confirmValue) {
                            showError('account_number_confirm', 'Account numbers do not match');
                            confirmAccountNumber.classList.add('is-invalid');
                            return false;
                        } else {
                            clearError('account_number_confirm');
                            confirmAccountNumber.classList.remove('is-invalid');
                            return true;
                        }
                    }
                }
                return true;
            }
            
            function showError(fieldName, message) {
                const errorSpan = document.getElementById(fieldName + '_error');
                if (errorSpan) {
                    errorSpan.textContent = message;
                    errorSpan.style.display = 'block';
                    errorSpan.style.color = '#dc3545';
                }
            }
            
            function clearError(fieldName) {
                const errorSpan = document.getElementById(fieldName + '_error');
                if (errorSpan) {
                    errorSpan.textContent = '';
                    errorSpan.style.display = 'none';
                }
            }
            
            function clearAllErrors() {
                const errorSpans = document.querySelectorAll('span[id$="_error"]');
                errorSpans.forEach(span => {
                    span.textContent = '';
                    span.style.display = 'none';
                });
                
                const invalidFields = document.querySelectorAll('.is-invalid');
                invalidFields.forEach(field => {
                    field.classList.remove('is-invalid');
                });
            }
            
            function setupInputRestrictions() {
                // Phone inputs
                const phoneInputs = document.querySelectorAll('input[type="tel"]');
                phoneInputs.forEach(input => {
                    input.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
                    });
                });
                
                // Pincode
                const pincodeInput = document.getElementById('signin-Pincode');
                if (pincodeInput) {
                    pincodeInput.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9]/g, '');
                        clearError('pincode');
                        this.classList.remove('is-invalid');
                    });
                }
                
                // Account numbers
                const accountInputs = document.querySelectorAll('input[name="account_number"], input[name="account_number_confirm"]');
                accountInputs.forEach(input => {
                    input.addEventListener('input', function(e) {
                        this.value = this.value.replace(/[^0-9]/g, '');
                        if (this.name === 'account_number') {
                            clearError('account_number');
                            this.classList.remove('is-invalid');
                        }
                        if (this.name === 'account_number_confirm') {
                            clearError('account_number_confirm');
                            this.classList.remove('is-invalid');
                        }
                        
                        // Validate account number matching in real-time
                        validateAccountNumberMatch();
                    });
                });
                
                // GST - more flexible input handling
                const gstInput = document.getElementById('signin-GST');
                if (gstInput) {
                    gstInput.addEventListener('input', function(e) {
                        // Allow spaces, dashes, and other separators but clean them for validation
                        this.value = this.value.toUpperCase();
                        clearError('gst_number');
                        this.classList.remove('is-invalid');
                    });
                }
                
                // IFSC
                const ifscInput = document.querySelector('input[name="ifsc"]');
                if (ifscInput) {
                    ifscInput.addEventListener('input', function(e) {
                        this.value = this.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
                        clearError('ifsc');
                        this.classList.remove('is-invalid');
                    });
                }
            }
            
            // Phone validation function
            function setupPhoneValidation() {
                const mobileInput = document.getElementById('signin-Mobile');
                if (mobileInput) {
                    let timeoutId;
                    
                    mobileInput.addEventListener('input', function() {
                        clearTimeout(timeoutId);
                        const phoneNumber = this.value.replace(/\D/g, '');
                        
                        if (phoneNumber.length >= 10) {
                            timeoutId = setTimeout(() => {
                                checkPhoneDuplicate(phoneNumber);
                            }, 500); // Debounce for 500ms
                        } else {
                            clearError('mobile');
                            this.classList.remove('is-invalid');
                        }
                    });
                }
            }
            
            function checkPhoneDuplicate(phoneNumber) {
                if (phoneNumber.length < 10) return;
                
                fetch('<?php echo USER_BASEURL; ?>app/api/check_phone.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'phone=' + encodeURIComponent(phoneNumber)
                })
                .then(response => response.json())
                .then(data => {
                    const mobileInput = document.getElementById('signin-Mobile');
                    if (data.exists) {
                        showError('mobile', 'This phone number is already registered. Please use a different number.');
                        mobileInput.classList.add('is-invalid');
                    } else {
                        clearError('mobile');
                        mobileInput.classList.remove('is-invalid');
                    }
                })
                .catch(error => {
                    console.error('Error checking phone:', error);
                });
            }
            
            // Synchronous phone duplicate check for form validation
            function checkPhoneDuplicateSync(phoneNumber) {
                if (phoneNumber.length < 10) return false;
                
                // Use XMLHttpRequest for synchronous request
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '<?php echo USER_BASEURL; ?>app/api/check_phone.php', false); // false = synchronous
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('phone=' + encodeURIComponent(phoneNumber));
                
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        const mobileInput = document.getElementById('signin-Mobile');
                        if (data.exists) {
                            showError('mobile', 'This phone number is already registered. Please use a different number.');
                            mobileInput.classList.add('is-invalid');
                            return false;
                        } else {
                            clearError('mobile');
                            mobileInput.classList.remove('is-invalid');
                            return true;
                        }
                    } catch (e) {
                        console.error('Error parsing phone check response:', e);
                        return true; // Allow form submission if check fails
                    }
                }
                return true; // Allow form submission if check fails
            }
            
            // Modal function
            function showModal(icon, message, className) {
                const modalBody = document.getElementById('messageModalBody');
                if (modalBody) {
                    modalBody.innerHTML = `<div class="${className}"><strong>${icon}</strong> ${message}</div>`;
                    const modal = new bootstrap.Modal(document.getElementById('messageModal'));
                    modal.show();
                }
            }
            
        });
        

        // Global error hook disabled
        window.addEventListener('error', function (e) {
            console.error('[registration] Global error:', e.message, e.filename + ':' + e.lineno + ':' + e.colno);
        });
    </script>
    <script src="./include/security.js"></script>
    
    <script>
document.addEventListener('keydown', function(e) {
    // Disable F12
    if (e.key === "F12") {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+Shift+I
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'i') {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+Shift+C
    if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        return false;
    }

    // Disable Ctrl+U
    if (e.ctrlKey && e.key.toLowerCase() === 'u') {
        e.preventDefault();
        return false;
    }
});

// Optional: Disable right-click
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});
</script>
</body>

</html>
<?php
session_start();

require_once __DIR__ . '/includes/init.php';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

// Fetch user data if logged in
$user_data = null;
if ($is_logged_in) {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($db_connection, $user_query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>MY ACCOUNT | HAGIDY</title>

    <meta name="keywords" content="Marketplace ecommerce responsive HTML5 Template" />
    <meta name="description" content="Wolmart is powerful marketplace &amp; ecommerce responsive Html5 Template.">
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

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">

    <!-- Plugin CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/default-skin/default-skin.min.css">

    <!-- Default CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/style.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/demo12.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/my-account.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,100..700;1,100..700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />



</head>

<body class="my-account">
    <!-- Login Required Modal -->
    <div class="login-required-modal" id="login-required-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon">
                <i class="fas fa-user-lock"></i>
            </div>
            <div class="login-required-title">Login Required</div>
            <div class="login-required-message">
                Please login to your account to access your account details and manage your profile.
            </div>
            <div class="login-required-buttons">
                <a href="login.php" class="btn-login-required primary">Login Now</a>
                <a href="login.php#sign-up" class="btn-login-required secondary">Register</a>
            </div>
        </div>
    </div>

    <!-- Email Already Exists Modal -->
    <div class="login-required-modal" id="email-exists-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="login-required-title">Email Already Exists</div>
            <div class="login-required-message">
                This email address is already being used by another account. Please use a different email address.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeEmailExistsModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Profile Update Confirmation Modal -->
    <div class="login-required-modal" id="profile-confirm-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #ffc107;">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="login-required-title">Confirm Profile Update</div>
            <div class="login-required-message">
                Are you sure you want to update your profile information? This action cannot be undone.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="confirmProfileUpdate()">Yes, Update</button>
                <button class="btn-login-required secondary" onclick="closeProfileConfirmModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Profile Update Success Modal -->
    <div class="login-required-modal" id="profile-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title">Profile Updated Successfully!</div>
            <div class="login-required-message">
                Your profile information has been updated successfully.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeProfileSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Profile Update Error Modal -->
    <div class="login-required-modal" id="profile-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">Update Failed</div>
            <div class="login-required-message" id="profile-error-message">
                An error occurred while updating your profile. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeProfileErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Bank Verification Success Modal -->
    <div class="login-required-modal" id="bank-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title" id="bank-success-title">Bank Details Verified Successfully!</div>
            <div class="login-required-message" id="bank-success-message">
                Your bank details have been verified and saved successfully.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeBankSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Bank Error Modal -->
    <div class="login-required-modal" id="bank-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">Bank Details Error</div>
            <div class="login-required-message" id="bank-error-message">
                An error occurred while processing your bank details. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeBankErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- KYC Success Modal -->
    <div class="login-required-modal" id="kyc-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title">KYC Verified Successfully!</div>
            <div class="login-required-message">
                Your KYC details have been verified.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeKycSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- KYC Error Modal -->
    <div class="login-required-modal" id="kyc-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">KYC Error</div>
            <div class="login-required-message" id="kyc-error-message">
                An error occurred while processing your KYC. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeKycErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Password Change Success Modal -->
    <div class="login-required-modal" id="password-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title">Password Changed Successfully!</div>
            <div class="login-required-message">
                Your password has been changed successfully.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closePasswordSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Password Change Error Modal -->
    <div class="login-required-modal" id="password-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">Password Change Error</div>
            <div class="login-required-message" id="password-error-message">
                An error occurred while changing your password. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closePasswordErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Address Delete Confirmation Modal -->
    <div class="login-required-modal" id="address-delete-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="login-required-title">Delete Address</div>
            <div class="login-required-message">
                Are you sure you want to delete this address? This action cannot be undone.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="confirmDeleteAddress()">Yes, Delete</button>
                <button class="btn-login-required secondary" onclick="closeAddressDeleteModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Address Success Modal -->
    <div class="login-required-modal" id="address-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title" id="address-success-title">Success!</div>
            <div class="login-required-message" id="address-success-message">
                Address operation completed successfully.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeAddressSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Address Error Modal -->
    <div class="login-required-modal" id="address-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">Address Error</div>
            <div class="login-required-message" id="address-error-message">
                An error occurred while processing your address. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeAddressErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="login-required-modal" id="logout-confirmation-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #ffc107;">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <div class="login-required-title">Confirm Logout</div>
            <div class="login-required-message">
                Are you sure you want to logout? You will need to login again to access your account.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="confirmLogout()">Yes, Logout</button>
                <button class="btn-login-required secondary" onclick="closeLogoutConfirmationModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Logout Success Modal -->
    <div class="login-required-modal" id="logout-success-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #28a745;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="login-required-title">Logged Out Successfully</div>
            <div class="login-required-message">
                You have been logged out successfully. Redirecting to login page...
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeLogoutSuccessModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Logout Error Modal -->
    <div class="login-required-modal" id="logout-error-modal" style="display: none;">
        <div class="login-required-content">
            <div class="login-required-icon" style="color: #dc3545;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="login-required-title">Logout Error</div>
            <div class="login-required-message" id="logout-error-message">
                An error occurred while logging out. Please try again.
            </div>
            <div class="login-required-buttons">
                <button class="btn-login-required primary" onclick="closeLogoutErrorModal()">OK</button>
            </div>
        </div>
    </div>

    <div class="page-wrapper">
        <?php require_once __DIR__ . '/../includes/header.php'; ?>

        <!-- Start of Main -->
        <main class="main">
            <!-- Start of Page Header -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav MB-0 ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li>My Account </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Page Header -->

            <!-- End of Breadcrumb -->

            <!-- Start of PageContent -->
            <div class="page-content pt-2">
                <div class="container">
                    <div>
                        <a href="#"
                            class="btn btn-primary btn-outline btn-rounded left-sidebar-toggle btn-icon-left d-block d-lg-none mb-2 "
                            style="width: fit-content;"><i class="w-icon-category"></i>Menu</a>
                    </div>
                    <!-- End of Breadcrumb-nav -->
                    <div class="container">
                        <!-- Start of Sidebar, Shop Sidebar -->
                        <aside class="sidebar shop-sidebar sticky-sidebar-wrapper sidebar-fixed d-block d-lg-none">
                            <div class="sidebar-overlay"></div>
                            <a class="sidebar-close" href="#"><i class="close-icon"></i></a>
                            <div class="sidebar-content scrollable">
                                <div class="sticky-sidebar">
                                    <div class="tab tab-vertical row">
                                        <ul class="nav nav-tabs mb-6" role="tablist">
                                            <li class="nav-item">
                                                <a href="#account-details" class="nav-link active" data-toggle="tab"
                                                    role="tab" aria-selected="true">My Account</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#account-orders" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Order history</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#account-wishlist" class="nav-link" data-toggle="tab"
                                                    role="tab" aria-selected="false">Wishlist</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#bank-details" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Bank Details</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#kyc-details" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">KYC Details</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#addresses" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Address</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#coin-histroy" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Hagidy Coin History </a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#refer-friend" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Refer a Friend</a>
                                            </li>
                                            <li class="nav-item">
                                                <a href="#change-password" class="nav-link" data-toggle="tab" role="tab"
                                                    aria-selected="false">Change Password </a>
                                            </li>
                                            <li class="link-item link-item1 ">
                                                <a href="#"
                                                    onclick="showLogoutConfirmationModal(); return false;">Logout</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                    <div class="tab tab-vertical row gutter-lg">
                        <ul class="nav nav-tabs mb-6 d-lg-block d-none" role="tablist">
                            <li class="nav-item">
                                <a href="#account-details" class="nav-link active" data-toggle="tab" role="tab"
                                    aria-selected="true">My Account</a>
                            </li>
                            <li class="nav-item">
                                <a href="#account-orders" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Order history</a>
                            </li>
                            <li class="nav-item">
                                <a href="#account-wishlist" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Wishlist</a>
                            </li>
                            <li class="nav-item">
                                <a href="#bank-details" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Bank Details</a>
                            </li>
                            <li class="nav-item">
                                <a href="#kyc-details" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">KYC Details</a>
                            </li>
                            <li class="nav-item">
                                <a href="#addresses" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Address</a>
                            </li>
                            <li class="nav-item">
                                <a href="#coin-histroy" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Hagidy Coin History</a>
                            </li>
                            <li class="nav-item">
                                <a href="#refer-friend" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Refer a Friend</a>
                            </li>
                            <li class="nav-item">
                                <a href="#change-password" class="nav-link" data-toggle="tab" role="tab"
                                    aria-selected="false">Change Password </a>
                            </li>
                            <li class="link-item link-item1 ">
                                <a href="#" onclick="showLogoutConfirmationModal(); return false;">Logout</a>
                            </li>
                        </ul>

                        <div class="tab-content mb-6">
                            <div class="tab-pane active" id="account-details" role="tabpanel"
                                aria-labelledby="account-details-tab">
                                <div class="icon-box icon-box-side icon-box-light">

                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title mb-0 ls-normal">Account Details</h4>
                                    </div>
                                </div>
                                <form class="form account-details-form" id="profile-update-form" action="#"
                                    method="post">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="firstname" class="name-first">First name
                                                    <span>*</span></label>
                                                <input type="text" id="firstname" name="firstname" disabled
                                                    value="<?php echo $user_data ? htmlspecialchars($user_data['first_name']) : ''; ?>"
                                                    placeholder="Enter your first name"
                                                    class="form-control form-control-md disabled">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="lastname" class="name-first">Last name
                                                    <span>*</span></label>
                                                <input type="text" id="lastname" name="lastname" disabled
                                                    value="<?php echo $user_data ? htmlspecialchars($user_data['last_name']) : ''; ?>"
                                                    placeholder="Enter your last name"
                                                    class="form-control form-control-md disabled">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-3">
                                        <label for="display-name" class="name-first">Phone Number <span>*</span></label>
                                        <input type="text" id="display-name" name="display_name"
                                            value="<?php echo $user_data ? '+91 ' . htmlspecialchars($user_data['mobile']) : '+91 999999999'; ?>"
                                            class="form-control form-control-md mb-0 disabled" disabled>
                                        <p>To change your mobile number, please email us at <a href="#"
                                                class=" border-contactmail"> connect@Hagidy.com</a></p>
                                    </div>

                                    <div class="form-group mb-6">
                                        <label for="email_1">Email address <span class="req-star">*</span></label>
                                        <input type="email" id="email_1" name="email_1"
                                            value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                                            data-original-email="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                                            placeholder="Enter your email address" class="form-control form-control-md" required>
                                    </div>

                                    <button type="button" id="save-profile-btn"
                                        class="btn btn-dark btn-rounded btn-sm mb-4">Save
                                        Changes</button>
                                </form>
                            </div>
                            <div class="tab-pane" id="account-wishlist" role="tabpanel"
                                aria-labelledby="account-wishlist-tab">
                                <?php include __DIR__ . '/wishlist_embed.php'; ?>
                            </div>
                            <div class="tab-pane mb-4 border-order-icon" id="account-orders" role="tabpanel"
                                aria-labelledby="account-orders-tab">
                                <div class="icon-box icon-box-side icon-box-light order-history-flex">
                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title text-capitalize ls-normal mb-0 order-history-text">
                                            Order History</h4>
                                    </div>
                                    <div style="margin-left: 13rem !important; margin-top: -1rem !important;">
                                        <button class="excel-icon-text" id="export-orders-btn"> <i
                                                class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</button>
                                    </div>
                                </div>

                                <!-- Order History Filters -->
                                <div class="order-filters mb-4"
                                    style="background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e9ecef;">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">Filter by Month:</label>
                                            <select id="month-filter" class="form-control form-control-md">
                                                <option value="">All Months</option>
                                                <option value="01">January</option>
                                                <option value="02">February</option>
                                                <option value="03">March</option>
                                                <option value="04">April</option>
                                                <option value="05">May</option>
                                                <option value="06">June</option>
                                                <option value="07">July</option>
                                                <option value="08">August</option>
                                                <option value="09">September</option>
                                                <option value="10">October</option>
                                                <option value="11">November</option>
                                                <option value="12">December</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">From Date:</label>
                                            <input type="date" id="from-date-filter"
                                                class="form-control form-control-md">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold">To Date:</label>
                                            <input type="date" id="to-date-filter" class="form-control form-control-md">
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-12 text-center">
                                            <button id="apply-filters" class="btn btn-primary btn-sm me-2">
                                                <i class="fa-solid fa-filter"></i> Apply Filters
                                            </button>
                                            <button id="clear-filters" class="btn btn-outline-secondary btn-sm">
                                                <i class="fa-solid fa-times"></i> Clear Filters
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                // Fetch order history for the current user
                                $order_history_query = "SELECT * FROM `order` WHERE user_id = $user_id ORDER BY created_date DESC";
                                $order_history_result = mysqli_query($db_connection, $order_history_query);

                                // Function to get product details
                                function getProductDetails($product_id, $db_connection)
                                {
                                    $product_query = "SELECT product_name, images FROM products WHERE id = $product_id AND status = 'approved'";
                                    $product_result = mysqli_query($db_connection, $product_query);
                                    if ($product_result && mysqli_num_rows($product_result) > 0) {
                                        return mysqli_fetch_assoc($product_result);
                                    }
                                    return null;
                                }

                                // Function to get first image from images JSON
                                function getFirstImage($images_json)
                                {
                                    global $vendor_baseurl;
                                    if (empty($images_json)) {
                                        return USER_BASEURL . $vendor_baseurl . 'uploads/vendors/no-product.png'; // Default image
                                    }

                                    $images = json_decode($images_json, true);
                                    if (is_array($images) && !empty($images)) {
                                        return $images[0];
                                    }

                                    return USER_BASEURL . $vendor_baseurl . 'uploads/vendors/no-product.png'; // Default image
                                }

                                // Function to format order status
                                function getStatusClass($status)
                                {
                                    switch (strtolower($status)) {
                                        case 'pending':
                                            return 'order-status1';
                                        case 'processing':
                                            return 'order-status1';
                                        case 'shipped':
                                            return 'order-shipped';
                                        case 'cancelled':
                                            return 'order-out';
                                        case 'delivered':
                                            return 'order-delivered';
                                        default:
                                            return 'order-status1';
                                    }
                                }

                                // Function to format date
                                function formatOrderDate($date)
                                {
                                    return date('d F, Y', strtotime($date));
                                }
                                ?>
                                <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                                    <table class="shop-table account-orders-table mb-5">
                                        <thead>
                                            <tr>
                                                <th class="order-id">Order ID</th>
                                                <th class="order-id">Product</th>
                                                <th class="order-date">Order Date</th>
                                                <th class="order-status">Status</th>
                                                <th class="order-total">Total</th>
                                                <th class="order-total">Earned Coins</th>
                                                <th class="order-actions">Actions <i class="fa-solid fa-arrow-down"></i>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($order_history_result && mysqli_num_rows($order_history_result) > 0): ?>
                                                <?php while ($order = mysqli_fetch_assoc($order_history_result)): ?>
                                                    <?php
                                                    // Parse products JSON
                                                    $products_data = json_decode($order['products'], true);
                                                    $first_product = null;
                                                    $additional_count = 0;

                                                    if (is_array($products_data) && !empty($products_data)) {
                                                        $first_product_id = $products_data[0]['product_id'];
                                                        $first_product = getProductDetails($first_product_id, $db_connection);
                                                        $additional_count = count($products_data) - 1;
                                                    }

                                                    $product_name = $first_product ? $first_product['product_name'] : 'Product Not Found';
                                                    $product_image = $first_product ? getFirstImage($first_product['images']) : $vendor_baseurl . 'uploads/vendors/no-product.png';
                                                    $first_quantity = is_array($products_data) && !empty($products_data) ? $products_data[0]['quantity'] : 1;
                                                    $first_variant = is_array($products_data) && !empty($products_data) && isset($products_data[0]['variant']) ? $products_data[0]['variant'] : '';
                                                    ?>
                                                    <tr>
                                                        <td class="order-id order-id1"><b>#<?php echo $order['order_id']; ?></b>
                                                        </td>
                                                        <td class="order-date d-flex align-items-center gap-3">
                                                            <div>
                                                                <img src="<?php echo $product_image; ?>"
                                                                    style="width: 50px; height: 50px; border-radius: 50%; margin-right: 10px;"
                                                                    alt="<?php echo htmlspecialchars($product_name); ?>">
                                                            </div>
                                                            <div class="confortable-text">
                                                                <h3><?php echo htmlspecialchars($product_name); ?></h3>
                                                                <p>
                                                                    <?php echo $first_quantity; ?> x
                                                                    <?php echo !empty($first_variant) ? $first_variant : 'Item'; ?>
                                                                    <?php if ($additional_count > 0): ?>
                                                                        <span
                                                                            style="color: #666; font-weight: bold;">+<?php echo $additional_count; ?>
                                                                            more</span>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </td>
                                                        <td class="order-date order-date1">
                                                            <?php date_default_timezone_set('Asia/Kolkata');
                                                            echo date('d M Y', strtotime($order['created_date'])); ?><br>
                                                            <span
                                                                style="font-size: 12px; color: #666;"><?php echo date('h:i A', strtotime($order['created_date'])); ?></span>
                                                        </td>
                                                        <td
                                                            class="order-status <?php echo getStatusClass($order['order_status']); ?>">
                                                            <span><?php echo ucfirst($order['order_status']); ?></span>
                                                        </td>
                                                        <td class="order-total order-date1">
                                                            ₹<?php echo number_format($order['total_amount'], 2); ?>
                                                        </td>
                                                        <td class="">
                                                            <div class="coin-table1">
                                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png"
                                                                    style="width: 20px; height: 20px;" alt="">
                                                                <?php echo $order['coin_earn']; ?>
                                                            </div>
                                                        </td>
                                                        <td class="order-action">
                                                            <a href="./order-detail.php?order_id=<?php echo $order['order_id']; ?>"
                                                                class="btn btn-outline btn-default btn-block btn-sm btn-rounded">View</a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center" style="padding: 40px;">
                                                        <div style="color: #666;">
                                                            <i class="fa-solid fa-shopping-bag"
                                                                style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                                                            <h4>No orders found</h4>
                                                            <p>You haven't placed any orders yet.</p>
                                                            <a href="./shop.php" class="btn btn-primary">Start Shopping</a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                        </tbody>
                                    </table>
                                </div>

                            </div>
                            <div class="tab-pane" id="bank-details" role="tabpanel" aria-labelledby="bank-details-tab">
                                <div class="icon-box icon-box-side icon-box-light">

                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title mb-3 ls-normal">Bank Details</h4>
                                    </div>
                                </div>
                                <form class="form account-details-form" id="bank-details-form" action="#" method="post">



                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="account-number" class="name-first">Account Number
                                                    <span>*</span></label>
                                                <input type="number" id="account-number" name="account_number"
                                                    placeholder="Enter Account Number"
                                                    class="form-control form-control-md" pattern="[0-9]*"
                                                    inputmode="numeric" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="confirm-account-number" class="name-first">Confirm Account
                                                    Number <span>*</span></label>
                                                <input type="number" id="confirm-account-number"
                                                    name="confirm_account_number" placeholder="Confirm Account Number"
                                                    class="form-control form-control-md" pattern="[0-9]*"
                                                    inputmode="numeric" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="account-type" class="name-first">Account Type
                                                    <span>*</span></label>
                                                <select id="account-type" name="account_type"
                                                    class="form-control form-control-md" required>
                                                    <option value="" disabled selected>Select Account Type</option>
                                                    <option value="savings">Savings</option>
                                                    <option value="current">Current</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="ifsc-code" class="name-first">IFSC Code
                                                    <span>*</span></label>
                                                <input type="text" id="ifsc-code" name="ifsc_code"
                                                    placeholder="Enter IFSC Code" class="form-control form-control-md"
                                                    pattern="[A-Z]{4}0[A-Z0-9]{6}" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="account-holder-name" class="name-first">Account Holder Name
                                                    <span>*</span></label>
                                                <input type="text" id="account-holder-name" name="account_holder_name"
                                                    placeholder="Enter Account holder name "
                                                    class="form-control form-control-md" pattern="[A-Za-z\s.]+"
                                                    required>
                                            </div>
                                        </div>
                                        <script>
                                            const nameInput = document.getElementById('account-holder-name');

                                            // Listen to input events
                                            nameInput.addEventListener('input', function () {
                                                // Replace anything that is not a letter, space, or dot
                                                this.value = this.value.replace(/[^A-Za-z\s.]/g, '');
                                            });
                                        </script>
                                    </div>
                                    <button type="button" id="verify-bank-btn"
                                        class="btn btn-dark btn-rounded btn-sm mb-4">Verify &
                                        Update</button>

                                    <!-- OTP Section (Hidden by default) -->
                                    <div id="otp-section" style="display: none;" class="mt-4">
                                        <div class="form-group mb-3">
                                            <label for="bank-otp" class="name-first">Enter OTP <span>*</span></label>
                                            <input type="number" id="bank-otp" name="bank_otp"
                                                placeholder="Enter 6-digit OTP" class="form-control form-control-md"
                                                maxlength="6" pattern="[0-9]{6}">
                                        </div>
                                        <button type="button" id="verify-otp-btn"
                                            class="btn btn-success btn-rounded btn-sm mb-4">Verify OTP</button>
                                    </div>

                                    <div class="otp-order" id="otp-message" style="display: none;">
                                        <p>
                                            Please verify with OTP to update your bank details.
                                        </p>
                                    </div>
                                </form>
                            </div>
                            <div class="tab-pane" id="kyc-details" role="tabpanel" aria-labelledby="kyc-details-tab">
                                <div class="icon-box icon-box-side icon-box-light">

                                    <div class="icon-box-content kyc-details">
                                        <h4 class="icon-box-title mb-0 ls-normal mb-3">KYC Details</h4>
                                        <button type="button" id="edit-kyc-btn"
                                            class="btn btn-outline btn-rounded btn-sm"
                                            style="display:none; margin-left:10px;">Edit KYC</button>
                                        <div class="order-status1" id="kyc-status-pending"> <span>Pending</span>
                                        </div>
                                        <div class="order-shipped" id="kyc-status-approved" style="display:none;">
                                            <span>Approved</span>
                                        </div>
                                        <div class="order-out" id="kyc-status-rejected" style="display:none;">
                                            <span>Rejected</span>
                                        </div>
                                    </div>
                                </div>
                                <form class="form account-details-form" id="kyc-details-form" action="#" method="post">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="mobile-number" class="name-first">Mobile Number
                                                    <span>*</span></label>
                                                <input type="text" id="mobile-number" name="mobile_number"
                                                    value="<?php echo $user_data ? '+91 ' . htmlspecialchars($user_data['mobile']) : ''; ?>"
                                                    class="form-control form-control-md mb-0 disabled" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="user-name" class="name-first">User Name
                                                    <span>*</span></label>
                                                <input type="text" id="user-name" name="user_name"
                                                    value="<?php echo $user_data ? htmlspecialchars($user_data['first_name'] . '.' . $user_data['last_name']) : ''; ?>"
                                                    class="form-control form-control-md mb-0 disabled" disabled>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kyc-firstname" class="name-first">First Name
                                                    <span>*</span></label>
                                                <input type="text" id="kyc-firstname" name="kyc_firstname"
                                                    placeholder="John" class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="kyc-lastname" class="name-first">Last Name
                                                    <span>*</span></label>
                                                <input type="text" id="kyc-lastname" name="kyc_lastname"
                                                    placeholder="Doe" class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="dob" class="name-first">Date of Birth <span>*</span></label>
                                                <input type="date" id="dob" name="dob"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="gender" class="name-first">Gender <span>*</span></label>
                                                <select id="gender" name="gender" class="form-control form-control-md"
                                                    required>
                                                    <option value="" disabled selected>Select Gender</option>
                                                    <option value="male">Male</option>
                                                    <option value="female">Female</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-3">
                                                <label for="kyc-city" class="name-first">City <span>*</span></label>
                                                <input type="text" id="kyc-city" name="kyc_city"
                                                    placeholder="Enter City Name" class="form-control form-control-md"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="pincode-main" class="name-first">Pincode
                                                    <span>*</span></label>
                                                <input type="number" id="pincode-main" name="pincode"
                                                    placeholder="Enter Pincode" class="form-control form-control-md"
                                                    pattern="[0-9]{6}" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="kyc-address" class="name-first">Address
                                                    <span>*</span></label>
                                                <input type="text" id="kyc-address" name="kyc_address"
                                                    placeholder="e.g., 123 Main Street"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="icon-box-content kyc-details">
                                                <h4 class="icon-box-title mb-0 ls-normal mb-3">Document Details</h4>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="pan-number" class="name-first">PAN Number
                                                    <span>*</span></label>
                                                <input type="text" id="pan-number" name="pan_number"
                                                    placeholder="Enter your PAN Number"
                                                    class="form-control form-control-md"
                                                    pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <label for="document-name" class="name-first">Name as per Document
                                                    <span>*</span></label>
                                                <input type="text" id="document-name" name="document_name"
                                                    placeholder="Enter your name as per PAN"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div id="kyc-upload-section">
                                                <label for="kyc-document-file" class="name-first ">Upload PAN Image
                                                    <span>*</span></label>
                                                <div class="file-upload-wrapper mt-2">
                                                    <div class="file-upload-box">
                                                        <input type="file" id="kyc-document-file"
                                                            class="file-upload-input" accept="image/*">
                                                        <div class="upload-content">
                                                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                                            <h5 class="mb-1">Drag your file(s) or <span>browse</span>
                                                            </h5>
                                                            <p class="text-muted mb-0">Max 10 MB files are allowed</p>
                                                        </div>
                                                    </div>
                                                    <div class="file-list"></div>
                                                </div>
                                            </div>
                                            <div id="kyc-pan-preview" style="display:none; margin-top:10px;">
                                                <label class="name-first">Uploaded PAN Image</label>
                                                <div>
                                                    <img id="kyc-pan-preview-img" src="" alt="PAN Image"
                                                        style="max-width: 260px; border: 1px solid #eee; border-radius: 6px;" 
                                                        onerror="this.src='<?php echo PUBLIC_ASSETS; ?>uploads/products/no-product.png'; this.alt='Image not available';" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="save-kyc-btn"
                                        class="btn btn-dark btn-rounded btn-sm mb-4">Save & Send OTP</button>

                                    <!-- KYC OTP Section (Hidden by default) -->
                                    <div id="kyc-otp-section" style="display: none;" class="mt-4">
                                        <div class="form-group mb-3">
                                            <label for="kyc-otp" class="name-first">Enter OTP <span>*</span></label>
                                            <input type="number" id="kyc-otp" name="kyc_otp"
                                                placeholder="Enter 6-digit OTP" class="form-control form-control-md"
                                                maxlength="6" pattern="[0-9]{6}">
                                        </div>
                                        <button type="button" id="verify-kyc-otp-btn"
                                            class="btn btn-success btn-rounded btn-sm mb-4">Verify OTP</button>
                                    </div>
                                    <div class="otp-order" id="kyc-otp-message" style="display: none;">
                                        <p>
                                            Please verify with OTP to complete your KYC.
                                        </p>
                                    </div>
                                </form>

                            </div>
                            <div class="tab-pane" id="coin-histroy" role="tabpanel" aria-labelledby="coin-histroy-tab">
                                <div class="icon-box icon-box-side icon-box-light coin-history-flex">
                                    <div class="icon-box-content kyc-details">
                                        <h4 class="icon-box-title mb-0 ls-normal mb-3">Hagidy Coin History</h4>
                                    </div>
                                    <div class="export-excel">
                                        <button class="excel-icon-text" id="export-coin-history-btn">
                                            <i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel
                                        </button>
                                    </div>
                                </div>
                                <div class="row d-flex align-items-center">
                                    <div class="col-lg-6 col-xl-6 col-md-12 col-sm-12 col-12">
                                        <div class="your-balance">
                                            <div>
                                                <h2>
                                                    Your Balance
                                                </h2>
                                            </div>
                                            <div class="d-flex align-items-center balance-coin-hagidy">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png" class="mr-1 coni-balance"
                                                    alt="">
                                                <h3 id="coin-balance">0.0</h3>
                                            </div>
                                        </div>
                                        <div class="coin-history-p">
                                            <p>
                                                Coin History
                                            </p>

                                        </div>
                                        <div class="border-order" id="coin-transactions-container">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="sr-only">Loading...</span>
                                                </div>
                                                <p class="mt-2">Loading coin history...</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-xl-6 col-md-12 col-sm-12 col-12">
                                        <div>
                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-histroy.svg" alt="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="refer-friend" role="tabpanel" aria-labelledby="refer-friend-tab">
                                <div class="icon-box icon-box-side icon-box-light" id="#rf">
                                    <div class="icon-box-content kyc-details">
                                        <h4 class="icon-box-title mb-0 ls-normal mb-3">Refer a Friend </h4>
                                    </div>
                                </div>
                                <div class="row d-flex align-items-center">
                                    <div class="col-xl-6  col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="earm-more-img">
                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/earn-more.svg" alt="">
                                            <h3>
                                                Share More, Earn More
                                            </h3>
                                        </div>
                                        <div class="unlink-border">
                                            <div class="unlink-text">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/unlink.svg" alt="">
                                                <h4>1. Copy & Share your referral link to Invite your family & friends.
                                                </h4>
                                            </div>
                                            <div class="unlink-text">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/exam.svg" alt="">
                                                <h4>2. You will earn Hagidy coins, every purchase made by them. </h4>
                                            </div>
                                            <div class="unlink-text">
                                                <img src="<?php echo PUBLIC_ASSETS; ?>images/wallet.svg" alt="">
                                                <h4>3. Stay Active to keep receiving Hagidy Coins.</h4>
                                            </div>
                                            <hr class="hr-unlink">
                                        </div>
                                        <div>
                                            <div class="refer-link-heading">
                                                <h3>
                                                    YOUR REFERRAL LINK
                                                </h3>
                                            </div>
                                            <div>
                                                <button
                                                    class="brn-link-copy"><?php echo $user_baseurl; ?>/login.php#register?ref=<?php echo $user_data ? htmlspecialchars($user_data['referral_code']) : ''; ?>
                                                    <i class="fa-regular fa-copy"></i></button>
                                            </div>
                                            <div class="refer-link-heading">
                                                <h4>
                                                    Tap to copy
                                                </h4>
                                            </div>
                                            <div class="text-center">
                                                <button class="btn-share-whatsapp btn-share-acc mr-2"> <img
                                                        src="<?php echo PUBLIC_ASSETS; ?>images/share.svg" alt=""> More</button>
                                                <button class="btn-share-whatsapp btn-whatsapp"> <img
                                                        src="<?php echo PUBLIC_ASSETS; ?>images/Whatsapp.svg" alt=""> Whatsapp</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                        <div class="refer-friend-img">
                                            <img src="<?php echo PUBLIC_ASSETS; ?>images/refer-friend.svg" alt="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane" id="change-password" role="tabpanel"
                                aria-labelledby="change-password-tab">
                                <div class="icon-box icon-box-side icon-box-light">

                                    <div class="icon-box-content kyc-details">
                                        <h4 class="icon-box-title mb-0 ls-normal mb-3">Password change</h4>

                                    </div>
                                </div>
                                <form class="form account-details-form" id="password-change-form" action="#"
                                    method="post">
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="current-password" class="name-first">Current Password
                                                    <span>*</span></label>
                                                <input type="password" id="current-password" name="current_password"
                                                    placeholder="Enter current password"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="new-password" class="name-first">New Password
                                                    <span>*</span></label>
                                                <input type="password" id="new-password" name="new_password"
                                                    placeholder="Enter new password"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group mb-3">
                                                <label for="confirm-password" class="name-first">Confirm Password
                                                    <span>*</span></label>
                                                <input type="password" id="confirm-password" name="confirm_password"
                                                    placeholder="Confirm new password"
                                                    class="form-control form-control-md" required>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" id="change-password-btn"
                                        class="btn btn-dark btn-rounded btn-sm mb-4">Change Password</button>

                                    <!-- Password OTP Section (Hidden by default) -->
                                    <div id="password-otp-section" style="display: none;" class="mt-4">
                                        <div class="form-group mb-3">
                                            <label for="password-otp" class="name-first">Enter OTP
                                                <span>*</span></label>
                                            <input type="number" id="password-otp" name="password_otp"
                                                placeholder="Enter 6-digit OTP" class="form-control form-control-md"
                                                maxlength="6" pattern="[0-9]{6}">
                                        </div>
                                        <button type="button" id="verify-password-otp-btn"
                                            class="btn btn-success btn-rounded btn-sm mb-4">Verify OTP</button>
                                    </div>
                                    <div class="otp-order" id="password-otp-message" style="display: none;">
                                        <p>
                                            Please verify with OTP to change your password.
                                        </p>
                                    </div>
                                </form>

                            </div>
                            <div class="tab-pane mb-4" id="addresses" role="tabpanel" aria-labelledby="addresses-tab">
                                <div
                                    class="icon-box icon-box-side icon-box-light d-flex justify-content-between align-items-center mb-3">
                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title text-capitalize ls-normal mb-3">Address</h4>
                                        <p>
                                            You can add up to 6 addresses only.
                                        </p>
                                    </div>
                                    <div>
                                        <div title="Edit"
                                            class="btn btn-block1 btn-icon-right btn-rounded btn-checkout1 edit-address-btn">
                                            + ADD ADDRESS
                                        </div>
                                    </div>
                                </div>
                                <!-- Dynamic Address Container -->
                                <div id="addresses-container" class="row">
                                    <!-- Address will be loaded here dynamically -->
                                </div>

                                <!-- No Address Message -->
                                <div id="no-addresses-message" class="text-center py-5" style="display: none;">
                                    <div class="icon-box icon-box-side icon-box-light">
                                        <div class="icon-box-content">
                                            <h4 class="icon-box-title mb-3">No Address Found</h4>
                                            <p>You haven't added any addresses yet. Click "ADD ADDRESS" to get started.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                            </div>
                            <div id="editModal" class="modal modal-lg"
                                style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); overflow-x: scroll;">
                                <div
                                    style="background:#fff; margin:5% auto; padding:20px 20px 20px 20px; border-radius:8px; width:90%; max-width:800px; position:relative; ">
                                    <div style="border-bottom: 1px solid #EBEBEB; margin: 10px 0; padding: 10px 0;"
                                        class="margin-bt-add-add">
                                        <span id="closeModal"
                                            style="position:absolute; right:25px; top:38px; font-size:14px; cursor:pointer; ">Close</span>
                                        <h4>ADD ADDRESS</h4>
                                    </div>
                                    <form id="address-form" method="post">
                                        <div class="row g-3">

                                            <!-- Address Fields -->
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="addressType" class="name-first">Address Type
                                                            <span>*</span></label>
                                                    </div>
                                                    <select id="addressType" name="address_type"
                                                        class="form-control form-control-md" required>
                                                        <option value="">Select</option>
                                                        <option value="home">Home</option>
                                                        <option value="business">Business</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="country" class="name-first">Country/Region
                                                            <span></span></label>
                                                    </div>
                                                    <select id="country" name="country_id"
                                                        class="form-control form-control-md" required>
                                                        <option value="">Select Country/Region</option>
                                                        <!-- Countries will be loaded dynamically -->
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="streetAddress" class="name-first">Street Address
                                                            <span>*</span></label>
                                                    </div>
                                                    <input type="text" id="streetAddress" name="street_address"
                                                        placeholder="House number and street name"
                                                        class="form-control form-control-md mb-2" required>
                                                    <input type="text" id="streetAddress2" name="street_address2"
                                                        placeholder="Apartment, suite, unit, etc. (optional)"
                                                        class="form-control form-control-md">
                                                </div>
                                            </div>
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="town" class="name-first">Town/City
                                                            <span>*</span></label>
                                                    </div>
                                                    <input type="text" id="town" name="city"
                                                        placeholder="Enter Town/City"
                                                        class="form-control form-control-md" required>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="state" class="name-first">State
                                                            <span>*</span></label>
                                                    </div>
                                                    <select id="state" name="state_id"
                                                        class="form-control form-control-md" required>
                                                        <option value="">Select State</option>
                                                        <!-- States will be loaded dynamically -->
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="pincode" class="name-first">PIN Code
                                                            <span>*</span></label>
                                                    </div>
                                                    <input type="number" id="pincode" name="pin_code"
                                                        placeholder="Enter PIN Code"
                                                        class="form-control form-control-md" required>
                                                </div>
                                            </div>
                                            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="mobile" class="name-first">Mobile Number
                                                            <span>*</span></label>
                                                    </div>
                                                    <input type="number" id="mobile" name="mobile_number"
                                                        placeholder="Enter Mobile Number"
                                                        class="form-control form-control-md" required>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group mb-3">
                                                    <div class="mb-2">
                                                        <label for="email" class="name-first">Email Address</label>
                                                    </div>
                                                    <input type="email" id="email" name="email_address"
                                                        placeholder="Enter Email" class="form-control form-control-md">
                                                </div>
                                            </div>


                                            <div class="mb-2 form-group set-primary-add">
                                                <input type="checkbox" id="vehicle1" name="is_primary" value="1">
                                                <label for="vehicle1" class="">Set as a primary Address</label>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" class="btn btn-svae1">Add Address</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="account-downloads">
                            <div class="icon-box icon-box-side icon-box-light">
                                <span class="icon-box-icon icon-downloads mr-2">
                                    <i class="w-icon-download"></i>
                                </span>
                                <div class="icon-box-content">
                                    <h4 class="icon-box-title ls-normal">Downloads</h4>
                                </div>
                            </div>
                            <p class="mb-4">No downloads available yet.</p>
                            <a href="#" class="btn btn-dark btn-rounded btn-icon-right">Go
                                Shop<i class="w-icon-long-arrow-right"></i></a>
                        </div>

                        <div class="tab-pane" id="account-addresses">
                            <div class="icon-box icon-box-side icon-box-light">
                                <span class="icon-box-icon icon-map-marker">
                                    <i class="w-icon-map-marker"></i>
                                </span>
                                <div class="icon-box-content">
                                    <h4 class="icon-box-title mb-0 ls-normal">Address</h4>
                                </div>
                            </div>
                            <p>The following addresses will be used on the checkout page
                                by default.</p>
                            <div class="row">
                                <div class="col-sm-6 mb-6">
                                    <div class="ecommerce-address billing-address pr-lg-8">
                                        <h4 class="title title-underline ls-25 font-weight-bold">Billing Address
                                        </h4>
                                        <address class="mb-4">
                                            <table class="address-table">
                                                <tbody>
                                                    <tr>
                                                        <th>Name:</th>
                                                        <td>John Doe</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Company:</th>
                                                        <td>Conia</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Address:</th>
                                                        <td>Wall Street</td>
                                                    </tr>
                                                    <tr>
                                                        <th>City:</th>
                                                        <td>California</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Country:</th>
                                                        <td>United States (US)</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Postcode:</th>
                                                        <td>92020</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Phone:</th>
                                                        <td>1112223334</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </address>
                                        <a href="#" class="btn btn-link btn-underline btn-icon-right text-primary">Edit
                                            your billing address<i class="w-icon-long-arrow-right"></i></a>
                                    </div>
                                </div>
                                <div class="col-sm-6 mb-6">
                                    <div class="ecommerce-address shipping-address pr-lg-8">
                                        <h4 class="title title-underline ls-25 font-weight-bold">Shipping Address
                                        </h4>
                                        <address class="mb-4">
                                            <table class="address-table">
                                                <tbody>
                                                    <tr>
                                                        <th>Name:</th>
                                                        <td>John Doe</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Company:</th>
                                                        <td>Conia</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Address:</th>
                                                        <td>Wall Street</td>
                                                    </tr>
                                                    <tr>
                                                        <th>City:</th>
                                                        <td>California</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Country:</th>
                                                        <td>United States (US)</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Postcode:</th>
                                                        <td>92020</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </address>
                                        <a href="#" class="btn btn-link btn-underline btn-icon-right text-primary">Edit
                                            your
                                            shipping address<i class="w-icon-long-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </div>
    </div>
    <!-- End of PageContent -->
    </main>
    <!-- End of Main -->
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>
    <!-- Wishlist Functionality (ensures wishlist buttons work in quickview modal) -->
    <script>
        // Wishlist Management System
        if (typeof window.WishlistManager === 'undefined') {
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

                isUserLoggedIn() {
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0): ?>
                    return true;
                    <?php else: ?>
                    return false;
                    <?php endif; ?>
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
                    const productId = this.getProductId(button);
                    if (!productId) {
                        this.showNotification('Unable to identify product. Please refresh the page and try again.', 'error');
                        return;
                    }
                    if (!this.isUserLoggedIn()) {
                        window.location.href = 'login.php';
                        return;
                    }
                    this.setButtonLoading(button, true);
                    try {
                        const isInWishlist = await this.isInWishlist(productId);
                        if (isInWishlist) {
                            await this.removeFromWishlist(productId, button);
                        } else {
                            await this.addToWishlist(productId, button);
                        }
                    } catch (error) {
                        this.showNotification('An unexpected error occurred. Please try again later.', 'error');
                    } finally {
                        this.setButtonLoading(button, false);
                    }
                }

                getProductId(button) {
                    let productId = button.getAttribute('data-product-id');
                    if (!productId) {
                        const modal = button.closest('.product-popup');
                        if (modal) productId = modal.getAttribute('data-product-id');
                    }
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
                    if (!productId && window.location.href.includes('product-detail.php')) {
                        const urlParams = new URLSearchParams(window.location.search);
                        productId = urlParams.get('id');
                    }
                    return productId ? parseInt(productId) : null;
                }

                async addToWishlist(productId, button) {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add', product_id: productId })
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
                }

                async removeFromWishlist(productId, button) {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'remove', product_id: productId })
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
                }

                async isInWishlist(productId) {
                    const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/wishlist_handler.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'check', product_id: productId })
                    });
                    const data = await response.json();
                    return data.success ? data.in_wishlist : false;
                }

                updateButtonState(button, isInWishlist) {
                    const icon = button.querySelector('i, .w-icon-heart, .w-icon-heart-full') || button;
                    if (isInWishlist) {
                        button.classList.add('active', 'in-wishlist');
                        button.classList.remove('w-icon-heart');
                        button.classList.add('w-icon-heart-full');
                        button.style.setProperty('color', '#ff4757', 'important');
                        if (icon && icon !== button) icon.style.setProperty('color', '#ff4757', 'important');
                        button.title = 'Remove from wishlist';
                    } else {
                        button.classList.remove('active', 'in-wishlist', 'w-icon-heart-full');
                        button.classList.add('w-icon-heart');
                        button.style.removeProperty('color');
                        if (icon && icon !== button) icon.style.removeProperty('color');
                        button.title = 'Add to wishlist';
                    }
                }

                updateAllProductCards(productId, isInWishlist) {
                    const allWishlistButtons = document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`);
                    allWishlistButtons.forEach(button => this.updateButtonState(button, isInWishlist));
                }

                setButtonLoading(button, isLoading) {
                    button.style.opacity = isLoading ? '0.6' : '';
                    button.style.pointerEvents = isLoading ? 'none' : '';
                }

                async checkWishlistStatus() {
                    const buttons = document.querySelectorAll('.btn-wishlist[data-wishlist-initialized="true"]');
                    for (const button of buttons) {
                        const productId = this.getProductId(button);
                        if (productId) {
                            try {
                                const isInWishlist = await this.isInWishlist(productId);
                                this.updateButtonState(button, isInWishlist);
                            } catch (e) {
                                this.updateButtonState(button, false);
                            }
                        } else {
                            this.updateButtonState(button, false);
                        }
                    }
                }

                async updateWishlistCount() {
                    try {
                        let count = 0;
                        const response = await fetch('<?php echo USER_BASEURL; ?>app/handlers/wishlist_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'get_count' })
                        });
                        const data = await response.json();
                        count = data.success ? data.count : 0;
                        const countElements = document.querySelectorAll('.wishlist-count, [data-wishlist-count], #wishlist-count');
                        countElements.forEach(element => {
                            element.textContent = count;
                            element.style.display = count > 0 ? 'inline' : 'none';
                        });
                    } catch (error) {}
                }

                showNotification(message, type = 'info') {
                    const notification = document.createElement('div');
                    const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '!' : 'ℹ';
                    const bg = (function(t){
                        if (t === 'success') return 'linear-gradient(135deg, #2ecc71, #27ae60)';
                        if (t === 'error') return 'linear-gradient(135deg, #e74c3c, #c0392b)';
                        if (t === 'warning') return 'linear-gradient(135deg, #f39c12, #d35400)';
                        return 'linear-gradient(135deg, #3498db, #2980b9)';
                    })(type);
                    notification.className = `wishlist-notification wishlist-notification-${type}`;
                    notification.innerHTML = `<span style="margin-right: 8px; font-weight: bold;">${icon}</span>${message}`;
                    notification.style.cssText = `position: fixed; top: 20px; right: 20px; color: white; padding: 16px 24px; z-index: 10000; font-size: 14px; line-height: 1.4; max-width: 350px; animation: slideInRight 0.3s ease; cursor: pointer; background: ${bg}; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);`;
                    document.body.appendChild(notification);
                    notification.addEventListener('click', () => { if (notification.parentNode) notification.parentNode.removeChild(notification); });
                    setTimeout(() => { if (notification.parentNode) notification.parentNode.removeChild(notification); }, 4000);
                }

                initDynamicWishlistButtons() {
                    this.initWishlistButtons();
                    setTimeout(() => { this.checkWishlistStatus(); }, 100);
                }
            }
            window.WishlistManager = WishlistManager;
            window.wishlistManager = new WishlistManager();
        } else {
            // Ensure available globally
            if (!window.wishlistManager) window.wishlistManager = new window.WishlistManager();
        }
    </script>
    <!-- Quickview JavaScript (ensure modal initializes and wishlist works inside) -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                    if (productId) {
                        quickviewBtn.style.opacity = '0.6';
                        if (typeof Wolmart !== 'undefined' && Wolmart.popup) {
                            Wolmart.popup({
                                items: { src: 'quickview.php?id=' + productId, type: 'ajax' },
                                ajax: { settings: { method: 'GET' } },
                                callbacks: {
                                    ajaxContentAdded: function() {
                                        try { if (typeof Wolmart.initProductSingle === 'function') Wolmart.initProductSingle(); } catch(e){}
                                        try { if (window.wishlistManager) window.wishlistManager.initDynamicWishlistButtons(); } catch(e){}
                                        quickviewBtn.style.opacity = '';
                                    },
                                    close: function () { quickviewBtn.style.opacity = ''; }
                                }
                            }, 'quickview');
                        } else {
                            // Fallback
                            fetch('quickview.php?id=' + productId).then(r=>r.text()).then(html=>{
                                const overlay = document.createElement('div');
                                overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;';
                                const modal = document.createElement('div');
                                modal.style.cssText = 'background:white;border-radius:8px;max-width:90%;max-height:90%;overflow:auto;position:relative;';
                                const closeBtn = document.createElement('button');
                                closeBtn.innerHTML = '×';
                                closeBtn.style.cssText = 'position:absolute;top:10px;right:15px;background:none;border:none;font-size:24px;cursor:pointer;z-index:10000;';
                                closeBtn.onclick = () => document.body.removeChild(overlay);
                                modal.innerHTML = html; modal.appendChild(closeBtn);
                                overlay.appendChild(modal); document.body.appendChild(overlay);
                                setTimeout(()=>{ try { if (window.wishlistManager) window.wishlistManager.initDynamicWishlistButtons(); } catch(e){} }, 100);
                            }).finally(()=>{ quickviewBtn.style.opacity=''; });
                        }
                    }
                });
            }

            waitForWolmart();
        });
    </script>
    <script>
        // Open modal on any edit icon click
        document.querySelectorAll('.edit-address-btn').forEach(function (btn) {
            btn.onclick = function () {
                document.getElementById('editModal').style.display = 'block';
            }
        });
        document.getElementById('closeModal').onclick = function () {
            closeAddressFormModal();
        };
        window.onclick = function (event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeAddressFormModal();
            }
        }
    </script>

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
            const sidebar = document.querySelector('.sidebar.shop-sidebar.sticky-sidebar-wrapper');
            const sidebarLinks = document.querySelectorAll('.sidebar.shop-sidebar.sticky-sidebar-wrapper .nav-link, .sidebar.shop-sidebar.sticky-sidebar-wrapper .link-item a');
            const sidebarClose = document.querySelector('.sidebar-close');

            sidebarLinks.forEach(function (link) {
                link.addEventListener('click', function () {
                    // Simulate clicking the close button to close the sidebar
                    if (sidebarClose) {
                        sidebarClose.click();
                    }
                    // Alternatively, directly remove the active class or hide the sidebar
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                });
            });
        });

/* Coin History Export Button Styling */
.coin - history - flex {
            display: flex;
            justify - content: space - between;
            align - items: center;
        }

.excel - icon - text {
            background: #f8f9fa;
            color: #495057;
            border: 1px solid #dee2e6;
            padding: 8px 16px;
            border - radius: 6px;
            font - size: 14px;
            font - weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align - items: center;
            gap: 8px;
        }

.excel - icon - text:hover {
            background: #e9ecef;
            border - color: #adb5bd;
            transform: translateY(-1px);
            box - shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

.excel - icon - text i {
            font - size: 16px;
        }
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

            // Check if user is logged in and show modal if not
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                // Show login required modal
                const modal = document.getElementById('login-required-modal');
                if (modal) {
                    modal.style.display = 'flex';

                    // Add click outside to close
                    modal.addEventListener('click', (e) => {
                        if (e.target === modal) {
                            // Redirect to home page if user closes modal
                            window.location.href = 'index.php';
                        }
                    });
                }
            }

            // Profile Update Functionality
            if (isLoggedIn) {
                const saveProfileBtn = document.getElementById('save-profile-btn');
                if (saveProfileBtn) {
                    saveProfileBtn.addEventListener('click', handleProfileUpdate);
                }

                // Email validation on blur
                const emailInput = document.getElementById('email_1');
                if (emailInput) {
                    emailInput.addEventListener('blur', validateEmail);
                }

                // Bank Details Functionality
                const verifyBankBtn = document.getElementById('verify-bank-btn');
                if (verifyBankBtn) {
                    verifyBankBtn.addEventListener('click', handleBankVerification);
                }

                const verifyOtpBtn = document.getElementById('verify-otp-btn');
                if (verifyOtpBtn) {
                    verifyOtpBtn.addEventListener('click', handleOTPVerification);
                }

                // Load bank details on page load
                loadBankDetails();

                // Clear pending bank details on page unload or after timeout
                clearPendingBankDetailsOnExit();

                // Add real-time account number validation
                const accountNumberInput = document.getElementById('account-number');
                const confirmAccountNumberInput = document.getElementById('confirm-account-number');

                if (accountNumberInput && confirmAccountNumberInput) {
                    accountNumberInput.addEventListener('input', validateAccountNumbers);
                    confirmAccountNumberInput.addEventListener('input', validateAccountNumbers);
                }

                // Address Management Functionality
                const addAddressBtn = document.querySelector('.edit-address-btn');
                if (addAddressBtn) {
                    addAddressBtn.addEventListener('click', openAddAddressModal);
                }

                // Load addresses on page load
                loadAddress();

                // Load countries on page load
                loadCountries();

                // Initialize tab functionality
                initializeTabs();

                // Load coin data when coin history tab is clicked or when page loads
                const coinHistoryTab = document.querySelector('a[href="#coin-histroy"]');
                if (coinHistoryTab) {
                    coinHistoryTab.addEventListener('click', function () {
                        console.log('Coin history tab clicked, loading data...');
                        loadCoinData();
                    });

                    // Also load coin data if the coin history tab is already active on page load
                    if (coinHistoryTab.classList.contains('active')) {
                        console.log('Coin history tab is active on page load, loading data...');
                        loadCoinData();
                    }
                }

                // Load coin data on page load if coin history tab is visible
                setTimeout(() => {
                    const coinHistoryPane = document.getElementById('coin-histroy');
                    if (coinHistoryPane && coinHistoryPane.classList.contains('active')) {
                        console.log('Coin history pane is active, loading data...');
                        loadCoinData();
                    }
                }, 500);

                // Test coin data loading after page load (for debugging)
                setTimeout(() => {
                    console.log('Testing coin data loading...');
                    console.log('Available functions:', typeof loadCoinData, typeof loadCoinBalance, typeof loadCoinTransactions);

                    // Test if we can call the function directly
                    if (typeof loadCoinData === 'function') {
                        console.log('loadCoinData function is available, testing...');
                        // Uncomment the line below to test automatic loading
                        // loadCoinData();
                    }
                }, 2000);

                // Test tab functionality
                setTimeout(() => {
                    console.log('Testing tab functionality...');
                    const testLinks = document.querySelectorAll('.nav-tabs .nav-link[data-toggle="tab"]');
                    console.log('Available tab links:', testLinks.length);
                    testLinks.forEach((link, index) => {
                        console.log(`Tab ${index + 1}: ${link.getAttribute('href')} - ${link.textContent.trim()}`);
                    });
                }, 1000);

                // Add country change event listener
                const countrySelect = document.getElementById('country');
                if (countrySelect) {
                    countrySelect.addEventListener('change', function () {
                        const countryId = this.value;
                        if (countryId) {
                            loadStates(countryId);
                        } else {
                            clearStates();
                        }
                    });
                }

                // Add form submission handler
                const addressForm = document.getElementById('address-form');
                if (addressForm) {
                    addressForm.addEventListener('submit', function (e) {
                        e.preventDefault();
                        handleAddressSubmit();
                    });

                    // Add real-time validation for all required fields
                    addAddressFormValidation();
                }

                // KYC Functionality
                const saveKycBtn = document.getElementById('save-kyc-btn');
                if (saveKycBtn) {
                    saveKycBtn.addEventListener('click', handleKycSave);
                }
                const verifyKycOtpBtn = document.getElementById('verify-kyc-otp-btn');
                if (verifyKycOtpBtn) {
                    verifyKycOtpBtn.addEventListener('click', handleKycOTPVerification);
                }
                // Load KYC details and states on page load
                loadKycDetails();
                loadKycStates();
                // Wire KYC document upload
                const kycFile = document.getElementById('kyc-document-file');
                if (kycFile) {
                    kycFile.addEventListener('change', handleKycDocumentUpload);
                }

                // Password Change Functionality
                const changePasswordBtn = document.getElementById('change-password-btn');
                if (changePasswordBtn) {
                    changePasswordBtn.addEventListener('click', handlePasswordChange);
                }
                const verifyPasswordOtpBtn = document.getElementById('verify-password-otp-btn');
                if (verifyPasswordOtpBtn) {
                    verifyPasswordOtpBtn.addEventListener('click', handlePasswordOTPVerification);
                }

                // Order History Filters
                const applyFiltersBtn = document.getElementById('apply-filters');
                const clearFiltersBtn = document.getElementById('clear-filters');

                if (applyFiltersBtn) {
                    applyFiltersBtn.addEventListener('click', applyOrderFilters);
                }
                if (clearFiltersBtn) {
                    clearFiltersBtn.addEventListener('click', clearOrderFilters);
                }

                // Coin History Export
                const exportCoinHistoryBtn = document.getElementById('export-coin-history-btn');
                if (exportCoinHistoryBtn) {
                    exportCoinHistoryBtn.addEventListener('click', exportCoinHistoryToExcel);
                }
            }
        });

        // Profile Update Functions
        function handleProfileUpdate() {
            const firstName = document.getElementById('firstname').value.trim();
            const lastName = document.getElementById('lastname').value.trim();
            const email = document.getElementById('email_1').value.trim();

            // Validate required fields
            if (!firstName || !lastName || !email) {
                showProfileErrorModal('Please fill in all required fields.');
                return;
            }

            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showProfileErrorModal('Please enter a valid email address.');
                return;
            }

            // Show confirmation modal
            showProfileConfirmModal();
        }

        function validateEmail() {
            const emailInput = document.getElementById('email_1');
            const email = emailInput.value.trim();
            const original = (emailInput.getAttribute('data-original-email') || '').trim();

            // Do nothing if email hasn't changed from original
            if (original && email.toLowerCase() === original.toLowerCase()) {
                return;
            }

            if (!email) return;

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                return; // Let the form validation handle invalid format
            }

            // Check if email exists
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_email',
                    email: email
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.email_exists) {
                        showEmailExistsModal();
                    }
                })
                .catch(error => {
                    console.error('Email validation error:', error);
                });
        }

        function confirmProfileUpdate() {
            const firstName = document.getElementById('firstname').value.trim();
            const lastName = document.getElementById('lastname').value.trim();
            const email = document.getElementById('email_1').value.trim();

            // Close confirmation modal
            closeProfileConfirmModal();

            // Show loading state
            const saveBtn = document.getElementById('save-profile-btn');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Updating...';
            saveBtn.disabled = true;

            // Send update request
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_profile',
                    first_name: firstName,
                    last_name: lastName,
                    email: email
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showProfileSuccessModal();
                    } else {
                        showProfileErrorModal(data.message || 'Failed to update profile. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Profile update error:', error);
                    showProfileErrorModal('An error occurred while updating your profile. Please try again.');
                })
                .finally(() => {
                    // Reset button state
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                });
        }

        // Modal Functions
        function showEmailExistsModal() {
            const modal = document.getElementById('email-exists-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeEmailExistsModal() {
            const modal = document.getElementById('email-exists-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showProfileConfirmModal() {
            const modal = document.getElementById('profile-confirm-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeProfileConfirmModal() {
            const modal = document.getElementById('profile-confirm-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showProfileSuccessModal() {
            const modal = document.getElementById('profile-success-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeProfileSuccessModal() {
            const modal = document.getElementById('profile-success-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showProfileErrorModal(message) {
            const modal = document.getElementById('profile-error-modal');
            const messageElement = document.getElementById('profile-error-message');

            if (modal && messageElement) {
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        function closeProfileErrorModal() {
            const modal = document.getElementById('profile-error-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Bank Details Functions
        function loadBankDetails() {
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_bank_details'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        // Populate form with existing bank details
                        document.getElementById('account-number').value = data.data.account_number || '';
                        document.getElementById('confirm-account-number').value = data.data.account_number || '';
                        document.getElementById('account-type').value = data.data.account_type || '';
                        document.getElementById('ifsc-code').value = data.data.ifsc_code || '';
                        document.getElementById('account-holder-name').value = data.data.account_holder_name || '';

                        // Show verification status
                        if (data.data.status === 'verified') {
                            showBankVerifiedStatus();
                        } else {
                            // Reset button to normal state for unverified or new entries
                            resetBankButton();
                        }
                    } else {
                        // No bank details exist, reset button to normal state
                        resetBankButton();
                    }
                })
                .catch(error => {
                    console.error('Error loading bank details:', error);
                    resetBankButton();
                });
        }

        function handleBankVerification() {
            const accountNumber = document.getElementById('account-number').value.trim();
            const confirmAccountNumber = document.getElementById('confirm-account-number').value.trim();
            const accountType = document.getElementById('account-type').value;
            const ifscCode = document.getElementById('ifsc-code').value.trim();
            const accountHolderName = document.getElementById('account-holder-name').value.trim();

            // Validate required fields
            if (!accountNumber || !confirmAccountNumber || !accountType || !ifscCode || !accountHolderName) {
                showBankErrorModal('Please fill in all required fields.');
                return;
            }

            // Validate account number match
            if (accountNumber !== confirmAccountNumber) {
                showBankErrorModal('Account number and confirm account number do not match. Please check and try again.');
                return;
            }

            // Validate account number format (numeric only)
            if (!/^[0-9]+$/.test(accountNumber)) {
                showBankErrorModal('Please enter a valid account number (numbers only).');
                return;
            }

            // Validate IFSC code format
            if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifscCode)) {
                showBankErrorModal('Please enter a valid IFSC code.');
                return;
            }

            // Validate account holder name (letters and periods only)
            if (!/^[A-Za-z\s.]+$/.test(accountHolderName)) {
                showBankErrorModal('Account holder name can only contain letters and periods (.).');
                return;
            }

            // Show loading state
            const verifyBtn = document.getElementById('verify-bank-btn');
            const originalText = verifyBtn.textContent;
            verifyBtn.textContent = 'Sending OTP...';
            verifyBtn.disabled = true;

            // Save bank details
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'save_bank_details',
                    account_number: accountNumber,
                    account_type: accountType,
                    ifsc_code: ifscCode,
                    account_holder_name: accountHolderName
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show OTP section
                        document.getElementById('otp-section').style.display = 'block';

                        // Show OTP message
                        document.getElementById('otp-message').style.display = 'block';

                        // Show OTP from server response
                        if (data.otp) {
                            showOTPToast(data.otp);
                            // Store OTP for verification (in a real system, this would be sent via SMS/Email)
                            window.currentOTP = data.otp;
                        }

                        // Show OTP sent message (don't show success modal yet)
                        // The OTP toast will show the OTP, no need for additional modal

                        // Reset button to normal state after sending OTP
                        resetBankButton();

                    } else {
                        showBankErrorModal(data.message || 'Failed to send OTP. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Bank verification error:', error);
                    showBankErrorModal('An error occurred while sending OTP. Please try again.');
                })
                .finally(() => {
                    // Reset button state
                    verifyBtn.textContent = originalText;
                    verifyBtn.disabled = false;
                });
        }

        function handleOTPVerification() {
            const otp = document.getElementById('bank-otp').value.trim();

            if (!otp) {
                showBankErrorModal('Please enter the OTP.');
                return;
            }

            if (!/^[0-9]{6}$/.test(otp)) {
                showBankErrorModal('Please enter a valid 6-digit OTP.');
                return;
            }

            // Show loading state
            const verifyOtpBtn = document.getElementById('verify-otp-btn');
            const originalText = verifyOtpBtn.textContent;
            verifyOtpBtn.textContent = 'Verifying...';
            verifyOtpBtn.disabled = true;

            // Verify OTP
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'verify_bank_otp',
                    otp: otp
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showBankSuccessModal('Bank details verified and saved successfully!');
                        // Hide OTP section
                        document.getElementById('otp-section').style.display = 'none';
                        // Hide OTP message
                        document.getElementById('otp-message').style.display = 'none';
                        // Clear OTP field
                        document.getElementById('bank-otp').value = '';
                        // Show verified status
                        showBankVerifiedStatus();
                        // Reload bank details to show updated information
                        loadBankDetails();
                    } else {
                        showBankErrorModal(data.message || 'Invalid OTP. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('OTP verification error:', error);
                    showBankErrorModal('An error occurred while verifying OTP. Please try again.');
                })
                .finally(() => {
                    // Reset button state
                    verifyOtpBtn.textContent = originalText;
                    verifyOtpBtn.disabled = false;
                });
        }

        // OTP is now generated on the server side

        function showOTPToast(otp) {
            // Create toast notification
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-key"></i>
            <span>OTP sent! Your OTP is: <strong>${otp}</strong></span>
        </div>
    `;

            // Add toast styles
            toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #336699, #1914fe);
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10002;
        animation: slideInRight 0.3s ease;
    `;

            document.body.appendChild(toast);

            // Auto remove after 10 seconds
            setTimeout(() => {
                toast.remove();
            }, 10000);
        }

        function showBankVerifiedStatus() {
            const verifyBtn = document.getElementById('verify-bank-btn');
            verifyBtn.textContent = 'Bank Details Verified ✓';
            verifyBtn.className = 'btn btn-success btn-rounded btn-sm mb-4';
            // Don't disable the button - allow multiple updates
            verifyBtn.disabled = false;
        }

        function resetBankButton() {
            const verifyBtn = document.getElementById('verify-bank-btn');
            verifyBtn.textContent = 'Verify & Update';
            verifyBtn.className = 'btn btn-dark btn-rounded btn-sm mb-4';
            verifyBtn.disabled = false;
        }

        function validateAccountNumbers() {
            const accountNumber = document.getElementById('account-number').value.trim();
            const confirmAccountNumber = document.getElementById('confirm-account-number').value.trim();
            const confirmField = document.getElementById('confirm-account-number');

            // Only validate if both fields have values
            if (accountNumber && confirmAccountNumber) {
                if (accountNumber === confirmAccountNumber) {
                    // Numbers match - show success styling
                    confirmField.style.borderColor = '#28a745';
                    confirmField.style.backgroundColor = '#f8fff9';
                    hideAccountNumberMessage();
                } else {
                    // Numbers don't match - show error styling
                    confirmField.style.borderColor = '#dc3545';
                    confirmField.style.backgroundColor = '#fff8f8';
                    showAccountNumberMessage('Account numbers do not match', 'error');
                }
            } else {
                // Reset styling if fields are empty
                confirmField.style.borderColor = '';
                confirmField.style.backgroundColor = '';
                hideAccountNumberMessage();
            }
        }

        function showAccountNumberMessage(message, type) {
            // Remove existing message if any
            hideAccountNumberMessage();

            // Create message element
            const messageDiv = document.createElement('div');
            messageDiv.id = 'account-number-message';
            messageDiv.className = `account-validation-message ${type}`;
            messageDiv.textContent = message;

            // Insert after confirm account number field
            const confirmField = document.getElementById('confirm-account-number');
            const parentDiv = confirmField.parentNode;
            parentDiv.appendChild(messageDiv);
        }

        function hideAccountNumberMessage() {
            const existingMessage = document.getElementById('account-number-message');
            if (existingMessage) {
                existingMessage.remove();
            }
        }

        // Bank Modal Functions
        function showBankSuccessModal(message = 'Bank details verified and saved successfully!', title = 'Bank Details Verified Successfully!') {
            const modal = document.getElementById('bank-success-modal');
            const titleElement = document.getElementById('bank-success-title');
            const messageElement = document.getElementById('bank-success-message');
            
            if (modal && titleElement && messageElement) {
                titleElement.textContent = title;
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        function closeBankSuccessModal() {
            const modal = document.getElementById('bank-success-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showBankErrorModal(message) {
            const modal = document.getElementById('bank-error-modal');
            const messageElement = document.getElementById('bank-error-message');

            if (modal && messageElement) {
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        // KYC Modal Functions
        function showKycSuccessModal() {
            const modal = document.getElementById('kyc-success-modal');
            if (modal) { modal.style.display = 'flex'; }
        }
        function closeKycSuccessModal() {
            const modal = document.getElementById('kyc-success-modal');
            if (modal) { modal.style.display = 'none'; }
        }
        function showKycErrorModal(message) {
            const modal = document.getElementById('kyc-error-modal');
            const msg = document.getElementById('kyc-error-message');
            if (modal && msg) { msg.textContent = message; modal.style.display = 'flex'; }
        }
        function closeKycErrorModal() {
            const modal = document.getElementById('kyc-error-modal');
            if (modal) { modal.style.display = 'none'; }
        }

        // Password Change Modal Functions
        function showPasswordSuccessModal() {
            const modal = document.getElementById('password-success-modal');
            if (modal) { modal.style.display = 'flex'; }
        }
        function closePasswordSuccessModal() {
            const modal = document.getElementById('password-success-modal');
            if (modal) { modal.style.display = 'none'; }
        }
        function showPasswordErrorModal(message) {
            const modal = document.getElementById('password-error-modal');
            const msg = document.getElementById('password-error-message');
            if (modal && msg) { msg.textContent = message; modal.style.display = 'flex'; }
        }
        function closePasswordErrorModal() {
            const modal = document.getElementById('password-error-modal');
            if (modal) { modal.style.display = 'none'; }
        }

        function closeBankErrorModal() {
            const modal = document.getElementById('bank-error-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Tab Management Functions
        function initializeTabs() {
            console.log('Initializing tabs...');

            // Get all tab links
            const tabLinks = document.querySelectorAll('.nav-tabs .nav-link[data-toggle="tab"]');
            console.log('Found tab links:', tabLinks.length);

            tabLinks.forEach((link, index) => {
                console.log(`Setting up tab ${index + 1}:`, link.getAttribute('href'));

                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    console.log('Tab clicked:', this.getAttribute('href'));

                    // Remove active class from all tab links
                    tabLinks.forEach(tabLink => {
                        tabLink.classList.remove('active');
                        tabLink.setAttribute('aria-selected', 'false');
                    });

                    // Add active class to clicked tab link
                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');

                    // Get target tab pane
                    const targetId = this.getAttribute('href').substring(1);
                    console.log('Target ID:', targetId);
                    const targetPane = document.getElementById(targetId);
                    console.log('Target pane found:', !!targetPane);

                    if (targetPane) {
                        // Hide all tab panes
                        const allPanes = document.querySelectorAll('.tab-pane');
                        allPanes.forEach(pane => {
                            pane.classList.remove('active');
                        });

                        // Show target tab pane
                        targetPane.classList.add('active');
                        console.log('Tab switched to:', targetId);

                        // Load coin data if coin history tab is activated
                        if (targetId === 'coin-histroy') {
                            console.log('Coin history tab activated, loading data...');
                            setTimeout(() => {
                                loadCoinData();
                            }, 100);
                        }
                    } else {
                        console.error('Target pane not found for ID:', targetId);
                    }
                });
            });

            console.log('Tab initialization complete');
        }

        // Coin Data Management Functions
        function loadCoinData() {
            console.log('Loading coin data...');

            // Check if user is logged in
            const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
            console.log('User logged in:', isLoggedIn);

            if (!isLoggedIn) {
                console.error('User not logged in, cannot load coin data');
                return;
            }

            // Load coin balance
            loadCoinBalance();

            // Load coin transactions
            loadCoinTransactions();
        }

        function loadCoinBalance() {
            console.log('Fetching coin balance...');

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_coin_balance'
                })
            })
                .then(response => {
                    console.log('Coin balance response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Coin balance response data:', data);
                    if (data.success) {
                        const balanceElement = document.getElementById('coin-balance');
                        if (balanceElement) {
                            balanceElement.textContent = data.balance;
                            console.log('Coin balance updated in UI:', data.balance);
                        } else {
                            console.error('Balance element not found');
                        }
                    } else {
                        console.error('Error loading coin balance:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading coin balance:', error);
                });
        }

        function loadCoinTransactions() {
            console.log('Fetching coin transactions...');

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_coin_transactions'
                })
            })
                .then(response => {
                    console.log('Coin transactions response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Coin transactions response data:', data);
                    if (data.success) {
                        // Store transactions globally for export
                        window.currentCoinTransactions = data.transactions;
                        displayCoinTransactions(data.transactions);
                        console.log('Coin transactions loaded:', data.transactions.length);
                    } else {
                        console.error('Error loading coin transactions:', data.message);
                        displayCoinTransactionsError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading coin transactions:', error);
                    displayCoinTransactionsError('Failed to load coin transactions');
                });
        }

        function displayCoinTransactions(transactions) {
            const container = document.getElementById('coin-transactions-container');
            if (!container) return;

            if (transactions.length === 0) {
                container.innerHTML = `
            <div class="text-center py-4">
                <p class="text-muted">No coin transactions found</p>
            </div>
        `;
                return;
            }

            let html = '';
            transactions.forEach(transaction => {
                const date = new Date(transaction.created_date);
                const formattedDate = date.toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const typeClass = transaction.type === 'earned' ? 'text-success' : 'text-danger';
                const typeIcon = transaction.type === 'earned' ? '+' : '-';

                html += `
            <div class="order-hi-flex">
                <div>
                    <div class="order-id-text">
                        <h4>
                            Order ID: <span>#${transaction.order_id}</span>
                        </h4>
                        <p>
                            ${formattedDate}
                        </p>
                    </div>
                </div>
                <div class="order-price">
                    <img src="<?php echo PUBLIC_ASSETS; ?>images/coin-hagidy.png"
                        style="width: 20px; height: 20px;" alt=""> 
                    <span class="${typeClass}">${typeIcon}${transaction.coins}</span>
                </div>
            </div>
        `;
            });

            container.innerHTML = html;
        }

        // Export Coin History to Excel
        function exportCoinHistoryToExcel() {
            // Get current coin transactions data
            const transactions = window.currentCoinTransactions || [];

            if (transactions.length === 0) {
                showNotification('No coin history data to export', 'warning');
                return;
            }

            // Create Excel workbook using SheetJS
            const workbook = XLSX.utils.book_new();

            // Prepare data for Excel (no Transaction Type column)
            const excelData = [
                ['Hagidy Coin History Report'],
                ['Generated on: ' + new Date().toLocaleString()],
                [''], // Empty row
                ['Order ID', 'Transaction Type', 'Coins', 'Date', 'Self Purchase/ Community Purchase']
            ];

            // Add transaction data
            transactions.forEach(transaction => {
                // Normalize Order ID (drop any leading '#')
                const orderId = String(transaction.order_id || '')
                    .trim()
                    .replace(/^#+/, '')
                    .replace(/^[^0-9]*/, '');

                // Coins as plain number (remove + sign and any non-numeric chars)
                const coins = Math.abs(Number(String(transaction.coins || 0).replace(/[^0-9.-]/g, ''))) || 0;

                // Format date as dd/mm/yyyy; handle strings like "27 September 2025 at 10:06"
                const rawDate = transaction.created_date || transaction.date || '';
                let formattedDate = '';
                if (rawDate) {
                    let s = String(rawDate);
                    if (s.includes(' at ')) s = s.split(' at ')[0];
                    const monthMap = { january: '01', february: '02', march: '03', april: '04', may: '05', june: '06', july: '07', august: '08', september: '09', october: '10', november: '11', december: '12' };
                    const m = s.match(/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})$/);
                    if (m) {
                        const dd = m[1].padStart(2, '0');
                        const mm = monthMap[m[2].toLowerCase()] || '01';
                        const yyyy = m[3];
                        formattedDate = `${dd}/${mm}/${yyyy}`;
                    } else if (/^\d{2}[\/\-]\d{2}[\/\-]\d{4}$/.test(s)) {
                        const parts = s.replace(/-/g, '/').split('/');
                        formattedDate = `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[2]}`;
                    } else {
                        const d = new Date(rawDate);
                        if (!isNaN(d.getTime())) {
                            const dd = String(d.getDate()).padStart(2, '0');
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const yyyy = String(d.getFullYear());
                            formattedDate = `${dd}/${mm}/${yyyy}`;
                        }
                    }
                }

                // Self vs Community Purchase
                const type = transaction.type === 'earned' ? 'Earned' : 'Spent';
                const isCommunity = (transaction.purchase_type === 'community') || (transaction.source === 'community') || (transaction.type === 'community');
                const description = isCommunity ? 'Community Purchase' : 'Self Purchase';

                excelData.push([
                    orderId,
                    type,
                    coins,
                    formattedDate,
                    description
                ]);
            });

            // Create worksheet
            const worksheet = XLSX.utils.aoa_to_sheet(excelData);

            // Set column widths (4 columns)
            worksheet['!cols'] = [
                { width: 15 }, // Order ID
                { width: 18 }, // Transaction Type
                { width: 12 }, // Coins
                { width: 14 }, // Date (dd/mm/yyyy)
                { width: 28 }  // Description
            ];

            // Style the header row
            const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
            for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: 3, c: col }); // Row 4 (0-indexed)
                if (!worksheet[cellAddress]) continue;
                worksheet[cellAddress].s = {
                    font: { bold: true },
                    fill: { fgColor: { rgb: "E3F2FD" } },
                    alignment: { horizontal: "center" }
                };
            }

            // Add worksheet to workbook
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Coin History');

            // Generate Excel file
            const excelBuffer = XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });

            // Create and download file
            const blob = new Blob([excelBuffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);

            link.setAttribute('href', url);
            link.setAttribute('download', `Hagidy_Coin_History_${new Date().toISOString().split('T')[0]}.xlsx`);
            link.style.visibility = 'hidden';

            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showNotification('Coin history exported to Excel successfully!', 'success');
        }


        function displayCoinTransactionsError(message) {
            const container = document.getElementById('coin-transactions-container');
            if (!container) return;

            container.innerHTML = `
        <div class="text-center py-4">
            <p class="text-danger">Error: ${message}</p>
            <button class="btn btn-primary btn-sm" onclick="loadCoinData()">Retry</button>
        </div>
    `;
        }

        // Address Management Functions
        let currentAddressId = null;

        function loadAddress() {
            fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_addresses',
                    show_all: true  // Show all addresses for my-account page
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAddress(data.addresses);
                    } else {
                        console.error('Error loading addresses:', data.message);
                        showAddressErrorModal(data.message || 'Failed to load addresses');
                    }
                })
                .catch(error => {
                    console.error('Error loading addresses:', error);
                    showAddressErrorModal('An error occurred while loading addresses');
                });
        }

        function displayAddress(addresses) {
            const container = document.getElementById('addresses-container');
            const noAddressMessage = document.getElementById('no-addresses-message');

            if (!addresses || addresses.length === 0) {
                container.innerHTML = '';
                noAddressMessage.style.display = 'block';
                return;
            }

            noAddressMessage.style.display = 'none';

            let html = '';
            addresses.forEach((address, index) => {
                const isPrimary = address.primary_address == 1;
                const planClass = isPrimary ? 'basic-plan' : 'complete-plan';
                const radioId = `address_${address.id}`;

                html += `
            <div class="col-md-6 mb-2">
                <div class="plans">
                    <label class="plan ${planClass}" for="${radioId}">
                        <input ${isPrimary ? 'checked' : ''} type="radio" id="${radioId}" name="selected_address" value="${address.id}" />
                        <div class="plan-content">
                            <div class="plan-details">
                                <span>${address.address_type}</span>
                                <p>${address.street_address}, ${address.city}, ${address.state_name}, ${address.country_name} - ${address.pin_code}</p>
                                <small>Mobile: ${address.mobile_number}</small>
                            </div>
                            <div class="d-flex">
                                <i class="fas fa-pen edit-address-btn" title="Edit" data-address-id="${address.id}" style="cursor:pointer;"></i>
                                <i class="fas fa-trash-alt delete-address-btn" title="Delete" data-address-id="${address.id}" style="cursor:pointer; margin-left:10px;"></i>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        `;
            });

            container.innerHTML = html;

            // Add event listeners to edit and delete buttons
            container.querySelectorAll('.edit-address-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const addressId = e.target.getAttribute('data-address-id');
                    editAddress(addressId);
                });
            });

            container.querySelectorAll('.delete-address-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const addressId = e.target.getAttribute('data-address-id');
                    deleteAddress(addressId);
                });
            });
        }

        function openAddAddressModal() {
            // Close any existing modals first
            closeAllAddressModals();

            currentAddressId = null;
            // Open the existing modal (assuming it exists)
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.style.display = 'block';
                // Update modal title
                const titleElement = modal.querySelector('h4');
                if (titleElement) {
                    titleElement.textContent = 'ADD ADDRESS';
                }
                // Update submit button text
                const submitBtn = modal.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.textContent = 'Add Address';
                }

                // Clear form completely first
                clearAddressForm().then(() => {
                    // Load countries if not already loaded
                    loadCountries();
                    // Re-initialize validation for the form
                    addAddressFormValidation();
                });
            }
        }

        function editAddress(addressId) {
            // Close any existing modals first
            closeAllAddressModals();

            currentAddressId = addressId;
            console.log('Editing address:', { addressId, currentAddressId });

            // Get address details
            fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_address',
                    address_id: addressId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Open modal and populate form
                        const modal = document.getElementById('editModal');
                        if (modal) {
                            modal.style.display = 'block';
                            // Update modal title
                            const titleElement = modal.querySelector('h4');
                            if (titleElement) {
                                titleElement.textContent = 'UPDATE ADDRESS';
                            }
                            // Update submit button text
                            const submitBtn = modal.querySelector('button[type="submit"]');
                            if (submitBtn) {
                                submitBtn.textContent = 'Update Address';
                            }
                            // Clear form first, then load countries and populate
                            clearAddressForm(false).then(() => {
                                // Load countries first, then populate form
                                loadCountries().then(() => {
                                    populateAddressForm(data.address);
                                    // Re-initialize validation for the form
                                    addAddressFormValidation();
                                });
                            });
                        }
                    } else {
                        showAddressErrorModal(data.message || 'Failed to load address details');
                    }
                })
                .catch(error => {
                    console.error('Error loading address:', error);
                    showAddressErrorModal('An error occurred while loading address details');
                });
        }

        function deleteAddress(addressId) {
            currentAddressId = addressId;
            showAddressDeleteModal();
        }

        function confirmDeleteAddress() {
            if (!currentAddressId) return;

            fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete_address',
                    address_id: currentAddressId
                })
            })
                .then(response => response.json())
                .then(data => {
                    closeAddressDeleteModal();
                    if (data.success) {
                        showAddressSuccessModal('Address deleted successfully!');
                        loadAddress(); // Reload addresses
                    } else {
                        showAddressErrorModal(data.message || 'Failed to delete address');
                    }
                })
                .catch(error => {
                    console.error('Error deleting address:', error);
                    closeAddressDeleteModal();
                    showAddressErrorModal('An error occurred while deleting address');
                });
        }

        function clearAddressForm(resetAddressId = true) {
            // Clear all form fields manually
            const fields = {
                'addressType': '',
                'country': '',
                'state': '',
                'streetAddress': '',
                'streetAddress2': '', // Clear the optional second address line
                'town': '',
                'pincode': '',
                'mobile': '',
                'email': '',
                'vehicle1': false
            };

            Object.keys(fields).forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (field) {
                    if (field.type === 'checkbox') {
                        field.checked = fields[fieldName];
                    } else {
                        field.value = fields[fieldName];
                    }
                }
            });

            // Clear states dropdown
            clearStates();

            // Clear all validation errors and remove all validation classes
            const requiredFields = ['addressType', 'country', 'state', 'streetAddress', 'town', 'pincode', 'mobile', 'email'];
            requiredFields.forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) {
                    element.classList.remove('validation-error', 'validation-success');
                    const existingError = element.parentNode.querySelector('.field-error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            });

            // Only reset current address ID if explicitly requested (for new addresses)
            if (resetAddressId) {
            currentAddressId = null;
            }

            // Force a small delay to ensure DOM is updated
            return new Promise(resolve => {
                setTimeout(resolve, 50);
            });
        }

        // KYC Functions
        // Safe fallbacks for OTP generation and toast display used by KYC flow
        if (typeof window.generateOTP !== 'function') {
            function generateOTP() {
                try {
                    // Use cryptographically strong random if available
                    const array = new Uint32Array(1);
                    if (window.crypto && window.crypto.getRandomValues) {
                        window.crypto.getRandomValues(array);
                        return String(array[0] % 1000000).padStart(6, '0');
                    }
                } catch (e) { }
                return String(Math.floor(Math.random() * 1000000)).padStart(6, '0');
            }
        }

        if (typeof window.showOTPToast !== 'function') {
            function showOTPToast(message) {
                try {
                    // Remove any existing toast
                    const old = document.getElementById('kyc-otp-toast');
                    if (old && old.parentNode) old.parentNode.removeChild(old);
                    const toast = document.createElement('div');
                    toast.id = 'kyc-otp-toast';
                    toast.textContent = 'Your OTP is: ' + message;
                    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;background:linear-gradient(135deg,#2ecc71,#27ae60);color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);font-size:14px;';
                    document.body.appendChild(toast);
                    setTimeout(() => { if (toast && toast.parentNode) toast.parentNode.removeChild(toast); }, 4000);
                } catch (e) {
                    // As a last resort, alert
                    alert('OTP: ' + message);
                }
            }
        }

        function loadKycDetails() {
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_kyc_details' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const k = data.data;
                        setValue('kyc-firstname', k.first_name || '');
                        setValue('kyc-lastname', k.last_name || '');
                        setValue('dob', k.dob || '');
                        setSelectCaseInsensitive('gender', k.gender || '');
                        setSelect('kyc-city', k.city || '');
                        setValue('pincode-main', k.pincode || '');
                        setValue('kyc-address', k.address || '');
                        setValue('pan-number', k.pan_number || '');
                        setValue('document-name', k.document_name || '');
                        // Update status labels
                        updateKycStatusLabels(k.status || 'pending');
                        // Gate editing if status is not rejected; allow Edit via button
                        if (k.status && k.status.toLowerCase() !== 'rejected') {
                            toggleKycEditable(false);
                            // Show preview if we have a stored document path
                            if (k.document) {
                                const prev = document.getElementById('kyc-pan-preview');
                                const img = document.getElementById('kyc-pan-preview-img');
                                const upload = document.getElementById('kyc-upload-section');
                                if (img && prev && upload) {
                                    // Fix image path - ensure it's a complete URL
                                    const imagePath = k.document.startsWith('http') ? k.document : USER_BASEURL + k.document;
                                    img.src = imagePath;
                                    prev.style.display = '';
                                    upload.style.display = 'none';
                                    
                                    // Debug: Log image path for troubleshooting
                                    console.log('Loading KYC image:', imagePath);
                                }
                            } else {
                                const upload = document.getElementById('kyc-upload-section');
                                if (upload) upload.style.display = 'none';
                            }
                            // Show Edit button to enable updates
                            const editBtn = document.getElementById('edit-kyc-btn');
                            if (editBtn) {
                                editBtn.style.display = '';
                                editBtn.onclick = function () { toggleKycEditable(true); };
                            }
                        } else {
                            toggleKycEditable(true);
                            // Allow upload and hide preview on rejected
                            const prev = document.getElementById('kyc-pan-preview');
                            const upload = document.getElementById('kyc-upload-section');
                            if (prev) prev.style.display = 'none';
                            if (upload) upload.style.display = '';
                            const editBtn = document.getElementById('edit-kyc-btn');
                            if (editBtn) { editBtn.style.display = 'none'; }
                        }
                    }
                })
                .catch(() => { });
        }

        function setValue(id, val) { const el = document.getElementById(id); if (el) el.value = val; }
        function setSelect(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

        function setSelectCaseInsensitive(id, val) {
            const el = document.getElementById(id);
            if (!el) return;
            const want = String(val).toLowerCase();
            let matched = false;
            Array.from(el.options).forEach(opt => {
                if (String(opt.value).toLowerCase() === want || String(opt.textContent).toLowerCase() === want) {
                    opt.selected = true;
                    matched = true;
                }
            });
            if (!matched) { el.selectedIndex = 0; }
        }

        function updateKycStatusLabels(status) {
            const s = String(status).toLowerCase();
            const map = {
                pending: 'kyc-status-pending',
                approved: 'kyc-status-approved',
                rejected: 'kyc-status-rejected'
            };
            ['kyc-status-pending', 'kyc-status-approved', 'kyc-status-rejected'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });
            const showId = map[s] || 'kyc-status-pending';
            const showEl = document.getElementById(showId);
            if (showEl) showEl.style.display = '';
        }

        function toggleKycEditable(editable) {
            const ids = ['kyc-firstname', 'kyc-lastname', 'dob', 'gender', 'kyc-city', 'pincode-main', 'kyc-address', 'pan-number', 'document-name'];
            ids.forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.disabled = !editable; }
            });
            const saveBtn = document.getElementById('save-kyc-btn');
            if (saveBtn) { saveBtn.style.display = editable ? '' : 'none'; }
            const uploadSection = document.getElementById('kyc-upload-section');
            const previewSection = document.getElementById('kyc-pan-preview');
            if (uploadSection && previewSection) {
                if (editable) {
                    uploadSection.style.display = '';
                }
            }
            const editBtn = document.getElementById('edit-kyc-btn');
            if (editBtn) { editBtn.style.display = editable ? 'none' : ''; }
        }

        function loadKycStates() {
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_states_master' })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const sel = document.getElementById('kyc-city');
                        if (sel) {
                            sel.innerHTML = '<option value="" disabled selected>Select City</option>' +
                                data.states.map(s => `<option value="${s.name}">${s.name}</option>`).join('');
                        }
                    }
                })
                .catch(() => { });
        }

        function handleKycSave() {
            const firstName = (document.getElementById('kyc-firstname') || {}).value?.trim() || '';
            const lastName = (document.getElementById('kyc-lastname') || {}).value?.trim() || '';
            const dob = (document.getElementById('dob') || {}).value || '';
            const gender = (document.getElementById('gender') || {}).value || '';
            const city = (document.getElementById('kyc-city') || {}).value || '';
            const pincode = (document.getElementById('pincode-main') || {}).value?.trim() || '';
            const address = (document.getElementById('kyc-address') || {}).value?.trim() || '';
            const pan = (document.getElementById('pan-number') || {}).value?.trim() || '';
            const docName = (document.getElementById('document-name') || {}).value?.trim() || '';

            // Check for missing required fields and show specific field names
            const missingFields = [];
            if (!firstName) missingFields.push('First Name');
            if (!lastName) missingFields.push('Last Name');
            if (!dob) missingFields.push('Date of Birth');
            if (!gender) missingFields.push('Gender');
            if (!city) missingFields.push('City');
            if (!pincode) missingFields.push('Pincode');
            if (!address) missingFields.push('Address');
            if (!pan) missingFields.push('PAN Number');
            if (!docName) missingFields.push('Name as per Document');

            if (missingFields.length > 0) {
                const fieldList = missingFields.join(', ');
                showKycErrorModal(`Please fill in the following required fields: ${fieldList}`);
                return;
            }
            if (!/^[0-9]{6}$/.test(pincode)) { showKycErrorModal('Please enter a valid 6-digit pincode.'); return; }
            if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.toUpperCase())) { showKycErrorModal('Please enter a valid PAN number.'); return; }

            // Require KYC document image uploaded (preview should be visible with a valid src)
            const previewImg = document.getElementById('kyc-pan-preview-img');
            const hasImage = previewImg && typeof previewImg.src === 'string' && previewImg.src.trim() !== '';
            if (!hasImage) {
                showKycErrorModal('Please upload a KYC document image before submitting.');
                return;
            }

            const btn = document.getElementById('save-kyc-btn');
            const original = btn.textContent; btn.textContent = 'Saving...'; btn.disabled = true;

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_kyc_details',
                    first_name: firstName,
                    last_name: lastName,
                    dob: dob,
                    gender: gender,
                    city: city,
                    pincode: pincode,
                    address: address,
                    pan_number: pan.toUpperCase(),
                    document_name: docName
                })
            })
                .then(async r => {
                    try { return await r.json(); } catch (e) {
                        const txt = await r.text();
                        throw new Error(txt || '');
                    }
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('kyc-otp-section').style.display = 'block';
                        document.getElementById('kyc-otp-message').style.display = 'block';
                        const otp = generateOTP();
                        showOTPToast(otp);
                        // Store OTP in backend
                        fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'store_kyc_otp', otp: otp })
                        }).catch(() => { });
                    } else {
                        showKycErrorModal(data.message || 'Failed to save KYC details.');
                    }
                })
                .catch(err => {
                    const msg = (err && err.message) ? err.message : 'An error occurred while saving KYC details.';
                    showKycErrorModal(msg);
                })
                .finally(() => { btn.textContent = original; btn.disabled = false; });
        }

        function handleKycOTPVerification() {
            const otp = (document.getElementById('kyc-otp') || {}).value?.trim() || '';
            if (!otp) {
                showKycErrorModal('Please fill in the following required field: OTP');
                return;
            }
            if (!/^[0-9]{6}$/.test(otp)) {
                showKycErrorModal('Please enter a valid 6-digit OTP.');
                return;
            }

            const btn = document.getElementById('verify-kyc-otp-btn');
            const original = btn.textContent; btn.textContent = 'Verifying...'; btn.disabled = true;

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'verify_kyc_otp', otp: otp })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showKycSuccessModal();
                        document.getElementById('kyc-otp-section').style.display = 'none';
                        document.getElementById('kyc-otp-message').style.display = 'none';
                        const otpField = document.getElementById('kyc-otp'); if (otpField) otpField.value = '';
                    } else {
                        showKycErrorModal(data.message || 'Invalid OTP. Please try again.');
                    }
                })
                .catch(() => showKycErrorModal('An error occurred while verifying OTP.'))
                .finally(() => { btn.textContent = original; btn.disabled = false; });
        }

        function handleKycDocumentUpload(e) {
            const fileInput = e.target;
            if (!fileInput.files || !fileInput.files[0]) return;
            const file = fileInput.files[0];
            const formData = new FormData();
            formData.append('action', 'upload_kyc_document');
            formData.append('document', file);

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const prev = document.getElementById('kyc-pan-preview');
                        const img = document.getElementById('kyc-pan-preview-img');
                        const upload = document.getElementById('kyc-upload-section');
                        if (img && prev && upload) {
                            // Fix image path construction
                            const imagePath = data.path.startsWith('http') ? data.path : USER_BASEURL + data.path;
                            img.src = imagePath;
                            prev.style.display = '';
                            upload.style.display = 'none';
                            
                            // Debug: Log image path for troubleshooting
                            console.log('Uploaded KYC image path:', imagePath);
                        }
                        // showOTPToast('Document uploaded');
                    } else {
                        showKycErrorModal(data.message || 'Failed to upload document');
                    }
                })
                .catch(() => showKycErrorModal('An error occurred while uploading document'));
        }

        // Password Change Functions
        function handlePasswordChange() {
            const currentPassword = (document.getElementById('current-password') || {}).value?.trim() || '';
            const newPassword = (document.getElementById('new-password') || {}).value?.trim() || '';
            const confirmPassword = (document.getElementById('confirm-password') || {}).value?.trim() || '';

            if (!currentPassword || !newPassword || !confirmPassword) {
                showPasswordErrorModal('Please fill in all required fields.');
                return;
            }
            if (newPassword !== confirmPassword) {
                showPasswordErrorModal('New password and confirm password do not match.');
                return;
            }
            if (newPassword.length < 6) {
                showPasswordErrorModal('New password must be at least 6 characters long.');
                return;
            }

            const btn = document.getElementById('change-password-btn');
            const original = btn.textContent; btn.textContent = 'Sending OTP...'; btn.disabled = true;

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'change_password',
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('password-otp-section').style.display = 'block';
                        document.getElementById('password-otp-message').style.display = 'block';
                        showOTPToast(data.otp);
                    } else {
                        showPasswordErrorModal(data.message || 'Failed to initiate password change.');
                    }
                })
                .catch(() => showPasswordErrorModal('An error occurred while changing password.'))
                .finally(() => { btn.textContent = original; btn.disabled = false; });
        }

        function handlePasswordOTPVerification() {
            const otp = (document.getElementById('password-otp') || {}).value?.trim() || '';
            if (!otp) { showPasswordErrorModal('Please enter the OTP.'); return; }
            if (!/^[0-9]{6}$/.test(otp)) { showPasswordErrorModal('Please enter a valid 6-digit OTP.'); return; }

            const btn = document.getElementById('verify-password-otp-btn');
            const original = btn.textContent; btn.textContent = 'Verifying...'; btn.disabled = true;

            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'verify_password_otp', otp: otp })
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showPasswordSuccessModal();
                        document.getElementById('password-otp-section').style.display = 'none';
                        document.getElementById('password-otp-message').style.display = 'none';
                        // Clear form
                        ['current-password', 'new-password', 'confirm-password', 'password-otp'].forEach(id => {
                            const el = document.getElementById(id); if (el) el.value = '';
                        });
                    } else {
                        showPasswordErrorModal(data.message || 'Invalid OTP. Please try again.');
                    }
                })
                .catch(() => showPasswordErrorModal('An error occurred while verifying OTP.'))
                .finally(() => { btn.textContent = original; btn.disabled = false; });
        }

        function populateAddressForm(address) {
            // Populate form fields with address data using correct field IDs
            const fields = {
                'addressType': address.address_type,
                'streetAddress': address.street_address,
                'streetAddress2': address.street_address2,
                'town': address.city,
                'pincode': address.pin_code,
                'mobile': address.mobile_number,
                'email': address.email_address,
                'vehicle1': address.primary_address
            };

            // Populate basic fields by ID
            Object.keys(fields).forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    if (field.type === 'radio' || field.type === 'checkbox') {
                        field.checked = fields[fieldId] == 1;
                    } else {
                        field.value = fields[fieldId] || '';
                    }
                }
            });

            // Handle country selection
            const countrySelect = document.getElementById('country');
            if (countrySelect && address.country_id) {
                countrySelect.value = address.country_id;
                // Load states for the selected country
                loadStates(address.country_id).then(() => {
                    // Set state after states are loaded
                    const stateSelect = document.getElementById('state');
                    if (stateSelect && address.state_id) {
                        stateSelect.value = address.state_id;
                    }
                });
            }
        }

        // Address Modal Functions
        function showAddressDeleteModal() {
            const modal = document.getElementById('address-delete-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeAddressDeleteModal() {
            const modal = document.getElementById('address-delete-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showAddressSuccessModal(message) {
            const modal = document.getElementById('address-success-modal');
            const messageElement = document.getElementById('address-success-message');

            if (modal && messageElement) {
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        function closeAddressSuccessModal() {
            const modal = document.getElementById('address-success-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showAddressErrorModal(message) {
            const modal = document.getElementById('address-error-modal');
            const messageElement = document.getElementById('address-error-message');

            if (modal && messageElement) {
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        function closeAddressErrorModal() {
            const modal = document.getElementById('address-error-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function closeAllAddressModals() {
            closeAddressErrorModal();
            closeAddressSuccessModal();
            closeAddressDeleteModal();
        }

        function closeAddressFormModal() {
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.style.display = 'none';
                // Clear form when closing modal and reset address ID
                clearAddressForm(true);
            }
        }

        // Logout Modal Functions
        function showLogoutConfirmationModal() {
            const modal = document.getElementById('logout-confirmation-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeLogoutConfirmationModal() {
            const modal = document.getElementById('logout-confirmation-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showLogoutSuccessModal() {
            const modal = document.getElementById('logout-success-modal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeLogoutSuccessModal() {
            const modal = document.getElementById('logout-success-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function showLogoutErrorModal(message) {
            const modal = document.getElementById('logout-error-modal');
            const messageElement = document.getElementById('logout-error-message');

            if (modal && messageElement) {
                messageElement.textContent = message;
                modal.style.display = 'flex';
            }
        }

        function closeLogoutErrorModal() {
            const modal = document.getElementById('logout-error-modal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Logout Functions
        function confirmLogout() {
            closeLogoutConfirmationModal();

            // Send logout request
            fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'logout'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear localStorage
                        localStorage.clear();

                        // Clear sessionStorage
                        sessionStorage.clear();

                        // Clear all cookies
                        document.cookie.split(";").forEach(function (c) {
                            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                        });

                        // Show success modal
                        showLogoutSuccessModal();

                        // Redirect to login page after 2 seconds
                        setTimeout(() => {
                            // window.location.href = 'login.php';
                            window.location.href = '<?php echo USER_BASEURL; ?>';
                        }, 500);
                    } else {
                        // Show error modal
                        showLogoutErrorModal('Error logging out: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error logging out:', error);
                    // Force redirect even if API fails
                    localStorage.clear();
                    sessionStorage.clear();
                    showLogoutSuccessModal();
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                });
        }

        // Country and State Loading Functions
        function loadCountries() {
            return fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_countries'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateCountryDropdown(data.countries);
                        return data.countries;
                    } else {
                        console.error('Error loading countries:', data.message);
                        return [];
                    }
                })
                .catch(error => {
                    console.error('Error loading countries:', error);
                    return [];
                });
        }

        function populateCountryDropdown(countries) {
            const countrySelect = document.getElementById('country');

            if (!countrySelect) {
                console.error('Country select element not found!');
                return;
            }

            // Clear existing options except the first one
            countrySelect.innerHTML = '<option value="">Select Country/Region</option>';

            countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                if (country.id == 99) {
                    option.selected = true;
                    loadStates(country.id);
                }
                countrySelect.appendChild(option);
            });
        }

        function loadStates(countryId) {
            return fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'get_states',
                    country_id: countryId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateStateDropdown(data.states);
                        return data.states;
                    } else {
                        console.error('Error loading states:', data.message);
                        clearStates();
                        return [];
                    }
                })
                .catch(error => {
                    console.error('Error loading states:', error);
                    clearStates();
                    return [];
                });
        }

        function populateStateDropdown(states) {
            const stateSelect = document.getElementById('state');

            if (!stateSelect) {
                console.error('State select element not found!');
                return;
            }

            // Clear existing options except the first one
            stateSelect.innerHTML = '<option value="">Select State</option>';

            states.forEach(state => {
                const option = document.createElement('option');
                option.value = state.id;
                option.textContent = state.name;

                stateSelect.appendChild(option);
            });
        }

        function clearStates() {
            const stateSelect = document.getElementById('state');
            if (stateSelect) {
                stateSelect.innerHTML = '<option value="">Select State</option>';
            }
        }

        // Address Form Submission
        // Address Form Validation Functions
        function addAddressFormValidation() {
            const requiredFields = [
                { id: 'addressType', name: 'Address Type' },
                { id: 'state', name: 'State' },
                { id: 'streetAddress', name: 'Street Address' },
                { id: 'town', name: 'Town/City' },
                { id: 'pincode', name: 'PIN Code' },
                { id: 'mobile', name: 'Mobile Number' }
            ];

            // Add validation to each required field
            requiredFields.forEach(field => {
                const element = document.getElementById(field.id);
                if (element) {
                    // Add event listeners for real-time validation
                    element.addEventListener('blur', () => validateField(field.id, field.name));
                    element.addEventListener('input', () => {
                        const value = element.value.trim();
                        if (value) {
                            clearFieldError(field.id);
                        } else {
                            // Remove all validation classes for empty fields
                            element.classList.remove('validation-error', 'validation-success');
                            const existingError = element.parentNode.querySelector('.field-error-message');
                            if (existingError) {
                                existingError.remove();
                            }
                        }
                    });
                }
            });

            // Special validation for mobile number
            const mobileField = document.getElementById('mobile');
            if (mobileField) {
                mobileField.addEventListener('input', () => {
                    const value = mobileField.value.trim();
                    if (value) {
                        clearFieldError('mobile');
                        if (value.length < 10) {
                            showFieldError('mobile', 'Mobile number must have at least 10 digits');
                        }
                    } else {
                        // Remove all validation classes for empty fields
                        mobileField.classList.remove('validation-error', 'validation-success');
                        const existingError = mobileField.parentNode.querySelector('.field-error-message');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                });
            }

            // Email validation (optional field)
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.addEventListener('blur', () => {
                    const email = emailField.value.trim();
                    if (email) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            showFieldError('email', 'Please enter a valid email address');
                        } else {
                            clearFieldError('email');
                        }
                    } else {
                        // Remove all validation classes for empty fields
                        emailField.classList.remove('validation-error', 'validation-success');
                        const existingError = emailField.parentNode.querySelector('.field-error-message');
                        if (existingError) {
                            existingError.remove();
                        }
                    }
                });
            }
        }

        function validateField(fieldId, fieldName) {
            const element = document.getElementById(fieldId);
            if (!element) return false;

            const value = element.value.trim();

            if (!value) {
                showFieldError(fieldId, `${fieldName} is required`);
                return false;
            }

            // Special validation for mobile number
            if (fieldId === 'mobile') {
                if (value.length < 10) {
                    showFieldError(fieldId, 'Mobile number must have at least 10 digits');
                    return false;
                }
                if (!/^\d+$/.test(value)) {
                    showFieldError(fieldId, 'Mobile number must contain only digits');
                    return false;
                }
            }

            // Special validation for PIN code
            if (fieldId === 'pincode') {
                if (!/^\d+$/.test(value)) {
                    showFieldError(fieldId, 'PIN code must contain only digits');
                    return false;
                }
            }

            // Only clear error if field has content and is valid
            if (value.trim()) {
                clearFieldError(fieldId);
                return true;
            }
            return false;
        }

        function showFieldError(fieldId, message) {
            const element = document.getElementById(fieldId);
            if (!element) return;

            // Add error class
            element.classList.add('validation-error');
            element.classList.remove('validation-success');

            // Remove existing error message
            const existingError = element.parentNode.querySelector('.field-error-message');
            if (existingError) {
                existingError.remove();
            }

            // Add error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error-message';
            errorDiv.style.color = '#dc3545';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '5px';
            errorDiv.textContent = message;

            element.parentNode.appendChild(errorDiv);
        }

        function clearFieldError(fieldId) {
            const element = document.getElementById(fieldId);
            if (!element) return;

            element.classList.remove('validation-error');
            // Only add success class if field has content
            if (element.value.trim()) {
                element.classList.add('validation-success');
            } else {
                element.classList.remove('validation-success');
            }

            const existingError = element.parentNode.querySelector('.field-error-message');
            if (existingError) {
                existingError.remove();
            }
        }

        function validateAllFields() {
            const requiredFields = [
                { id: 'addressType', name: 'Address Type' },
                { id: 'state', name: 'State' },
                { id: 'streetAddress', name: 'Street Address' },
                { id: 'town', name: 'Town/City' },
                { id: 'pincode', name: 'PIN Code' },
                { id: 'mobile', name: 'Mobile Number' }
            ];

            let isValid = true;
            let firstInvalidField = null;

            requiredFields.forEach(field => {
                const fieldValid = validateField(field.id, field.name);
                if (!fieldValid && !firstInvalidField) {
                    firstInvalidField = field.name;
                }
                if (!fieldValid) {
                    isValid = false;
                }
            });

            // Validate email if provided
            const emailField = document.getElementById('email');
            if (emailField && emailField.value.trim()) {
                const email = emailField.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showFieldError('email', 'Please enter a valid email address');
                    isValid = false;
                }
            }

            return { isValid, firstInvalidField };
        }

        function handleAddressSubmit() {
            // Close any existing address modals first
            closeAllAddressModals();

            // Validate all fields first
            const validation = validateAllFields();

            if (!validation.isValid) {
                // Focus on first invalid field
                const firstInvalidFieldId = validation.firstInvalidField?.toLowerCase().replace(/[^a-z]/g, '');
                if (firstInvalidFieldId) {
                    const fieldMap = {
                        'addresstype': 'addressType',
                        'state': 'state',
                        'streetaddress': 'streetAddress',
                        'towncity': 'town',
                        'pincode': 'pincode',
                        'mobilenumber': 'mobile'
                    };

                    const fieldId = fieldMap[firstInvalidFieldId] || 'addressType';
                    const element = document.getElementById(fieldId);
                    if (element) {
                        element.focus();
                    }
                }

                showAddressErrorModal('Please fill all required fields correctly');
                return;
            }

            // Get form data
            const formData = {
                address_type: document.getElementById('addressType').value,
                country_id: document.getElementById('country').value || null, // Make country optional
                state_id: document.getElementById('state').value,
                street_address: document.getElementById('streetAddress').value,
                street_address2: document.getElementById('streetAddress2').value, // Include second address line
                city: document.getElementById('town').value,
                pin_code: document.getElementById('pincode').value,
                mobile_number: document.getElementById('mobile').value,
                email_address: document.getElementById('email').value,
                is_primary: document.getElementById('vehicle1').checked ? 1 : 0
            };

            // Determine if this is an add or update operation
            const action = currentAddressId ? 'update_address' : 'add_address';
            console.log('Address form submission:', { action, currentAddressId, formData });
            if (currentAddressId) {
                formData.address_id = currentAddressId;
            }

            // Show loading state
            const submitBtn = document.querySelector('#address-form button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = currentAddressId ? 'Updating...' : 'Adding...';
            submitBtn.disabled = true;

            // Submit the form
            fetch('<?php echo USER_BASEURL; ?>app/handlers/address_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    ...formData
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close any existing error modals first
                        closeAddressErrorModal();

                        // Close address form modal
                        const modal = document.getElementById('editModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }

                        // Show success message
                        const message = currentAddressId ? 'Address updated successfully!' : 'Address added successfully!';
                        showAddressSuccessModal(message);

                        // Reload addresses
                        loadAddress();

                        // Reset current address ID
                        currentAddressId = null;
                    } else {
                        // Close any existing success modals first
                        closeAddressSuccessModal();
                        showAddressErrorModal(data.message || 'Failed to save address');
                    }
                })
                .catch(error => {
                    console.error('Address submission error:', error);
                    // Close any existing success modals first
                    closeAddressSuccessModal();
                    showAddressErrorModal('An error occurred while saving the address');
                })
                .finally(() => {
                    // Reset button state
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
        }

        // Order History Filter Functions
        function applyOrderFilters() {
            const monthFilter = document.getElementById('month-filter').value;
            const fromDate = document.getElementById('from-date-filter').value;
            const toDate = document.getElementById('to-date-filter').value;

            // Get all order rows
            const orderRows = document.querySelectorAll('#account-orders tbody tr');

            orderRows.forEach(row => {
                let showRow = true;

                // Get order date from the row
                const dateCell = row.querySelector('.order-date1');
                if (!dateCell) return;

                const orderDateText = dateCell.textContent.trim();
                const orderDate = new Date(orderDateText);

                // Check month filter
                if (monthFilter) {
                    const orderMonth = String(orderDate.getMonth() + 1).padStart(2, '0');
                    if (orderMonth !== monthFilter) {
                        showRow = false;
                    }
                }

                // Check date range filter
                if (fromDate || toDate) {
                    const orderDateStr = orderDate.toISOString().split('T')[0];

                    if (fromDate && orderDateStr < fromDate) {
                        showRow = false;
                    }
                    if (toDate && orderDateStr > toDate) {
                        showRow = false;
                    }
                }

                // Show or hide row
                if (showRow) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide "No orders found" message
            const visibleRows = Array.from(orderRows).filter(row => row.style.display !== 'none');
            const noOrdersRow = document.querySelector('#account-orders tbody tr td[colspan="7"]');

            if (visibleRows.length === 0 && noOrdersRow) {
                noOrdersRow.parentElement.style.display = '';
            } else if (noOrdersRow) {
                noOrdersRow.parentElement.style.display = 'none';
            }

            // Show filter results count
            showFilterResults(visibleRows.length);
        }

        function clearOrderFilters() {
            // Reset filter inputs
            document.getElementById('month-filter').value = '';
            document.getElementById('from-date-filter').value = '';
            document.getElementById('to-date-filter').value = '';

            // Show all rows
            const orderRows = document.querySelectorAll('#account-orders tbody tr');
            orderRows.forEach(row => {
                row.style.display = '';
            });

            // Hide filter results
            hideFilterResults();
        }

        function showFilterResults(count) {
            // Remove existing results message
            const existingResults = document.querySelector('.filter-results');
            if (existingResults) {
                existingResults.remove();
            }

            // Create results message
            const resultsDiv = document.createElement('div');
            resultsDiv.className = 'filter-results';
            resultsDiv.style.cssText = 'background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #2196f3;';
            resultsDiv.innerHTML = `<i class="fa-solid fa-info-circle"></i> Showing ${count} order(s) matching your filters.`;

            // Insert after filters
            const filtersDiv = document.querySelector('.order-filters');
            if (filtersDiv) {
                filtersDiv.insertAdjacentElement('afterend', resultsDiv);
            }
        }

        function hideFilterResults() {
            const existingResults = document.querySelector('.filter-results');
            if (existingResults) {
                existingResults.remove();
            }
        }

        // Auto-open tab based on URL hash
        document.addEventListener('DOMContentLoaded', function () {
            // Handle hash navigation first
            handleHashNavigation();

            // Add immediate click handler for referral links
            setTimeout(() => {
                const referralLinks = document.querySelectorAll('a[href*="#refer-friend"]');
                console.log('Found referral links:', referralLinks.length);
                referralLinks.forEach(link => {
                    console.log('Adding click handler to:', link);
                    link.addEventListener('click', function (e) {
                        if (window.location.pathname.includes('my-account.php')) {
                            e.preventDefault();
                            console.log('Referral link clicked, switching tab...');
                            switchToReferFriendTab();
                        }
                    });
                });
            }, 500);

            // Copy referral link to clipboard
            const copyBtn = document.querySelector('.brn-link-copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    // Get the referral link text (remove the copy icon)
                    const linkText = this.textContent.replace(/\s*<i.*<\/i>\s*/, '').trim();

                    // Use Clipboard API if available
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(linkText)
                            .then(() => {
                                showCopySuccessNotification();
                            })
                            .catch(() => {
                                // Fallback method
                                fallbackCopyToClipboard(linkText);
                            });
                    } else {
                        // Fallback method
                        fallbackCopyToClipboard(linkText);
                    }
                });
            }

            // Export Order History to Excel with/without filters
            const exportBtn = document.getElementById('export-orders-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    // Read current filters
                    const month = (document.getElementById('month-filter') || {}).value || '';
                    const fromDate = (document.getElementById('from-date-filter') || {}).value || '';
                    const toDate = (document.getElementById('to-date-filter') || {}).value || '';

                    // Create and submit a temporary form to trigger file download
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php echo USER_BASEURL; ?>app/Helpers/export_orders_excel.php';
                    form.style.display = 'none';

                    const addField = (name, value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    };

                    addField('month', month);
                    addField('from_date', fromDate);
                    addField('to_date', toDate);

                    document.body.appendChild(form);
                    form.submit();
                    setTimeout(() => { if (form.parentNode) form.parentNode.removeChild(form); }, 1000);
                });
            }

            // Handle WhatsApp sharing button
            const whatsappBtn = document.querySelector('.btn-whatsapp');
            if (whatsappBtn) {
                whatsappBtn.addEventListener('click', function () {
                    shareToWhatsApp();
                });
            }

            // Handle More sharing button
            const moreBtn = document.querySelector('.btn-share-acc');
            if (moreBtn) {
                moreBtn.addEventListener('click', function () {
                    showMoreSharingOptions();
                });
            }

            // Fallback copy method
            function fallbackCopyToClipboard(text) {
                const tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                tempInput.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    showCopySuccessNotification();
                } catch (e) {
                    console.error('Copy failed:', e);
                }
                document.body.removeChild(tempInput);
            }

            // Show copy success notification
            function showCopySuccessNotification() {
                // Create notification element
                const notification = document.createElement('div');
                notification.className = 'copy-notification';
                notification.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                font-family: 'Poppins', sans-serif;
                font-weight: 500;
                animation: slideInRight 0.3s ease;
                cursor: pointer;
            ">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                Referral link copied to clipboard!
            </div>
        `;

                document.body.appendChild(notification);

                // Remove notification after 3 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.style.animation = 'slideOutRight 0.3s ease';
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.parentNode.removeChild(notification);
                            }
                        }, 300);
                    }
                }, 3000);

                // Add click to dismiss
                notification.addEventListener('click', () => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                });
            }

            // Get referral link and message
            function getReferralData() {
                const copyBtn = document.querySelector('.brn-link-copy');
                const referralLink = copyBtn ? copyBtn.textContent.replace(/\s*<i.*<\/i>\s*/, '').trim() : '';
                const message = `Join me on Hagidy and start earning rewards! 

Use my referral link to get started:
${referralLink}

Earn Hagidy coins on every purchase
Discover amazing products
Share with friends and earn more!

Register now!`;

                return { link: referralLink, message: message };
            }

            // Share to WhatsApp
            function shareToWhatsApp() {
                const { link, message } = getReferralData();
                const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(message)}`;

                // Open WhatsApp in new tab
                window.open(whatsappUrl, '_blank');

                // Show success notification
                showSharingNotification('WhatsApp');
            }

            // Show more sharing options
            function showMoreSharingOptions() {
                const { link, message } = getReferralData();

                // Create modal for sharing options
                const modal = document.createElement('div');
                modal.className = 'sharing-modal';
                modal.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 12px;
                    max-width: 500px;
                    width: 90%;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                ">
                    <h3 style="margin-bottom: 20px; color: #333;">Share Your Referral Link</h3>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Referral Link:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" value="${link}" readonly style="
                                flex: 1;
                                padding: 10px;
                                border: 1px solid #ddd;
                                border-radius: 6px;
                                background: #f9f9f9;
                            " id="referral-link-input">
                            <button onclick="copyReferralLink()" style="
                                padding: 10px 15px;
                                background: #007bff;
                                color: white;
                                border: none;
                                border-radius: 6px;
                                cursor: pointer;
                            ">Copy</button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">Share Message:</label>
                        <textarea readonly style="
                            width: 100%;
                            height: 120px;
                            padding: 10px;
                            border: 1px solid #ddd;
                            border-radius: 6px;
                            background: #f9f9f9;
                            resize: vertical;
                        ">${message}</textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="shareToFacebook()" style="
                            flex: 1;
                            padding: 12px;
                            background: #1877f2;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                        ">
                            <i class="fab fa-facebook"></i> Facebook
                        </button>
                        <button onclick="shareToTwitter()" style="
                            flex: 1;
                            padding: 12px;
                            background: #1da1f2;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                        ">
                            <i class="fab fa-twitter"></i> Twitter
                        </button>
                        <button onclick="shareToTelegram()" style="
                            flex: 1;
                            padding: 12px;
                            background: #0088cc;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                        ">
                            <i class="fab fa-telegram"></i> Telegram
                        </button>
                        <button onclick="shareViaEmail()" style="
                            flex: 1;
                            padding: 12px;
                            background: #ea4335;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                        ">
                            <i class="fas fa-envelope"></i> Email
                        </button>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button onclick="closeSharingModal()" style="
                            padding: 10px 20px;
                            background: #6c757d;
                            color: white;
                            border: none;
                            border-radius: 6px;
                            cursor: pointer;
                        ">Close</button>
                    </div>
                </div>
            </div>
        `;

                document.body.appendChild(modal);

                // Add global functions for sharing
                window.copyReferralLink = function () {
                    const input = document.getElementById('referral-link-input');
                    input.select();
                    document.execCommand('copy');
                    showSharingNotification('Link copied!');
                };

                window.shareToFacebook = function () {
                    const { link, message } = getReferralData();
                    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}&quote=${encodeURIComponent(message)}`;
                    window.open(facebookUrl, '_blank');
                    showSharingNotification('Facebook');
                };

                window.shareToTwitter = function () {
                    const { link, message } = getReferralData();
                    const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(message)}`;
                    window.open(twitterUrl, '_blank');
                    showSharingNotification('Twitter');
                };

                window.shareToTelegram = function () {
                    const { link, message } = getReferralData();
                    const telegramUrl = `https://t.me/share/url?url=${encodeURIComponent(link)}&text=${encodeURIComponent(message)}`;
                    window.open(telegramUrl, '_blank');
                    showSharingNotification('Telegram');
                };

                window.shareViaEmail = function () {
                    const { link, message } = getReferralData();
                    const emailUrl = `mailto:?subject=Join me on Hagidy!&body=${encodeURIComponent(message)}`;
                    window.location.href = emailUrl;
                    showSharingNotification('Email');
                };

                window.closeSharingModal = function () {
                    document.body.removeChild(modal);
                };
            }

            // Show sharing notification
            function showSharingNotification(platform) {
                const notification = document.createElement('div');
                notification.innerHTML = `
            <div style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                z-index: 10001;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease;
            ">
                <i class="fas fa-share-alt" style="margin-right: 8px;"></i>
                Shared to ${platform}!
            </div>
        `;

                document.body.appendChild(notification);

                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 3000);
            }

            // Handle hash navigation function
            function handleHashNavigation() {
                const hash = window.location.hash;
                if (hash) {
                    // Remove the # symbol
                    const tabId = hash.substring(1);
                    console.log('Hash detected:', tabId);

                    // Wait a bit for DOM to be fully ready
                    setTimeout(() => {
                        // Find the corresponding tab link and tab pane
                        const tabLink = document.querySelector(`a[href="#${tabId}"]`);
                        const tabPane = document.getElementById(tabId);

                        console.log('Tab link found:', tabLink);
                        console.log('Tab pane found:', tabPane);

                        if (tabLink && tabPane) {
                            // Remove active class from all tabs and panes
                            document.querySelectorAll('.nav-link').forEach(link => {
                                link.classList.remove('active');
                                link.setAttribute('aria-selected', 'false');
                            });
                            document.querySelectorAll('.tab-pane').forEach(pane => {
                                pane.classList.remove('active', 'show');
                            });

                            // Add active class to the target tab and pane
                            tabLink.classList.add('active');
                            tabLink.setAttribute('aria-selected', 'true');
                            tabPane.classList.add('active', 'show');

                            console.log('Tab activated:', tabId);

                            // Scroll to the tab content
                            setTimeout(() => {
                                tabPane.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }, 100);
                        } else {
                            console.log('Tab link or pane not found for:', tabId);
                            // Try alternative selectors
                            const altTabLink = document.querySelector(`a[href*="${tabId}"]`);
                            const altTabPane = document.querySelector(`[id*="${tabId}"]`);
                            console.log('Alternative tab link:', altTabLink);
                            console.log('Alternative tab pane:', altTabPane);
                        }
                    }, 100);
                }
            }

            // Check if there's a hash in the URL
            handleHashNavigation();

            // Listen for hash changes
            window.addEventListener('hashchange', function () {
                handleHashNavigation();
            });

            // Handle referral link clicks from header
            const referralLinks = document.querySelectorAll('a[href*="#refer-friend"]');
            referralLinks.forEach(link => {
                link.addEventListener('click', function (e) {
                    // If we're already on my-account.php, prevent default and switch tab
                    if (window.location.pathname.includes('my-account.php')) {
                        e.preventDefault();
                        console.log('Referral link clicked on same page, switching tab...');

                        // Force switch to refer-friend tab
                        switchToReferFriendTab();
                    }
                });
            });

            // Also handle clicks on any element with referral link
            document.addEventListener('click', function (e) {
                const target = e.target.closest('a[href*="#refer-friend"]');
                if (target && window.location.pathname.includes('my-account.php')) {
                    e.preventDefault();
                    console.log('Referral link clicked via event delegation, switching tab...');
                    switchToReferFriendTab();
                }
            });

            // Function to switch to refer-friend tab
            function switchToReferFriendTab() {
                const tabId = 'refer-friend';
                const tabLink = document.querySelector(`a[href="#${tabId}"]`);
                const tabPane = document.getElementById(tabId);

                console.log('Switching to refer-friend tab...');
                console.log('Tab link found:', tabLink);
                console.log('Tab pane found:', tabPane);

                if (tabLink && tabPane) {
                    // Remove active class from all tabs and panes
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                        link.setAttribute('aria-selected', 'false');
                    });
                    document.querySelectorAll('.tab-pane').forEach(pane => {
                        pane.classList.remove('active', 'show');
                    });

                    // Add active class to the target tab and pane
                    tabLink.classList.add('active');
                    tabLink.setAttribute('aria-selected', 'true');
                    tabPane.classList.add('active', 'show');

                    console.log('Refer-friend tab activated successfully');

                    // Update URL hash
                    window.history.pushState(null, null, '#refer-friend');

                    // Scroll to the tab content
                    setTimeout(() => {
                        tabPane.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }, 100);
                } else {
                    console.log('Refer-friend tab elements not found');
                }
            }
        });

        // Function to clear pending bank details on page exit or timeout
        function clearPendingBankDetailsOnExit() {
            // Clear pending details when user navigates away
            window.addEventListener('beforeunload', function() {
                if (document.getElementById('otp-section').style.display === 'block') {
                    // Clear pending bank details via API call
                    fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'clear_pending_bank_details'
                        })
                    }).catch(error => {
                        console.log('Could not clear pending bank details:', error);
                    });
                }
            });

            // Clear pending details after 10 minutes (600000 ms)
            setTimeout(function() {
                if (document.getElementById('otp-section').style.display === 'block') {
                    // Hide OTP section and clear pending details
                    document.getElementById('otp-section').style.display = 'none';
                    document.getElementById('otp-message').style.display = 'none';
                    document.getElementById('bank-otp').value = '';
                    
                    // Clear pending bank details via API call
                    fetch('<?php echo USER_BASEURL; ?>app/handlers/profile_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'clear_pending_bank_details'
                        })
                    }).then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Pending bank details cleared due to timeout');
                        }
                    }).catch(error => {
                        console.log('Could not clear pending bank details:', error);
                    });
                }
            }, 600000); // 10 minutes
        }
    </script>
</body>

</html>
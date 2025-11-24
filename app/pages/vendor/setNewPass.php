<?php session_start(); 
include __DIR__ . '/../includes/init.php';
// Check if this is a valid flow - either verified OTP or forgot password
$isVerifiedFlow = isset($_SESSION['otp_stage']) && $_SESSION['otp_stage'] === 'verified';
$isForgotPasswordFlow = isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true;

if (!$isVerifiedFlow && !$isForgotPasswordFlow) {
    header("Location: ./registration.php");
    exit;
}

// For registration flow, ensure OTP is verified
if (!isset($_SESSION['forgot_password']) && (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] != 1)) {
    header("Location: ./verifyOtp.php");
    exit;
}

// For forgot password flow, ensure OTP is verified
if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true && (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] != 1)) {
    header("Location: ./verifyOtp.php");
    exit;
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
    <title> NEWPASSWORD | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords" content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>icon-fonts/RemixIcons/fonts/remixicon.css">

</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic ">
           
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
                <div class="my-5 mt-0 d-flex justify-content-center">
                    <a href="index.php">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/login-logo.png" alt="logo" class="desktop-logo">
                    </a>
                </div>
                <div class="card custom-card">
                    <div class="card-body p-5">
                        <?php 
                        $pageTitle = "Set New Password";
                        $pageDescription = "";
                        if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true) {
                            $pageTitle = "Reset Password";
                            $pageDescription = "Enter your new password below.";
                        }
                        ?>
                        <p class="h5 fw-semibold mb-4 text-center"><?php echo $pageTitle; ?></p>
                        <?php if (!empty($pageDescription)): ?>
                        <p class="mb-4 text-muted op-7 fw-normal text-center"><?php echo $pageDescription; ?></p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION) && isset($_SESSION['error_setpass']) && $_SESSION['error_setpass']): ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px;">
                            <?php echo htmlspecialchars($_SESSION['error_setpass']); unset($_SESSION['error_setpass']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION) && isset($_SESSION['reset_error']) && $_SESSION['reset_error']): ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px;">
                            <?php echo htmlspecialchars($_SESSION['reset_error']); unset($_SESSION['reset_error']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Success message will be shown in modal, not as alert -->
                        
                        <?php 
                        // Determine which handler to use based on flow
                        $action = USER_BASEURL . 'app/handlers/set_password_handler.php';
                        
                        if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true) {
                            $action = USER_BASEURL . 'app/handlers/reset_password_handler.php';
                        }
                        // echo $action;
                        ?>
                        <form method="post" action="<?php echo $action; ?>">
                        <div class="row gy-3">
                            <div class="col-xl-12">
                                <label for="signin-username" class="form-label text-default">New Password <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="new-password" name="password"
                                        placeholder="Confirm Password">
                                    <button class="btn btn-light" type="button"
                                        onclick="createpassword('new-password',this)" id="button-addon2"><i
                                            class="ri-eye-off-line align-middle"></i></button>
                                </div>
                            </div>
                            <div class="col-xl-12 mb-4">
                                <label for="signin-password" class="form-label text-default d-block">Confirm Password
                                    <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="confirm-password" name="password_confirm"
                                        placeholder="Confirm Password">
                                    <button class="btn btn-light" type="button"
                                        onclick="createpassword('confirm-password',this)" id="button-addon2"><i
                                            class="ri-eye-off-line align-middle"></i></button>
                                </div>
                                <!-- <div class="mt-2 text-end">
                                  <a href="./forgotPassword.html" class="float-end text-sky-blue forgot-border">Forget password ?</a>
                                </div> -->
                            </div>
                            <div class="col-xl-12 d-grid mt-2">
                                <button type="submit" class="btn btn-lg btn-primary">Submit</button>
                            </div>
                        </div>
                        </form>
                      
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/show-password.js"></script>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-success" id="successModalLabel">
                        <i class="ri-check-line me-2"></i>Success!
                    </h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="ri-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h6 class="text-success mb-2">
                        <?php 
                        if (isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true) {
                            echo 'Password Reset Successfully!';
                        } else {
                            echo 'Password Set Successfully!';
                        }
                        ?>
                    </h6>
                    <p class="text-muted mb-0">You will be redirected automatically in <span id="countdown">3</span> seconds...</p>
                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="redirectUser()">Redirect Now</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if password was set successfully
            <?php 
            $showModal = false;
            if (isset($_SESSION['password_success']) && $_SESSION['password_success']) {
                $showModal = true;
                unset($_SESSION['password_success']); // Clear after checking
            }
            ?>
            
            <?php if ($showModal): ?>
showSuccessModal();
            <?php else: ?>
<?php endif; ?>
            
            function showSuccessModal() {
                const modal = new bootstrap.Modal(document.getElementById('successModal'));
                modal.show();
                
                // Start countdown
                let countdown = 3;
                const countdownElement = document.getElementById('countdown');
                
                const timer = setInterval(function() {
                    countdown--;
                    countdownElement.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        modal.hide();
                        
                        // Redirect based on vendor status
                        redirectUser();
                    }
                }, 1000);
            }
            
            function redirectUser() {
                // Simple and direct redirect
// Get the redirect URL from PHP session
                const redirectUrl = '<?php 
                    if (isset($_SESSION["redirect_url"])) {
                        echo $_SESSION["redirect_url"];
                        unset($_SESSION["redirect_url"]); // Clear after use
                    } else {
                        echo "./index.php";
                    }
                ?>';
// Multiple redirect methods for reliability
                try {
                    // Method 1: Replace current page
                    window.location.replace(redirectUrl);
                } catch (e) {
                    try {
                        // Method 2: Assign new location
                        window.location.href = redirectUrl;
                    } catch (e2) {
                        try {
                            // Method 3: Direct assignment
                            window.location = redirectUrl;
                        } catch (e3) {
                            // Method 4: Create form and submit
                            const form = document.createElement('form');
                            form.method = 'GET';
                            form.action = redirectUrl;
                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                }
                
                // Backup redirect after 1 second
                setTimeout(function() {
                    if (window.location.href.includes('setNewPass.php')) {
window.location.href = redirectUrl;
                    }
                }, 1000);
            }
        });
    </script>

    <script src="<?php echo PUBLIC_ASSETS; ?>js/security.js"></script>
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
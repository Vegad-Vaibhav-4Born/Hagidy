<?php session_start(); 
include __DIR__ . '/../includes/init.php';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light"
    data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title> FORGOTPASSWORD | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="hagidy website">

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

        <div class="row justify-content-center align-items-center  authentication authentication-basic h-100">
  
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12 ">
                <div class="my-5  d-flex justify-content-center ali">
                    <a href="index.php">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/login-logo.png" alt="logo" class="desktop-logo">
                    </a>
                </div>
                <div class="card custom-card">
                    <div class="card-body p-5">
                        <p class="h5 fw-semibold mb-2 text-center">Forgot Password</p>
                        <p class="mb-4 text-muted op-7 fw-normal text-center">Enter your Register Phone Number and
                            Verify with OTP</p>
                        
                        <?php if (isset($_SESSION['forgot_error'])): ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom: 12px;">
                            <?php echo htmlspecialchars($_SESSION['forgot_error']); unset($_SESSION['forgot_error']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['forgot_success'])): ?>
                        <div class="alert alert-success" role="alert" style="margin-bottom: 12px;">
                            <?php echo htmlspecialchars($_SESSION['forgot_success']); unset($_SESSION['forgot_success']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="post" action="<?php echo USER_BASEURL; ?>app/handlers/forgot_password_handler.php">
                            <div class="row gy-3">
                                <div class="col-xl-12">
                                    <label for="signin-username" class="form-label text-default">Mobile Number <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-lg mb-4" id="signin-username"
                                        name="mobile" placeholder="Enter Mobile Number" required>
                                </div>
                                <div class="col-xl-12 d-grid mt-2">
                                    <button type="submit" class="btn btn-lg btn-primary">Send OTP</button>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const mobileInput = document.getElementById('signin-username');
            
            // Add input validation
            mobileInput.addEventListener('input', function() {
                const value = this.value.replace(/\D/g, ''); // Remove non-digits
                this.value = value;
                
                // Clear any existing error styling
                this.classList.remove('is-invalid');
                
                // Validate length
                if (value.length > 0 && value.length < 10) {
                    this.classList.add('is-invalid');
                }
            });
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                const mobile = mobileInput.value.trim();
                
                if (!mobile) {
                    e.preventDefault();
                    showError('Please enter your mobile number.');
                    mobileInput.classList.add('is-invalid');
                    mobileInput.focus();
                    return;
                }
                
                if (mobile.length < 10) {
                    e.preventDefault();
                    showError('Please enter a valid 10-digit mobile number.');
                    mobileInput.classList.add('is-invalid');
                    mobileInput.focus();
                    return;
                }
            });
            
            function showError(message) {
                // Remove existing error alerts
                const existingAlerts = document.querySelectorAll('.alert-danger');
                existingAlerts.forEach(alert => alert.remove());
                
                // Create new error alert
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger';
                errorDiv.style.marginBottom = '12px';
                errorDiv.textContent = message;
                
                // Insert before form
                form.parentNode.insertBefore(errorDiv, form);
            }
        });
    </script>

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
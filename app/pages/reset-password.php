<?php
session_start();
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}


// Check if user is authorized to reset password
if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot-password.php");
    exit;
}

$user_id = $_SESSION['reset_user_id'];

// Handle password reset
if (isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password in database
        $update_query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Clear session variables
            unset($_SESSION['otp_verified']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['forgot_password_mobile']);
            
            $success_message = "Password updated successfully! You can now login with your new password.";
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error_message = "Failed to update password. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>Reset Password | HAGIDY</title>

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
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">

    <!-- Plugin CSS -->
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

</head>

<body>
    <div class="page-wrapper">
           <?php include __DIR__ . '/../includes/header.php';?>    

        <!-- Start of Main -->
        <main class="main login-page">
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="./login.php">Login / Register</a></li>
                            <li>Reset Password</li>
                        </ul>
                    </nav>
                </div>
            </div>

            <!-- End of Breadcrumb -->
            <div class="page-content">
                <div class="container">
                    <div class="login-popup">
                        <div class="tab tab-nav-boxed tab-nav-center tab-nav-underline">
                            <a class="nav-link active text-start otp-box mb-3">Reset Password</a>
                            <p class="mb-0">Please enter your new password</p>
                            <div class="tab-content">
                                <div class="tab-pane active" id="sign-in">
                                    <?php if (isset($error_message)): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo $error_message; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($success_message)): ?>
                                        <div class="alert alert-success" role="alert">
                                            <?php echo $success_message; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="resetPasswordForm">
                                        <div class="form-group">
                                            <label>New Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="password" id="password" 
                                                   placeholder="Enter new password" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" 
                                                   placeholder="Confirm new password" required>
                                        </div>
                                        <button type="submit" name="reset_password" class="btn btn-primary w-100">Reset Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <!-- End of Main -->
        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>

    <!-- Password Validation JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            form.addEventListener('submit', function(e) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (password.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long!');
                    return false;
                }
            });
            
            // Real-time password matching
            confirmPassword.addEventListener('input', function() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            });
        });
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
});
</script>
</body>

</html>


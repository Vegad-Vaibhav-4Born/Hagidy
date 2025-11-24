<?php 
    
    include '../includes/init.php';
?>

<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title> LOGIN | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
	<meta name="keywords" content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/vendor/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet" >

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/styles.min.css" rel="stylesheet" >

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/icons.min.css" rel="stylesheet" >

    <!-- Remixicon: correct font path -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>icon-fonts/RemixIcons/fonts/remixicon.css">
    


</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
            
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
                <div class="my-5 mt-0 d-flex justify-content-center">
                    <a href="index.php">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/login-logo.png" alt="logo" class="desktop-logo">
                    </a>
                </div>
                <div class="card custom-card">
                    <div class="card-body p-5">
                        <p class="h5 fw-semibold mb-2 text-center">Login</p>
                        <p class="mb-4 text-muted op-7 fw-normal text-center">Welcome & Join us by creating a free account !</p>
                        <?php  if (isset($_SESSION['login_error'])): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?></div>
                        <?php endif; ?>
                        <form method="post" action="<?php echo USER_BASEURL; ?>app/handlers/login_handler.php">
                        <div class="row gy-3">
                            <div class="col-xl-12">
                                <label for="signin-username" class="form-label text-default">Mobile Number <span class="text-danger">*</span></label>
                                <input type="number" class="form-control form-control-lg" id="signin-username" name="mobile" placeholder="Enter Mobile Number">
                            </div>
                            <div class="col-xl-12 mb-2">
                                <label for="signin-password" class="form-label text-default d-block">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="signin-password" name="password" placeholder="password">
                                    <button class="btn btn-light" type="button" onclick="createpassword('signin-password',this)" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></button>
                                </div>
                                <div class="mt-2 text-end">
                                  <a href="./forgotPassword.php" class="float-end text-sky-blue forgot-border">Forgot password ?</a>
                                </div>
                            </div>
                            <div class="col-xl-12 d-grid mt-2">
                                <button type="submit" class="btn btn-lg btn-primary">Login</button>
                            </div>
                        </div>
                        </form>
                        <div class="text-center">
                            <p class="fs-12 text-muted mt-3">Don't have an account? <a href="./registration.php" class="text-primary">Register as a Seller</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/show-password.js"></script>
    <script src="include/security.js"></script>
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

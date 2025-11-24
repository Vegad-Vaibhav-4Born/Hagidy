<?php
include __DIR__ . '/../includes/init.php';  

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mobile_no = $_POST['mobile_no'];
    $password = $_POST['password'];

    // WARNING: This approach is vulnerable to SQL injection
    // Only use for learning purposes, not in production
    $query = "SELECT * FROM superadmin WHERE mobile_no = '$mobile_no' AND password = '$password'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $admin = mysqli_fetch_assoc($result);
        $_SESSION['superadmin_id'] = $admin['id'];
        $_SESSION['admin_mobile'] = $admin['mobile_no'];
        // echo "Login successful";
        // print_r($_SESSION);
        header('Location: index.php');
        exit();
    } else {
        $error_message = "Invalid mobile number or password!";
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
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title> LOGIN | HADIDY</title>
    <meta name="Description" content="Hagidy-Super-Admin">
    <meta name="Author" content="Hagidy-Super-Admin">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template dashboard, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, dashboard, dashboard template bootstrap, admin dashboard bootstrap.">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/admin/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/icons.min.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
                <div class="my-5 d-flex justify-content-center mt-0">
                    <a href="index.html">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/login-logo.png" alt="logo" class="desktop-logo">
                    </a>
                </div>
                <div class="card custom-card">
                    <div class="card-body p-5">
                        <p class="h5 fw-semibold mb-2 text-center">Management Login</p>
                        <p class="mb-4 text-muted op-7 fw-normal text-center">Access the powerful dashboard and manage
                            everything from one place.</p>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row gy-3">
                                <div class="col-xl-12">
                                    <label for="mobile_no" class="form-label text-default">Mobile Number <span
                                            class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-lg" id="mobile_no"
                                        name="mobile_no" placeholder="Enter Mobile Number" required>
                                </div>
                                <div class="col-xl-12 mb-2">
                                    <label for="password" class="form-label text-default d-block">Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="password"
                                            name="password" placeholder="password" required>
                                        <button class="btn btn-light" type="button"
                                            onclick="createpassword('password',this)" id="button-addon2"><i
                                                class="ri-eye-off-line align-middle"></i></button>
                                    </div>
                                </div>
                                <div class="col-xl-12 d-grid mt-2">
                                    <button type="submit" class="btn btn-lg btn-primary">Login</button>
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
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/show-password.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/security.js"></script>

    <script>
        document.addEventListener('keydown', function (e) {
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
        document.addEventListener('contextmenu', function (e) {
            e.preventDefault();
        });
    </script>
</body>

</html>
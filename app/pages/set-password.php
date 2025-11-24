<?php
session_start();
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}


$mobile = $_SESSION['mobile'];
$sql = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile'");

$mobile = $_SESSION['mobile'];
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $password = mysqli_real_escape_string($db_connection, $_POST['password']);
    $confirm  = mysqli_real_escape_string($db_connection, $_POST['confirm_password']);

    if (empty($password) || empty($confirm)) {
        $message = "<p style='color:red;'>Both fields are required.</p>";
    } elseif ($password !== $confirm) {
        $message = "<p style='color:red;'>Passwords do not match.</p>";
    } else {
        // Hash the password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Update password & clear OTP
        $update = mysqli_query($db_connection, "UPDATE users SET password='$hashed', otp=NULL WHERE mobile='$mobile'");

        if ($update) {
            unset($_SESSION['otp']); // clear OTP if stored
            $message = "<p style='color:green;'>Password set successfully. Redirecting...</p>";
            
            // Redirect to login after 2s
            echo "<script>
                    setTimeout(function(){
                        window.location.href='index.php';
                    }, 2000);
                  </script>";
        } else {
            $message = "<p style='color:red;'>Database error: " . mysqli_error($db_connection) . "</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>SET PASSWORD | HAGIDY</title>

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
                            <li>Set Password</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->
            <div class="page-content">
                <div class="container">
                    <div class="login-popup">
                        <div class="tab tab-nav-boxed tab-nav-center tab-nav-underline">
                            <a class="nav-link active text-start otp-box">Set Password</a>
                            <div class="tab-content">
                                <div class="tab-pane active" id="sign-in">
                                    <form method="POST">
                                        <div class="form-group">
                                            <label>Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="password" id="password" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                        </div>
                                        <?php if(!empty($message)) echo $message; ?>
                                        <button type="submit" class="btn btn-primary w-100">Set Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <!-- End of Main -->
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

            // Handle "Show more" click
            const showMoreLink = document.querySelector('.show-more');
            if (showMoreLink) {
                showMoreLink.addEventListener('click', function (event) {
                    event.preventDefault();
                    // Redirect to search results page or expand suggestions
                    window.location.href = 'shop.php?search=' + encodeURIComponent(searchInput.value);
                });
            }

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

<?php
require_once __DIR__ . '/includes/init.php';

// Check if user is already logged in (has user_id in session)
if(isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])){
    header("Location: index.php");
    exit;
}

// Check if user is in forgot password flow
if (isset($_SESSION['forgot_password_mobile'])) {
    $mobile = $_SESSION['forgot_password_mobile'];
    $is_forgot_password = true;
} elseif (isset($_SESSION['mobile'])) {
    $mobile = $_SESSION['mobile'];
    $is_forgot_password = false;
} else {
    header("Location: login.php");
    exit;
}

$sql = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile'");
$data = mysqli_fetch_assoc($sql);
$mobile = $data['mobile'];
$display_otp = $data['otp'];
// AJAX OTP verify endpoint
if (isset($_POST['ajax_verify']) && isset($_POST['otp'])) {
    $otp = mysqli_real_escape_string($db_connection, trim($_POST['otp']));
    $query = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile' AND otp='$otp'");
    header('Content-Type: application/json');
    if ($query && mysqli_num_rows($query) > 0) {
        if ($is_forgot_password) {
            // For forgot password flow, redirect to password reset page
            $_SESSION['otp_verified'] = true;
            $_SESSION['reset_user_id'] = $data['id'];
            echo json_encode(['status' => 'success', 'message' => 'OTP Verified Successfully. Please set your new password.', 'redirect' => 'reset-password.php']);
        } else {
            // For normal login flow - generate customer_id if not exists
            if (empty($data['customer_id'])) {
                // Generate customer ID (B + 9 digits)
                $result = mysqli_query($db_connection, "SELECT customer_id FROM users WHERE customer_id IS NOT NULL ORDER BY customer_id DESC LIMIT 1");
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $last_customer_id = $row['customer_id'];
                    $numeric_part = substr($last_customer_id, 1);
                    $next_number = intval($numeric_part) + 1;
                } else {
                    $next_number = 1;
                }
                
                $customer_id = 'B' . str_pad($next_number, 9, '0', STR_PAD_LEFT);
                
                // Update user with customer_id
                $update_customer_id = mysqli_query($db_connection, "UPDATE users SET customer_id='$customer_id' WHERE id='" . $data['id'] . "'");
                if (!$update_customer_id) {
                    echo json_encode(['status' => 'error', 'message' => 'Error updating customer ID.']);
                    exit;
                }
            }
            
            $_SESSION['user_id'] = $data['id'];
            echo json_encode(['status' => 'success', 'message' => 'OTP Verified Successfully. You are now logged in.', 'redirect' => 'index.php']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP.']);
    }
    exit;
}
if (isset($_POST['verify_otp'])) {
    // Collect 6 digits from inputs and join them
    $otp = $_POST['d1'].$_POST['d2'].$_POST['d3'].$_POST['d4'].$_POST['d5'].$_POST['d6'];

    $query = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile' AND otp='$otp'");
    if (mysqli_num_rows($query) > 0) {
        if ($is_forgot_password) {
            // For forgot password flow, redirect to password reset page
            $_SESSION['otp_verified'] = true;
            $_SESSION['reset_user_id'] = $data['id'];
            header("Location: reset-password.php");
        } else {
            // For normal login flow - generate customer_id if not exists
            if (empty($data['customer_id'])) {
                // Generate customer ID (B + 9 digits)
                $result = mysqli_query($db_connection, "SELECT customer_id FROM users WHERE customer_id IS NOT NULL ORDER BY customer_id DESC LIMIT 1");
                
                if ($result && mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $last_customer_id = $row['customer_id'];
                    $numeric_part = substr($last_customer_id, 1);
                    $next_number = intval($numeric_part) + 1;
                } else {
                    $next_number = 1;
                }
                
                $customer_id = 'B' . str_pad($next_number, 9, '0', STR_PAD_LEFT);
                
                // Update user with customer_id
                $update_customer_id = mysqli_query($db_connection, "UPDATE users SET customer_id='$customer_id' WHERE id='" . $data['id'] . "'");
                if (!$update_customer_id) {
                    echo "Error updating customer ID.";
                    exit;
                }
            }
            
            $_SESSION['user_id'] = $data['id'];
            header("Location: index.php");
        }
        exit;
    } else {
        echo "Invalid OTP.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>OTP | HAGIDY</title>

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
                            <li>OTP Verification</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->
            <div class="page-content">
                <div class="container">
                    <div class="login-popup">
                        <div class="tab tab-nav-boxed tab-nav-center tab-nav-underline">
                            <a class="nav-link active text-start otp-box p-0 m-0">
                                <?php echo $is_forgot_password ? 'RESET PASSWORD - OTP VERIFICATION' : 'OTP VERIFICATION'; ?>
                            </a>
                            <div class="tab-content">
                                <div class="tab-pane active pt-3" id="sign-in">
                                    <form method="post" id="otpForm">
                                    <p>We have sent an OTP to your Register Mobile Number <br><b>+91 <?= $mobile; ?></b></p>
                                    <?php if ($is_forgot_password): ?>
                                        <p class="text-info">Please verify your OTP to reset your password.</p>
                                    <?php endif; ?>
                                    Your OTP is <b id="otpMessage" ><?php echo $display_otp; ?></b> <!-- Remove this line in production -->
                                    <div class="form-group otp-flex">
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d1" pattern="[0-9]*" required>
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d2" pattern="[0-9]*" required>
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d3" pattern="[0-9]*" required>
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d4" pattern="[0-9]*" required>
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d5" pattern="[0-9]*" required>
                                        <input type="text" class="form-control otp-input" maxlength="1"
                                            inputmode="numeric" name="d6" pattern="[0-9]*" required>
                                    </div>
                                    <div class="form-checkbox d-flex align-items-center justify-content-between">
    <a href="javascript:void(0)" id="resendOtp">Resend OTP</a>
</div>
<p id="otpResendMsg" class="text-success"></p>
                                    <!-- <a href="./set-password.php" class="btn btn-primary">Verify</a> -->
                                     <button type="submit" name="verify_otp" class="btn btn-primary w-100">Verify</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script>
document.getElementById("resendOtp").addEventListener("click", function () {
    fetch("resend_otp.php", { method: "POST" })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                showToast(data.message || 'OTP sent again', 'success');
                setTimeout(()=>{ location.reload(); }, 600);
            } else {
                showToast(data.message || 'Failed to resend OTP', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Network error. Please try again.', 'error');
        });
});
</script>
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
    <!-- Toast CSS/JS -->
    <style>
        .toast-container{position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px}
        .toast{min-width:240px;max-width:340px;padding:12px 14px;border-radius:6px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.15);opacity:.98;display:flex;align-items:flex-start;gap:10px}
        .toast-success{background:#16a34a}
        .toast-error{background:#dc2626}
        .toast-info{background:#2563eb}
        .toast-close{background:transparent;border:0;color:#fff;margin-left:auto;cursor:pointer}
    </style>
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
    <script>
        function showToast(message, type = 'info', duration = 3000){
            const container = document.getElementById('toastContainer');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + (type==='success'?'success':type==='error'?'error':'info');
            toast.innerHTML = '<span>'+ (message||'') +'</span>'+
                              '<button class="toast-close" aria-label="Close" onclick="this.parentElement.remove()">×</button>';
            container.appendChild(toast);
            setTimeout(()=>{ toast.remove(); }, duration);
        }
    </script>
    <script>
        // Verify OTP via AJAX with toast feedback
        document.addEventListener('DOMContentLoaded', function(){
            const otpForm = document.getElementById('otpForm');
            if(!otpForm) return;
            otpForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const inputs = document.querySelectorAll('.otp-input');
                let otp = '';
                inputs.forEach(i=> otp += (i.value||''));
                if(otp.length !== 6){
                    showToast('Please enter 6-digit OTP', 'error');
                    return;
                }
                try{
                    const formData = new FormData();
                    formData.append('ajax_verify', '1');
                    formData.append('otp', otp);
                    const res = await fetch(window.location.href, { method:'POST', body: formData });
                    const data = await res.json();
                    if(data.status === 'success'){
                        showToast(data.message || 'OTP verified', 'success');
                        const redirectTo = data.redirect || 'index.php';
                        setTimeout(()=>{ window.location.href = redirectTo; }, 700);
                    } else {
                        showToast(data.message || 'Invalid OTP', 'error');
                    }
                }catch(err){
                    showToast('Network error. Please try again.', 'error');
                }
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('.otp-input');
            inputs.forEach((input, idx) => {
                input.addEventListener('input', function (e) {
                    this.value = this.value.replace(/[^0-9]/g, ''); // Only numbers
                    if (this.value.length === 1 && idx < inputs.length - 1) {
                        inputs[idx + 1].focus();
                    }
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && idx > 0) {
                        inputs[idx - 1].focus();
                    }
                });
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

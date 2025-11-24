<?php
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string)
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// Check if user is already logged in
if (isset($_SESSION['mobile']) && !empty($_SESSION['mobile'])) {
    header("Location: index.php");
    exit;
}
// Session already started via includes/init.php bootstrap

// Generate random referral code
function generateReferralCode($length = 6)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}

// Generate customer ID (B + 9 digits)
function generateCustomerId()
{
    global $db_connection;

    // Get the highest existing customer_id
    $result = mysqli_query($db_connection, "SELECT customer_id FROM users WHERE customer_id IS NOT NULL ORDER BY customer_id DESC LIMIT 1");

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_customer_id = $row['customer_id'];

        // Extract the numeric part (remove 'B' prefix)
        $numeric_part = substr($last_customer_id, 1);
        $next_number = intval($numeric_part) + 1;
    } else {
        // First customer
        $next_number = 1;
    }

    // Format as B + 9 digits (padded with zeros)
    $customer_id = 'B' . str_pad($next_number, 9, '0', STR_PAD_LEFT);

    return $customer_id;
}

if (isset($_POST['register'])) {
    $first_name = mysqli_real_escape_string($db_connection, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($db_connection, $_POST['last_name']);
    $mobile = mysqli_real_escape_string($db_connection, $_POST['mobile']);
    $email = mysqli_real_escape_string($db_connection, $_POST['email']);
    $password = mysqli_real_escape_string($db_connection, $_POST['password']);
    // $password   = mysqli_real_escape_string($db_connection, $_POST['password']) ?? '123';
    // $password   = '123'; 
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $referral_code = generateReferralCode();
    $customer_id = generateCustomerId();

    // Generate OTP
    $otp = rand(100000, 999999);
    $referred_by = "NULL";

    // Insert user
    $check = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile' OR email='$email'");
    if (mysqli_num_rows($check) > 0) {
        echo "Mobile or Email already registered.";
    } else {
        if (!empty($_POST['referral_code']) && $_POST['refer_choice'] === "yes") {
            $ref_code = mysqli_real_escape_string($db_connection, $_POST['referral_code']);

            // Check if input is a mobile number (contains only digits and is 10 digits)
            if (preg_match('/^[0-9]{10}$/', $ref_code)) {
                // Search by mobile number
                $checkRef = mysqli_query($db_connection, "SELECT id FROM users WHERE mobile='$ref_code'");
            } else {
                // Search by referral code
                $checkRef = mysqli_query($db_connection, "SELECT id FROM users WHERE referral_code='$ref_code'");
            }

            if (mysqli_num_rows($checkRef) > 0) {
                $refRow = mysqli_fetch_assoc($checkRef);
                $referred_by = $refRow['id'];
            } else {
                echo "<p class='text-red'>Oops! That referral code or mobile number doesn't exist.</p>";
            }
        }
        // $sql = "INSERT INTO users(first_name,last_name,mobile,email,password,otp,referral_code,referred_by,customer_id) 
        //         VALUES('$first_name','$last_name','$mobile','$email','$hashed','$otp','$referral_code','$referred_by','$customer_id')";
        $sql = "INSERT INTO users(first_name,last_name,mobile,email,password,otp,referral_code,referred_by) 
                VALUES('$first_name','$last_name','$mobile','$email','$hashed','$otp','$referral_code','$referred_by')";
        if (mysqli_query($db_connection, $sql)) {
            // Get last inserted user ID
            $user_id = mysqli_insert_id($db_connection);

            // Store user info in session
            // $_SESSION['user_id'] = $user_id;
            $_SESSION['mobile'] = $mobile;
            echo "Registration successful. Redirecting to OTP verification...";
            // header("Location: otp.php");
            exit;
        } else {
            echo "Error: " . mysqli_error($db_connection);
        }
    }
}

// ---------------- LOGIN ----------------
if (isset($_POST['login'])) {
    $mobile = mysqli_real_escape_string($db_connection, $_POST['mobile']);
    $password = mysqli_real_escape_string($db_connection, $_POST['password']);
    $remember_me = isset($_POST['remember']) ? true : false;

    // Check if mobile number exists
    $mobileCheck = mysqli_query($db_connection, "SELECT * FROM users WHERE mobile='$mobile'");

    if (mysqli_num_rows($mobileCheck) > 0) {
        $row = mysqli_fetch_assoc($mobileCheck);

        if (password_verify($password, $row['password'])) {
            // Check if user has completed OTP verification (customer_id exists)
            if (empty($row['customer_id'])) {
                // Set mobile in session for OTP verification
                $_SESSION['mobile'] = $row['mobile'];
                header("Location: otp.php");
                exit;
            }

            $otp = rand(100000, 999999);
            // Update OTP in database
            $update_otp = mysqli_query($db_connection, "UPDATE users SET otp='$otp' WHERE id='" . $row['id'] . "'");
            if (!$update_otp) {
                echo "Error updating OTP: " . mysqli_error($db_connection);
                exit;
            }

            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['mobile'] = $row['mobile'];

            // Set session duration using cookies (since we can't modify session settings after session_start)
            if ($remember_me) {
                // 30 days - extend session cookie
                $session_duration = 30 * 24 * 60 * 60;
                setcookie(session_name(), session_id(), time() + $session_duration, '/');
                $_SESSION['remember_me'] = true;
                $_SESSION['session_expires'] = time() + $session_duration;
            } else {
                // 7 days - extend session cookie
                $session_duration = 7 * 24 * 60 * 60;
                setcookie(session_name(), session_id(), time() + $session_duration, '/');
                $_SESSION['remember_me'] = false;
                $_SESSION['session_expires'] = time() + $session_duration;
            }

            // OTP not verified yet
            // echo "Please verify your OTP.";
            // header("Location: otp.php");
            header("Location: index.php");
            exit;
        } else {
            echo "Incorrect password.";
        }
    } else {
        echo "Mobile number not registered.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>LOGIN | HAGIDY</title>

    <meta name="keywords" content="Marketplace ecommerce responsive HTML5 Template" />
    <meta name="description" content="Wolmart is powerful marketplace &amp; ecommerce responsive Html5 Template.">
    <meta name="author" content="D-THEMES">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo PUBLIC_ASSETS; ?>images/icons/favicon.png">

    <!-- WebFont.js -->
    <script>
        WebFontConfig = {
            google: {
                families: ['Poppins:400,500,600,700']
            }
        };
        (function(d) {
            var wf = d.createElement('script'),
                s = d.scripts[0];
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
        <?php include __DIR__ . '/../includes/header.php'; ?>

        <!-- Start of Main -->
        <main class="main login-page">
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="index.php">Home</a></li>
                            <li>Login / Register</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->
            <div class="page-content">
                <div class="container">
                    <div class="login-popup">
                        <div class="tab tab-nav-boxed tab-nav-center tab-nav-underline">
                            <ul class="nav nav-tabs text-uppercase" role="tablist">
                                <li class="nav-item">
                                    <a href="#sign-in" class="nav-link active">Sign In</a>
                                </li>
                                <li class="nav-item">
                                    <a href="#sign-up" class="nav-link">Sign Up</a>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="sign-in">
                                    <form method="POST" id="signInForm">
                                        <div class="form-group">
                                            <label>Mobile Number <span class="req-star">*</span></label>
                                            <input type="number" class="form-control" name="mobile" id="mobile"
                                                required>
                                        </div>
                                        <div class="form-group mb-0">
                                            <label>Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="password" id="password"
                                                required>
                                        </div>
                                        <div class="form-checkbox d-flex align-items-center justify-content-between">
                                            <input type="checkbox" class="custom-checkbox" id="remember"
                                                name="remember">
                                            <label for="remember">Remember me</label>
                                            <a href="./forgot-password.php">Forgot Password? </a>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100" name="login">Sign
                                            In</button>

                                        <!-- <a href="#" class="btn btn-primary">Sign In</a> -->
                                    </form>
                                </div>
                                <div class="tab-pane" id="sign-up">
                                    <form method="POST" id="signUpForm">
                                        <div class="form-group">
                                            <label>First Name <span class="req-star">*</span></label>
                                            <input type="text" class="form-control" name="first_name" id="email_1"
                                                required>
                                        </div>
                                        <div class="form-group">
                                            <label>Last Name <span class="req-star">*</span></label>
                                            <input type="text" class="form-control" name="last_name" id="email_1"
                                                required>
                                        </div>
                                        <div class="form-group">
                                            <label>Mobile Number <span class="req-star">*</span></label>
                                            <input type="number" class="form-control" name="mobile" id="email_1"
                                                required>
                                        </div>
                                        <div class="form-group">
                                            <label>Your Email address <span class="req-star">*</span></label>
                                            <input type="email" class="form-control" name="email" id="email_1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Password <span class="req-star">*</span></label>
                                            <input type="password" class="form-control" name="password" id="email_1"
                                                required>
                                        </div>
                                        <div class="form-checkbox d-flex align-items-center justify-content-between">
                                            <a class="border-0">You have a refer code ?</a>
                                            <div class="yes-no-flex">
                                                <div class="form-checkbox user-checkbox mt-0 no-flex">
                                                    <input type="radio" class="custom-radio radio-round" id="refer-no"
                                                        name="refer_choice" value="no" checked>
                                                    <label for="refer-no">No</label>
                                                </div>
                                                <div class="form-checkbox user-checkbox mt-0 no-flex">
                                                    <input type="radio" class="custom-radio radio-round" id="refer-yes"
                                                        name="refer_choice" value="yes">
                                                    <label for="refer-yes">Yes</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="referral-box" style="display:none;">
                                            <div class="form-group">
                                                <label>Refer Code / Mobile Number</label>
                                                <div class="refer-verify">
                                                    <input type="text" class="form-control" name="referral_code"
                                                        id="referral_code"
                                                        placeholder="Enter referral code or mobile number">
                                                    <button type="button" id="verify-referral"
                                                        class="btn btn-primary btn-buy mb-1">Verify</button>
                                                </div>
                                            </div>
                                            <p id="ref-success" class="text-green" style="display:none;"></p>
                                            <p id="ref-error" class="text-red" style="display:none;">Oops! That referral
                                                code or mobile number doesn't exist.</p>
                                        </div>

                                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                                        <script>
                                            $('input[name="refer_choice"]').on('change', function() {
                                                if ($(this).val() === 'yes') {
                                                    $('#referral-box').show();
                                                } else {
                                                    $('#referral-box').hide();
                                                    $('#ref-success, #ref-error').hide();
                                                    $('#referral_code').val('');
                                                }
                                            });

                                            $('#verify-referral').on('click', function() {
                                                var code = $('#referral_code').val();
                                                if (code.trim() === '') return;
                                                $.post('<?php echo USER_BASEURL; ?>app/api/check_referral.php', {
                                                    referral_code: code
                                                }, function(res) {
                                                    // let data = JSON.parse(res);
                                                    let data = res;
                                                    if (data.status === 'success') {
                                                        $('#ref-success').text(data.name + "'s referral has been applied successfully.").show();
                                                        $('#ref-error').hide();

                                                        // Disable the field and button after successful verification
                                                        $('#referral_code').prop('readonly', true).css({
                                                            'background-color': '#f5f5f5',
                                                            'cursor': 'not-allowed'
                                                        });
                                                        $('#verify-referral').prop('disabled', true).css({
                                                            // 'background-color': '#6c757d',
                                                            'cursor': 'not-allowed'
                                                        });
                                                    } else {
                                                        $('#ref-error').show();
                                                        $('#ref-success').hide();
                                                    }
                                                });
                                            });
                                        </script>

                                        <script>
                                            // Auto-open Sign Up and apply referral from URL hash/query
                                            document.addEventListener('DOMContentLoaded', function() {
                                                function parseRefFromUrl() {
                                                    // Support both ?ref= in search and #register?ref= in hash
                                                    const searchParams = new URLSearchParams(window.location.search);
                                                    if (searchParams.get('ref')) return searchParams.get('ref');
                                                    const hash = window.location.hash || '';
                                                    if (hash.startsWith('#register')) {
                                                        const idx = hash.indexOf('?');
                                                        if (idx !== -1) {
                                                            const hashQuery = hash.substring(idx + 1);
                                                            const hashParams = new URLSearchParams(hashQuery);
                                                            if (hashParams.get('ref')) return hashParams.get('ref');
                                                        }
                                                    }
                                                    return null;
                                                }

                                                function switchToSignUpTab() {
                                                    const signInLink = document.querySelector('a[href="#sign-in"]');
                                                    const signUpLink = document.querySelector('a[href="#sign-up"]');
                                                    const signInPane = document.getElementById('sign-in');
                                                    const signUpPane = document.getElementById('sign-up');
                                                    if (signInLink && signUpLink && signInPane && signUpPane) {
                                                        signInLink.classList.remove('active');
                                                        signUpLink.classList.add('active');
                                                        signInPane.classList.remove('active');
                                                        signUpPane.classList.add('active');
                                                    }
                                                }

                                                const shouldOpenRegister = (window.location.hash || '').toLowerCase().startsWith('#register');
                                                const refCode = parseRefFromUrl();

                                                if (shouldOpenRegister || refCode) {
                                                    switchToSignUpTab();
                                                }

                                                if (refCode) {
                                                    // Select "Yes" for referral, show box, set value and verify
                                                    const yesRadio = document.getElementById('refer-yes');
                                                    const noRadio = document.getElementById('refer-no');
                                                    const box = document.getElementById('referral-box');
                                                    const input = document.getElementById('referral_code');
                                                    if (yesRadio) yesRadio.checked = true;
                                                    if (noRadio) noRadio.checked = false;
                                                    if (box) box.style.display = '';
                                                    if (input) input.value = refCode;
                                                    // Trigger existing verification flow
                                                    if (typeof jQuery !== 'undefined') {
                                                        jQuery('#verify-referral').trigger('click');
                                                    } else {
                                                        var btn = document.getElementById('verify-referral');
                                                        if (btn) btn.click();
                                                    }
                                                }
                                            });
                                        </script>


                                        <p class="text-green d-none" id="referral-success"></p>
                                        <p class="text-red d-none" id="referral-error"></p>
                                        <p>Your personal data will be used to support your experience throughout this
                                            website, to manage access to your account, and for other purposes described
                                            in our <a href="privacy_policy.php" href="_blank"
                                                class="text-primary">Privacy Policy</a>.</p>
                                        <div class="form-checkbox d-flex align-items-center mb-3">
                                            <input type="checkbox" class="custom-checkbox" id="agree" name="agree"
                                                required>
                                            <label for="agree">I agree to the <a href="privacy_policy.php" href="_blank"
                                                    class="text-primary">Privacy Policy</a></label>
                                        </div>
                                        <!-- <a href="#" class="btn btn-primary">Sign Up</a> -->
                                        <button type="submit" class="btn btn-primary w-100" name="register">Sign
                                            Up</button>

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

    <!-- Toast CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>css/index-wishlist.css">
    <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
    <script>
        function showToast(message, type = 'info', duration = 3000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'toast toast-' + (type === 'success' ? 'success' : type === 'error' ? 'error' : 'info');
            toast.innerHTML = '<span>' + (message || '') + '</span>' +
                '<button class="toast-close" aria-label="Close" onclick="this.parentElement.remove()">×</button>';
            container.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, duration);
        }
    </script>

    <!-- AJAX login/register with toasts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const signInForm = document.getElementById('signInForm');
            const signUpForm = document.getElementById('signUpForm');

            if (signInForm) {
                signInForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(signInForm);
                    try {
                        const res = await fetch('<?php echo USER_BASEURL; ?>app/api/login.php', {
                            method: 'POST',
                            body: formData
                        });
                        console.log(res);
                        console.log("testing");
                        const data = await res.json();
                        if (data.status === 'success') {
                            showToast(data.message || 'Logged in successfully', 'success');
                            const redirectTo = data.redirect || 'index.php';
                            setTimeout(() => {
                                window.location.href = redirectTo;
                            }, 800);
                        } else {
                            showToast(data.message || 'Login failed', 'error');
                            // If there's a redirect in the error response, redirect to OTP page
                            if (data.redirect) {
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1000);
                            }
                        }
                    } catch (err) {
                        showToast('Network error. Please try again.', 'error');
                    }
                });
            }

            if (signUpForm) {
                signUpForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(signUpForm);

                    // Ensure referral fields are included when present (incl. auto-filled from URL)
                    const refInput = document.getElementById('referral_code');
                    const referYes = document.getElementById('refer-yes');
                    if (refInput && refInput.value.trim() !== '') {
                        formData.set('referral_code', refInput.value.trim());
                        formData.set('refer_choice', (referYes && referYes.checked) ? 'yes' : 'yes');
                    }

                    try {
                        const res = await fetch('<?php echo USER_BASEURL; ?>app/api/register.php', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        if (data.status === 'success') {

                            // var formData = new FormData($form[0]);
                            var firstName = formData.get('first_name') || '';
                            var lastName = formData.get('last_name') || '';
                            var mobile = formData.get('mobile') || '';
                            var referral_code = formData.get('referral_code') || '';
                            // var password = formData.get('password') || '';
                            var agree = formData.get('agree') || '';

                            const insertAssociate = {
                                "firstName": firstName,
                                "lastName": lastName,
                                "phoneNumber": mobile,
                                "ReEnterphoneNumber": mobile,
                                "ReferralId": referral_code,
                                "pincode": 0,
                                "username": data.customer_id,
                                // "Password": password,
                                // "ReEnterpassword": password,
                                "acceptTermsNConditions": agree === "on" ? true : false
                            }
                            console.log(insertAssociate);
                            debugger;

                            const response1 = await fetch('<?php echo ADMIN_BASEURL; ?>admins/registerAssociate', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify(insertAssociate)
                            });

                            const result1 = await response1.json();
                            // if (result1) {
                            //     showToast('Associate registration successful', 'success');
                            // } else {
                            //     showToast('Associate registration failed: ' + (result1.message || 'Unknown error'), 'error');
                            // }


                            showToast(data.message || 'Registration successful', 'success');
                            const redirectTo = data.redirect || 'otp.php';
                            setTimeout(() => {
                                window.location.href = redirectTo;
                            }, 800);
                        } else {
                            showToast(data.message || 'Registration failed', 'error');
                        }
                    } catch (err) {
                        showToast('Network error. Please try again.', 'error');
                    }
                });
            }
        });
    </script>

    <!-- Search Suggestions JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const searchSuggestions = document.getElementById('searchSuggestions');

            // Show suggestions when input is focused
            searchInput.addEventListener('focus', function() {
                searchSuggestions.classList.add('show');
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !searchSuggestions.contains(event.target)) {
                    searchSuggestions.classList.remove('show');
                }
            });

            // Handle suggestion item clicks
            const suggestionItems = document.querySelectorAll('.suggestion-item');
            suggestionItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const title = this.querySelector('.suggestion-title').textContent;
                    searchInput.value = title;
                    searchSuggestions.classList.remove('show');
                });
            });

            // Hide suggestions on form submit
            const searchForm = searchInput.closest('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    searchSuggestions.classList.remove('show');
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stickyElement = document.querySelector('.containt-sticy2');

            window.addEventListener('scroll', function() {
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
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../includes/init.php';
// Block direct access without proper session
// Check for registration flow
$isRegistrationFlow = isset($_SESSION['otp_stage']) && $_SESSION['otp_stage'] === 'registration';
// Check for forgot password flow  
$isForgotPasswordFlow = isset($_SESSION['forgot_password']) && $_SESSION['forgot_password'] === true;

if (!$isRegistrationFlow && !$isForgotPasswordFlow) {
    header("Location: ./registration.php"); 
    exit;
}

// Prevent access if already verified (completed flow)
if (isset($_SESSION['otp_stage']) && $_SESSION['otp_stage'] === 'verified') {
    header("Location: ./setNewPass.php");
    exit;
}

// Optional: reset OTP verification on page load
if (!isset($_SESSION['otp_verified'])) {
    $_SESSION['otp_verified'] = 0;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>VERIFY OTP | HADIDY</title>
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
    <!-- Optional CSS for OTP Input Styling -->
    <style>
        .otp-input {
            width: 40px;
            height: 40px;
            text-align: center;
            font-size: 18px;
            border: 1px solid #ccc;
            border-radius: 4px;
            outline: none;
            /* Ensure spinner controls are visible */
            -moz-appearance: textfield; /* Firefox */
        }
        .otp-input:focus {
            border-color: #3B4B6B;
            box-shadow: 0 0 5px rgba(59, 75, 107, 0.3);
        }
        /* Show spinner controls for WebKit browsers (Chrome, Safari, Edge) */
        .otp-input::-webkit-outer-spin-button,
        .otp-input::-webkit-inner-spin-button {
            -webkit-appearance: inner-spin-button;
            display: none;
            height: 100%;
            cursor: pointer;
        }
        /* Ensure spinner is visible on hover and focus */
        .otp-input:hover::-webkit-outer-spin-button,
        .otp-input:hover::-webkit-inner-spin-button,
        .otp-input:focus::-webkit-outer-spin-button,
        .otp-input:focus::-webkit-inner-spin-button {
            display: none;
        }
    </style>
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
                        <p class="h4 fw-bold mb-2 text-center">Verify Your Account</p>
                        <?php if (isset($_SESSION['otp_error'])): ?>
                        <div class="alert alert-danger" role="alert" style="margin-top: 10px;">
                            <?php echo htmlspecialchars($_SESSION['otp_error']); unset($_SESSION['otp_error']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['otp_success'])): ?>
                        <div class="alert alert-success" role="alert" style="margin-top: 10px;">
                            <?php echo htmlspecialchars($_SESSION['otp_success']); unset($_SESSION['otp_success']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        // Get OTP for display (for testing purposes)
                        $displayOtp = '';
                        
                        // First try to get from session (faster)
                        if (isset($_SESSION['otp'])) {
                            $displayOtp = $_SESSION['otp'];
                        } else {
                            // Fallback to database
                            require_once __DIR__ . '/../includes/init.php';
                            if (isset($_SESSION['pending_phone'])) {
                                $phone = $_SESSION['pending_phone'];
                                $phoneEsc = mysqli_real_escape_string($con, $phone);
                                $res = mysqli_query($con, "SELECT otp_code FROM otp_verification WHERE phone_number = '{$phoneEsc}' ORDER BY id DESC LIMIT 1");
                                if ($res && mysqli_num_rows($res) > 0) {
                                    $row = mysqli_fetch_assoc($res);
                                    $displayOtp = $row['otp_code'];
                                }
                            }
                        }
                        ?>
                        
                        <?php if (!empty($displayOtp)): ?>
                        <div class="alert alert-info" role="alert" style="margin-top: 10px; text-align: center;">
                            <strong>For Testing: Your OTP is: <?php echo htmlspecialchars($displayOtp); ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <p class="mb-4 text-muted op-7 fw-normal text-center">Enter the 6 digit code sent to the registered Mobile Number.</p>
                        
                        <!-- Resend OTP Form (Separate) -->
                        
                        <!-- OTP Verification Form -->
                        <form method="post" action="<?php echo USER_BASEURL; ?>app/handlers/verify_otp_handler.php" id="otp-form">
                            <div class="d-flex justify-content-center gap-3 mb-3">
                                <input type="number" maxlength="1" class="otp-input" name="d1" autocomplete="off" required />
                                <input type="number" maxlength="1" class="otp-input" name="d2" autocomplete="off" required />
                                <input type="number" maxlength="1" class="otp-input" name="d3" autocomplete="off" required />
                                <input type="number" maxlength="1" class="otp-input" name="d4" autocomplete="off" required />
                                <input type="number" maxlength="1" class="otp-input" name="d5" autocomplete="off" required />
                                <input type="number" maxlength="1" class="otp-input" name="d6" autocomplete="off" required />
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-lg btn-primary">Verify OTP</button>
                            </div>
                            <div class="text-center">
                                <p class="fs-14 mt-2" style="color: #ff3c3c;">*Don't share the verification code with anyone!</p>
                            </div>
                        </form>
                        <div class="mb-4 text-end">
                            <button type="button" class="btn btn-link p-0 text-primary" style="font-size: 15px; color: #336699 !important;" data-bs-toggle="modal" data-bs-target="#resendOtpModal">Resend OTP</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="messageModalLabel">OTP Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="messageModalBody">
                    <!-- Message content will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Resend OTP Confirmation Modal -->
    <div class="modal fade" id="resendOtpModal" tabindex="-1" aria-labelledby="resendOtpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered ">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-black" id="resendOtpModalLabel">
                        <i class="ri-refresh-line me-2"></i> <span style="font-weight: 500; color: black">Resend OTP</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="ri-question-line text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3">Are you sure you want to resend OTP?</h6>
                    <p class="text-muted mb-0">This will generate a new verification code and invalidate the current one.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="<?php echo USER_BASEURL; ?>app/handlers/verify_otp_handler.php" style="display: inline;">
                        <button type="submit" name="resend" value="1" class="btn btn-primary">Yes, Resend OTP</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/vendor/show-password.js"></script>

    <!-- OTP Input Handling JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Check for session messages and show modal
            <?php
            if (isset($_SESSION['otp_error'])) {
                $message = $_SESSION['otp_error'];
                unset($_SESSION['otp_error']);
                echo "showModal('❌', `$message`, 'text-danger');";
            }
            if (isset($_SESSION['otp_success'])) {
                $message = $_SESSION['otp_success'];
                unset($_SESSION['otp_success']);
                echo "showModal('✅', `$message`, 'text-success');";
            }
            ?>
            
            // Modal function
            function showModal(icon, message, className) {
                const modalBody = document.getElementById('messageModalBody');
                modalBody.innerHTML = `<div class="${className}"><strong>${icon}</strong> ${message}</div>`;
                
                const modal = new bootstrap.Modal(document.getElementById('messageModal'));
                modal.show();
            }
            
            // OTP Form validation
            const otpForm = document.getElementById('otp-form');
            const otpInputs = document.querySelectorAll('.otp-input');
            
            otpForm.addEventListener('submit', function(e) {
                let isValid = true;
                const otpValues = [];
                
                otpInputs.forEach((input, index) => {
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        otpValues.push(input.value);
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    showModal('❌', 'Please enter all 6 digits of the OTP.', 'text-danger');
                    return;
                }
                
                // Check if all digits are numeric
                const otpString = otpValues.join('');
                if (!/^[0-9]{6}$/.test(otpString)) {
                    e.preventDefault();
                    showModal('❌', 'Please enter a valid 6-digit numeric OTP.', 'text-danger');
                    return;
                }
            });

            otpInputs.forEach((input, index) => {
                input.addEventListener('input', (e) => {
                    // Allow only numeric input
                    const value = e.target.value;
                    if (!/^[0-9]$/.test(value) && value !== '') {
                        e.target.value = '';
                        return;
                    }

                    // Move to next input if a digit is entered
                    if (value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                });

                input.addEventListener('keydown', (e) => {
                    // Move to previous input on backspace if current input is empty
                    if (e.key === 'Backspace' && input.value === '' && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });

                // Handle paste event
                input.addEventListener('paste', (e) => {
                    e.preventDefault();
                    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '');
                    if (pastedData.length) {
                        for (let i = 0; i < otpInputs.length && i < pastedData.length; i++) {
                            otpInputs[i].value = pastedData[i];
                            if (i < otpInputs.length - 1) {
                                otpInputs[i + 1].focus();
                            }
                        }
                    }
                });
            });
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
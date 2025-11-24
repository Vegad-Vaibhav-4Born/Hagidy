<?php 
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}


// Handle AJAX contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'))) {
    header('Content-Type: application/json');
    $response = [ 'success' => false, 'message' => 'Unable to submit message.' ];

    // Collect and sanitize inputs
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $query_id = rand(100000, 999999);
    $status = 'Pending';
    // Validate
    if ($name === '' || $mobile === '' || $email === '' || $message === '') {
        echo json_encode([ 'success' => false, 'message' => 'Please fill in all fields.' ]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([ 'success' => false, 'message' => 'Please enter a valid email address.' ]);
        exit;
    }
    // Optional: basic mobile validation (10-15 digits)
    if (!preg_match('/^\+?[0-9]{7,15}$/', preg_replace('/\s+/', '', $mobile))) {
        echo json_encode([ 'success' => false, 'message' => 'Please enter a valid mobile number.' ]);
        exit;
    }

    // Insert into DB
    $query = "INSERT INTO contactus (`query_id`,`name`, `mobile`, `email`, `message`, `timestamp`, `status`) VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($db_connection, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssssss', $query_id, $name, $mobile, $email, $message, $status);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([ 'success' => true, 'message' => 'Thank you! Your message has been sent.' ]);
            mysqli_stmt_close($stmt);
            exit;
        }
        $err = mysqli_error($db_connection);
        mysqli_stmt_close($stmt);
        echo json_encode([ 'success' => false, 'message' => 'Database error. Please try again later.' ]);
        exit;
    }
    echo json_encode([ 'success' => false, 'message' => 'Server error. Please try again later.' ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>CONTACT US | HAGIDY</title>

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
        <main class="main">
            <!-- Start of Breadcrumb -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="demo1.php">Home</a></li>
                            <li>Contact Us</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Breadcrumb -->


            <!-- Start of PageContent -->
            <div class="page-content contact-us">
                <div class="container">
                    <section class="content-title-section mb-10">
                        <h3 class="title title-center mb-3">Contact
                            Information
                        </h3>
                        <p class="text-center">We value your feedback and questions. Please reach out to us at:</p>
                    </section>
                    <!-- End of Contact Title Section -->

                    <section class="contact-information-section mb-10">
                        <div class=" swiper-container swiper-theme " data-swiper-options="{
                            'spaceBetween': 20,
                            'slidesPerView': 1,
                            'breakpoints': {
                                '480': {
                                    'slidesPerView': 2
                                },
                                '768': {
                                    'slidesPerView': 3
                                },
                                '992': {
                                    'slidesPerView': 4
                                }
                            }
                        }">
                            <div class="swiper-wrapper row cols-xl-4 cols-md-3 cols-sm-2 cols-1">
                                <div class="swiper-slide icon-box text-center icon-box-primary">
                                    <span class="icon-box-icon icon-email">
                                        <i class="w-icon-envelop-closed"></i>
                                    </span>
                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title">E-mail Address</h4>
                                        <p><a href="/cdn-cgi/l/email-protection" class="__cf_email__"
                                                data-cfemail="e38e828a8fa3869b828e938f86cd808c8e">connect@hagidy.com</a>
                                        </p>
                                    </div>
                                </div>
                                <div class="swiper-slide icon-box text-center icon-box-primary">
                                    <span class="icon-box-icon icon-headphone">
                                        <i class="w-icon-headphone"></i>
                                    </span>
                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title">Phone Number</h4>
                                        <p>+91 8939555771</p>
                                    </div>
                                </div>
                                <div class="swiper-slide icon-box text-center icon-box-primary">
                                    <span class="icon-box-icon icon-fax">
                                        <i class="w-icon-fax"></i>
                                    </span>
                                    <div class="icon-box-content">
                                        <h4 class="icon-box-title">Website</h4>
                                        <p>www.hagidy.com</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                    <!-- End of Contact Information section -->

                    <hr class="divider mb-10 pb-1">

                    <section class="contact-section">
                        <div class="row gutter-lg pb-3">
                            <div class="col-lg-6 mb-8">
                                <h4 class="title mb-3">Send Us a Message</h4>
                                <form class="form contact-us-form" id="contactUsForm" action="#" method="post">
                                    <div class="form-group">
                                        <label for="cu_name">Your Name</label>
                                        <input type="text" id="cu_name" name="name" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cu_email">Your Email</label>
                                        <input type="email" id="cu_email" name="email" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cu_mobile">Your Mobile</label>
                                        <input type="number" id="cu_mobile" name="mobile" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cu_message">Your Message</label>
                                        <textarea id="cu_message" name="message" cols="30" rows="5"
                                            class="form-control"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-dark btn-rounded">Send Now</button>
                                </form>
                            </div>
                        </div>
                    </section>
                    <!-- End of Contact Section -->
                </div>

            </div>
            <!-- End of PageContent -->
        </main>
        <!-- End of Main -->

        <!-- Start of Footer -->
        <?php include __DIR__ . '/../includes/footer.php'; ?>
        <!-- End of Footer -->
    </div>
    <!-- End of Page Wrapper -->


    <!-- Plugin JS File -->
    <script data-cfasync="false" src="/cdn-cgi/scripts/5c5dd728/cloudflare-static/email-decode.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/jquery.magnific-popup.min.js"></script>
    <script src="<?php echo PUBLIC_ASSETS; ?>js/main.min.js"></script>

    <script>
        // Lightweight toast utility
        function showToast(message, type) {
            var toast = document.createElement('div');
            toast.className = 'hagidy-toast ' + (type || 'info');
            toast.textContent = message;
            toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:10000;color:#fff;padding:12px 16px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:14px;opacity:0;transition:opacity .25s ease';
            toast.style.background = type === 'success' ? '#27ae60' : (type === 'error' ? '#e74c3c' : '#2980b9');
            document.body.appendChild(toast);
        setTimeout(function(){ toast.style.opacity = '1'; }, 10);
        setTimeout(function(){ toast.style.opacity='0'; setTimeout(function(){ if(toast.parentNode){ toast.parentNode.removeChild(toast); } }, 250); }, 3000);
        }

    document.addEventListener('DOMContentLoaded', function(){
            var form = document.getElementById('contactUsForm');
            if (!form) return;
        form.addEventListener('submit', function(e){
                e.preventDefault();
                var formData = new FormData(form);
                formData.append('ajax', '1');
                var submitBtn = form.querySelector('button[type="submit"]');
                var originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';

            fetch(window.location.pathname, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r){ return r.json().catch(function(){ return { success:false, message:'Unexpected server response.' }; }); })
                .then(function(res){
                        if (res && res.success) {
                            showToast(res.message || 'Message sent successfully!', 'success');
                            form.reset();
                        } else {
                            showToast((res && res.message) || 'Failed to send message.', 'error');
                        }
                    })
                .catch(function(){ showToast('Network error. Please try again.', 'error'); })
                .finally(function(){ submitBtn.disabled = false; submitBtn.innerHTML = originalText; });
            });
        });
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key="></script>
    <script>

        // Map Markers
        var mapMarkers = [{
            address: "New York, NY 10017",
            html: "<strong>New York Office<\/strong><br>New York, NY 10017",
            popup: true
        }];

        // Map Initial Location
        var initLatitude = 40.75198;
        var initLongitude = -73.96978;

        // Map Extended Settings
        var mapSettings = {
            controls: {
                draggable: !window.Wolmart.isMobile,
                panControl: true,
                zoomControl: true,
                mapTypeControl: true,
                scaleControl: true,
                streetViewControl: true,
                overviewMapControl: true
            },
            scrollwheel: false,
            markers: mapMarkers,
            latitude: initLatitude,
            longitude: initLongitude,
            zoom: 11
        };

        var map = $('#googlemaps').gMap(mapSettings);

        // Map text-center At
        var mapCenterAt = function (options, e) {
            e.preventDefault();
            $('#googlemaps').gMap("centerAt", options);
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
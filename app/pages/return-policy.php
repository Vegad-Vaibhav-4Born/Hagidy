<?php 
require_once __DIR__ . '/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">

    <title>Return Policy | HAGIDY</title>

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

    <!-- Vendor CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">

    <!-- Plugin CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/magnific-popup/magnific-popup.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>vendor/swiper/swiper-bundle.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/photoswipe.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo PUBLIC_ASSETS; ?>vendor/photoswipe/default-skin/default-skin.min.css">

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css"
        integrity="sha512-DxV+EoADOkOygM4IR9yXP8Sb2qwgidEmeqAEmDKIOfPRQZOWbXCzLC6vjbZyy0vPisbH2SyW27+ddLVCN+OMzQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

</head>

<body class="my-account">
    <div class="page-wrapper">
      <?php include __DIR__ . '/../includes/header.php';?>    


        <!-- Start of Main -->
        <main class="main">
            <!-- Start of Page Header -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav MB-0 ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li>Return Policy </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Page Header -->

            <!-- End of Breadcrumb -->

            <!-- Start of PageContent -->
            <div class="page-content pt-2">
                <div class="container">
                    <div class="row">
                        <div class="return-police">
                            <h3>
                                Return Policy
                            </h3>
                            <p>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.
                            </p>
                            <div class="ul-return">
                                <ul class="list-type-check">
                                    <li>Nunc nec porttitor turpis. In eu risus enim. In vitae mollis elit.</li>
                                    <li>Vivamus finibus vel mauris ut vehicula.</li>
                                    <li>Nullam a magna porttitor, dictum risus nec, faucibus sapien.</li>
                                    <li>Ultrices eros in cursus turpis massa tincidunt ante in nibh mauris cursus
                                        mattis.</li>
                                    <li>Cras ornare arcu dui vivamus arcu felis bibendum ut tristique.</li>
                                    <li>Pulvinar elementum integer enim neque volutpat.</li>
                                    <li>Nunc nec porttitor turpis. In eu risus enim. In vitae mollis elit.</li>
                                    <li>Vivamus finibus vel mauris ut vehicula.</li>
                                </ul>
                            </div>
                            <P>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet,
                                consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu cursus vitae congue
                                mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus
                                quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet, consectetur adipiscing
                                elit, sed do eiusmod tempor incididunt arcu cursus vitae congue mauris. Sagittis id
                                consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus quam id leo in vitae
                                turpis massa.
                            </P>
                            <p>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.
                            </p>
                            <P>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet,
                                consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu cursus vitae congue
                                mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus
                                quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet, consectetur adipiscing
                                elit, sed do eiusmod tempor incididunt arcu cursus vitae congue mauris. Sagittis id
                                consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus quam id leo in vitae
                                turpis massa.
                            </P>
                        </div>
                        <div class="return-police">
                            <h3>
                                Return Policy point 2
                            </h3>
                            <P>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet,
                                consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu cursus vitae congue
                                mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus
                                quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet, consectetur adipiscing
                                elit, sed do eiusmod tempor incididunt arcu cursus vitae congue mauris. Sagittis id
                                consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus quam id leo in vitae
                                turpis massa.
                            </P>
                            <p>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.
                            </p>
                            <P>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet,
                                consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu cursus vitae congue
                                mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus
                                quam id leo in vitae turpis massa.Lorem ipsum dolor sit amet, consectetur adipiscing
                                elit, sed do eiusmod tempor incididunt arcu cursus vitae congue mauris. Sagittis id
                                consectetur purus ut. Tellus rutrum tellus pelle Vel pretium lectus quam id leo in vitae
                                turpis massa.
                            </P>
                            <P>
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor
                                incididunt arcu cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus
                                rutrum tellus pelle Vel pretium lectus quam id leo in vitae turpis massa.Lorem ipsum
                                dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt arcu
                                cursus vitae congue mauris. Sagittis id consectetur purus ut. Tellus rutrum tellus pelle
                                Vel pretium lectus quam id leo in vitae turpis massa.
                            </P>
                        </div>
                    </div>
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
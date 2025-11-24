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

    <title>PRIVACY POLICY | HAGIDY</title>

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


        <main class="main about-page">
            <!-- Start of Page Header -->
            <div class="page-header mb-5">
                <div class="container">
                    <nav class="breadcrumb-nav ">
                        <ul class="breadcrumb bb-no">
                            <li><a href="./index.php">Home</a></li>
                            <li>Privacy Policy</li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- End of Page Header -->

            <div class="page-content">
                <div class="container mb-5">
                    <div class="row justify-content-center">
                        <div class="col-md-8">

                            <div class="card btn-shadow">
                                <div class="card-body">

                                    <h3 class="wishlist-title">Privacy Policy</h3>

                                    <div class="privacy-content">

                                        <strong>Introduction</strong>
                                        <p>Hagidy Technologies (“Hagidy”, “we”, “our”, or “us”) is committed to protecting the privacy of every individual who uses our platform, whether as a buyer, seller, or visitor. This Privacy Policy explains how we collect, use, share, and safeguard your personal information. By accessing or using the Hagidy website, mobile application, or related services, you agree to the practices described in this policy.</p>

                                        <strong>Information We Collect</strong>
                                        <p>When you use Hagidy as a buyer, we collect details such as your name, email address, phone number, billing and shipping addresses, and payment information, which is handled securely through trusted third-party gateways. We also gather information about your purchases, transaction history, customer service interactions, and feedback that you voluntarily provide. In addition, we automatically collect certain technical information, including your IP address, device details, browser type, and, where permitted, location data, to improve your experience and protect platform security.</p>
                                        <p>If you register as a seller, we collect the information required to establish your account and ensure compliance with legal and regulatory obligations. This may include your business name, entity details, official addresses, phone numbers, bank account details for payments, and identity verification documents such as GSTIN, PAN, or incorporation certificates. We also collect product listings, inventory data, sales records, and communications with Hagidy. Technical usage information from the seller dashboard may also be recorded to improve platform functionality.</p>

                                        <strong>How We Use Your Information</strong>
                                        <p>We use personal information to operate our marketplace effectively and provide you with reliable services. For buyers, this includes processing orders, facilitating payments, arranging deliveries through our logistics partners, sending confirmations and updates, and offering customer support. For sellers, the information we collect helps us verify identities, manage product listings and payouts, comply with tax and regulatory requirements, and improve vendor management systems.</p>
                                        <p>We also use data to monitor and prevent fraudulent activities, secure transactions, enhance the performance of our platform, and provide personalized communication and marketing where legally permitted. All data processing is carried out fairly, transparently, and only for legitimate business purposes.</p>

                                        <strong>Sharing of Information</strong>
                                        <p>Hagidy does not sell or rent your personal information to third parties. However, in order to fulfill our services, we may share your data with trusted partners such as payment processors, logistics and delivery providers, KYC verification agencies, IT service providers, compliance advisors, and government authorities when legally required. All third-party partners are contractually obligated to maintain strict confidentiality and data protection standards.</p>

                                        <strong>Data Security</strong>
                                        <p>Your privacy and data security are extremely important to us. Hagidy implements advanced technical and organizational measures to safeguard your information from unauthorized access, misuse, or disclosure. Sensitive data is encrypted during transfer and at rest, and access is restricted to authorized personnel only. We regularly review and update our systems to ensure the highest levels of security. Please note that while we take reasonable precautions, no online platform can guarantee absolute security.</p>

                                        <strong>Data Retention</strong>
                                        <p>We retain your personal data only for as long as it is necessary to fulfill the purposes outlined in this Privacy Policy or as required by applicable law. Buyer and seller account data are stored while the account remains active. Financial and transactional records are preserved for at least seven years to meet tax and compliance obligations. Data used for marketing will be retained until you opt out of receiving such communications or request its deletion.</p>

                                        <strong>International Transfers</strong>
                                        <p>In some cases, your personal information may be transferred to and stored on servers located outside your home country. Where such transfers occur, Hagidy ensures that appropriate safeguards are in place, including data processing agreements and standard contractual clauses, to maintain compliance with applicable privacy laws.</p>

                                        <strong>Your Rights</strong>
                                        <p>Depending on your jurisdiction, you may have certain rights in relation to your personal information. These may include the right to access, correct, delete, or restrict the use of your data, as well as the right to withdraw consent for marketing communications. Users in the European Union are granted rights under the General Data Protection Regulation (GDPR), while users in India are protected under the Digital Personal Data Protection Act. You can exercise these rights by contacting us directly at <a href="mailto:Connect@hagidy.com">Connect@hagidy.com</a>.</p>

                                        <strong>Cookies and Tracking Technologies</strong>
                                        <p>Hagidy uses cookies and similar technologies to improve user experience, support essential functions, and analyze site usage. These tools help us manage sessions, remember preferences, and measure performance to improve our platform. You can adjust cookie settings through your browser or our Cookie Consent Manager, though disabling cookies may affect functionality.</p>

                                        <strong>Marketing Communications</strong>
                                        <p>We may send promotional offers, product updates, and service-related communications by email, SMS, Whatsapp, or in-app notifications, in accordance with legal requirements. You may opt out of marketing communications at any time through your account settings or by contacting our support team. Please note that transactional messages regarding your orders and account cannot be opted out of.</p>

                                        <strong>Children's Privacy</strong>
                                        <p>Hagidy is not designed for or directed at individuals under the age of 18, and we do not knowingly collect personal information from minors. If we become aware that data has been collected from a child without parental consent, we will delete it promptly.</p>

                                        <strong>Changes to This Privacy Policy</strong>
                                        <p>We may update this Privacy Policy from time to time to reflect changes in technology, laws, or business practices. Continued use of Hagidy after such updates will signify your acceptance of the revised policy.</p>

                                        <strong>Contact Us</strong>
                                        <p>If you have questions, concerns, or requests regarding your personal data or this Privacy Policy, please reach out to:</p>
                                        <p>
                                            Hagidy Marketplace<br>
                                            Email: <a href="mailto:Connect@hagidy.com">Connect@Hagidy.com</a>
                                        </p>

                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- End of PageContent -->
        </main>

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

</body>

</html>
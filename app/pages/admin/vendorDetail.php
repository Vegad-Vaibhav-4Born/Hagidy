<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}
if (! isset($_GET['vendor_id'])) {
    header('Location: newRequest.php');
}
$vendor_id = $_GET['vendor_id'];

$update_seen = mysqli_query($con, "UPDATE vendor_registration SET seen = 1 WHERE id = '$vendor_id'");

// Get vendor data first
$query = "SELECT * FROM vendor_registration WHERE id = '$vendor_id' LIMIT 1";
$result = mysqli_query($con, $query);
$vendor = mysqli_fetch_assoc($result);

if (!$vendor) {
    // If no vendor found, redirect back
    header('Location: newRequest.php');
    exit;
}

// Handle form submission for editing vendor details
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_vendor') {
        $business_name = mysqli_real_escape_string($con, $_POST['business_name'] ?? '');
        $business_address = mysqli_real_escape_string($con, $_POST['business_address'] ?? '');
        $business_type = mysqli_real_escape_string($con, $_POST['business_type'] ?? '');
        $business_type_other = mysqli_real_escape_string($con, $_POST['business_type_other'] ?? '');
        $state = mysqli_real_escape_string($con, $_POST['state'] ?? '');
        $city = mysqli_real_escape_string($con, $_POST['city'] ?? '');
        $pincode = mysqli_real_escape_string($con, $_POST['pincode'] ?? '');
        $owner_name = mysqli_real_escape_string($con, $_POST['owner_name'] ?? '');
        $mobile_number = mysqli_real_escape_string($con, $_POST['mobile_number'] ?? '');
        $whatsapp_number = mysqli_real_escape_string($con, $_POST['whatsapp_number'] ?? '');
        $email = mysqli_real_escape_string($con, $_POST['email'] ?? '');
        $website_social = mysqli_real_escape_string($con, $_POST['website_social'] ?? '');
        $gst_number = mysqli_real_escape_string($con, $_POST['gst_number'] ?? '');
        $account_name = mysqli_real_escape_string($con, $_POST['account_name'] ?? '');
        $account_type = mysqli_real_escape_string($con, $_POST['account_type'] ?? '');
        $account_number = mysqli_real_escape_string($con, $_POST['account_number'] ?? '');
        $confirm_account_number = mysqli_real_escape_string($con, $_POST['confirm_account_number'] ?? '');
        $bank_name = mysqli_real_escape_string($con, $_POST['bank_name'] ?? '');
        $ifsc_code = mysqli_real_escape_string($con, $_POST['ifsc_code'] ?? '');
        $product_categories = implode(',', $_POST['product_categories'] ?? []);
        $avg_products = mysqli_real_escape_string($con, $_POST['avg_products'] ?? '');
        $manufacture_products = mysqli_real_escape_string($con, $_POST['manufacture_products'] ?? '');
        $signature_name = mysqli_real_escape_string($con, $_POST['signature_name'] ?? '');
        // Handle editable status from form (optional)
        $status_input = mysqli_real_escape_string($con, $_POST['status_input'] ?? '');
        $status_map = [
            'pending' => 'pending',
            'hold' => 'hold',
            'rejected' => 'rejected',
            'approved' => 'approved'
        ];
        $status = isset($status_map[$status_input]) ? $status_map[$status_input] : ($vendor['status'] ?? 'pending');
        $active_status = ($status === 'approved') ? 'active' : 'inactive';

        // Generate a safe folder name from business name (e.g., Vaghela_s_Store)
        $safeBusinessDir = preg_replace('/[^A-Za-z0-9_]+/', '_', str_replace([' ', "'"], '_', $business_name));
        $safeBusinessDir = preg_replace('/_+/', '_', trim($safeBusinessDir, '_'));

        // Handle GST certificate upload - only if new file is uploaded
        $gst_certificate_path = $vendor['gst_certificate'] ?? ''; // Keep existing path
        if (
            isset($_FILES['gst_certificate']) &&
            isset($_FILES['gst_certificate']['tmp_name']) &&
            is_uploaded_file($_FILES['gst_certificate']['tmp_name']) &&
            isset($_FILES['gst_certificate']['error']) && $_FILES['gst_certificate']['error'] === 0 &&
            (int)($_FILES['gst_certificate']['size'] ?? 0) > 0
        ) {
            $gst_file = $_FILES['gst_certificate'];
            $gst_extension = pathinfo($gst_file['name'], PATHINFO_EXTENSION);
            $gst_filename = 'gst_' . time() . '_' . uniqid() . '.' . $gst_extension;
            $gst_upload_dir = __DIR__ . '/../../../public/uploads/vendors/' . $safeBusinessDir . '/';
            if (!file_exists($gst_upload_dir)) {
                @mkdir($gst_upload_dir, 0777, true);
            }
            $gst_upload_path = $gst_upload_dir . $gst_filename;
            if (@move_uploaded_file($gst_file['tmp_name'], $gst_upload_path)) {
                // Delete old file if it exists
                if (!empty($vendor['gst_certificate'])) {
                    $old_file_path = __DIR__ . '/../../../public/' . $vendor['gst_certificate'];
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $gst_certificate_path = 'uploads/vendors/' . $safeBusinessDir . '/' . $gst_filename;
            }
        }

        // Handle cancelled cheque upload - only if new file is uploaded
        $cancelled_cheque_path = $vendor['cancelled_cheque'] ?? ''; // Keep existing path
        if (
            isset($_FILES['cancelled_cheque']) &&
            isset($_FILES['cancelled_cheque']['tmp_name']) &&
            is_uploaded_file($_FILES['cancelled_cheque']['tmp_name']) &&
            isset($_FILES['cancelled_cheque']['error']) && $_FILES['cancelled_cheque']['error'] === 0 &&
            (int)($_FILES['cancelled_cheque']['size'] ?? 0) > 0
        ) {
            $cheque_file = $_FILES['cancelled_cheque'];
            $cheque_extension = pathinfo($cheque_file['name'], PATHINFO_EXTENSION);
            $cheque_filename = 'cheque_' . time() . '_' . uniqid() . '.' . $cheque_extension;
            $cheque_upload_dir = __DIR__ . '/../../../public/uploads/vendors/' . $safeBusinessDir . '/';
            if (!file_exists($cheque_upload_dir)) {
                @mkdir($cheque_upload_dir, 0777, true);
            }
            $cheque_upload_path = $cheque_upload_dir . $cheque_filename;
            if (@move_uploaded_file($cheque_file['tmp_name'], $cheque_upload_path)) {
                // Delete old file if it exists
                if (!empty($vendor['cancelled_cheque'])) {
                    $old_file_path = __DIR__ . '/../../../public/' . $vendor['cancelled_cheque'];
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $cancelled_cheque_path = 'uploads/vendors/' . $safeBusinessDir . '/' . $cheque_filename;
            }
        }

        $update_query = "UPDATE vendor_registration SET 
            business_name = '$business_name',
            business_address = '$business_address',
            business_type = '$business_type',
            business_type_other = '$business_type_other',
            state = '$state',
            city = '$city',
            pincode = '$pincode',
            owner_name = '$owner_name',
            mobile_number = '$mobile_number',
            whatsapp_number = '$whatsapp_number',
            email = '$email',
            website_social = '$website_social',
            gst_number = '$gst_number',
            gst_certificate = '$gst_certificate_path',
            account_name = '$account_name',
            account_type = '$account_type',
            account_number = '$account_number',
            confirm_account_number = '$confirm_account_number',
            bank_name = '$bank_name',
            ifsc_code = '$ifsc_code',
            cancelled_cheque = '$cancelled_cheque_path',
            product_categories = '$product_categories',
            avg_products = '$avg_products',
            manufacture_products = '$manufacture_products',
            signature_name = '$signature_name',
            status = '$status',
            active_status = '$active_status',
            updated_at = NOW()
            WHERE id = '$vendor_id'";

        if (mysqli_query($con, $update_query)) {
            // Redirect to self to refresh data and reset form state
            header('Location: vendorDetail.php?vendor_id=' . urlencode($vendor_id) . '&updated=1');
            exit;
        } else {
            $error_message = "Error updating vendor details: " . mysqli_error($con);
        }
    }

    // Handle status changes
    if ($_POST['action'] == 'change_status') {
        $status_input = $_POST['status'] ?? 'pending';
        $comment = mysqli_real_escape_string($con, $_POST['comment'] ?? '');

        // Map input values to database values
        $status_map = [
            'hold' => 'hold',
            'reject' => 'rejected',
            'approve' => 'approved'
        ];

        $status = isset($status_map[$status_input]) ? $status_map[$status_input] : 'pending';
        $status = mysqli_real_escape_string($con, $status ?? '');
        if ($status == 'approved') {
            $active_status = 'active';
        } else {
            $active_status = 'inactive';
        }

        $indianTime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $approvedAt = $indianTime->format('Y-m-d H:i:s');
        $rejectedAt = $indianTime->format('Y-m-d H:i:s');
        $holdAt = $indianTime->format('Y-m-d H:i:s');

        if ($status == 'approved') {
            $status_query = "UPDATE vendor_registration SET 
                status = '$status',
                status_note = '$comment',
                active_status = '$active_status',
                approved_at = '$approvedAt',
                rejected_at = '',
                hold_at = ''
                WHERE id = '$vendor_id'";
        } else if ($status == 'rejected') {
            $status_query = "UPDATE vendor_registration SET 
                status = '$status',
                status_note = '$comment',
                active_status = '$active_status',
                rejected_at = '$rejectedAt',
                approved_at = '',
                hold_at = ''
                WHERE id = '$vendor_id'";
        } else if ($status == 'hold') {
            $status_query = "UPDATE vendor_registration SET 
                status = '$status',
                status_note = '$comment',
                active_status = '$active_status',
                hold_at = '$holdAt',
                approved_at = '',
                rejected_at = ''
                WHERE id = '$vendor_id'";
        }

        if (mysqli_query($con, $status_query)) {
            $success_message = "Application {$status_input} successfully!";

            // Redirect back to the same vendor details page (PRG pattern)
            header('Location: vendorDetail.php?vendor_id=' . urlencode($vendor_id) . '&status_updated=' . urlencode($status_input));
            exit;
        } else {
            $error_message = "Error updating status: " . mysqli_error($con);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>SELLER-DETAILS | HADIDY</title>
    <meta name="Description" content="Hagidy-Super-Admin">
    <meta name="Author" content="Hagidy-Super-Admin">
    <meta name="keywords"
        content="blazor bootstrap, c# blazor, admin panel, blazor c#, template dashboard, admin, bootstrap admin template, blazor, blazorbootstrap, bootstrap 5 templates, dashboard, dashboard template bootstrap, admin dashboard bootstrap.">

    <!-- Favicon -->
    <link rel="icon" href="<?php echo PUBLIC_ASSETS; ?>images/admin/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/icons.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">


</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php'; ?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php'; ?>
        <!-- End::app-sidebar -->

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid ">
                <div class="d-md-flex d-block align-items-center   justify-content-between mt-4 page-header-breadcrumb">
                </div>


                <!-- Start:: row-2 -->
                <div class="row  justify-content-center">
                    <div class="col-12 col-xl-9 col-lg-9 col-md-12 col-sm-12">
                        <div
                            class="d-md-flex d-block align-items-center justify-content-between my-2 page-header-breadcrumb">
                            <div class="d-flex align-items-center gap-4">
                                <h1 class="page-title fw-semibold fs-18 mb-0">All Vendors</h1>

                            </div>
                            <div
                                class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                                <div class="ms-md-1 ms-0">
                                    <nav>
                                        <ol class="breadcrumb mb-0">
                                            <li class="breadcrumb-item">
                                                <a href="allVendors.php">All Vendors</a>
                                            </li>

                                            <li class="breadcrumb-item active" aria-current="page">Vendor Details</li>
                                        </ol>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <div class="card custom-card ">

                            <div class="card-body p-4">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <form id="vendorForm" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_vendor">
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                                <label class="form-label">Business/Store Name<span
                                                        class="text-danger">*</span></label>
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div class="dropdown mb-2">
                                                        <?php
                                                        $vStatus = strtolower($vendor['status']);
                                                        $statusText = $vStatus === 'approved' ? 'Approved' : ($vStatus === 'hold' ? 'Hold' : (($vStatus === 'rejected' || $vStatus === 'reject') ? 'Rejected' : 'Pending'));
                                                        $statusClass = $vStatus === 'approved' ? 'bg-outline-success' : ($vStatus === 'hold' ? 'bg-outline-info' : (($vStatus === 'rejected' || $vStatus === 'reject') ? 'bg-outline-danger' : 'bg-outline-warning'));
                                                        ?>
                                                        <button class="badge rounded-pill <?php echo $statusClass; ?> dropdown-toggle status-badge" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                            <?php echo $statusText; ?>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <li><a class="dropdown-item <?php echo $vStatus === 'approved' ? 'active' : ''; ?>" href="#" onclick="updateVendorStatus('approve', this)">Approve</a></li>
                                                            <li><a class="dropdown-item <?php echo $vStatus === 'hold' ? 'active' : ''; ?>" href="#" onclick="updateVendorStatus('hold', this)">Hold</a></li>
                                                            <li><a class="dropdown-item <?php echo ($vStatus === 'rejected' || $vStatus === 'reject') ? 'active' : ''; ?>" href="#" onclick="updateVendorStatus('reject', this)">Reject</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <input class="form-control" type="text" name="business_name" value="<?php echo htmlspecialchars($vendor['business_name']); ?>"
                                                    id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                    readonly="" required>
                                            </div>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <label class="form-label">Business Address<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="business_address" value="<?php echo htmlspecialchars($vendor['business_address']); ?>" disabled readonly required>
                                        </div>
                                        <label class="form-label">Business Type<span class="text-danger">*</span></label>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">

                                            <select class="form-select" name="business_type" aria-label="Disabled select example" disabled="">
                                                <option value="">Select Business Type</option>
                                                <?php
                                                $businessTypes = mysqli_query($con, "SELECT * FROM business_types ORDER BY type_name");
                                                while ($bt = mysqli_fetch_assoc($businessTypes)): ?>
                                                    <option value="<?php echo $bt['id']; ?>" <?php echo ($vendor['business_type'] == $bt['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($bt['type_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <!-- <label class="form-label">Business Type<span class="text-danger">*</span></label> -->
                                            <input class="form-control" type="text" name="business_type_other" value="<?php echo htmlspecialchars($vendor['business_type_other']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">State<span class="text-danger">*</span></label>
                                            <select class="form-select" name="state" aria-label="Disabled select example" disabled="">
                                                <?php
                                                $selected_state = $vendor['state'];
                                                $states = mysqli_query($con, "SELECT * FROM state WHERE country_id = 99 AND status = 1");
                                                while ($state = mysqli_fetch_assoc($states)) {
                                                    echo '<option value="' . $state['id'] . '" ' . ($selected_state == $state['id'] ? 'selected' : '') . '>' . $state['name'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">City<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="city" value="<?php echo htmlspecialchars($vendor['city']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Pincode<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="pincode" value="<?php echo htmlspecialchars($vendor['pincode']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Owner / Contact Person Name<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="owner_name" value="<?php echo htmlspecialchars($vendor['owner_name']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Mobile Number<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="mobile_number" value="<?php echo htmlspecialchars($vendor['mobile_number']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">WhatsApp Number<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($vendor['whatsapp_number']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Email Address<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="email" value="<?php echo htmlspecialchars($vendor['email']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Website Links / Social Media<span
                                                    class="text-danger"></span></label>
                                            <input class="form-control" type="text" name="website_social" value="<?php echo htmlspecialchars($vendor['website_social']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">GST Number<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="gst_number" value="<?php echo htmlspecialchars($vendor['gst_number']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">GST Certificate<span
                                                    class="text-danger">*</span></label><br>
                                            <div class="d-flex gap-2 align-items-center">
                                                <a href="#" class="btn btn-lg btn-primary w-auto" data-bs-toggle="modal" data-bs-target="#gstCertificateModal">View
                                                    Certificate</a>
                                                <div id="gst_upload_section" style="display: none;">
                                                    <input type="file" class="form-control" name="gst_certificate" id="gst_certificate" accept="image/*" style="display: none;" onchange="previewGSTCertificate(this)">
                                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('gst_certificate').click()">Upload New</button>
                                                </div>
                                            </div>
                                            <div id="gst_preview" class="mt-2" style="display: none;">
                                                <img id="gst_preview_img" src="" alt="GST Certificate Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                                            </div>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Account Name<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="account_name" value="<?php echo htmlspecialchars($vendor['account_name']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Account Type<span class="text-danger">*</span></label>
                                            <select class="form-select" name="account_type" aria-label="Disabled select example" disabled="">
                                                <option value="Savings" <?php echo ($vendor['account_type'] == 'Savings') ? 'selected' : ''; ?>>Savings</option>
                                                <option value="Current" <?php echo ($vendor['account_type'] == 'Current') ? 'selected' : ''; ?>>Current</option>
                                                <option value="Fixed Deposit" <?php echo ($vendor['account_type'] == 'Fixed Deposit') ? 'selected' : ''; ?>>Fixed Deposit</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Account Number<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="account_number" value="<?php echo htmlspecialchars($vendor['account_number']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Confirm Account Number<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="confirm_account_number" value="<?php echo htmlspecialchars($vendor['confirm_account_number']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Bank Name<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="bank_name" value="<?php echo htmlspecialchars($vendor['bank_name']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">IFSC Code<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="ifsc_code" value="<?php echo htmlspecialchars($vendor['ifsc_code']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div
                                            class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3 d-flex align-items-center">
                                            <label class="form-label">Uploaded Cancelled Cheque / Bank Passbook<span
                                                    class="text-danger">*</span></label>

                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3 ">
                                            <div class="d-flex gap-2 align-items-center">
                                                <a href="#" class="btn btn-lg btn-primary w-auto" data-bs-toggle="modal"
                                                    data-bs-target="#cancelledChequeModal">View Cancelled Cheque</a>
                                                <div id="cheque_upload_section" style="display: none;">
                                                    <input type="file" class="form-control" name="cancelled_cheque" id="cancelled_cheque" accept="image/*" style="display: none;" onchange="previewCancelledCheque(this)">
                                                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('cancelled_cheque').click()">Upload New</button>
                                                </div>
                                            </div>
                                            <div id="cheque_preview" class="mt-2" style="display: none;">
                                                <img id="cheque_preview_img" src="" alt="Cancelled Cheque Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                                            </div>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label">Categories of Products You Plan to Sell<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-control" name="product_categories[]" id="product-s" multiple>
                                                <?php
                                                $selected_categories = explode(',', $vendor['product_categories'] ?? '');
                                                $categories = mysqli_query($con, "SELECT * FROM category ORDER BY name");
                                                while ($category = mysqli_fetch_assoc($categories)):
                                                ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo in_array($category['id'], $selected_categories) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Average Number of Products You Intend to List<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="avg_products" aria-label="Disabled select example" disabled="">
                                                <option value="">Select Average Number of Products</option>
                                                <?php
                                                $avg_products = mysqli_query($con, "SELECT * FROM productno_list ORDER BY number");
                                                while ($avg_product = mysqli_fetch_assoc($avg_products)): ?>
                                                    <option value="<?php echo $avg_product['id']; ?>" <?php echo htmlspecialchars($vendor['avg_products']) == $avg_product['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($avg_product['number']); ?></option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Do you manufacture your products?<span
                                                    class="text-danger">*</span></label>
                                            <select class="form-select" name="manufacture_products" aria-label="Disabled select example" disabled="">
                                                <option value="1" <?php echo htmlspecialchars($vendor['manufacture_products']) == '1' ? 'selected' : ''; ?>>Yes</option>
                                                <option value="0" <?php echo htmlspecialchars($vendor['manufacture_products']) == '0' ? 'selected' : ''; ?>>No</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Name (as Signature)<span
                                                    class="text-danger">*</span></label>
                                            <input class="form-control" type="text" name="signature_name" value="<?php echo htmlspecialchars($vendor['signature_name']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="col-12 col-xl-6 col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <label class="form-label">Date<span class="text-danger">*</span></label>
                                            <input class="form-control" type="text" value="<?php echo htmlspecialchars($vendor['registration_date']); ?>"
                                                id="disabled-readonlytext" aria-label="Disabled input example" disabled=""
                                                readonly="">
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                                            <div id="editButtons" style="display: none;">
                                                <button type="submit" id="saveBtn" class="btn btn-lg btn-primary me-2">Save</button>
                                                <button type="button" id="cancelBtn" class="btn btn-lg btn-primary">Cancel</button>
                                            </div>
                                            <button type="button" id="editSellerBtn" class="btn btn-lg btn-primary reject-btn">Edit</button>
                                        </div>
                                </form>

                                <!-- Hidden form for status update (simple confirm) -->
                                <form id="vendorStatusForm" method="POST" style="display: none;">
                                    <input type="hidden" name="action" value="change_status">
                                    <input type="hidden" name="status" id="vendorStatusValue" value="">
                                    <input type="hidden" name="comment" id="vendorStatusComment" value="Status updated by admin">
                                </form>

                            </div>
                        </div>

                    </div>
                    <!-- End:: row-2 -->


                </div>
            </div>
            <!-- End::app-content -->


            <!-- GST Certificate Modal -->
            <div class="modal fade" id="gstCertificateModal" tabindex="-1"
                aria-labelledby="gstCertificateModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" style="border-radius:12px;">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-dark" id="gstCertificateModalLabel">GST Certificate</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <?php if (!empty($vendor['gst_certificate'])): ?>
                                <img src="<?php echo PUBLIC_ASSETS . $vendor['gst_certificate']; ?>" alt="GST Certificate"
                                    style="max-width:100%; border-radius:8px;">
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fe fe-info me-2"></i>
                                    No GST certificate uploaded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancelled Cheque Modal -->
            <div class="modal fade" id="cancelledChequeModal" tabindex="-1"
                aria-labelledby="cancelledChequeModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" style="border-radius:12px;">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-dark" id="cancelledChequeModalLabel">Cancelled Cheque / Bank Passbook</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <?php if (!empty($vendor['cancelled_cheque'])): ?>
                                <img src="<?php echo PUBLIC_ASSETS . $vendor['cancelled_cheque']; ?>" alt="Cancelled Cheque"
                                    style="max-width:100%; border-radius:8px;">
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fe fe-info me-2"></i>
                                    No cancelled cheque uploaded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>



            <!-- Footer Start -->
            <footer class="footer mt-auto py-3 bg-white text-center">
                <div class="container">
                    <span class="text-muted"> Copyright © 2025 <span id="year"></span> <a href="#"
                            class="text-primary fw-semibold"> Hagidy </a>.
                        Designed with <span class="bi bi-heart-fill text-danger"></span> by <a
                            href="javascript:void(0);">
                            <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology
                            </span>
                        </a>
                    </span>
                </div>
            </footer>
            <!-- Footer End -->

        </div>

        <!-- Vendor Status Update Confirmation Modal -->
        <!-- Vendor Status Update Confirmation Modal -->
        <div class="modal fade" id="vendorConfirmStatusModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                    <div class="modal-header border-0">
                        <h5 class="" id="statusModalTitle">Confirm Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p id="vendorConfirmStatusText" class="mb-3"></p>

                        <div id="commentSection" style="display: none;">
                            <label class="form-label">Reason / Comment <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="statusCommentInput" placeholder="Please provide a reason..." required>
                            <small class="text-muted">This will be saved and visible to the vendor.</small>
                        </div>
                    </div>
                    <div class="modal-footer border-0 justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="vendorConfirmStatusBtn">Confirm</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Scroll To Top -->
        <div class="scrollToTop">
            <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
        </div>
        <div id="responsive-overlay"></div>
        <!-- Scroll To Top -->

        <!-- Popper JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/@popperjs/core/umd/popper.min.js"></script>

        <!-- Bootstrap JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>

        <!-- Defaultmenu JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/defaultmenu.min.js"></script>

        <!-- Node Waves JS-->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/node-waves/waves.min.js"></script>

        <!-- Sticky JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/sticky.js"></script>

        <!-- Simplebar JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/simplebar/simplebar.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/simplebar.js"></script>

        <!-- Color Picker JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/@simonwep/pickr/pickr.es5.min.js"></script>


        <!-- Apex Charts JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/apexcharts/apexcharts.min.js"></script>

        <!-- Ecommerce-Dashboard JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/ecommerce-dashboard.js"></script>


        <!-- Custom-Switcher JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom-switcher.min.js"></script>

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>

        <!-- Internal Add Products JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/add-products.js"></script>

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>

</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editBtn = document.getElementById('editSellerBtn');
        var saveBtn = document.getElementById('saveBtn');
        var cancelBtn = document.getElementById('cancelBtn');
        var editButtons = document.getElementById('editButtons');
        var isEditing = false;
        var vendorForm = document.getElementById('vendorForm');
        var originalValues = {};

        // Store original values when page loads
        function storeOriginalValues() {
            document.querySelectorAll('.card-body input, .card-body select, .card-body textarea').forEach(function(el) {
                if (el.name) {
                    originalValues[el.name] = el.value;
                }
            });
        }

        // Restore original values
        function restoreOriginalValues() {
            Object.keys(originalValues).forEach(function(name) {
                var element = document.querySelector('[name="' + name + '"]');
                if (element) {
                    element.value = originalValues[name];
                }
            });
        }

        // Initialize original values
        storeOriginalValues();

        editBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Enable all fields
            document.querySelectorAll('.card-body input, .card-body select, .card-body textarea').forEach(function(el) {
                el.removeAttribute('disabled');
                el.removeAttribute('readonly');
                el.disabled = false;
                el.readOnly = false;
            });

            var choicesSelect = document.getElementById('product-s');
            if (choicesSelect) {
                choicesSelect.removeAttribute('disabled');
                choicesSelect.disabled = false;
            }

            // Show upload sections
            document.getElementById('gst_upload_section').style.display = 'block';
            document.getElementById('cheque_upload_section').style.display = 'block';

            // Show save/cancel buttons and hide edit button
            editBtn.style.display = 'none';
            editButtons.style.display = 'block';
            isEditing = true;
        });

        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Restore original values
            restoreOriginalValues();

            // Disable all fields
            document.querySelectorAll('.card-body input, .card-body select, .card-body textarea').forEach(function(el) {
                el.setAttribute('disabled', 'disabled');
                el.setAttribute('readonly', 'readonly');
                el.disabled = true;
                el.readOnly = true;
            });

            var choicesSelect = document.getElementById('product-s');
            if (choicesSelect) {
                choicesSelect.setAttribute('disabled', 'disabled');
                choicesSelect.disabled = true;
            }

            // Hide upload sections
            document.getElementById('gst_upload_section').style.display = 'none';
            document.getElementById('cheque_upload_section').style.display = 'none';

            // Hide save/cancel buttons and show edit button
            editButtons.style.display = 'none';
            editBtn.style.display = 'inline-block';
            isEditing = false;

            // Clear file inputs and previews
            document.getElementById('gst_certificate').value = '';
            document.getElementById('cancelled_cheque').value = '';
            document.getElementById('gst_preview').style.display = 'none';
            document.getElementById('cheque_preview').style.display = 'none';
        });

        saveBtn.addEventListener('click', function(e) {
            // Form validation will be handled by the form submit event
        });

        window.updateVendorStatus = function(newStatus, clickedElement) {
            const modalEl = document.getElementById('vendorConfirmStatusModal');
            const textEl = document.getElementById('vendorConfirmStatusText');
            const commentSection = document.getElementById('commentSection');
            const commentInput = document.getElementById('statusCommentInput');
            const modalTitle = document.getElementById('statusModalTitle');

            // Reset
            commentSection.style.display = 'none';
            commentInput.value = '';
            commentInput.removeAttribute('required');

            let label = '';
            let needsComment = false;

            switch (newStatus) {
                case 'approve':
                    label = 'Approve';
                    modalTitle.textContent = 'Approve Application';
                    break;
                case 'hold':
                    label = 'Hold';
                    modalTitle.textContent = 'Put on Hold';
                    needsComment = true;
                    break;
                case 'reject':
                    label = 'Reject';
                    modalTitle.textContent = 'Reject Application';
                    needsComment = true;
                    break;
            }

            textEl.textContent = `Are you sure you want to ${label.toLowerCase()} this vendor application?`;

            if (needsComment) {
                commentSection.style.display = 'block';
                commentInput.setAttribute('required', 'required');
            }

            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();

            // Handle Confirm Button
            const confirmBtn = document.getElementById('vendorConfirmStatusBtn');
            const newBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

            newBtn.addEventListener('click', function() {
                if (needsComment && !commentInput.value.trim()) {
                    commentInput.focus();
                    commentInput.classList.add('is-invalid');
                    return;
                }

                // Set values
                document.getElementById('vendorStatusValue').value = newStatus;
                document.getElementById('vendorStatusComment').value = commentInput.value.trim() ||
                    (newStatus === 'approve' ? 'Application approved by admin' :
                        newStatus === 'hold' ? 'Application put on hold' : 'Application rejected');

                document.getElementById('vendorStatusForm').submit();
            });
        };

        // Handle direct approval
        var approveBtn = document.getElementById('approveBtn');
        if (approveBtn) {
            approveBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to approve this application?')) {
                    var approveForm = document.getElementById('approveForm');
                    if (approveForm) {
                        approveForm.submit();
                    } else {}
                }
            });
        } else {}

        // Form validation
        vendorForm.addEventListener('submit', function(e) {
            var requiredFields = vendorForm.querySelectorAll('[required]');
            var isValid = true;

            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Status dropdown -> open confirmation modal
        var statusDropdown = document.getElementById('statusDropdown');
        var originalStatusValue = statusDropdown ? statusDropdown.value : null;
        var statusForm = document.getElementById('statusForm');
        var statusModalEl = document.getElementById('statusModal');
        var statusModal;

        if (statusDropdown) {
            statusDropdown.addEventListener('change', function() {
                var selected = this.value; // pending | hold | reject | approve
                var modalTitle = document.getElementById('statusModalLabel');
                if (modalTitle) {
                    switch (selected) {
                        case 'hold':
                            modalTitle.textContent = 'Put on Hold';
                            break;
                        case 'reject':
                            modalTitle.textContent = 'Reject Application';
                            break;
                        case 'approve':
                            modalTitle.textContent = 'Approve Application';
                            break;
                        default:
                            modalTitle.textContent = 'Change Status';
                    }
                }
                document.getElementById('statusValue').value = selected;
                if (!statusModal) {
                    statusModal = new bootstrap.Modal(statusModalEl);
                }
                statusModal.show();
            });
        }

        // If modal is closed without submitting, revert dropdown
        if (statusModalEl) {
            statusModalEl.addEventListener('hidden.bs.modal', function() {
                if (statusDropdown && statusForm && !statusForm.dataset.submitted) {
                    statusDropdown.value = originalStatusValue;
                }
                if (statusForm) {
                    statusForm.dataset.submitted = '';
                    document.getElementById('statusComment').value = '';
                }
            });
        }

        // Mark form as submitted so we don't revert on hide
        if (statusForm) {
            statusForm.addEventListener('submit', function() {
                this.dataset.submitted = '1';
            });
        }

        // Remove old status form validation (comment-based) as we now use simple confirm modal

        // Auto-hide alerts after 5 seconds (no auto-refresh)
        var alerts = document.querySelectorAll('.alert');

        if (alerts.length > 0) {
            // Hide alerts after 5 seconds
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        }
    });
</script>
<script src="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        new Choices('#product-s', {
            removeItemButton: true,
            placeholder: true,
            placeholderValue: '',
            searchEnabled: true,
            allowHTML: true
        });
    });

    // Preview functions for file uploads
    function previewGSTCertificate(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('gst_preview_img').src = e.target.result;
                document.getElementById('gst_preview').style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function previewCancelledCheque(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('cheque_preview_img').src = e.target.result;
                document.getElementById('cheque_preview').style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<!-- Add this script before </body> to open modal on Submit -->
<script>
    document.querySelector('.btn.btn-primary[style*="background:#3B4B6B"]').addEventListener('click', function(e) {
        e.preventDefault();
        var modal = new bootstrap.Modal(document.getElementById('confirmWithdrawModal'));
        modal.show();
    });
</script>



</body>

</html>
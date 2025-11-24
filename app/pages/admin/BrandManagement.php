    <?php 
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}


// ---------- Brand Images CRUD (Upload, List, Edit, Delete) ----------
// Ensure upload directory exists
$brandUploadDir = __DIR__ . '/../../../public/uploads/brand_images/';
if (!is_dir($brandUploadDir)) {
    @mkdir($brandUploadDir, 0777, true);
}

// Helper to validate and move uploaded image
function saveUploadedBrandImage(array $file, string $destDir): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'No file uploaded or upload error.'];
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        return [false, 'Only JPG, PNG or WEBP images are allowed.'];
    }
    $ext = $allowed[$mime];
    $filename = 'brand_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return [false, 'Failed to save uploaded file.'];
    }
    $relativePath = 'uploads/brand_images/' . $filename;
    return [true, $relativePath];
}

// Add new brand image
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_brand'])) {
    list($ok, $result) = saveUploadedBrandImage($_FILES['brand_image'] ?? [], $brandUploadDir);
    if ($ok) {
        $path = mysqli_real_escape_string($con, $result);
        mysqli_query($con, "INSERT INTO brand_images (image_path, status, created_at) VALUES ('" . $path . "', 'Active', NOW())");
        $_SESSION['success_message'] = 'Brand image added successfully.';
    } else {
        $_SESSION['error_message'] = $result;
    }
    header('Location: BrandManagement.php');
    exit;
}

// Toggle brand image status Active/Inactive
if (isset($_GET['toggle_id'])) {
    $toggle_id = (int)$_GET['toggle_id'];
    mysqli_query($con, "UPDATE brand_images SET status = CASE WHEN status='Active' THEN 'Inactive' ELSE 'Active' END WHERE id=" . $toggle_id . " LIMIT 1");
    $sel = mysqli_query($con, "SELECT status FROM brand_images WHERE id=" . $toggle_id . " LIMIT 1");
    if ($sel && mysqli_num_rows($sel) > 0) {
        $row = mysqli_fetch_assoc($sel);
        $isActive = $row['status'];
    }
    $_SESSION['success_message'] = 'Brand ' . ($isActive === 'Active' ? 'Activated' : 'Deactivated') . ' successfully.';
     header('Location: BrandManagement.php');
    exit;
}

// Update existing brand image (replace file)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_brand'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $sel = mysqli_query($con, "SELECT image_path FROM brand_images WHERE id=" . $id . " LIMIT 1");
        if ($sel && mysqli_num_rows($sel) > 0) {
            $row = mysqli_fetch_assoc($sel);
            list($ok, $result) = saveUploadedBrandImage($_FILES['brand_image'] ?? [], $brandUploadDir);
            if ($ok) {
                // Delete old file
                if (!empty($row['image_path'])) {
                    $old = __DIR__ . '/../../../public/' . ltrim($row['image_path'], '/\\');
                    if (is_file($old)) { @unlink($old); }
                }
                $path = mysqli_real_escape_string($con, $result);
                mysqli_query($con, "UPDATE brand_images SET image_path='" . $path . "' WHERE id=" . $id . " LIMIT 1");
                $_SESSION['success_message'] = 'Brand image updated successfully.';
            } else {
                $_SESSION['error_message'] = $result;
            }
        } else {
            $_SESSION['error_message'] = 'Brand image not found.';
        }
    }
 header('Location: BrandManagement.php');
    exit;
}

// Delete brand image
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $sel = mysqli_query($con, "SELECT image_path FROM brand_images WHERE id=" . $delete_id . " LIMIT 1");
    if ($sel && mysqli_num_rows($sel) > 0) {
        $row = mysqli_fetch_assoc($sel);
        if (!empty($row['image_path'])) {
            $filePath = __DIR__ . '/../../../public/' . ltrim($row['image_path'], '/\\');
            if (is_file($filePath)) { @unlink($filePath); }
        }
        mysqli_query($con, "DELETE FROM brand_images WHERE id=" . $delete_id . " LIMIT 1");
        $_SESSION['success_message'] = 'Brand image deleted.';
    } else {
        $_SESSION['error_message'] = 'Brand image not found.';
    }
    header('Location: BrandManagement.php');
    exit;
}

// Fetch all brand images
$brands_result = mysqli_query($con, "SELECT id, image_path, status, created_at FROM brand_images ORDER BY created_at DESC");
// Count
$total_records = $brands_result ? mysqli_num_rows($brands_result) : 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>
    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="Hagidy-Super-Admin">
    <title>BRAND MANAGEMENT | HADIDY</title>
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
    <!-- FlatPickr CSS -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.css">
    <!-- Choices Css -->
    <link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>libs/choices.js/public/assets/styles/choices.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Custom CSS for Address Truncation -->

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
    

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">
                <!-- Page Header -->
                <div class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <div>
                        <h1 class="page-title fw-semibold fs-18 mb-0">Brand Images</h1>
                        <p class="text-muted mb-0">Total: <?php echo $total_records; ?> images</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                        <input type="file" name="brand_image" accept="image/*" class="form-control" required>
                        <button type="submit" name="save_brand" class="btn btn-secondary btn-wave waves-effect waves-light">Upload</button>
                    </form>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">
                                 
                                         <div>
                         <!-- <button class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</button> -->
                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    
                                    <div class="selecton-order">
                                        <!-- Search removed for Brand Images page -->
                                    </div>
                                 
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col" style="width:80px">#</th>
                                            <th scope="col">Preview</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Created At</th>
                                            <th scope="col">Activate / Deactivate</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider">
                                        <?php 
                                        $row_number = 1;
                                        if($brands_result && mysqli_num_rows($brands_result) > 0) {
                                            while($brand = mysqli_fetch_assoc($brands_result)) {
                                                $created_on = $brand['created_at'] ? date('d, M Y - h:i A', strtotime($brand['created_at'])) : '-';
                                        ?>
                                        <tr>
                                            <td><?php echo $row_number; ?></td>
                                            <td>
                                                <img src="<?php echo PUBLIC_ASSETS; ?><?php echo htmlspecialchars($brand['image_path']); ?>" alt="brand" style="width:64px;height:64px;object-fit:contain;background:#fff;border:1px solid #eee;border-radius:6px;">
                                            </td>
                                            
                                            <td>
                                                <?php $isActive = (isset($brand['status']) ? $brand['status'] : 'Inactive') === 'Active'; ?>
                                                <?php if($isActive): ?>
                                                    <span class="badge rounded-pill bg-outline-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-outline-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                               <?php
                                                if (!empty($created_on)) {
                                                    try {
                                                        // Try to normalize if date is in "24, Sep 2025 - 11:29 AM" format
                                                        $normalized = str_replace([',', '-'], '', $created_on); // remove commas and dashes
                                                        $normalized = preg_replace('/\s+/', ' ', trim($normalized)); // clean spaces
                                                
                                                        // Attempt flexible parsing
                                                        $dt = DateTime::createFromFormat('j M Y h:i A', $normalized);
                                                        if (!$dt) {
                                                            // fallback: let DateTime try automatically
                                                            $dt = new DateTime($created_on);
                                                        }
                                                
                                                        // Convert to India timezone (if needed)
                                                        $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
                                                        echo $dt->format('d M Y, h:i A');
                                                    } catch (Exception $e) {
                                                        echo htmlspecialchars($created_on); // fallback to raw
                                                    }
                                                } else {
                                                    echo '—';
                                                }
                                                ?>

                                            </td>

          
                                            <td>
                                                <a href="#" class="btn btn-sm toggleBrandBtn <?php echo $isActive ? 'btn-outline-warning' : 'btn-outline-success'; ?>" data-brand-id="<?php echo (int)$brand['id']; ?>" data-current-status="<?php echo $isActive ? 'Active' : 'Inactive'; ?>">
                                                    <?php echo $isActive ? 'Deactivate' : 'Activate'; ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <form method="POST" enctype="multipart/form-data" class="d-inline">
                                                        <input type="hidden" name="id" value="<?php echo (int)$brand['id']; ?>">
                                                        <label class="btn btn-sm btn-outline-secondary mb-0">
                                                            Replace
                                                            <input type="file" name="brand_image" accept="image/*" class="d-none" onchange="this.form.submit()">
                                                        </label>
                                                        <input type="hidden" name="update_brand" value="1">
                                                    </form>
                                                   
                                                    <a href="#" class="i-icon-trash deleteBrandBtn" data-brand-id="<?php echo (int)$brand['id']; ?>" title="Delete">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php 
                                                $row_number++;
                                            }
                                        } else {
                                        ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                                    <p>No brand images found</p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php } ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->
            </div>
            <!-- End::app-content -->

             <!-- Cancel Order Confirmation Modal -->
            <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div class="modal-body text-center p-4">
                    <!-- Icon -->
                    <div class="mb-3">
                        <div style="width: 60px; height: 60px; background-color: #F45B4B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-info" style="color: white; font-size: 24px;"></i>
                        </div>
                    </div>
                    
                    <!-- Message -->
                    <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                        Are you sure, you want to Delete this Brand Image?
                    </h5>
                    
                    <!-- Buttons -->
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-outline-danger" id="cancelNoBtn" style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                            No
                        </button>
                        <button type="button" class="btn btn-primary" id="cancelYesBtn" style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                            Yes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activate/Deactivate Confirmation Modal (Brand Images) -->
    <div class="modal fade" id="toggleBrandImageModal" tabindex="-1" aria-labelledby="toggleBrandImageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <div style="width: 60px; height: 60px; background-color:#4A5568; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                            <i class="fas fa-question" style="color: white; font-size: 24px;"></i>
                        </div>
                    </div>
                    <h5 class="mb-4" id="toggleBrandImageModalLabel" style="color: #4A5568; font-weight: 600; font-size: 18px;">Are you sure?</h5>
                    <p id="toggleBrandImageText" class="text-muted"></p>
                    <div class="d-flex gap-3 justify-content-center mt-3">
                        <button type="button" class="btn btn-outline-danger" id="toggleBrandImageCancelBtn" style="border-radius: 8px; padding: 8px 24px; font-weight: 500;">No</button>
                        <button type="button" class="btn btn-primary" id="toggleBrandImageConfirmBtn" style="background-color:#4A5568; border-color:#4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">Yes</button>
                    </div>
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

        <!-- Date & Time Picker JS -->
            <script src="<?php echo PUBLIC_ASSETS; ?>libs/flatpickr/flatpickr.min.js"></script>
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/date&time_pickers.js"></script>

        <!-- Custom JS -->
        <script src="<?php echo PUBLIC_ASSETS; ?>js/admin/custom.js"></script>

     <!-- Modal JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all cancel buttons and modal elements
        const cancelBtns = document.querySelectorAll('.cancelOrderBtn');
        const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
        const cancelNoBtn = document.getElementById('cancelNoBtn');
        const cancelYesBtn = document.getElementById('cancelYesBtn');

        // Show modal when any delete button is clicked
        const deleteBtns = document.querySelectorAll('.deleteBrandBtn');
        deleteBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const brandId = btn.getAttribute('data-brand-id');
                cancelYesBtn.setAttribute('data-brand-id', brandId);
                modal.show();
            });
        });

        // Handle No button click
        if (cancelNoBtn) {
            cancelNoBtn.addEventListener('click', function() {
                modal.hide();
            });
        }

        // Handle Yes button click
        if (cancelYesBtn) {
            cancelYesBtn.addEventListener('click', function() {
                const brandId = cancelYesBtn.getAttribute('data-brand-id');
                if (brandId) {
                    window.location.href = 'BrandManagement.php?delete_id=' + brandId;
                }
            });
        }
        // Toggle brand image via confirm modal
        const toggleBrandModalEl = document.getElementById('toggleBrandImageModal');
        const toggleBrandModal = new bootstrap.Modal(toggleBrandModalEl);
        const toggleBrandText = document.getElementById('toggleBrandImageText');
        const toggleBrandConfirmBtn = document.getElementById('toggleBrandImageConfirmBtn');
        const toggleBrandCancelBtn = document.getElementById('toggleBrandImageCancelBtn');
        let pendingBrandToggleId = null;

        document.querySelectorAll('.toggleBrandBtn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                pendingBrandToggleId = btn.getAttribute('data-brand-id');
                const current = btn.getAttribute('data-current-status');
                const next = current === 'Active' ? 'deactivate' : 'activate';
                toggleBrandText.textContent = `Are you sure you want to ${next} this brand image?`;
                toggleBrandModal.show();
            });
        });

        if (toggleBrandCancelBtn) {
            toggleBrandCancelBtn.addEventListener('click', function(){
                pendingBrandToggleId = null;
                toggleBrandModal.hide();
            });
        }

        if (toggleBrandConfirmBtn) {
            toggleBrandConfirmBtn.addEventListener('click', function(){
                if (pendingBrandToggleId) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('toggle_id', pendingBrandToggleId);
                    window.location.href = url.toString();
                }
            });
        }
    });
</script>



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

    
<script>
    document.addEventListener('DOMContentLoaded', function () {
        new Choices('#product-s2', {
            removeItemButton: true,
            placeholder: true,
            placeholderValue: '',
            searchEnabled: true,
            allowHTML: true
        });
    });
</script>

</body>

</html>
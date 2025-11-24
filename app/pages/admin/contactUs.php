<?php
include __DIR__ . '/../includes/init.php';
if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

$superadmin_id = $_SESSION['superadmin_id'];

// Export to Excel (all filtered data, no pagination)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Clean all output buffers FIRST to prevent corruption
    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    } else {
        @ob_end_clean();
    }
    
    // Load PhpSpreadsheet
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if ($autoloadPath && file_exists($autoloadPath)) { require_once $autoloadPath; }
    if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
        // Clean buffers before error message
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        echo 'Excel export packages not found. Please install PhpSpreadsheet via Composer.';
        exit;
    }

    try {
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $where = '';
        if ($q !== '') {
            $safe = mysqli_real_escape_string($con, $q);
            $like = "%$safe%";
            $where = "WHERE (query_id LIKE '$like' OR name LIKE '$like' OR mobile LIKE '$like' OR email LIKE '$like' OR message LIKE '$like')";
        }

        $rs = mysqli_query($con, "SELECT id, query_id, name, mobile, email, message, timestamp, status FROM contactus $where ORDER BY timestamp DESC");

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contact Us');

        // Headers
        $headers = [
            'A1' => 'ID',
            'B1' => 'Query ID',
            'C1' => 'Name',
            'D1' => 'Mobile',
            'E1' => 'Email',
            'F1' => 'Message',
            'G1' => 'Timestamp',
            'H1' => 'Status',
        ];
        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $rowNum = 2;
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) {
                $sheet->setCellValue('A' . $rowNum, $row['id']);
                $sheet->setCellValue('B' . $rowNum, $row['query_id']);
                $sheet->setCellValue('C' . $rowNum, $row['name']);
                $sheet->setCellValue('D' . $rowNum, $row['mobile']);
                $sheet->setCellValue('E' . $rowNum, $row['email']);
                $sheet->setCellValue('F' . $rowNum, $row['message']);
                $sheet->setCellValue('G' . $rowNum, date('d,M Y h:i A', strtotime($row['timestamp'])));
                $sheet->setCellValue('H' . $rowNum, $row['status']);
                $rowNum++;
            }
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Clean output buffers again before sending headers
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        } else {
            @ob_end_clean();
        }

        // Set proper headers for Excel download
        $fileName = 'contactus_export_' . date('Ymd_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');

        // Write file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
    } catch (\Throwable $e) {
        // Clean buffers before error message
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        header('Content-Type: text/plain');
        header('Cache-Control: no-cache');
        http_response_code(500);
        echo 'Failed to export Contact Us: ' . $e->getMessage();
        exit;
    }
}

// Inline AJAX handlers: update status / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax'];
    if ($action === 'update_status') {
        $id = (int) ($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowed = ['Resolved', 'Pending', 'In-Progress'];
        if ($id > 0 && in_array($status, $allowed, true)) {
            $sid = mysqli_real_escape_string($con, (string) $id);
            $sst = mysqli_real_escape_string($con, $status);
            $ok = mysqli_query($con, "UPDATE contactus SET status='$sst' WHERE id='$sid' LIMIT 1");
            echo json_encode(['success' => (bool) $ok]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
        }
        exit;
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $sid = mysqli_real_escape_string($con, (string) $id);
            $ok = mysqli_query($con, "DELETE FROM contactus WHERE id='$sid' LIMIT 1");
            echo json_encode(['success' => (bool) $ok]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid id']);
        }
        exit;
    }
}

// List renderer (returns HTML rows) - GET ajax=list
if (isset($_GET['ajax']) && $_GET['ajax'] === 'list') {
    header('Content-Type: text/html; charset=UTF-8');
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $where = '';
    if ($q !== '') {
        $safe = mysqli_real_escape_string($con, $q);
        $like = "%$safe%";
        $where = "WHERE (query_id LIKE '$like' OR name LIKE '$like' OR mobile LIKE '$like' OR email LIKE '$like' OR message LIKE '$like')";
    }
    $rs = mysqli_query($con, "SELECT id, query_id, name, mobile, email, message, timestamp, status FROM contactus $where ORDER BY timestamp DESC");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $rowNum = 1;
        while ($row = mysqli_fetch_assoc($rs)) {
            $badgeClass = 'bg-outline-secondary';
            if ($row['status'] === 'Resolved')
                $badgeClass = 'bg-outline-success';
            elseif ($row['status'] === 'Pending')
                $badgeClass = 'bg-outline-danger';
            elseif ($row['status'] === 'In-Progress')
                $badgeClass = 'bg-outline-warning';
            ?>
            <tr data-id="<?php echo (int) $row['id']; ?>">
                <td><?php echo $rowNum++; ?></td>
                <td>Contact</td>
                <td class="rqu-id">#<?php echo htmlspecialchars($row['query_id']); ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><span><?php echo nl2br(htmlspecialchars($row['message'])); ?></span></td>
                <td><?php 
                
                if (!empty($row['timestamp'])) {
                    $dt_timestamp = new DateTime($row['timestamp'], new DateTimeZone('UTC')); // assuming stored as UTC/server time
                    $dt_timestamp->setTimezone(new DateTimeZone('Asia/Kolkata'));
                    echo $dt_timestamp->format('d M Y, h:i A');
                } else {
                    echo '—';
                }
                ?></td>
                <td>
                    <div class="dropdown">
                        <button class="badge rounded-pill <?php echo $badgeClass; ?> dropdown-toggle status-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item status-option" data-status="Resolved" href="#">Resolved</a></li>
                            <li><a class="dropdown-item status-option" data-status="Pending" href="#">Pending</a></li>
                            <li><a class="dropdown-item status-option" data-status="In-Progress" href="#">In-Progress</a></li>
                        </ul>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" class="i-icon-trash delete-contact"><i class="fa-solid fa-trash-can"></i></a>
                    </div>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr>
        <td colspan="10" class="text-center">
        <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>
        <div class="text-muted">No contact queries found.</div>
        </td></tr>';
    }
    exit;
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
    <title>CONTACT-US | HADIDY</title>
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
                <div
                    class="d-flex align-items-center justify-content-between my-3 page-header-breadcrumb gap-2 flex-wrap">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Contact Us</h1>

                </div>
                <!-- Page Header Close -->
                <!-- Start:: row-2 -->
                <div class="row">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap w-100-product">

                                    <div>
                                        <?php $exportUrl = basename(__FILE__) . '?' . http_build_query(array_merge($_GET, ['export' => 'excel', 'q' => isset($_GET['q']) ? $_GET['q'] : ''])); ?>
                                        <a href="<?php echo htmlspecialchars($exportUrl); ?>"
                                            class="btn-down-excle1 w-100"><i class="fa-solid fa-file-arrow-down"></i>
                                            Export to Excel</a>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">

                                    <div class="selecton-order position-relative">
                                        <input type="search" id="contact-search" class="form-control"
                                            placeholder="Search" autocomplete="off">
                                        <button type="button" id="contact-clear"
                                            class="btn btn-link position-absolute end-0 top-50 translate-middle-y px-2"
                                            style="text-decoration:none; display:none;"></button>
                                    </div>

                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped text-nowrap align-middle table-bordered-vertical">
                                    <thead class="table-group-divider">
                                        <tr>
                                            <th scope="col">No</th>
                                            <th scope="col">Type</th>
                                            <th scope="col">Query ID</th>
                                            <th scope="col">Name</th>
                                            <th scope="col">Mobile Number</th>
                                            <th scope="col">Email Address</th>
                                            <th scope="col">Query Message</th>
                                            <th scope="col">Timestamp</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="table-group-divider" id="contact-tbody">
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-center mt-3 mb-3">
                                <nav>
                                    <ul class="pagination pagination-sm mb-0" id="contact-pagination"></ul>
                                </nav>
                            </div>
                        </div>

                    </div>
                </div>
                <!-- End:: row-2 -->
            </div>
            <!-- End::app-content -->

            <!-- Cancel Order Confirmation Modal -->
            <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content"
                        style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="modal-body text-center p-4">
                            <!-- Icon -->
                            <div class="mb-3">
                                <div
                                    style="width: 60px; height: 60px; background-color: #F45B4B; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto;">
                                    <i class="fas fa-info" style="color: white; font-size: 24px;"></i>
                                </div>
                            </div>

                            <!-- Message -->
                            <h5 class="mb-4" style="color: #4A5568; font-weight: 600; font-size: 18px;">
                                Are you sure, you want to Delete <br> the Contact ?
                            </h5>

                            <!-- Buttons -->
                            <div class="d-flex gap-3 justify-content-center">
                                <button type="button" class="btn btn-outline-danger" id="cancelNoBtn"
                                    style="  border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    No
                                </button>
                                <button type="button" class="btn btn-primary" id="cancelYesBtn"
                                    style="background-color: #4A5568; border-color: #4A5568; border-radius: 8px; padding: 8px 24px; font-weight: 500;">
                                    Yes
                                </button>
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
            document.addEventListener('DOMContentLoaded', function () {
                // Load table initially
                function loadList() {
                    const q = (document.getElementById('contact-search')?.value || '').trim();
                    const url = 'contactUs.php?ajax=list' + (q ? ('&q=' + encodeURIComponent(q)) : '') + '&_=' + Date.now();
                    fetch(url, { headers: { 'Accept': 'text/html' }, cache: 'no-store' })
                        .then(r => r.text())
                        .then(html => {
                            const tbody = document.getElementById('contact-tbody');
                            const content = (html || '').trim();
                            if (!content) {
                                tbody.innerHTML = '<tr>\
        <td colspan="10" class="text-center">\
        <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>\
        <div class="text-muted">No contact queries found.</div>\
        </td></tr>';
                            } else {
                                tbody.innerHTML = content;
                            }
                        })
                        .catch(() => {
                            const tbody = document.getElementById('contact-tbody');
                            if (tbody) {
                                tbody.innerHTML = '<tr>\
        <td colspan="10" class="text-center">\
        <i class="fe fe-inbox fs-48 mb-3 text-muted"></i>\
        <div class="text-muted">No contact queries found.</div>\
        </td></tr>';
                            }
                        });
                }
                loadList();

                // Search with debounce and clear button
                const searchEl = document.getElementById('contact-search');
                const clearEl = document.getElementById('contact-clear');
                let debounceTimer = null;
                if (searchEl) {
                    searchEl.addEventListener('input', function () {
                        clearEl.style.display = this.value.trim() ? 'inline' : 'none';
                        if (debounceTimer) clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(loadList, 300);
                    });
                }
                if (clearEl) {
                    clearEl.addEventListener('click', function () {
                        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
                        clearEl.style.display = 'none';
                        loadList();
                    });
                }

                // Delegate status change with confirmation modal
                document.addEventListener('click', function (e) {
                    const opt = e.target.closest('.status-option');
                    if (!opt) return;
                    e.preventDefault();
                    const tr = opt.closest('tr');
                    const id = tr ? tr.getAttribute('data-id') : '';
                    const status = opt.getAttribute('data-status');
                    if (!id || !status) return;

                    const modalEl = document.getElementById('confirmContactStatusModal');
                    if (!modalEl) return;
                    const textEl = modalEl.querySelector('#contactConfirmStatusText');
                    if (textEl) { textEl.textContent = 'Are you sure you want to update the status to ' + status + '?'; }
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();

                    const yesBtn = modalEl.querySelector('#contactConfirmStatusBtn');
                    const onYes = () => {
                        const fd = new FormData(); fd.append('ajax', 'update_status'); fd.append('id', id); fd.append('status', status);
                        fetch('contactUs.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(j => { if (j && j.success) { loadList(); } })
                            .catch(() => { })
                            .finally(() => { modal.hide(); cleanup(); });
                    };
                    function cleanup() { yesBtn?.removeEventListener('click', onYes); }
                    yesBtn?.addEventListener('click', onYes);
                    modalEl.addEventListener('hidden.bs.modal', function handleHide() { cleanup(); modalEl.removeEventListener('hidden.bs.modal', handleHide); });
                });

                // Delegate delete
                document.addEventListener('click', function (e) {
                    const del = e.target.closest('.delete-contact');
                    if (!del) return;
                    e.preventDefault();
                    const tr = del.closest('tr');
                    const id = tr ? tr.getAttribute('data-id') : '';
                    if (!id) return;
                    // open confirm modal; on yes perform delete
                    const modalEl = document.getElementById('cancelOrderModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                    const noBtn = document.getElementById('cancelNoBtn');
                    const yesBtn = document.getElementById('cancelYesBtn');
                    const onNo = () => { modal.hide(); cleanup(); };
                    const onYes = () => {
                        const fd = new FormData(); fd.append('ajax', 'delete'); fd.append('id', id);
                        fetch('contactUs.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(j => { if (j && j.success) { loadList(); } })
                            .catch(() => { })
                            .finally(() => { modal.hide(); cleanup(); });
                    };
                    function cleanup() {
                        noBtn?.removeEventListener('click', onNo);
                        yesBtn?.removeEventListener('click', onYes);
                    }
                    noBtn?.addEventListener('click', onNo);
                    yesBtn?.addEventListener('click', onYes);
                });
                // Get all cancel buttons and modal elements
                const cancelBtns = document.querySelectorAll('.cancelOrderBtn');
                const modal = new bootstrap.Modal(document.getElementById('cancelOrderModal'));
                const cancelNoBtn = document.getElementById('cancelNoBtn');
                const cancelYesBtn = document.getElementById('cancelYesBtn');

                // Show modal when any cancel button is clicked
                cancelBtns.forEach(function (btn) {
                    btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        modal.show();
                    });
                });

                // Handle No button click
                if (cancelNoBtn) {
                    cancelNoBtn.addEventListener('click', function () {
                        modal.hide();
                    });
                }

                // Handle Yes button click
                // Deletion handled above; remove demo alert
            });
        </script>
        <!-- Confirm Contact Status Change Modal -->
        <div class="modal fade" id="confirmContactStatusModal" tabindex="-1"
            aria-labelledby="confirmContactStatusModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-black fw-bold" id="confirmContactStatusModalLabel">Confirm Status
                            Change</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="d-flex flex-column align-items-center justify-content-center">
                            <div class="d-flex align-items-center justify-content-center mb-2">
                                <i class="ti ti-alert-triangle text-warning me-2 fs-1"></i>
                            </div>
                            <p class="text-black mb-0" id="contactConfirmStatusText">Are you sure?</p>
                        </div>
                    </div>
                    <div class="modal-footer p-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="contactConfirmStatusBtn">Yes, Update</button>
                    </div>
                </div>
            </div>
        </div>
       
        <script src="<?php echo PUBLIC_ASSETS; ?>libs/bootstrap/js/bootstrap.bundle.min.js"></script>


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
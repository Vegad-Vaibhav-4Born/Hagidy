<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include '../config/db.php';
if(!isset($_SESSION['vendor_reg_id'])){
    header('Location: login.php');
}
$vendor_reg_id = $_SESSION['vendor_reg_id'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light"
    data-menu-styles="dark" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="hagidy website" content="hagidy website">
    <title>WITHDRAW-REQUEST | HADIDY</title>
    <meta name="Description" content="hagidy website">
    <meta name="Author" content="hagidy website">
    <meta name="keywords"
        content="hagidy website">

    <!-- Favicon -->
    <link rel="icon" href="./assets/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Choices JS -->
    <script src="./assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>

    <!-- Main Theme Js -->
    <script src="./assets/js/main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="./assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Style Css -->
    <link href="./assets/css/styles.min.css" rel="stylesheet">

    <!-- Icons Css -->
    <link href="./assets/css/icons.css" rel="stylesheet">

    <!-- Node Waves Css -->
    <link href="./assets/libs/node-waves/waves.min.css" rel="stylesheet">

    <!-- Simplebar Css -->
    <link href="./assets/libs/simplebar/simplebar.min.css" rel="stylesheet">

    <!-- Color Picker Css -->
    <link rel="stylesheet" href="./assets/libs/flatpickr/flatpickr.min.css">
    <link rel="stylesheet" href="./assets/libs/@simonwep/pickr/themes/nano.min.css">

    <!-- Choices Css -->
    <link rel="stylesheet" href="./assets/libs/choices.js/public/assets/styles/choices.min.css">


</head>

<body>

    <!-- Loader -->
    <div id="loader">
        <img src="./assets/images/media/loader.svg" alt="">
    </div>
    <!-- Loader -->

    <div class="page">
        <!-- app-header -->
        <?php include './include/header.php';?>
        <!-- /app-header -->
        <!-- Start::app-sidebar -->
        <?php include './include/sidebar.php';?>
        <!-- End::app-sidebar -->
         

        <!-- Start::app-content -->
        <div class="main-content app-content">
            <div class="container-fluid">

                <!-- Page Header -->
                <div class="d-md-flex d-block align-items-center justify-content-between my-2 page-header-breadcrumb">
                    <div class="d-flex align-items-center gap-4">
                    <h1 class="page-title fw-semibold fs-18 mb-0">Select Orders </h1>
                    <div class="d-flex align-items-center gap-3" style="font-size: 14px;">
                        <span class="text-muted">Available for withdraw : <b style="color:#222;">₹29,368</b></span>
                        <span style="color:#bdbdbd;">||</span>
                        <span class="text-muted">Selected for withdrawal : <b style="color:#222;">₹9,368</b></span>
                        </div>
                        </div>
                    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb"><div class="ms-md-1 ms-0"> <nav> <ol class="breadcrumb mb-0"> <li class="breadcrumb-item"><a href="javascript:void(0);">Withdrawal Management</a></li> <li class="breadcrumb-item active" aria-current="page">Withdrawal Requests</li> </ol> </nav> </div> </div>
                </div>
                <!-- Page Header Close -->

                <!-- Start::row-1 -->
                <div class="row">
                    <div class="col-12">
                        
                    </div>
                </div>
             
                <div class="row">
                    <div class="col-12 col-xl-12 col-lg-12 col-md-12 col-sm-12">
                        <div class="card custom-card">

                            <div class="card-body">
                                 <div class="card-header justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="input-group">
                                        <div class="input-group-text text-muted"> <i class="ri-calendar-line"></i>
                                        </div> <input type="text" class="form-control flatpickr-input" id="daterange"
                                            placeholder="Date range picker" readonly="readonly">
                                    </div>
                                    <div>
                                        <select id="inputState" class="form-select form-select-lg"
                                            style="width: 150px;">
                                            <option selected="">Select Status</option>
                                            <option value="Order Confirmed">Order Confirmed</option>
                                            <option value="Shipped">Shipped</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div for="Default-sorting"><b>Sort By :</b></div>
                                    <select id="Default-sorting" class="form-select form-select-lg"
                                        style="width: 150px;">
                                        <option selected="">Default sorting</option>
                                        <option value="Order Confirmed">Sort by latest Order</option>
                                        <option value="Shipped">Sort by oldest Order</option>
                                        <option value="Shipped">Sort by Amount: high to low</option>
                                        <option value="Shipped">Sort by Amount: low to high</option>
                                    </select>
                                    <div>
                                        <input type="search" class="form-control" placeholder="Search "
                                            aria-describedby="button-addon2">
                                    </div>
                                </div>
                            </div>
                                 <div class="table-responsive">
            <table class="table table-striped text-nowrap align-middle">
                <thead class="table-light">
                    <tr>
                        <th></th>
                        <th>Order ID</th>
                        <th>Order Date</th>
                        <th>Transaction Id</th>
                        <th>Order Amount</th>
                        <th>Payment Method</th>
                        <th>Order Delivery Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>24,Nov 2022 - 04:42PM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹1,299</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>24,Nov 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>18,Nov 2022 - 06:53AM</td>
                        <td>-</td>
                        <td>₹799</td>
                        <td><span class="badge bg-secondary-transparent">Cash on Delivery</span></td>
                        <td>18,Nov 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>21,Oct 2022 - 11:36AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹349</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>21,Oct 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>16,Oct 2022 - 12:45AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹189</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>16,Oct 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>12,Aug 2022 - 11:21AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹2,499</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>12,Aug 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>05,Sep 2022 - 10:14AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹899</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>05,Sep 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>18,Nov 2022 - 14:35PM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹499</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>18,Nov 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>27,Nov 2022 - 05:12AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹999</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>27,Nov 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>29,Nov 2022 - 16:32PM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹1,499</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>29,Nov 2022</td>
                    </tr>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td><b>#132456</b></td>
                        <td>27,Nov 2022 - 05:12AM</td>
                        <td>ACRAF23DB3C4</td>
                        <td>₹999</td>
                        <td><span class="badge bg-success-transparent">Online Payment</span></td>
                        <td>27,Nov 2022</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-danger px-4">Clear</button>
            <button class="btn btn-primary px-4" style="background:#3B4B6B;">Submit</button>
        </div>
                            </div>
                        </div>
                    </div>
                 
                </div>
                <!-- End:: row-2 -->

            </div>
        </div>
        <!-- End::app-content -->


        <!-- Confirm Withdraw Modal -->
        <div class="modal fade" id="confirmWithdrawModal" tabindex="-1" aria-labelledby="confirmWithdrawModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:16px; padding:20px;">
            <div class="modal-body text-center py-4" >
                <div class="mb-4">
                <!-- Example SVG icon, replace src with your actual icon if needed -->
                <img src="./assets/images/confirm.png" alt="Withdraw Icon" style="width:56px; height:56px;">
                </div>
                <h4 class="fw-bold mb-4">Confirm withdrawal Amount</h4>
                <div class="d-flex justify-content-center align-items-center mb-2" style="gap:0;">
                <span class="d-flex align-items-center justify-content-center" style="background:#3B4B6B; color:#fff; font-size:22px; font-weight:500; border-radius:8px 0 0 8px; height:48px; width:48px;">₹</span>
                <span class="d-flex align-items-center justify-content-center bg-white" style="font-size:28px; font-weight:700; border-radius:0 8px 8px 0; height:48px; min-width:140px; border:1px solid #e5e7eb;">29,368</span>
                </div>
                <div class="mb-4" style="font-size:16px;">
                Available for withdrawal : <b>₹29,368</b>
                </div>
                 <div class="d-flex justify-content-center gap-4 mt-4">
          <button type="button" class="btn btn-outline-danger px-5 py-2" data-bs-dismiss="modal" style="font-weight:500; border-radius:12px; font-size:18px; border:2px solid #ff3c3c;">No</button>
          <button type="button" class="btn px-5 py-2" style="background:#3B4B6B; color:#fff; font-weight:500; border-radius:12px; font-size:18px;">Yes, Confirm</button>
        </div>
            </div>
            </div>
        </div>
        </div>

        <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModal" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="input-group">
                            <a href="javascript:void(0);" class="input-group-text" id="Search-Grid"><i
                                    class="fe fe-search header-link-icon fs-18"></i></a>
                            <input type="search" class="form-control border-0 px-2" placeholder="Search"
                                aria-label="Username">
                            <a href="javascript:void(0);" class="input-group-text" id="voice-search"><i
                                    class="fe fe-mic header-link-icon"></i></a>
                            <a href="javascript:void(0);" class="btn btn-light btn-icon" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <i class="fe fe-more-vertical"></i>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="javascript:void(0);">Action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Another action</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Something else here</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="javascript:void(0);">Separated link</a></li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <p class="font-weight-semibold text-muted mb-2">Are You Looking For...</p>
                            <span class="search-tags"><i class="fe fe-user me-2"></i>People<a href="javascript:void(0)"
                                    class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-file-text me-2"></i>Pages<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-align-left me-2"></i>Articles<a
                                    href="javascript:void(0)" class="tag-addon"><i class="fe fe-x"></i></a></span>
                            <span class="search-tags"><i class="fe fe-server me-2"></i>Tags<a href="javascript:void(0)"
                                    class="tag-addon"><i class="fe fe-x"></i></a></span>
                        </div>
                        <div class="my-4">
                            <p class="font-weight-semibold text-muted mb-2">Recent Search :</p>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="notifications.php"><span>Notifications</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-2 alert">
                                <a href="alerts.php"><span>Alerts</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                            <div class="p-2 border br-5 d-flex align-items-center text-muted mb-0 alert">
                                <a href="mail.php"><span>Mail</span></a>
                                <a class="ms-auto lh-1" href="javascript:void(0);" data-bs-dismiss="alert"
                                    aria-label="Close"><i class="fe fe-x text-muted"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="btn-group ms-auto">
                            <button class="btn btn-sm btn-primary-light">Search</button>
                            <button class="btn btn-sm btn-primary">Clear Recents</button>
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
                    Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                        <span class="fw-semibold text-sky-blue text-decoration-underline">Mechodal Technology </span>
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
    <script src="./assets/libs/@popperjs/core/umd/popper.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="./assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Defaultmenu JS -->
    <script src="./assets/js/defaultmenu.min.js"></script>

    <!-- Node Waves JS-->
    <script src="./assets/libs/node-waves/waves.min.js"></script>

    <!-- Sticky JS -->
    <script src="./assets/js/sticky.js"></script>

    <!-- Simplebar JS -->
    <script src="./assets/libs/simplebar/simplebar.min.js"></script>
    <script src="./assets/js/simplebar.js"></script>

    <!-- Color Picker JS -->
    <script src="./assets/libs/@simonwep/pickr/pickr.es5.min.js"></script>


    <!-- Apex Charts JS -->
    <script src="./assets/libs/apexcharts/apexcharts.min.js"></script>

    <!-- Ecommerce-Dashboard JS -->
    <script src="./assets/js/ecommerce-dashboard.js"></script>


    <!-- Custom-Switcher JS -->
    <script src="./assets/js/custom-switcher.min.js"></script>

    <!-- Custom JS -->
    <script src="./assets/js/custom.js"></script>

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
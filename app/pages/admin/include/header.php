<?php
include __DIR__ . '/../../includes/init.php';  

if (!isset($_SESSION['superadmin_id'])) {
    header('Location: login.php');
    exit;
}

$superadmin_id = $_SESSION['superadmin_id'];


$get_superadmin_query = "SELECT * FROM superadmin WHERE id = '$superadmin_id'";
$get_superadmin_result = mysqli_query($con, $get_superadmin_query);
if ($get_superadmin_result && mysqli_num_rows($get_superadmin_result) > 0) {
    $get_superadmin_row = mysqli_fetch_assoc($get_superadmin_result);
    $superadmin_name = $get_superadmin_row['name'] ?? 'Unknown';
} else {
    // No record found — maybe invalid session
    $superadmin_name = 'Unknown';
}


$get_notification_query = "SELECT * FROM notifications WHERE receiver_type = 'superadmin' AND receiver_id = '$superadmin_id' AND is_read = 0";
$get_notification_result = mysqli_query($con, $get_notification_query);
$notification_count = mysqli_num_rows($get_notification_result);
?>
 <link href="<?php echo PUBLIC_ASSETS; ?>css/admin/header.css" rel="stylesheet">
<header class="app-header">

    <!-- Start::main-header-container -->
    <div class="main-header-container container-fluid">

        <!-- Start::header-content-left -->
        <div class="header-content-left">

            <!-- Start::header-element -->
            <div class="header-element">
                <div class="horizontal-logo">
                    <a href="index.php" class="header-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/toggle-logo.png" alt="logo" class="toggle-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-dark">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="toggle-dark">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="desktop-white">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/main-logo.png" alt="logo" class="toggle-white">
                    </a>
                </div>
            </div>
            <!-- End::header-element -->

            <!-- Start::header-element -->
            <div class="header-element">
                <!-- Start::header-link -->
                <a aria-label="Hide Sidebar"
                    class="sidemenu-toggle header-link animated-arrow hor-toggle horizontal-navtoggle"
                    data-bs-toggle="sidebar" href="javascript:void(0);"><span></span></a>
                <!-- End::header-link -->
            </div>
            <!-- End::header-element -->

        </div>
        <!-- End::header-content-left -->

        <!-- Start::header-content-right -->
        <div class="header-content-right">
            <div class="header-element d-flex align-items-center seller-id">
                <h4>Super-Admin </h4>
            </div>
            <div class="header-element header-link dropdown-toggle">
                <div class="active-header">
                    Active
                </div>
            </div>
            <!-- Start::header-element -->
            <div class="header-element notifications-dropdown">
                <!-- Start::header-link|dropdown-toggle -->
                <a href="javascript:void(0);" class="header-link dropdown-toggle" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" id="messageDropdown" aria-expanded="false">
                    <i class="bx bx-bell header-link-icon"></i>
                    <span class="badge bg-secondary rounded-pill header-icon-badge pulse pulse-secondary"
                        id="notification-icon-badge"><?php echo $notification_count; ?></span>
                </a>
                <!-- End::header-link|dropdown-toggle -->
                <!-- Start::main-header-dropdown -->
                <div class="main-header-dropdown dropdown-menu dropdown-menu-end" data-popper-placement="none">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <p class="mb-0 fs-17 fw-semibold">Notifications</p>
                            <span class="badge bg-secondary-transparent"
                                id="notifiation-data"><?php echo $notification_count; ?> Unread</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <div class="p-5 empty-item1 d-none" id="empty-item1">
                        <div class="text-center">
                            <span class="avatar avatar-xl avatar-rounded bg-secondary-transparent">
                                <i class="ri-notification-off-line fs-2"></i>
                            </span>
                            <h6 class="fw-semibold mt-3">No New Notifications</h6>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0" id="header-notification-scroll"></ul>
                    <div class="p-3 empty-header-item1 border-top" id="empty-header-item1">
                        <div class="d-grid">
                            <a href="#" class="btn btn-primary">View All</a>
                        </div>
                    </div>

                </div>
                <!-- End::main-header-dropdown -->
            </div>
            <!-- End::header-element -->

            <!-- Start::header-element -->
            <div class="header-element">
                <!-- Start::header-link|dropdown-toggle -->
                <a href="javascript:void(0);" class="header-link dropdown-toggle" id="mainHeaderProfile"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <div class="d-flex align-items-center">
                        <div class="me-sm-2 me-0">
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/admin/user.png" alt="img" width="32" height="32"
                                class="rounded-circle">
                        </div>
                        <div class="d-sm-block d-none">
                            <p class="fw-semibold mb-0 lh-1"><?php echo $superadmin_name; ?></p>
                            <!-- <span class="op-7 fw-normal d-block fs-11">Web Designer</span> -->
                        </div>
                    </div>
                </a>
                <!-- End::header-link|dropdown-toggle -->
                <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                    aria-labelledby="mainHeaderProfile">
                    <!--<li><a class="dropdown-item d-flex" href="profile.php"><i-->
                    <!--            class="ti ti-user-circle fs-18 me-2 op-7"></i>Profile</a></li>-->
                    <li><a class="dropdown-item d-flex" href="javascript:void(0);" onclick="showLogoutConfirmation()"><i
                                class="ti ti-logout fs-18 me-2 op-7"></i>Log Out</a></li>
                </ul>
            </div>
            <!-- End::header-element -->

        </div>
        <!-- End::header-content-right -->

    </div>
    <!-- End::main-header-container -->

</header>

<script>
    // Dynamic superadmin notifications (mirrors vendor side)
    function saLoadNotifications() {
        fetch('<?php echo USER_BASEURL; ?>/app/api/get_notifications.php')
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) return;
                const count = data.unread_count || 0;
                const badge = document.getElementById('notification-icon-badge');
                const unreadBadge = document.getElementById('notifiation-data');
                if (badge) { badge.textContent = count; badge.style.display = count > 0 ? 'inline' : 'none'; }
                if (unreadBadge) { unreadBadge.textContent = count + ' Unread'; }

                const container = document.getElementById('header-notification-scroll');
                if (!container) return;
                if (!data.notifications || data.notifications.length === 0) {
                    container.innerHTML = '';
                    document.getElementById('empty-item1').classList.remove('d-none');
                    document.getElementById('empty-header-item1').classList.add('d-none');
                    return;
                }
                container.innerHTML = data.notifications.map(n => {
                    const icon = saIcon(n.type);
                    const bg = saBg(n.type);
                    const strong = n.is_read ? '' : 'fw-bold';
                    return `
                    <li class="dropdown-item notification-item" data-id="${n.id}">
                        <div class="d-flex align-items-start">
                            <div class="pe-2">
                                <span class="avatar avatar-md ${bg} avatar-rounded"><i class="${icon} fs-18"></i></span>
                            </div>
                            <div class="flex-grow-1 d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-0 ${strong}"><a href="${n.link_url || '#'}" onclick="saMarkRead(${n.id})">${n.title}</a></p>
                                    <span class="text-muted fw-normal fs-12 header-notification-text">${n.message}</span>
                                    <div class="text-muted fs-11 mt-1">${n.time_ago || ''}</div>
                                </div>
                                <div>
                                    <a href="javascript:void(0);" onclick="saMarkRead(${n.id})" class="min-w-fit-content text-muted me-1 dropdown-item-close1"><i class="ti ti-x fs-16"></i></a>
                                </div>
                            </div>
                        </div>
                    </li>`;
                }).join('');
            })
            .catch(() => { });
    }

    function saIcon(t) {
        const map = {
            order_new: 'ti ti-shopping-cart', order_status: 'ti ti-truck',
            registration_status: 'ti ti-user-check', kyc_status: 'ti ti-id',
            payment_status: 'ti ti-credit-card', default: 'ti ti-bell'
        };
        return map[t] || map.default;
    }
    function saBg(t) {
        const map = {
            order_new: 'bg-primary-transparent', order_status: 'bg-info-transparent',
            registration_status: 'bg-success-transparent', kyc_status: 'bg-warning-transparent',
            payment_status: 'bg-secondary-transparent', default: 'bg-primary-transparent'
        };
        return map[t] || map.default;
    }

    function saMarkRead(id) {
        const fd = new FormData(); fd.append('notification_id', id);
        fetch('./api/mark_notification_read.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => saLoadNotifications())
            .catch(() => { });
    }

    document.addEventListener('DOMContentLoaded', function () {
        saLoadNotifications();
        setInterval(saLoadNotifications, 30000);
    });

    // Logout functionality
    function showLogoutConfirmation() {
        const modalElement = document.getElementById('logoutConfirmationModal');
        modalElement.style.display = 'flex';
        modalElement.classList.add('show');
    }

    function confirmLogout() {
        // Hide confirmation modal
        const confirmationModalElement = document.getElementById('logoutConfirmationModal');
        confirmationModalElement.style.display = 'none';
        confirmationModalElement.classList.remove('show');

        // Show success modal
        const successModalElement = document.getElementById('logoutSuccessModal');
        successModalElement.style.display = 'flex';
        successModalElement.classList.add('show');

        // Start countdown
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');

        const timer = setInterval(() => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timer);
                // Redirect to logout.php
                window.location.href = './logout.php';
            }
        }, 1000);
    }

    function closeLogoutConfirmationModal() {
        const modalElement = document.getElementById('logoutConfirmationModal');
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
    }

    function closeLogoutSuccessModal() {
        const modalElement = document.getElementById('logoutSuccessModal');
        modalElement.style.display = 'none';
        modalElement.classList.remove('show');
        // Redirect to logout.php immediately
        window.location.href = './logout.php';
    }
</script>

<!-- Logout Confirmation Modal -->
<div class="login-required-modal" id="logoutConfirmationModal" style="display: none;">
    <div class="login-required-content">
        <div class="login-required-icon" style="color: #ffc107;">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <div class="login-required-title">Confirm Logout</div>
        <div class="login-required-message">
            Are you sure you want to logout? You will need to login again to access your account.
        </div>
        <div class="login-required-buttons">
            <button class="btn-login-required primary" onclick="confirmLogout()">Yes, Logout</button>
            <button class="btn-login-required secondary" onclick="closeLogoutConfirmationModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Logout Success Modal -->
<div class="login-required-modal" id="logoutSuccessModal" style="display: none;">
    <div class="login-required-content">
        <div class="login-required-icon" style="color: #28a745;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="login-required-title">Logged Out Successfully</div>
        <div class="login-required-message">
            You have been logged out successfully. Redirecting to login page in <span id="countdown">5</span> seconds...
        </div>
        <div class="login-required-buttons">
            <button class="btn-login-required primary" onclick="closeLogoutSuccessModal()">OK</button>
        </div>
    </div>
</div>

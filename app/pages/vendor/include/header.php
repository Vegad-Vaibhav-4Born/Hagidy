<?php
include __DIR__ . '/../../includes/init.php';  


$vendor_reg_id = isset($_SESSION['vendor_reg_id']) ? $_SESSION['vendor_reg_id'] : null;
$vendor_business_name = '';
$owner_name = '';
$active_status = '';
$notification_count = 0;
$status = '';
if ($vendor_reg_id && isset($con) && $con) {
    $rs = mysqli_query($con, "SELECT * FROM vendor_registration WHERE id = '" . mysqli_real_escape_string($con, $vendor_reg_id) . "'");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $vendor_details = mysqli_fetch_assoc($rs);
        $vendor_business_name = isset($vendor_details['business_name']) ? $vendor_details['business_name'] : '';
        $owner_name = isset($vendor_details['owner_name']) ? $vendor_details['owner_name'] : '';
        $active_status = isset($vendor_details['active_status']) ? $vendor_details['active_status'] : '';
        $status = isset($vendor_details['status']) ? $vendor_details['status'] : '';
        $vendor_id = isset($vendor_details['vendor_id']) ? $vendor_details['vendor_id'] : '';
        $seller_id = isset($vendor_details['seller_id']) ? $vendor_details['seller_id'] : '';
    }

    // Get notification count
    $notif_query = mysqli_query($con, "SELECT COUNT(*) as count FROM notifications WHERE receiver_type = 'vendor' AND receiver_id = '$vendor_reg_id' AND is_read = 0");
    if ($notif_query && mysqli_num_rows($notif_query) > 0) {
        $notif_data = mysqli_fetch_assoc($notif_query);
        $notification_count = $notif_data['count'] ?? 0;
    }
}

// Global access guard: block pages if vendor status is not approved/active
try {
    $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
    // Allow only profileSetting.php and logout.php when status is pending
    if (!empty($status) && $status === 'pending') {
        $allowedWhenPending = ['profileSetting.php', 'logout.php'];
        if (!in_array($currentPage, $allowedWhenPending, true)) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['flash_error'] = 'Your vendor account is pending approval. Access is restricted until activation.';
            if (!headers_sent()) {
                header('Location: ./profileSetting.php');
                exit;
            }
        }
    }
    // For hold or rejected, allow only profileSetting.php and logout.php
    if (!empty($status) && ($status === 'hold' || $status === 'rejected')) {
        $allowed = ['profileSetting.php', 'logout.php'];
        if (!in_array($currentPage, $allowed, true)) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['flash_error'] = ($status === 'hold')
                ? 'Your vendor account is on hold. Please contact support or update your profile.'
                : 'Your vendor account is rejected. Please contact support for further assistance.';
            if (!headers_sent()) {
                header('Location: ./profileSetting.php');
                exit;
            }
        }
    }
} catch (Throwable $e) {
    // fail open silently
}

?>
<link rel="stylesheet" href="<?php echo PUBLIC_ASSETS; ?>icon-fonts/RemixIcons/fonts/remixicon.css">
<header class="app-header">

    <!-- Start::main-header-container -->
    <div class="main-header-container container-fluid">

        <!-- Start::header-content-left -->
        <div class="header-content-left">

            <!-- Start::header-element -->
            <div class="header-element">
                <div class="horizontal-logo">
                    <a href="index.php" class="header-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/toggle-logo.png" alt="logo" class="toggle-logo">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-dark">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="toggle-dark">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="desktop-white">
                        <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/main-logo.png" alt="logo" class="toggle-white">
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
                <h4>Seller ID : <span class="text-sky-blue">#<?php echo $vendor_id; ?></span></h4>

            </div>
            <div class="header-element d-flex align-items-center">
                <?php if ($active_status == 'active') { ?>
                    <span class="badge rounded-pill bg-outline-success">Active</span>
                <?php } else { ?>
                    <span class="badge rounded-pill bg-outline-danger">Inactive</span>
                <?php } ?>
            </div>
          
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
                    <ul class="list-unstyled mb-0" id="header-notification-scroll">
                        <!-- Dynamic notifications will be loaded here -->
                    </ul>
                    <div class="p-3 empty-header-item1 border-top">
                        <div class="d-grid">
                            <a href="#" class="btn btn-primary">View All</a>
                        </div>
                    </div>
                    <div class="p-5 empty-item1 d-none">
                        <div class="text-center">
                            <span class="avatar avatar-xl avatar-rounded bg-secondary-transparent">
                                <i class="ri-notification-off-line fs-2"></i>
                            </span>
                            <h6 class="fw-semibold mt-3">No New Notifications</h6>
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
                            <img src="<?php echo PUBLIC_ASSETS; ?>images/vendor/user.png" alt="img" width="32" height="32"
                                class="rounded-circle">
                        </div>
                        <div class="d-sm-block d-none">
                            <p class="fw-semibold mb-0 lh-1">
                                <?php echo $owner_name; ?>
                            </p>
                            <span class="op-7 fw-normal d-block fs-11"><?php echo $vendor_business_name; ?></span>
                        </div>
                    </div>
                </a>
                <!-- End::header-link|dropdown-toggle -->
                <ul class="main-header-dropdown dropdown-menu pt-0 overflow-hidden header-profile-dropdown dropdown-menu-end"
                    aria-labelledby="mainHeaderProfile">
                    <li><a class="dropdown-item d-flex" href="profileSetting.php"><i
                                class="ti ti-user-circle fs-18 me-2 op-7"></i>Profile</a></li>
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
            You have been logged out successfully. Redirecting to login page in <span id="countdown">3</span> seconds...
        </div>
        <div class="login-required-buttons">
            <button class="btn-login-required primary" onclick="closeLogoutSuccessModal()">OK</button>
        </div>
    </div>
</div>

<script>
    // Load notifications dynamically
    function loadNotifications() {
        fetch('<?php echo USER_BASEURL; ?>app/api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge(data.unread_count);
                    renderNotifications(data.notifications);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    function updateNotificationBadge(count) {
        const badge = document.getElementById('notification-icon-badge');
        const unreadBadge = document.getElementById('notifiation-data');

        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }

        if (unreadBadge) {
            unreadBadge.textContent = count + ' Unread';
        }
    }

    function renderNotifications(notifications) {
        const container = document.getElementById('header-notification-scroll');
        const emptyItem = document.querySelector('.empty-item1');

        if (notifications.length === 0) {
            container.innerHTML = '';
            if (emptyItem) emptyItem.classList.remove('d-none');
            return;
        }

        if (emptyItem) emptyItem.classList.add('d-none');

        container.innerHTML = notifications.map(notification => {
            const iconClass = getNotificationIcon(notification.type);
            const bgClass = getNotificationBgClass(notification.type);
            const isReadClass = notification.is_read ? '' : 'fw-bold';

            return `
                    <li class="dropdown-item notification-item" data-id="${notification.id}">
                        <div class="d-flex align-items-start">
                            <div class="pe-2">
                                <span class="avatar avatar-md ${bgClass} avatar-rounded">
                                    <i class="${iconClass} fs-18"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 d-flex align-items-center justify-content-between">
                                <div>
                                    <p class="mb-0 fw-semibold ${isReadClass}">
                                        <a href="${notification.link_url || '#'}" onclick="markAsRead(${notification.id})">
                                            ${notification.title}
                                        </a>
                                    </p>
                                    <span class="text-muted fw-normal fs-12 header-notification-text">
                                        ${notification.message}
                                    </span>
                                    <div class="text-muted fs-11 mt-1">${notification.time_ago}</div>
                                </div>
                                <div>
                                    <a href="javascript:void(0);" onclick="markAsRead(${notification.id})" 
                                       class="min-w-fit-content text-muted me-1 dropdown-item-close1">
                                        <i class="ti ti-x fs-16"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                `;
        }).join('');
    }

    function getNotificationIcon(type) {
        const icons = {
            'order_new': 'ti ti-shopping-cart',
            'order_status': 'ti ti-truck',
            'registration_status': 'ti ti-user-check',
            'kyc_status': 'ti ti-id',
            'payment_status': 'ti ti-credit-card',
            'default': 'ti ti-bell'
        };
        return icons[type] || icons.default;
    }

    function getNotificationBgClass(type) {
        const classes = {
            'order_new': 'bg-primary-transparent',
            'order_status': 'bg-info-transparent',
            'registration_status': 'bg-success-transparent',
            'kyc_status': 'bg-warning-transparent',
            'payment_status': 'bg-secondary-transparent',
            'default': 'bg-primary-transparent'
        };
        return classes[type] || classes.default;
    }

    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('notification_id', notificationId);

        fetch('<?php echo USER_BASEURL; ?>app/api/mark_notification_read.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload notifications to update the UI
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
    }

    // Load notifications when page loads
    document.addEventListener('DOMContentLoaded', function () {
        loadNotifications();

        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
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
        let countdown = 3;
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
<link href="<?php echo PUBLIC_ASSETS; ?>css/vendor/header.css" rel="stylesheet">
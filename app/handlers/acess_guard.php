<?php
require_once __DIR__ . '/../pages/includes/init.php';

// Require logged-in vendor
if (!isset($_SESSION['vendor_reg_id'])) {
    if (!headers_sent()) { header('Location: login.php'); exit; }
}

$vendor_reg_id = $_SESSION['vendor_reg_id'] ?? null;

try {
    if ($vendor_reg_id) {
        $qid = mysqli_real_escape_string($con, $vendor_reg_id);
        $rs_guard = mysqli_query($con, "SELECT status FROM vendor_registration WHERE id='{$qid}' LIMIT 1");
        if ($rs_guard && mysqli_num_rows($rs_guard) > 0) {
            $row_guard = mysqli_fetch_assoc($rs_guard);
            $v_status = strtolower(trim($row_guard['status'] ?? ''));

            $currentPage = basename($_SERVER['PHP_SELF'] ?? '');
            $allowed = ['profileSetting.php','logout.php'];

            if (in_array($v_status, ['pending','hold','rejected'], true) && !in_array($currentPage, $allowed, true)) {
                $_SESSION['flash_error'] = (
                    $v_status === 'pending' ? 'Your vendor account is pending approval. Access is restricted until activation.' :
                    ($v_status === 'hold' ? 'Your vendor account is on hold. Please contact support or update your profile.' : 'Your vendor account is rejected. Please contact support for further assistance.')
                );
                if (!headers_sent()) { header('Location: ./profileSetting.php'); exit; }
            }
        }
    }
} catch (Throwable $e) { /* ignore */ }
?>
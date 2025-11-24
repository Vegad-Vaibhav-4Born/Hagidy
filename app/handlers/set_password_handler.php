<?php
require_once __DIR__ . '/../pages/includes/init.php';
function backTo($url) { header('Location: ' . $url); exit; }

function generateUniqueVendorId($con) {
  do {
      // Random 6 digit ID
      $vendor_id = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

      // Check if it already exists
      $check = mysqli_query($con, "SELECT 1 FROM vendor_registration WHERE vendor_id = '$vendor_id' LIMIT 1");
  } while (mysqli_num_rows($check) > 0);

  return $vendor_id;
}

// vendor_id column should store the seller_id we generate below
$vendorId = generateUniqueVendorId($con); // kept for backward-compatibility but not used
function generateSellerId($con) {
    
    // Get the highest existing customer_id
    $result = mysqli_query($con, "SELECT vendor_id FROM vendor_registration WHERE vendor_id IS NOT NULL ORDER BY vendor_id DESC LIMIT 1");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $last_seller_id = $row['vendor_id'];
        
        // Extract the numeric part (remove 'B' prefix)
        $numeric_part = substr($last_seller_id, 1);
        $next_number = intval($numeric_part) + 1;
    } else {
        // First customer
        $next_number = 1;
    }
    
    // Format as B + 9 digits (padded with zeros)
    $seller_id = 'S' . str_pad($next_number, 9, '0', STR_PAD_LEFT);
    
    return $seller_id;
}
$seller_id = generateSellerId($con);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { backTo(' '. USER_BASEURL . 'vendor/setNewPass.php'); }

if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] != 1 || !isset($_SESSION['pending_phone'])) {
    backTo('' . USER_BASEURL . 'vendor/registration.php');
}

$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';
if ($password === '' || $confirm === '' || $password !== $confirm) {
    $_SESSION['error_setpass'] = 'Passwords do not match.';
    backTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}

// Optional: enforce minimum length
if (strlen($password) < 6) {
    $_SESSION['error_setpass'] = 'Password must be at least 6 characters.';
    backTo('' . USER_BASEURL . 'vendor/setNewPass.php');
}

$phone = $_SESSION['pending_phone'];
$email = isset($_SESSION['pending_email']) ? $_SESSION['pending_email'] : null;
$reg = isset($_SESSION['vendor_registration']) ? $_SESSION['vendor_registration'] : [];

// Hash password
$hash = password_hash($password, PASSWORD_BCRYPT);

// Ensure users table has unique mobile - direct query with escaping
$phoneEsc = mysqli_real_escape_string($con, $phone);
$emailEsc = mysqli_real_escape_string($con, (string)$email);
$hashEsc = mysqli_real_escape_string($con, $hash);

// Insert full vendor_registration row with password hash
$status = 'pending';
$now = date('Y-m-d H:i:s');
// Insert vendor_registration with escaping
$fields = [
  'business_name','business_address','business_type','business_type_other','state','city','pincode','owner_name',
  'mobile_number','whatsapp_number','email','website_social','gst_number','gst_certificate','account_name','account_type',
  'account_number','confirm_account_number','bank_name','ifsc_code','cancelled_cheque','product_categories','avg_products',
  'manufacture_products','signature_name','registration_date'
];
$vals = [];
$vals['business_name'] = mysqli_real_escape_string($con, (string)($reg['business_name'] ?? ''));
$vals['business_address'] = mysqli_real_escape_string($con, (string)($reg['business_address'] ?? ''));
$vals['business_type'] = mysqli_real_escape_string($con, (string)($reg['business_type'] ?? ''));
$vals['business_type_other'] = mysqli_real_escape_string($con, (string)($reg['business_type_other'] ?? ''));
$vals['state'] = mysqli_real_escape_string($con, (string)($reg['state'] ?? ''));
$vals['city'] = mysqli_real_escape_string($con, (string)($reg['city'] ?? ''));
$vals['pincode'] = mysqli_real_escape_string($con, (string)($reg['pincode'] ?? ''));
$vals['owner_name'] = mysqli_real_escape_string($con, (string)($reg['owner_name'] ?? ''));
$vals['mobile_number'] = mysqli_real_escape_string($con, (string)$phone);
$vals['whatsapp_number'] = mysqli_real_escape_string($con, (string)($reg['whatsapp_number'] ?? ''));
$vals['email'] = mysqli_real_escape_string($con, (string)$email);
$vals['website_social'] = mysqli_real_escape_string($con, (string)($reg['website_social'] ?? ''));
$vals['gst_number'] = mysqli_real_escape_string($con, (string)($reg['gst_number'] ?? ''));
$vals['gst_certificate'] = mysqli_real_escape_string($con, (string)($reg['gst_certificate'] ?? ''));
$vals['account_name'] = mysqli_real_escape_string($con, (string)($reg['account_name'] ?? ''));
$vals['account_type'] = mysqli_real_escape_string($con, (string)($reg['account_type'] ?? ''));
$vals['account_number'] = mysqli_real_escape_string($con, (string)($reg['account_number'] ?? ''));
$vals['confirm_account_number'] = mysqli_real_escape_string($con, (string)($reg['confirm_account_number'] ?? ''));
$vals['bank_name'] = mysqli_real_escape_string($con, (string)($reg['bank_name'] ?? ''));
$vals['ifsc_code'] = mysqli_real_escape_string($con, (string)($reg['ifsc_code'] ?? ''));
$vals['cancelled_cheque'] = mysqli_real_escape_string($con, (string)($reg['cancelled_cheque'] ?? ''));
$vals['product_categories'] = mysqli_real_escape_string($con, (string)($reg['product_categories'] ?? ''));
$vals['avg_products'] = mysqli_real_escape_string($con, (string)($reg['avg_products'] ?? ''));
// Normalize manufacture_products to 'Yes' or 'No' to match DB schema
$manuRaw = isset($reg['manufacture_products']) ? (string)$reg['manufacture_products'] : '';
$manuNorm = strtolower(trim($manuRaw));
if (in_array($manuNorm, ['yes','1','true','y','plain'], true)) {
    $manuNorm = 'Yes';
} elseif (in_array($manuNorm, ['no','0','false','n','relaxed'], true)) {
    $manuNorm = 'No';
} else {
    $manuNorm = 'No';
}
$vals['manufacture_products'] = mysqli_real_escape_string($con, $manuNorm);
$vals['signature_name'] = mysqli_real_escape_string($con, (string)($reg['signature_name'] ?? ''));
$vals['registration_date'] = mysqli_real_escape_string($con, (string)($reg['registration_date'] ?? date('Y-m-d')));

$statusEsc = mysqli_real_escape_string($con, $status);
$nowEsc = mysqli_real_escape_string($con, $now);
$hashEsc = mysqli_real_escape_string($con, $hash);
$vendorIdEsc = mysqli_real_escape_string($con, $seller_id);
$seller_id = generateSellerId($con);
$sql = "INSERT INTO vendor_registration (
  vendor_id,business_name,business_address,business_type,business_type_other,state,city,pincode,owner_name,mobile_number,whatsapp_number,email,website_social,gst_number,gst_certificate,account_name,account_type,account_number,confirm_account_number,bank_name,ifsc_code,cancelled_cheque,product_categories,avg_products,manufacture_products,signature_name,registration_date,status,created_at,updated_at,password
) VALUES (
  '{$vendorIdEsc}','{$vals['business_name']}','{$vals['business_address']}','{$vals['business_type']}','{$vals['business_type_other']}','{$vals['state']}','{$vals['city']}','{$vals['pincode']}','{$vals['owner_name']}','{$vals['mobile_number']}','{$vals['whatsapp_number']}','{$vals['email']}','{$vals['website_social']}','{$vals['gst_number']}','{$vals['gst_certificate']}','{$vals['account_name']}','{$vals['account_type']}','{$vals['account_number']}','{$vals['confirm_account_number']}','{$vals['bank_name']}','{$vals['ifsc_code']}','{$vals['cancelled_cheque']}','{$vals['product_categories']}','{$vals['avg_products']}','{$vals['manufacture_products']}','{$vals['signature_name']}','{$vals['registration_date']}','{$statusEsc}','{$nowEsc}','{$nowEsc}','{$hashEsc}'
)";

$insertResult = mysqli_query($con, $sql);

// Check if insert failed due to duplicate email
if (!$insertResult) {
    $error = mysqli_error($con);
    if (strpos($error, 'Duplicate entry') !== false && strpos($error, 'email') !== false) {
        // Handle duplicate email - allow registration but set email to null or generate unique email
        $uniqueEmail = $vals['email'] . '_' . time() . '@duplicate.hagidy.com';
        $uniqueEmailEsc = mysqli_real_escape_string($con, $uniqueEmail);
        
        $sqlRetry = "INSERT INTO vendor_registration (
          vendor_id,business_name,business_address,business_type,business_type_other,state,city,pincode,owner_name,mobile_number,whatsapp_number,email,website_social,gst_number,gst_certificate,account_name,account_type,account_number,confirm_account_number,bank_name,ifsc_code,cancelled_cheque,product_categories,avg_products,manufacture_products,signature_name,registration_date,status,created_at,updated_at,password
        ) VALUES (
          '{$vendorIdEsc}','{$vals['business_name']}','{$vals['business_address']}','{$vals['business_type']}','{$vals['business_type_other']}','{$vals['state']}','{$vals['city']}','{$vals['pincode']}','{$vals['owner_name']}','{$vals['mobile_number']}','{$vals['whatsapp_number']}','{$uniqueEmailEsc}','{$vals['website_social']}','{$vals['gst_number']}','{$vals['gst_certificate']}','{$vals['account_name']}','{$vals['account_type']}','{$vals['account_number']}','{$vals['confirm_account_number']}','{$vals['bank_name']}','{$vals['ifsc_code']}','{$vals['cancelled_cheque']}','{$vals['product_categories']}','{$vals['avg_products']}','{$vals['manufacture_products']}','{$vals['signature_name']}','{$vals['registration_date']}','{$statusEsc}','{$nowEsc}','{$nowEsc}','{$hashEsc}'
        )";
        
        $insertResult = mysqli_query($con, $sqlRetry);
        
        if (!$insertResult) {
            // If still failing, redirect with error
            $_SESSION['error_setpass'] = 'Registration failed. Please try again.';
            backTo(' '. USER_BASEURL . 'vendor/setNewPass.php');
        }
    } else {
        // Other database error
        $_SESSION['error_setpass'] = 'Registration failed. Please try again.';
        backTo(' '. USER_BASEURL . 'vendor/setNewPass.php');
    }
}

// Get the newly created vendor ID for automatic login
$newVendorQuery = "SELECT id, business_name, status FROM vendor_registration WHERE vendor_id = '{$vendorIdEsc}' ORDER BY id DESC LIMIT 1";
$newVendorResult = mysqli_query($con, $newVendorQuery);
if ($newVendorResult && mysqli_num_rows($newVendorResult) > 0) {
    $newVendor = mysqli_fetch_assoc($newVendorResult);
    $regId = (int)$newVendor['id'];
    $businessName = (string)$newVendor['business_name'];
    $status = (string)$newVendor['status'];
    
    // Set session variables for automatic login
    $_SESSION['vendor_reg_id'] = $regId;
    $_SESSION['vendor_mobile'] = $phone;
    $_SESSION['vendor_business_name'] = $businessName;
    $_SESSION['vendor_status'] = $status;
    
    // Set success message for modal
    $_SESSION['password_success'] = 'Password set successfully! You will be redirected shortly.';
    
    // Set redirect URL in session for JavaScript
    $statusLower = strtolower($status);
    if ($statusLower === 'pending' || $statusLower === 'hold') {
        $_SESSION['redirect_url'] = './profileSetting.php';
    } else {
        $_SESSION['redirect_url'] = './index.php';
    }
    
    // Clean session flags - but keep vendor session data for login
    unset($_SESSION['otp_verified']);
    unset($_SESSION['otp_stage']);
    unset($_SESSION['pending_phone']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['vendor_registration']);
    
    // Set a completion flag to prevent going back to earlier steps
    $_SESSION['registration_completed'] = true;
    
    // Redirect back to setNewPass.php to show success modal
    backTo(' '. USER_BASEURL . 'vendor/setNewPass.php');
} else {
    // Fallback: if we can't get vendor details, redirect to login
    unset($_SESSION['otp_verified']);
    unset($_SESSION['pending_phone']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['vendor_registration']);
    backTo(' '. USER_BASEURL . 'vendor/login.php');
}



<?php
require_once __DIR__ . '/../pages/includes/init.php';

// Ensure HTML escaping helper exists on this page
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF_8');
    }
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data (JSON or form-data fallback)
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    // Fallback to standard POST for multipart/form-data submissions
    if (!empty($_POST)) {
        $input = $_POST;
    } else {
        $input = [];
    }
}

$action = isset($input['action']) ? trim($input['action']) : '';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

if (!$is_logged_in) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to update your profile',
        'login_required' => true
    ]);
    exit;
}

switch ($action) {
    case 'update_profile':
        updateProfile($input, $user_id, $db_connection);
        break;

    case 'check_email':
        checkEmailExists($input, $user_id, $db_connection);
        break;

    case 'get_bank_details':
        getBankDetails($user_id, $db_connection);
        break;

    case 'get_coin_transactions':
        getCoinTransactions($user_id, $db_connection);
        break;

    case 'get_coin_balance':
        getCoinBalance($user_id, $db_connection);
        break;

    case 'save_bank_details':
        saveBankDetails($input, $user_id, $db_connection);
        break;

    case 'verify_bank_otp':
        verifyBankOTP($input, $user_id, $db_connection);
        break;

    case 'clear_pending_bank_details':
        clearPendingBankDetails($user_id);
        break;

    // KYC flows
    case 'get_kyc_details':
        getKycDetails($user_id, $db_connection);
        break;

    case 'save_kyc_details':
        saveKycDetails($input, $user_id, $db_connection);
        break;

    case 'verify_kyc_otp':
        verifyKycOTP($input, $user_id, $db_connection);
        break;

    case 'store_kyc_otp':
        storeKycOtp($input, $user_id, $db_connection);
        break;

    case 'get_states_master':
        getStatesMaster($db_connection);
        break;

    case 'upload_kyc_document':
        uploadKycDocument($user_id, $db_connection);
        break;

    case 'change_password':
        changePassword($input, $user_id, $db_connection);
        break;

    case 'verify_password_otp':
        verifyPasswordOTP($input, $user_id, $db_connection);
        break;

    case 'logout':
        logoutUser();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

function updateProfile($input, $user_id, $db_connection)
{
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                throw new Exception("Please fill in all required fields");
            }
        }

        $first_name = trim($input['first_name']);
        $last_name = trim($input['last_name']);
        $email = trim($input['email']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Check if email already exists for another user
        $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($db_connection, $check_email);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            mysqli_stmt_close($stmt);
            throw new Exception("Email already exists. Please use another email address.");
        }

        mysqli_stmt_close($stmt);

        // Update user profile
        $update_query = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $update_query);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "sssi", $first_name, $last_name, $email, $user_id);

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update profile: ' . mysqli_stmt_error($stmt));
        }

        if (mysqli_stmt_affected_rows($stmt) === 0) {
            throw new Exception('No changes were made to your profile');
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully!',
            'data' => [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function checkEmailExists($input, $user_id, $db_connection)
{
    try {
        if (!isset($input['email']) || empty(trim($input['email']))) {
            throw new Exception("Email is required");
        }

        $email = trim($input['email']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address");
        }

        // Check if email already exists for another user
        $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = mysqli_prepare($db_connection, $check_email);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $email_exists = mysqli_num_rows($result) > 0;
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'email_exists' => $email_exists,
            'message' => $email_exists ? 'Email already exists. Please use another email address.' : 'Email is available'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function getBankDetails($user_id, $db_connection)
{
    try {
        $query = "SELECT * FROM user_bank WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $query);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $bank_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'data' => $bank_data
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function saveBankDetails($input, $user_id, $db_connection)
{
    try {
        // Validate required fields
        $required_fields = ['account_number', 'account_type', 'ifsc_code', 'account_holder_name'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                throw new Exception("Please fill in all required fields");
            }
        }

        $account_number = trim($input['account_number']);
        $account_type = trim($input['account_type']);
        $ifsc_code = trim($input['ifsc_code']);
        $account_holder_name = trim($input['account_holder_name']);

        // Check if bank details already exist
        $check_query = "SELECT id FROM user_bank WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $check_query);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Generate OTP for bank verification
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store bank details temporarily in session for OTP verification
        // Only save to database after OTP verification
        $_SESSION['pending_bank_details'] = [
            'account_number' => $account_number,
            'account_type' => $account_type,
            'ifsc_code' => $ifsc_code,
            'account_holder_name' => $account_holder_name,
            'user_id' => $user_id,
            'is_update' => $existing ? true : false,
            'timestamp' => time()
        ];

        // Store OTP in users table
        $otp_query = "UPDATE users SET otp = ? WHERE id = ?";
        $otp_stmt = mysqli_prepare($db_connection, $otp_query);
        if (!$otp_stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        mysqli_stmt_bind_param($otp_stmt, "si", $otp, $user_id);
        if (!mysqli_stmt_execute($otp_stmt)) {
            throw new Exception('Failed to store OTP: ' . mysqli_stmt_error($otp_stmt));
        }
        mysqli_stmt_close($otp_stmt);

        echo json_encode([
            'success' => true,
            'message' => 'OTP sent for bank details verification!',
            'action' => 'otp_sent',
            'otp' => $otp // In production, send via SMS/Email
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function verifyBankOTP($input, $user_id, $db_connection)
{
    try {
        if (!isset($input['otp']) || empty(trim($input['otp']))) {
            throw new Exception("Please enter the OTP");
        }

        $otp = trim($input['otp']);

        // Validate OTP format
        if (!(strlen($otp) === 6 && is_numeric($otp))) {
            throw new Exception("Invalid OTP. Please enter a valid 6-digit OTP.");
        }

        // Get stored OTP from users table
        $otp_query = "SELECT otp FROM users WHERE id = ?";
        $otp_stmt = mysqli_prepare($db_connection, $otp_query);

        if (!$otp_stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($otp_stmt, "i", $user_id);
        mysqli_stmt_execute($otp_stmt);
        $result = mysqli_stmt_get_result($otp_stmt);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($otp_stmt);

        if (!$user_data || empty($user_data['otp'])) {
            throw new Exception("No OTP found. Please request a new OTP.");
        }

        // Verify OTP
        if ($user_data['otp'] !== $otp) {
            throw new Exception("Invalid OTP. Please enter the correct OTP.");
        }

        // Check if pending bank details exist in session
        if (!isset($_SESSION['pending_bank_details']) || empty($_SESSION['pending_bank_details'])) {
            throw new Exception("No pending bank details found. Please try again.");
        }

        $pending_details = $_SESSION['pending_bank_details'];
        
        // Verify the pending details belong to the current user
        if ($pending_details['user_id'] != $user_id) {
            throw new Exception("Invalid session data. Please try again.");
        }

        // Check if bank details already exist
        $check_query = "SELECT id FROM user_bank WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $check_query);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($existing) {
            // Update existing bank details
            $update_query = "UPDATE user_bank SET 
                account_number = ?, 
                account_type = ?, 
                ifsc_code = ?, 
                account_holder_name = ?,
                status = 'verified',
                number_verify_status = 'verified'
                WHERE user_id = ?";

            $stmt = mysqli_prepare($db_connection, $update_query);

            if (!$stmt) {
                throw new Exception('Database error: ' . mysqli_error($db_connection));
            }

            mysqli_stmt_bind_param($stmt, "ssssi", 
                $pending_details['account_number'], 
                $pending_details['account_type'], 
                $pending_details['ifsc_code'], 
                $pending_details['account_holder_name'], 
                $user_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update bank details: ' . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);

        } else {
            // Insert new bank details
            $insert_query = "INSERT INTO user_bank (
                user_id, account_number, account_type, ifsc_code, 
                account_holder_name, status, number_verify_status, created_date
            ) VALUES (?, ?, ?, ?, ?, 'verified', 'verified', NOW())";

            $stmt = mysqli_prepare($db_connection, $insert_query);

            if (!$stmt) {
                throw new Exception('Database error: ' . mysqli_error($db_connection));
            }

            mysqli_stmt_bind_param($stmt, "issss", 
                $user_id, 
                $pending_details['account_number'], 
                $pending_details['account_type'], 
                $pending_details['ifsc_code'], 
                $pending_details['account_holder_name']
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to save bank details: ' . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
        }

        // Clear pending bank details from session
        unset($_SESSION['pending_bank_details']);

        // Clear OTP after successful verification
        $clear_otp_query = "UPDATE users SET otp = NULL WHERE id = ?";
        $clear_stmt = mysqli_prepare($db_connection, $clear_otp_query);
        if ($clear_stmt) {
            mysqli_stmt_bind_param($clear_stmt, "i", $user_id);
            mysqli_stmt_execute($clear_stmt);
            mysqli_stmt_close($clear_stmt);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Bank details verified and saved successfully!'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * KYC: Fetch existing details
 */
function getKycDetails($user_id, $db_connection)
{
    try {
        $query = "SELECT * FROM user_kyc WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $query);

        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $kyc = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'data' => $kyc
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * KYC: Create/update details; set status pending awaiting OTP verification
 */
function saveKycDetails($input, $user_id, $db_connection)
{
    try {
        $required_fields = [
            'first_name',
            'last_name',
            'dob',
            'gender',
            'city',
            'pincode',
            'address',
            'pan_number',
            'document_name'
        ];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || trim($input[$field]) === '') {
                throw new Exception('Please fill in all required fields');
            }
        }

        $first_name = trim($input['first_name']);
        $last_name = trim($input['last_name']);
        $dob = trim($input['dob']);
        $gender = trim($input['gender']);
        $city = trim($input['city']);
        $pincode = trim($input['pincode']);
        $address = trim($input['address']);
        $pan_number = strtoupper(trim($input['pan_number']));
        $document_name = trim($input['document_name']);

        // Basic validations
        if (!preg_match('/^[0-9]{6}$/', $pincode)) {
            throw new Exception('Please enter a valid 6-digit pincode');
        }
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
            throw new Exception('Please enter a valid PAN number');
        }

        // Normalize gender to match ENUM values (Male, Female, Other)
        $gender = ucfirst(strtolower($gender));

        // Ensure KYC document image is present in pending session; if not, fallback to existing DB image
        if (!isset($_SESSION['pending_kyc']['document']) || empty(trim($_SESSION['pending_kyc']['document'] ?? ''))) {
            $fallback_q = "SELECT document FROM user_kyc WHERE user_id = ? LIMIT 1";
            $doc_stmt = mysqli_prepare($db_connection, $fallback_q);
            if (!$doc_stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
            mysqli_stmt_bind_param($doc_stmt, 'i', $user_id);
            mysqli_stmt_execute($doc_stmt);
            $doc_res = mysqli_stmt_get_result($doc_stmt);
            $doc_row = mysqli_fetch_assoc($doc_res);
            mysqli_stmt_close($doc_stmt);

            if ($doc_row && !empty(trim($doc_row['document'] ?? ''))) {
                $_SESSION['pending_kyc'] = array_merge($_SESSION['pending_kyc'] ?? [], [
                    'document' => $doc_row['document']
                ]);
            } else {
                throw new Exception('Please upload a KYC document image before submitting.');
            }
        }

        // Stash all details in session; commit only after OTP verification
        $_SESSION['pending_kyc'] = array_merge($_SESSION['pending_kyc'] ?? [], [
            'user_id'       => $user_id,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'dob'           => $dob,
            'gender'        => $gender,
            'city'          => $city,
            'pincode'       => $pincode,
            'address'       => $address,
            'pan_number'    => $pan_number,
            'document_name' => $document_name,
            'timestamp'     => time()
        ]);

        // Generate OTP and store on user
        $otp = str_pad((string)rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = mysqli_prepare($db_connection, "UPDATE users SET otp = ? WHERE id = ?");
        if (!$stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
        mysqli_stmt_bind_param($stmt, 'si', $otp, $user_id);
        if (!mysqli_stmt_execute($stmt)) { throw new Exception('Failed to store OTP: ' . mysqli_stmt_error($stmt)); }
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'message' => 'OTP sent for KYC verification', 'action' => 'otp_sent', 'otp' => $otp]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * KYC: Verify OTP and mark KYC as verified
 */
function verifyKycOTP($input, $user_id, $db_connection)
{
    try {
        if (!isset($input['otp']) || empty(trim($input['otp']))) {
            throw new Exception('Please enter the OTP');
        }
        $otp = trim($input['otp']);
        if (!(strlen($otp) === 6 && is_numeric($otp))) {
            throw new Exception('Invalid OTP. Please enter a valid 6-digit OTP.');
        }

        // Compare with users.otp
        $get_q = "SELECT otp FROM users WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $get_q);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        $storedOtp = $row['otp'] ?? '';
        if ($storedOtp !== $otp) {
            throw new Exception('Invalid OTP. Please try again.');
        }

        // Ensure pending KYC exists
        if (!isset($_SESSION['pending_kyc']) || empty($_SESSION['pending_kyc'])) {
            throw new Exception('No pending KYC details found. Please submit again.');
        }
        $pending = $_SESSION['pending_kyc'];
        if ((int)($pending['user_id'] ?? 0) !== (int)$user_id) {
            throw new Exception('Session mismatch. Please try again.');
        }

        // Upsert KYC now (commit)
        $check_q = "SELECT id FROM user_kyc WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $check_q);
        if (!$stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($exists) {
            $q = "UPDATE user_kyc SET first_name=?, last_name=?, dob=?, gender=?, city=?, pincode=?, address=?, pan_number=?, document_name=?, document=?, status='approved', updated_date = NOW() WHERE user_id=?";
            $stmt = mysqli_prepare($db_connection, $q);
            if (!$stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
            mysqli_stmt_bind_param($stmt, 'ssssssssssi', $pending['first_name'], $pending['last_name'], $pending['dob'], $pending['gender'], $pending['city'], $pending['pincode'], $pending['address'], $pending['pan_number'], $pending['document_name'], $pending['document'], $user_id);
            if (!mysqli_stmt_execute($stmt)) { throw new Exception('Failed to update KYC: ' . mysqli_stmt_error($stmt)); }
            mysqli_stmt_close($stmt);
        } else {
            $q = "INSERT INTO user_kyc (user_id, first_name, last_name, dob, gender, city, pincode, address, pan_number, document_name, document, status, created_date) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'approved', NOW())";
            $stmt = mysqli_prepare($db_connection, $q);
            if (!$stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
            mysqli_stmt_bind_param($stmt, 'issssssssss', $user_id, $pending['first_name'], $pending['last_name'], $pending['dob'], $pending['gender'], $pending['city'], $pending['pincode'], $pending['address'], $pending['pan_number'], $pending['document_name'], $pending['document']);
            if (!mysqli_stmt_execute($stmt)) { throw new Exception('Failed to save KYC: ' . mysqli_stmt_error($stmt)); }
            mysqli_stmt_close($stmt);
        }

        // Clear pending and OTP
        unset($_SESSION['pending_kyc']);
        $stmt = mysqli_prepare($db_connection, "UPDATE users SET otp = NULL WHERE id = ?");
        if (!$stmt) { throw new Exception('Database error: ' . mysqli_error($db_connection)); }
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'message' => 'KYC details verified and saved successfully']);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Store KYC OTP in users.otp
 */
function storeKycOtp($input, $user_id, $db_connection)
{
    try {
        if (!isset($input['otp']) || empty(trim($input['otp']))) {
            throw new Exception('OTP is required');
        }
        $otp = trim($input['otp']);
        if (!(strlen($otp) === 6 && is_numeric($otp))) {
            throw new Exception('Invalid OTP format');
        }
        $stmt = mysqli_prepare($db_connection, "UPDATE users SET otp = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        mysqli_stmt_bind_param($stmt, "si", $otp, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to store OTP: ' . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'OTP stored']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get states list for dynamic KYC city dropdown
 */
function getStatesMaster($db_connection)
{
    try {
        $query = "SELECT id, name FROM states ORDER BY name";
        $result = mysqli_query($db_connection, $query);
        if (!$result) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        $states = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $states[] = ['id' => $row['id'], 'name' => $row['name']];
        }
        echo json_encode(['success' => true, 'states' => $states]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Handle KYC document upload and store path into user_kyc.document column
 */
function uploadKycDocument($user_id, $db_connection)
{
    try {
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error');
        }
        $file = $_FILES['document'];
        $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array(mime_content_type($file['tmp_name']), $allowed)) {
            throw new Exception('Only JPG/PNG images are allowed');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Max 10MB');
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = 'kyc_' . $user_id . '_' . time() . '.' . strtolower($ext);
        // Save inside New Code/public/uploads/kyc (not the project root /public)
        $uploadDir = __DIR__ . '/../../public/uploads/kyc/';

        
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $destPath = $uploadDir . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        // Public URL pointing to New Code/public/uploads/kyc/{file}
        $publicPath = rtrim(USER_BASEURL, '/') . '/public/uploads/kyc/' . $safeName;

        // Stash document path only; commit to DB after OTP verification in verifyKycOTP
        $_SESSION['pending_kyc'] = array_merge($_SESSION['pending_kyc'] ?? [], [
            'user_id'  => $user_id,
            'document' => $publicPath,
            'timestamp'=> time()
        ]);

        echo json_encode(['success' => true, 'message' => 'Document uploaded', 'path' => $publicPath, 'pending' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Change password with OTP verification
 */
function changePassword($input, $user_id, $db_connection)
{
    try {
        $required_fields = ['current_password', 'new_password', 'confirm_password'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                throw new Exception("Please fill in all required fields");
            }
        }

        $current_password = trim($input['current_password']);
        $new_password = trim($input['new_password']);
        $confirm_password = trim($input['confirm_password']);

        // Validate password match
        if ($new_password !== $confirm_password) {
            throw new Exception("New password and confirm password do not match");
        }

        // Validate password strength
        if (strlen($new_password) < 6) {
            throw new Exception("New password must be at least 6 characters long");
        }

        // Get current user data
        $user_query = "SELECT password FROM users WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $user_query);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user_data) {
            throw new Exception("User not found");
        }

        // Verify current password (assuming it's hashed)
        if (!password_verify($current_password, $user_data['password'])) {
            throw new Exception("Current password is incorrect");
        }

        // Generate OTP and store temporarily
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_stmt = mysqli_prepare($db_connection, "UPDATE users SET otp = ? WHERE id = ?");
        if (!$otp_stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }
        mysqli_stmt_bind_param($otp_stmt, "si", $otp, $user_id);
        mysqli_stmt_execute($otp_stmt);
        mysqli_stmt_close($otp_stmt);

        // Store new password temporarily in session for OTP verification
        $_SESSION['temp_new_password'] = password_hash($new_password, PASSWORD_DEFAULT);

        echo json_encode([
            'success' => true,
            'message' => 'OTP sent for password change verification',
            'otp' => $otp // In production, send via SMS/Email
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Verify OTP and update password
 */
function verifyPasswordOTP($input, $user_id, $db_connection)
{
    try {
        if (!isset($input['otp']) || empty(trim($input['otp']))) {
            throw new Exception("Please enter the OTP");
        }

        $otp = trim($input['otp']);
        if (!(strlen($otp) === 6 && is_numeric($otp))) {
            throw new Exception("Invalid OTP. Please enter a valid 6-digit OTP.");
        }

        // Check if temp password exists in session
        if (!isset($_SESSION['temp_new_password'])) {
            throw new Exception("Password change session expired. Please try again.");
        }

        // Verify OTP
        $otp_query = "SELECT otp FROM users WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $otp_query);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$user_data || $user_data['otp'] !== $otp) {
            throw new Exception("Invalid OTP. Please try again.");
        }

        // Update password and clear OTP
        $update_query = "UPDATE users SET password = ?, otp = NULL WHERE id = ?";
        $stmt = mysqli_prepare($db_connection, $update_query);
        if (!$stmt) {
            throw new Exception('Database error: ' . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "si", $_SESSION['temp_new_password'], $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception('Failed to update password: ' . mysqli_stmt_error($stmt));
        }

        mysqli_stmt_close($stmt);

        // Clear temp password from session
        unset($_SESSION['temp_new_password']);

        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully!'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get coin transactions for a user
 */
function getCoinTransactions($user_id, $db_connection)
{
    try {
        $query = "SELECT * FROM coin_transactions WHERE user_id = ? ORDER BY created_date DESC";
        $stmt = mysqli_prepare($db_connection, $query);

        if (!$stmt) {
            throw new Exception("Database error: " . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $transactions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $transactions[] = [
                'id' => $row['id'],
                'order_id' => $row['order_id'],
                'coins' => $row['coint'],
                'type' => $row['type'],
                'method' => $row['method'],
                'comment' => $row['comment'],
                'created_date' => $row['created_date']
            ];
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'transactions' => $transactions
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Get coin balance for a user
 */
function getCoinBalance($user_id, $db_connection)
{
    try {
        // Calculate total earned coins
        $earned_query = "SELECT SUM(CAST(coint AS DECIMAL(10,2))) as total_earned 
                        FROM coin_transactions 
                        WHERE user_id = ? AND type = 'earned'";
        $stmt = mysqli_prepare($db_connection, $earned_query);

        if (!$stmt) {
            throw new Exception("Database error: " . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $earned_data = mysqli_fetch_assoc($result);
        $total_earned = $earned_data['total_earned'] ?? 0;

        mysqli_stmt_close($stmt);

        // Calculate total spent coins
        $spent_query = "SELECT SUM(CAST(coint AS DECIMAL(10,2))) as total_spent 
                       FROM coin_transactions 
                       WHERE user_id = ? AND type = 'spent'";
        $stmt = mysqli_prepare($db_connection, $spent_query);

        if (!$stmt) {
            throw new Exception("Database error: " . mysqli_error($db_connection));
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $spent_data = mysqli_fetch_assoc($result);
        $total_spent = $spent_data['total_spent'] ?? 0;

        mysqli_stmt_close($stmt);

        // Calculate current balance
        $current_balance = $total_earned - $total_spent;

        echo json_encode([
            'success' => true,
            'balance' => number_format($current_balance, 1),
            'total_earned' => number_format($total_earned, 1),
            'total_spent' => number_format($total_spent, 1)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Logout user
 */
function logoutUser()
{
    try {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Clear all session variables
        $_SESSION = array();

        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        // Destroy the session
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully!'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function clearPendingBankDetails($user_id)
{
    try {
        // Clear pending bank details from session
        if (isset($_SESSION['pending_bank_details'])) {
            unset($_SESSION['pending_bank_details']);
        }

        // Clear OTP from users table
        global $db_connection;
        $clear_otp_query = "UPDATE users SET otp = NULL WHERE id = ?";
        $clear_stmt = mysqli_prepare($db_connection, $clear_otp_query);
        if ($clear_stmt) {
            mysqli_stmt_bind_param($clear_stmt, "i", $user_id);
            mysqli_stmt_execute($clear_stmt);
            mysqli_stmt_close($clear_stmt);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Pending bank details cleared successfully!'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
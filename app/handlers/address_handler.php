<?php
require_once __DIR__ . '/../pages/includes/init.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

// Check if user is logged in
$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
$is_logged_in = $user_id > 0;

if (!$is_logged_in) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to manage addresses',
        'login_required' => true
    ]);
    exit;
}

switch ($action) {
    case 'get_countries':
        // Get all countries
        $countries_query = "SELECT id, name, iso_code_2, iso_code_3 FROM country WHERE status = 1 ORDER BY name ASC";
        $result = mysqli_query($db_connection, $countries_query);

        $countries = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $countries[] = $row;
            }
        }

        echo json_encode([
            'success' => true,
            'countries' => $countries
        ]);
        break;

    case 'get_states':
        $country_id = isset($input['country_id']) ? (int) $input['country_id'] : 0;

        if ($country_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid country ID']);
            exit;
        }

        // Get states for specific country
        $states_query = "SELECT id, country_id, name, code FROM state WHERE country_id = ? AND status = 1 ORDER BY name ASC";
        $stmt = mysqli_prepare($db_connection, $states_query);

        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "i", $country_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $states = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $states[] = $row;
            }
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'states' => $states
        ]);
        break;

    case 'add_address':
        // Validate required fields
        $required_fields = ['address_type', 'state_id', 'street_address', 'city', 'pin_code', 'mobile_number'];

        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
                exit;
            }
        }

        $address_type = trim($input['address_type']);
        $country_id = isset($input['country_id']) && !empty($input['country_id']) ? (int) $input['country_id'] : null;
        $state_id = (int) $input['state_id'];
        $street_address = trim($input['street_address']);
        $street_address2 = isset($input['street_address2']) ? trim($input['street_address2']) : null;
        $city = trim($input['city']);
        $pin_code = trim($input['pin_code']);
        $mobile_number = trim($input['mobile_number']);
        $email_address = isset($input['email_address']) ? trim($input['email_address']) : null;
        $is_primary = isset($input['is_primary']) ? (int) $input['is_primary'] : 0;

        // If setting as primary, update all other addresses to non-primary
        if ($is_primary == 1) {
            $update_primary = "UPDATE user_address SET primary_address = 0 WHERE user_id = ?";
            $stmt = mysqli_prepare($db_connection, $update_primary);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        // Insert new address
        $insert_query = "
            INSERT INTO user_address 
            (user_id, primary_address, address_type, country_id, state_id, street_address, street_address2, city, pin_code, mobile_number, email_address, created_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

        $stmt = mysqli_prepare($db_connection, $insert_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "iisiissssss", $user_id, $is_primary, $address_type, $country_id, $state_id, $street_address, $street_address2, $city, $pin_code, $mobile_number, $email_address);


        if (mysqli_stmt_execute($stmt)) {
            $address_id = mysqli_insert_id($db_connection);
            echo json_encode([
                'success' => true,
                'message' => 'Address added successfully',
                'address_id' => $address_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add address']);
        }

        mysqli_stmt_close($stmt);
        break;

    case 'get_addresses':
        // Get user addresses with country and state names
        // Check if we should show all addresses or just latest 2
        $show_all = isset($input['show_all']) && ($input['show_all'] === true || $input['show_all'] === 'true' || $input['show_all'] === 1);

        if ($show_all) {
            // Show all addresses (for my-account page)
            $addresses_query = "
                SELECT ua.*, c.name as country_name, s.name as state_name 
                FROM user_address ua 
                LEFT JOIN country c ON ua.country_id = c.id 
                LEFT JOIN state s ON ua.state_id = s.id 
                WHERE ua.user_id = ? 
                ORDER BY ua.created_date DESC
            ";
        } else {
            // Show only latest 2 addresses (for other pages)
            $addresses_query = "
                SELECT ua.*, c.name as country_name, s.name as state_name 
                FROM user_address ua 
                LEFT JOIN country c ON ua.country_id = c.id 
                LEFT JOIN state s ON ua.state_id = s.id 
                WHERE ua.user_id = ? 
                ORDER BY ua.created_date DESC 
                LIMIT 2
            ";
        }

        $stmt = mysqli_prepare($db_connection, $addresses_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $addresses = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $addresses[] = $row;
            }
        }

        mysqli_stmt_close($stmt);

        echo json_encode([
            'success' => true,
            'addresses' => $addresses
        ]);
        break;

    case 'get_address':
        $address_id = isset($input['address_id']) ? (int) $input['address_id'] : 0;

        if ($address_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
            exit;
        }

        // Get specific address for editing
        $address_query = "
            SELECT ua.*, c.name as country_name, s.name as state_name 
            FROM user_address ua 
            LEFT JOIN country c ON ua.country_id = c.id 
            LEFT JOIN state s ON ua.state_id = s.id 
            WHERE ua.id = ? AND ua.user_id = ?
        ";

        $stmt = mysqli_prepare($db_connection, $address_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "ii", $address_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $address = mysqli_fetch_assoc($result);
            echo json_encode([
                'success' => true,
                'address' => $address
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Address not found']);
        }

        mysqli_stmt_close($stmt);
        break;

    case 'update_address':
        $address_id = isset($input['address_id']) ? (int) $input['address_id'] : 0;

        if ($address_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
            exit;
        }

        // Validate required fields
        $required_fields = ['address_type', 'state_id', 'street_address', 'city', 'pin_code', 'mobile_number'];

        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || empty(trim($input[$field]))) {
                echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
                exit;
            }
        }

        $address_type = trim($input['address_type']);
        $country_id = isset($input['country_id']) && !empty($input['country_id']) ? (int) $input['country_id'] : null;
        $state_id = (int) $input['state_id'];
        $street_address = trim($input['street_address']);
        $street_address2 = isset($input['street_address2']) ? trim($input['street_address2']) : null;
        $city = trim($input['city']);
        $pin_code = trim($input['pin_code']);
        $mobile_number = trim($input['mobile_number']);
        $email_address = isset($input['email_address']) ? trim($input['email_address']) : null;
        $is_primary = isset($input['is_primary']) ? (int) $input['is_primary'] : 0;

        // If setting as primary, update all other addresses to non-primary
        if ($is_primary == 1) {
            $update_primary = "UPDATE user_address SET primary_address = 0 WHERE user_id = ? AND id != ?";
            $stmt = mysqli_prepare($db_connection, $update_primary);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $address_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        // Update address
        $update_query = "
            UPDATE user_address 
            SET primary_address = ?, address_type = ?, country_id = ?, state_id = ?, 
                street_address = ?, street_address2 = ?, city = ?, pin_code = ?, mobile_number = ?, email_address = ?
            WHERE id = ? AND user_id = ?
        ";

        $stmt = mysqli_prepare($db_connection, $update_query);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "isiissssssii", $is_primary, $address_type, $country_id, $state_id, $street_address, $street_address2, $city, $pin_code, $mobile_number, $email_address, $address_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Address updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update address']);
        }

        mysqli_stmt_close($stmt);
        break;

    case 'set_primary':
        $address_id = isset($input['address_id']) ? (int) $input['address_id'] : 0;

        if ($address_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
            exit;
        }

        // First, set all addresses to non-primary
        $update_all = "UPDATE user_address SET primary_address = 0 WHERE user_id = ?";
        $stmt = mysqli_prepare($db_connection, $update_all);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        // Then set the selected address as primary
        $set_primary = "UPDATE user_address SET primary_address = 1 WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($db_connection, $set_primary);

        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "ii", $address_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Primary address updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to set primary address']);
        }

        mysqli_stmt_close($stmt);
        break;

    case 'delete_address':
        $address_id = isset($input['address_id']) ? (int) $input['address_id'] : 0;

        if ($address_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
            exit;
        }

        // Delete address
        $delete_query = "DELETE FROM user_address WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($db_connection, $delete_query);

        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }

        mysqli_stmt_bind_param($stmt, "ii", $address_id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
        }

        mysqli_stmt_close($stmt);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
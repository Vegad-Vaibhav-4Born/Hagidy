<?php

require_once __DIR__ . '/includes/init.php';

if (isset($_POST['referral_code'])) {
    $ref = mysqli_real_escape_string($db_connection, $_POST['referral_code']);
    
    // Check if input is a mobile number (contains only digits and is 10 digits)
    if (preg_match('/^[0-9]{10}$/', $ref)) {
        // Search by mobile number
        $query = mysqli_query($db_connection, "SELECT first_name,last_name FROM users WHERE mobile='$ref'");
    } else {
        // Search by referral code
        $query = mysqli_query($db_connection, "SELECT first_name,last_name FROM users WHERE referral_code='$ref'");
    }
    
    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        echo json_encode([
            "status" => "success",
            "name"   => $row['first_name'] . " " . $row['last_name']
        ]);
    } else {
        echo json_encode(["status" => "error"]);
    }
}

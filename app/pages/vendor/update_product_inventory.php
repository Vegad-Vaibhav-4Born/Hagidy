<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

include '../includes/init.php';

// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json');

if(!isset($_SESSION['vendor_reg_id'])){
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$vendor_reg_id = $_SESSION['vendor_reg_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $mrp = isset($_POST['mrp']) ? (float)$_POST['mrp'] : 0;
    $selling_price = isset($_POST['selling_price']) ? (float)$_POST['selling_price'] : 0;
    $gst = isset($_POST['gst']) ? (float)$_POST['gst'] : 0;
    $stock_quantity = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : 0;
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit;
    }
    
    // Verify that the product belongs to this vendor
    $checkQuery = "SELECT id FROM products WHERE id = '".mysqli_real_escape_string($con, $product_id)."' AND vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."' LIMIT 1";
    $checkResult = mysqli_query($con, $checkQuery);
    
    if (!$checkResult || mysqli_num_rows($checkResult) === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
        exit;
    }
    
    // Update the product with current date and time
    $current_datetime = date('Y-m-d H:i:s');
    $updateQuery = "UPDATE products SET 
                    mrp = '".mysqli_real_escape_string($con, $mrp)."',
                    selling_price = '".mysqli_real_escape_string($con, $selling_price)."',
                    gst = '".mysqli_real_escape_string($con, $gst)."',
                    inventory = '".mysqli_real_escape_string($con, $stock_quantity)."',
                    updated_date = '".mysqli_real_escape_string($con, $current_datetime)."'
                    WHERE id = '".mysqli_real_escape_string($con, $product_id)."' 
                    AND vendor_id = '".mysqli_real_escape_string($con, $vendor_reg_id)."'";
    
    if (mysqli_query($con, $updateQuery)) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

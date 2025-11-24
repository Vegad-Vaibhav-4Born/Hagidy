<?php
session_start();
require_once __DIR__ . '/../pages/includes/init.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get product ID from query parameter
$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';

if (empty($product_id)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

// Validate product ID
$product_id = mysqli_real_escape_string($con, $product_id);

// Get vendor ID from session
$vendor_id = isset($_SESSION['vendor_reg_id']) ? $_SESSION['vendor_reg_id'] : '';

if (empty($vendor_id)) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Fetch product specifications from database
$query = "SELECT specifications FROM products WHERE id = '$product_id' AND vendor_id = '$vendor_id' LIMIT 1";
$result = mysqli_query($con, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
    exit;
}

$row = mysqli_fetch_assoc($result);
$specifications_json = $row['specifications'] ?? '';

// Parse specifications
$specifications = [];
if (!empty($specifications_json)) {
    $decoded = json_decode($specifications_json, true);
    if (is_array($decoded)) {
        $specifications = $decoded;
    }
}

// Return specifications
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'specifications' => $specifications,
    'count' => count($specifications)
]);
?>

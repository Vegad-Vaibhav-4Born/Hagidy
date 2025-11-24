<?php
require_once __DIR__ . '/../pages/includes/init.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if product IDs are provided
if (!isset($_GET['ids']) || empty($_GET['ids'])) {
    echo json_encode(['success' => false, 'message' => 'No product IDs provided']);
    exit;
}

// Convert product IDs into integer array
$product_ids = $_GET['ids'];
$ids_array = array_filter(array_map('intval', explode(',', $product_ids)));

if (empty($ids_array)) {
    echo json_encode(['success' => false, 'message' => 'Invalid product IDs']);
    exit;
}
// Create placeholders for the IN clause
$placeholders = str_repeat('?,', count($ids_array) - 1) . '?';

// Fetch products from database (using correct column names from your schema)
$query = "SELECT id, product_name, selling_price, mrp, images, Inventory, coin, discount as discount_percentage 
          FROM products 
          WHERE status = 'approved' AND id IN ($placeholders) 
          ORDER BY id DESC";

$stmt = mysqli_prepare($db_connection, $query);

// Bind parameters dynamically
$types = str_repeat('i', count($ids_array));
mysqli_stmt_bind_param($stmt, $types, ...$ids_array);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

mysqli_stmt_close($stmt);

if (!empty($products)) {
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No products found',
        'products' => [],
        'count' => 0
    ]);
}
?>

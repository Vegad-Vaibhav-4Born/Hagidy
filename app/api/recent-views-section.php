
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../pages/includes/init.php';

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Get product IDs from POST request
$input = json_decode(file_get_contents('php://input'), true);
$product_ids = isset($input['product_ids']) ? array_map('intval', $input['product_ids']) : [];

if (empty($product_ids)) {
    echo json_encode(['products' => [], 'error' => 'No product IDs provided']);
    exit;
}

// Ensure product_ids are valid integers
$product_ids = array_filter($product_ids, function($id) { return $id > 0; });
if (empty($product_ids)) {
    echo json_encode(['products' => [], 'error' => 'Invalid product IDs']);
    exit;
}

$ids_str = implode(',', $product_ids);
$query = "SELECT id, product_name, selling_price, mrp, coin, images 
          FROM products 
          WHERE id IN ($ids_str) 
          AND status = 'approved'
          ORDER BY FIELD(id, $ids_str)";
$result = mysqli_query($db_connection, $query);

if (!$result) {
    error_log('Query failed: ' . mysqli_error($db_connection));
    echo json_encode(['products' => [], 'error' => 'Database query failed: ' . mysqli_error($db_connection)]);
    exit;
}

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['images'] = json_decode($row['images'], true) ?? [];
    
    // Get review data for this product
    $product_id = $row['id'];
    $reviews_query = "SELECT * FROM reviews WHERE product_id = '$product_id' ORDER BY created_date DESC";
    $reviews_result = mysqli_query($db_connection, $reviews_query);
    $total_reviews = 0;
    $total_rating = 0;
    $average_rating = 0;
    $average_rating_percentage = 0;
    
    if ($reviews_result) {
        while ($review = mysqli_fetch_assoc($reviews_result)) {
            $total_reviews++;
            $rating = floatval($review['rating']);
            $total_rating += $rating;
        }
    }
    
    // Calculate average rating
    if ($total_reviews > 0) {
        $average_rating = round($total_rating / $total_reviews, 1);
        $average_rating_percentage = $average_rating * 20; // Convert to percentage for display
    }
    
    // Add review data to product
    $row['total_reviews'] = $total_reviews;
    $row['average_rating'] = $average_rating;
    $row['average_rating_percentage'] = $average_rating_percentage;
    
    $products[] = $row;
}

echo json_encode(['products' => $products]);
?>
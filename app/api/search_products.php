<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
require_once __DIR__ . '/../pages/includes/init.php';
try {
    $search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
    $category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $subcategory_id = isset($_GET['subcategory']) ? intval($_GET['subcategory']) : 0;
    
    // Build the query
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Search term condition
    if (!empty($search_term)) {
        $where_conditions[] = "(product_name LIKE ? OR description LIKE ?)";
        $search_param = '%' . $search_term . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ss';
    }
    
    // Category condition
    if ($category_id > 0) {
        $where_conditions[] = "category_id = ?";
        $params[] = $category_id;
        $param_types .= 'i';
    }
    
    // Subcategory condition
    if ($subcategory_id > 0) {
        $where_conditions[] = "sub_category_id = ?";
        $params[] = $subcategory_id;
        $param_types .= 'i';
    }
    
    // If no search term and no category/subcategory, return empty
    if (empty($search_term) && $category_id == 0 && $subcategory_id == 0) {
        echo json_encode([
            'status' => 'success',
            'data' => []
        ]);
        exit;
    }
    $where_conditions[] = "status = 'approved'";
    // Build final query
    $query = "SELECT id, product_name, selling_price, mrp, images, coin FROM products";
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(' AND ', $where_conditions);
    }
    $query .= " ORDER BY product_name ASC LIMIT 5";
    
    // Execute query
    if (!empty($params)) {
        $stmt = mysqli_prepare($db_connection, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            throw new Exception('Failed to prepare statement');
        }
    } else {
        $result = mysqli_query($db_connection, $query);
    }
    
    $products = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Parse images
            $images = json_decode($row['images'], true);
            $image_url = '';
            if (is_array($images) && !empty($images[0])) {
                // $image_url = $vendor_baseurl . $images[0];
                $image_url = $images[0];
            } else {
                $image_url = $vendor_baseurl.'uploads/vendors/no-product.png'; // Default image
            }
            
            // Format price
            $price = '₹' . number_format($row['selling_price'], 2);
            
            $products[] = [
                'id' => $row['id'],
                'name' => $row['product_name'],
                'price' => $price,
                'image' => $image_url,
                'coin' => intval($row['coin']),
                'url' => 'product-detail.php?id=' . $row['id']
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Search failed: ' . $e->getMessage()
    ]);
}

mysqli_close($db_connection);
?>


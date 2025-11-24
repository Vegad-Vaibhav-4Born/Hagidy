<?php
// Shop functions for dynamic category, subcategory, and product display

/**
 * Get all categories from database
 */
function getAllCategories($db_connection) {
    $query = "SELECT * FROM category ORDER BY name ASC";
    $result = mysqli_query($db_connection, $query);
    
    if (!$result) {
        return [];
    }
    
    $categories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    return $categories;
}

/**
 * Get subcategories for a specific category
 */
function getSubcategoriesByCategory($db_connection, $category_id) {
    $query = "SELECT * FROM sub_category WHERE category_id = ? ORDER BY name ASC";
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $subcategories = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subcategories[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $subcategories;
}

/**
 * Get products by category and subcategory with filters
 */
function getProductsByCategory($db_connection, $category_id = null, $sub_category_id = null, $limit = 12, $offset = 0, $filters = [], $sort_by = 'default') {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Only show approved products
    $where_conditions[] = "p.status = 'approved'";

    if ($category_id) {
        $where_conditions[] = "p.category_id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if ($sub_category_id) {
        $where_conditions[] = "p.sub_category_id = ?";
        $params[] = $sub_category_id;
        $param_types .= "i";
    }
    
    // Price filter
    if (isset($filters['min_price']) && $filters['min_price'] > 0) {
        $where_conditions[] = "p.selling_price >= ?";
        $params[] = (float)$filters['min_price'];
        $param_types .= "d";
    }
    
    if (isset($filters['max_price']) && $filters['max_price'] > 0) {
        $where_conditions[] = "p.selling_price <= ?";
        $params[] = (float)$filters['max_price'];
        $param_types .= "d";
    }
    
    // Coin filter
    if (isset($filters['min_coins']) && $filters['min_coins'] >= 0) {
        $where_conditions[] = "p.coin >= ?";
        $params[] = $filters['min_coins'];
        $param_types .= "i";
    }
    
    if (isset($filters['max_coins']) && $filters['max_coins'] > 0) {
        $where_conditions[] = "p.coin <= ?";
        $params[] = $filters['max_coins'];
        $param_types .= "i";
    }
    
    // Rating minimum filter (e.g., 3.5 and above)
    if (isset($filters['min_rating']) && $filters['min_rating'] !== '') {
        $min = floatval($filters['min_rating']);
        $where_conditions[] = "(SELECT COALESCE(AVG(CAST(rating AS DECIMAL(3,2))), 0) FROM reviews WHERE product_id = p.id) >= ?";
        $params[] = $min;
        $param_types .= "d";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Determine sorting order
    $order_by = "ORDER BY p.created_date DESC"; // Default sorting
    switch ($sort_by) {
        case 'popularity':
            $order_by = "ORDER BY p.id DESC"; // Sort by product ID (can be changed to view count later)
            break;
        case 'rating':
            $order_by = "ORDER BY (SELECT COALESCE(AVG(CAST(rating AS DECIMAL(3,2))), 0) FROM reviews WHERE product_id = p.id) DESC, p.created_date DESC";
            break;
        case 'date':
            $order_by = "ORDER BY p.created_date DESC";
            break;
        case 'price-low':
            $order_by = "ORDER BY CAST(p.selling_price AS DECIMAL(10,2)) ASC, p.created_date DESC";
            break;
        case 'price-high':
            $order_by = "ORDER BY CAST(p.selling_price AS DECIMAL(10,2)) DESC, p.created_date DESC";
            break;
        default:
            $order_by = "ORDER BY p.created_date DESC";
            break;
    }
    
    $query = "SELECT p.*, c.name as category_name, sc.name as subcategory_name 
              FROM products p 
              LEFT JOIN category c ON p.category_id = c.id 
              LEFT JOIN sub_category sc ON p.sub_category_id = sc.id 
              $where_clause 
              $order_by 
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $param_types .= "ii";
    
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $products;
}

/**
 * Get total product count for pagination with filters
 */
function getProductCount($db_connection, $category_id = null, $sub_category_id = null, $filters = []) {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Only approved products
    $where_conditions[] = "status = 'approved'";

    if ($category_id) {
        $where_conditions[] = "category_id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if ($sub_category_id) {
        $where_conditions[] = "sub_category_id = ?";
        $params[] = $sub_category_id;
        $param_types .= "i";
    }
    
    // Price filter
    if (isset($filters['min_price']) && $filters['min_price'] > 0) {
        $where_conditions[] = "selling_price >= ?";
        $params[] = (float)$filters['min_price'];
        $param_types .= "d";
    }
    
    if (isset($filters['max_price']) && $filters['max_price'] > 0) {
        $where_conditions[] = "selling_price <= ?";
        $params[] = (float)$filters['max_price'];
        $param_types .= "d";
    }
    
    // Coin filter
    if (isset($filters['min_coins']) && $filters['min_coins'] >= 0) {
        $where_conditions[] = "coin >= ?";
        $params[] = $filters['min_coins'];
        $param_types .= "i";
    }
    
    if (isset($filters['max_coins']) && $filters['max_coins'] > 0) {
        $where_conditions[] = "coin <= ?";
        $params[] = $filters['max_coins'];
        $param_types .= "i";
    }
    
    // Rating minimum filter (e.g., 3.5 and above)
    if (isset($filters['min_rating']) && $filters['min_rating'] !== '') {
        $min = floatval($filters['min_rating']);
        $where_conditions[] = "(SELECT COALESCE(AVG(CAST(rating AS DECIMAL(3,2))), 0) FROM reviews WHERE product_id = products.id) >= ?";
        $params[] = $min;
        $param_types .= "d";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query = "SELECT COUNT(*) as total FROM products $where_clause";
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return 0;
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $row['total'] ?? 0;
}

/**
 * Get product images array from JSON string
 */
function getProductImages($images_json) {
    if (empty($images_json)) {
        return [];
    }
    
    $images = json_decode($images_json, true);
    return is_array($images) ? $images : [];
}

/**
 * Get default product image if no images available
 */
function getDefaultProductImage() {
    return 'uploads/vendors/no-product.png';
}

/**
 * Get product image URL
 */
function getProductImageUrl($images_json, $index = 0) {
    $images = getProductImages($images_json);
    if (!empty($images) && isset($images[$index]) && !empty($images[$index])) {
        // DB stores relative paths like 'uploads/vendors/xxx.jpg'
        return $images[$index];
    }
    return getDefaultProductImage();
}

/**
 * Format price with currency symbol
 */
function formatPrice($price) {
    return '₹' . number_format($price, 2);
}

/**
 * Calculate discount percentage
 */
function calculateDiscountPercentage($mrp, $selling_price) {
    if ($mrp <= 0) return 0;
    return round((($mrp - $selling_price) / $mrp) * 100);
}

/**
 * Generate product link
 */
function getProductLink($product_id) {
    return "product-detail.php?id=" . $product_id;
}

/**
 * Get product specifications as array
 */
function getProductSpecifications($specifications_json) {
    if (empty($specifications_json)) {
        return [];
    }
    
    $specs = json_decode($specifications_json, true);
    return is_array($specs) ? $specs : [];
}

/**
 * Get subcategory count for a category
 */
function getSubcategoryCount($db_connection, $category_id) {
    $query = "SELECT COUNT(*) as count FROM sub_category WHERE category_id = ?";
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return 0;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $row['count'] ?? 0;
}

/**
 * Get price filter ranges with product counts
 */
function getPriceFilterRanges($db_connection, $category_id = null, $sub_category_id = null) {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Only approved products
    $where_conditions[] = "status = 'approved'";

    if ($category_id) {
        $where_conditions[] = "category_id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if ($sub_category_id) {
        $where_conditions[] = "sub_category_id = ?";
        $params[] = $sub_category_id;
        $param_types .= "i";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query = "SELECT 
                CASE 
                    WHEN selling_price BETWEEN 1 AND 1000 THEN '1-1000'
                    WHEN selling_price BETWEEN 1001 AND 2000 THEN '1001-2000'
                    WHEN selling_price BETWEEN 2001 AND 5000 THEN '2001-5000'
                    WHEN selling_price BETWEEN 5001 AND 10000 THEN '5001-10000'
                    WHEN selling_price > 10000 THEN '10000+'
                END as price_range,
                COUNT(*) as count
              FROM products 
              $where_clause 
              GROUP BY price_range 
              ORDER BY MIN(selling_price)";
    
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $ranges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ranges[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $ranges;
}

/**
 * Get coin filter ranges with product counts
 */
function getCoinFilterRanges($db_connection, $category_id = null, $sub_category_id = null) {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Only approved products
    $where_conditions[] = "status = 'approved'";

    if ($category_id) {
        $where_conditions[] = "category_id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if ($sub_category_id) {
        $where_conditions[] = "sub_category_id = ?";
        $params[] = $sub_category_id;
        $param_types .= "i";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query = "SELECT 
                CASE 
                    WHEN coin BETWEEN 0 AND 10 THEN '0-10'
                    WHEN coin BETWEEN 11 AND 20 THEN '11-20'
                    WHEN coin BETWEEN 21 AND 50 THEN '21-50'
                    WHEN coin BETWEEN 51 AND 100 THEN '51-100'
                    WHEN coin > 100 THEN '100+'
                END as coin_range,
                COUNT(*) as count
              FROM products 
              $where_clause 
              GROUP BY coin_range 
              ORDER BY MIN(coin)";
    
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $ranges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $ranges[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $ranges;
}

/**
 * Get rating filter data using reviews table
 */
function getRatingFilterData($db_connection, $category_id = null, $sub_category_id = null) {
    $where_conditions = [];
    $params = [];
    $param_types = "";
    
    // Only approved products
    $where_conditions[] = "p.status = 'approved'";

    if ($category_id) {
        $where_conditions[] = "p.category_id = ?";
        $params[] = $category_id;
        $param_types .= "i";
    }
    
    if ($sub_category_id) {
        $where_conditions[] = "p.sub_category_id = ?";
        $params[] = $sub_category_id;
        $param_types .= "i";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    // Get products with average ratings from reviews table
    $query = "SELECT 
                p.id,
                p.product_name,
                COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) as avg_rating,
                COUNT(r.id) as review_count,
                CASE 
                    WHEN COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) = 0 THEN '0-1'
                    WHEN COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) BETWEEN 1.0 AND 2.0 THEN '1-2'
                    WHEN COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) BETWEEN 2.1 AND 3.0 THEN '2-3'
                    WHEN COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) BETWEEN 3.1 AND 4.0 THEN '3-4'
                    WHEN COALESCE(AVG(CAST(r.rating AS DECIMAL(3,2))), 0) BETWEEN 4.1 AND 5.0 THEN '4-5'
                END as rating_range
              FROM products p 
              LEFT JOIN reviews r ON p.id = r.product_id 
              $where_clause 
              GROUP BY p.id, p.product_name";
    
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return [];
    }
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $rating_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rating_data[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    // Group by rating ranges
    $rating_ranges = [];
    foreach ($rating_data as $data) {
        $range = $data['rating_range'];
        if (!isset($rating_ranges[$range])) {
            $rating_ranges[$range] = 0;
        }
        $rating_ranges[$range]++;
    }
    
    return $rating_ranges;
}

/**
 * Get product rating and review count
 */
function getProductRating($db_connection, $product_id) {
    $query = "SELECT 
                COUNT(*) as review_count, 
                COALESCE(AVG(CAST(rating AS DECIMAL(3,2))), 0) as avg_rating 
              FROM reviews 
              WHERE product_id = ?";
    
    $stmt = mysqli_prepare($db_connection, $query);
    if (!$stmt) {
        return ['review_count' => 0, 'avg_rating' => 0, 'rating_percentage' => 0];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $review_count = intval($data['review_count']);
    $avg_rating = floatval($data['avg_rating']);
    $rating_percentage = $avg_rating * 20; // Convert to percentage for display
    
    return [
        'review_count' => $review_count,
        'avg_rating' => $avg_rating,
        'rating_percentage' => $rating_percentage
    ];
}

/**
 * Get product count for a category
 */
function getCategoryProductCount($db_connection, $category_id) {
    $query = "SELECT COUNT(*) as count FROM products WHERE category_id = ? AND status = 'approved'";
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return 0;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $row['count'] ?? 0;
}

/**
 * Get product count for a subcategory
 */
function getSubcategoryProductCount($db_connection, $sub_category_id, $category_id) {
    $query = "SELECT COUNT(*) as count FROM products WHERE sub_category_id = ? AND category_id = ? AND status = 'approved'";
    $stmt = mysqli_prepare($db_connection, $query);
    
    if (!$stmt) {
        return 0;
    }
    
    mysqli_stmt_bind_param($stmt, "ii", $sub_category_id, $category_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    mysqli_stmt_close($stmt);
    return $row['count'] ?? 0;
}
?>

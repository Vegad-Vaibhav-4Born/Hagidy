<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
include __DIR__ . '/../pages/includes/init.php';
if(!isset($_SESSION['vendor_reg_id'])){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($category_id <= 0) {
    echo json_encode(['subcategories' => []]);
    exit;
}

$category_id_esc = mysqli_real_escape_string($con, $category_id);
$subcategories_query = "SELECT * FROM sub_category WHERE category_id = '$category_id_esc' ORDER BY name";
$subcategories_result = mysqli_query($con, $subcategories_query);

$subcategories = [];
if ($subcategories_result) {
    while ($row = mysqli_fetch_assoc($subcategories_result)) {
        $subcategories[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['subcategories' => $subcategories]);
?>

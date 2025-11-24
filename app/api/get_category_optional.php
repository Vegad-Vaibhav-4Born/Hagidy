<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../pages/includes/init.php';


$subcategoryId = isset($_GET['sub_category_id']) ? trim($_GET['sub_category_id']) : '';
$categoryId = isset($_GET['category_id']) ? trim($_GET['category_id']) : '';
$result = ['success'=>true,'optional'=>[]];

// Prefer subcategory-based optional attributes if provided
if ($subcategoryId !== '') {
    $scid = mysqli_real_escape_string($con, $subcategoryId);
    $rs = mysqli_query($con, "SELECT optional_attributes FROM sub_category WHERE id='$scid' LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $row = mysqli_fetch_assoc($rs);
        $csv = trim((string)($row['optional_attributes'] ?? ''));
        if ($csv !== '' && $csv !== 'null') {
            $result['optional'] = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $csv)))));
        }
    }
} elseif ($categoryId !== '') {
    // Backward compatibility: if only category is provided, try mapping via any subcategory under it (best-effort)
    $cid = mysqli_real_escape_string($con, $categoryId);
    $rs = mysqli_query($con, "SELECT optional_attributes FROM sub_category WHERE category_id='$cid' AND optional_attributes IS NOT NULL AND optional_attributes <> '' AND optional_attributes <> 'null' ORDER BY id LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $row = mysqli_fetch_assoc($rs);
        $csv = trim((string)($row['optional_attributes'] ?? ''));
        if ($csv !== '' && $csv !== 'null') {
            $result['optional'] = array_values(array_filter(array_map('intval', array_map('trim', explode(',', $csv)))));
        }
    }
}

echo json_encode($result);
exit;


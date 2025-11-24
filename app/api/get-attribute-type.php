<?php 
require_once __DIR__ . '/../pages/includes/init.php';

$id = $_POST['id'];

$q = mysqli_query($con, "SELECT attribute_type FROM attributes WHERE id='$id'");
$row = mysqli_fetch_assoc($q);

$type = $row['attribute_type'];

// fetch variants too
$vq = mysqli_query($con, "SELECT value FROM attribute_values WHERE attribute_id='$id'");
$variants = [];
while ($v = mysqli_fetch_assoc($vq)) {
    $variants[] = $v['value'];
}

echo json_encode([
    "type" => $type,
    "variants" => $variants
]);

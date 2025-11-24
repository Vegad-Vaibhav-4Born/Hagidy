<?php
include __DIR__ . '/../pages/includes/init.php';


header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $coin = floatval($_POST['coin'] ?? 0);
    $platform_fee = floatval($_POST['platform_fee'] ?? 0);

    if ($productId > 0) {
        $stmt = $con->prepare("UPDATE products SET coin = ?, platform_fee = ? WHERE id = ?");
        $stmt->bind_param("ddi", $coin, $platform_fee, $productId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

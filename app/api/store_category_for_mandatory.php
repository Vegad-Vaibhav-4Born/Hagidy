<?php
session_start();
require_once __DIR__ . '/../pages/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = isset($_POST['category_id']) ? trim($_POST['category_id']) : '';
    $sub_category_id = isset($_POST['sub_category_id']) ? trim($_POST['sub_category_id']) : '';

    if ($sub_category_id !== '') {
        $_SESSION['selected_subcategory_for_mandatory'] = $sub_category_id;
        // Also store its parent category if provided
        if ($category_id !== '') {
            $_SESSION['selected_category_for_mandatory'] = $category_id;
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($category_id !== '') {
        $_SESSION['selected_category_for_mandatory'] = $category_id;
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Invalid IDs']);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
exit;
?>

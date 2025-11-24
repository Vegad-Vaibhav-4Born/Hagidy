<?php
session_start();
require_once __DIR__ . '/../pages/includes/init.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Check if clear parameter is set
if (isset($_POST['clear']) && $_POST['clear'] === '1') {
    // Clear the existing specifications session data
    unset($_SESSION['existing_specifications']);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Session cleared']);
} else {
    // Return error response
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

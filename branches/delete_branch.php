<?php
ini_set('display_errors', 0);
error_reporting(0);

// Allow CORS for the specific origin
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002"); // Your front-end origin

// Allow the methods needed (GET, POST, DELETE, OPTIONS)
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow specific headers for authentication
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Allow credentials to be sent (for cookies/session)
header("Access-Control-Allow-Credentials: true");

// Ensure the response is JSON
header('Content-Type: application/json');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Respond with 200 OK and necessary headers
    header('HTTP/1.1 200 OK');
    echo json_encode(['success' => true]);
    exit();  // Stop further processing
}

include '../check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $branch_id = $_GET['branch_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM branches WHERE branch_id = ?");
        $stmt->execute([$branch_id]);

        echo json_encode(['success' => true, 'message' => 'Branch deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting branch: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}


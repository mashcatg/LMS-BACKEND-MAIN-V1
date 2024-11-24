<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow cross-origin requests from the frontend
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['course_ids'])) {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $course_ids = explode(',', $_GET['course_ids']);
        $placeholders = implode(',', array_fill(0, count($course_ids), '?'));

        $stmt = $conn->prepare("SELECT * FROM batches WHERE course_id IN ($placeholders)");
        $stmt->execute($course_ids);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['batches' => $batches]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching batches: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

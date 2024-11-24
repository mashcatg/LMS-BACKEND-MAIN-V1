<?php
ini_set('display_errors', 0);
error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
exit(0);
}
header('Content-Type: application/json');

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

    // Ensure you get the batch_id from the query parameters
    $batch_id = $_GET['batch_id'] ?? null;

    if ($batch_id) {
        try {
            $stmt = $conn->prepare("DELETE FROM batches WHERE batch_id = ?");
            $stmt->execute([$batch_id]);

            echo json_encode(['success' => true, 'message' => 'Batch deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error deleting batch: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Batch ID is required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
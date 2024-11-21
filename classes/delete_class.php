<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://localhost:3000");
    header("Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    exit(0); // End the script to avoid further processing
}
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }


    $class_id = $_GET['class_id'] ?? null;
    $playlist_id = $_GET['playlist_id'] ?? null;

    // Validate that class_id and playlist_id are provided
    if (!$class_id || !$playlist_id) {
        echo json_encode(['success' => false, 'message' => 'Class ID and Playlist ID are required']);
        exit();
    }

    try {
        // Prepare the SQL statement
        $sql = "DELETE FROM classes WHERE class_id = :class_id AND playlist_id = :playlist_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':class_id', $class_id, PDO::PARAM_INT);
        $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);

        // Execute the query and check if successful
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete class']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

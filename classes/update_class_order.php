<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow requests from the client
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Get the raw POST data and decode it
    $input = json_decode(file_get_contents("php://input"), true);
    $playlist_id = $input['playlist_id'] ?? null;
    $newOrder = $input['newOrder'] ?? [];

    if (!$playlist_id || empty($newOrder)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Update each class with the new index
        $stmt = $conn->prepare("UPDATE classes SET class_index = :class_index WHERE class_id = :class_id AND playlist_id = :playlist_id");

        foreach ($newOrder as $class) {
            $stmt->bindParam(':class_index', $class['class_index'], PDO::PARAM_INT);
            $stmt->bindParam(':class_id', $class['class_id'], PDO::PARAM_INT);
            $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Commit the transaction
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Class order updated successfully']);
    } catch (PDOException $e) {
        // Rollback the transaction in case of an error
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

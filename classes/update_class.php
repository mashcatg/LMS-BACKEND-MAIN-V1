<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];
        $class_id = $_GET['class_id'] ?? null;
        $class_name = $_POST['class_name'] ?? null;
        $class_link = $_POST['class_link'] ?? null;
        $class_description = $_POST['class_description'] ?? null;
        
        
        $note_ids = isset($_POST['note_id']) ? $_POST['note_id'] : null;
        if (is_array($note_ids)) {
            $note_id = implode(',', $note_ids); 
        } else {
            $note_id = $note_ids; 
        }
        
        $playlist_id = $_GET['playlist_id'] ?? null;

        // Validate that class_id is present
        if (!$class_id) {
            echo json_encode(['success' => false, 'message' => 'Class ID is required']);
            exit();
        }

        $sql = "UPDATE classes SET 
         class_name = :class_name, 
         class_link = :class_link, 
         class_description = :class_description,
         note_id = :note_id
         WHERE class_id = :class_id AND playlist_id = :playlist_id AND service_id = :service_id";


        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':class_name', $class_name);
        $stmt->bindParam(':class_link', $class_link);
        $stmt->bindParam(':class_description', $class_description);
        $stmt->bindParam(':note_id', $note_id);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':service_id', $service_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update class']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
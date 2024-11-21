<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Check if the database connection is established
if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
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
        $created_by = $_SESSION['admin_id'];
        $created_at = date('Y-m-d H:i:s');
        $class_name = $_POST['class_name'] ?? null;
        $class_link = $_POST['class_link'] ?? null;
        $class_description = $_POST['class_description'] ?? null;
        
        // Handle multiple note_id's as comma-separated values
        $note_ids = isset($_POST['note_id']) ? $_POST['note_id'] : null;
        if (is_array($note_ids)) {
            $note_id = implode(',', $note_ids); 
        } else {
            $note_id = $note_ids; // Single note_id
        }
        
        $playlist_id = $_GET['playlist_id'] ?? null;

        $sql = "INSERT INTO classes (class_name, class_link, class_description, note_id, playlist_id, service_id, created_by, created_at) 
                VALUES (:class_name, :class_link, :class_description, :note_id, :playlist_id, :service_id, :created_by, :created_at)";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':class_name', $class_name);
        $stmt->bindParam(':class_link', $class_link);
        $stmt->bindParam(':class_description', $class_description);
        $stmt->bindParam(':note_id', $note_id);
        $stmt->bindParam(':playlist_id', $playlist_id);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':created_by', $created_by);
        $stmt->bindParam(':created_at', $created_at);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add class']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

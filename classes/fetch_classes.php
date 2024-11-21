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
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];
        $sql = "SELECT * FROM classes WHERE service_id = :service_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->execute();

        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process each class to fetch note names
        foreach ($classes as &$class) {
            $note_ids = [];

            // Handle multiple note_ids
            if (strpos($class['note_id'], ',') !== false) {
                // Convert comma-separated note_ids to array
                $note_ids = explode(',', $class['note_id']);
            } else {
                // Single note_id
                $note_ids = [$class['note_id']];
            }

            // Fetch the note names based on note_ids
            if (!empty($note_ids)) {
                $note_ids_placeholder = implode(',', array_fill(0, count($note_ids), '?'));
                $note_query = "SELECT note_name FROM notes WHERE note_id IN ($note_ids_placeholder)";
                $note_stmt = $conn->prepare($note_query);
                $note_stmt->execute($note_ids);

                $notes = $note_stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch only the note_name column
                $class['note_names'] = $notes;
            } else {
                $class['note_names'] = [];
            }
        }

        echo json_encode(['success' => true, 'classes' => $classes]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>

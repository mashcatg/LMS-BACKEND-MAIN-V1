<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];

        // Fetch notes
        $stmt = $conn->prepare("
            SELECT
                notes.note_id,
                notes.note_name,
                notes.file_address,
                notes.note_tags,
                notes.course_id,   
                notes.batch_id,    
                notes.created_by,
                notes.created_at
            FROM
                notes
            WHERE
                notes.service_id = :service_id
            ORDER BY note_id DESC
        ");
        $stmt->execute([':service_id' => $service_id]);

        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notes as &$note) {
            // Fetch course names based on the comma-separated course_ids
            if (!empty($note['course_id'])) {
                $course_ids = explode(',', $note['course_id']);
                $course_placeholder = implode(',', array_fill(0, count($course_ids), '?'));
                $course_stmt = $conn->prepare("
                    SELECT GROUP_CONCAT(course_name ORDER BY course_name ASC SEPARATOR ', ') AS course_names
                    FROM courses
                    WHERE course_id IN ($course_placeholder)
                ");
                $course_stmt->execute($course_ids);
                $course_result = $course_stmt->fetch(PDO::FETCH_ASSOC);
                $note['course_names'] = $course_result['course_names'];
            }

            // Fetch batch names based on the comma-separated batch_ids
            if (!empty($note['batch_id'])) {
                $batch_ids = explode(',', $note['batch_id']);
                $batch_placeholder = implode(',', array_fill(0, count($batch_ids), '?'));
                $batch_stmt = $conn->prepare("
                    SELECT GROUP_CONCAT(batch_name ORDER BY batch_name ASC SEPARATOR ', ') AS batch_names
                    FROM batches
                    WHERE batch_id IN ($batch_placeholder)
                ");
                $batch_stmt->execute($batch_ids);
                $batch_result = $batch_stmt->fetch(PDO::FETCH_ASSOC);
                $note['batch_names'] = $batch_result['batch_names'];
            }

            // Truncate course_names and batch_names to 80 characters if they exceed the limit
            if (strlen($note['course_names']) > 80) {
                $note['course_names'] = substr($note['course_names'], 0, 77) . '...';
            }
            if (strlen($note['batch_names']) > 80) {
                $note['batch_names'] = substr($note['batch_names'], 0, 77) . '...';
            }
        }

        echo json_encode(['success' => true, 'notes' => $notes]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

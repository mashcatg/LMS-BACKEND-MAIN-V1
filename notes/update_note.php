<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers to allow cross-origin requests from the frontend
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
// Ensure the user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$created_by = $_SESSION['admin_id'];

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $note_id = $_GET['note_id'];
        $note_name = $_POST['note_name'];
        $note_tags = $_POST['note_tags'];
        $course_ids = $_POST['course_id'];
        $batch_ids = $_POST['batch_id'] ?? null;
        $created_at = date('Y-m-d H:i:s');
        $file_address = '';

        // Fetch current note details
        $stmt = $conn->prepare("SELECT course_id, batch_id, file_address FROM notes WHERE note_id = :note_id");
        $stmt->bindParam(':note_id', $note_id);
        $stmt->execute();
        $currentNote = $stmt->fetch(PDO::FETCH_ASSOC);

        // If a file is uploaded, handle it
        if (isset($_FILES['file_address']) && $_FILES['file_address']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['file_address']['tmp_name'];
            $file_name = $_FILES['file_address']['name'];
            $file_path = '../uploads/' . basename($file_name);
            $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/'.basename($file_name);
            // Move the uploaded file
            if (move_uploaded_file($file_tmp_path, $file_path)) {
                $file_address = $file_path; // Save the new file path
            } else {
                echo json_encode(['success' => false, 'message' => 'Error uploading file']);
                exit();
            }
        } else {
            // Use the existing values if no new file is uploaded
            $filePathToSave = $currentNote['file_address'];
        }

        // Use existing course and batch IDs if not provided
        $course_ids = !empty($course_ids) ? $course_ids : $currentNote['course_id'];
        $batch_ids = !empty($batch_ids) ? $batch_ids : $currentNote['batch_id'];

        // Update the note in the database
        $stmt = $conn->prepare("UPDATE notes SET note_name = :note_name, note_tags = :note_tags, course_id = :course_ids, batch_id = :batch_ids, created_by = :created_by, created_at = :created_at, file_address = :filePathToSave WHERE note_id = :note_id");
        
        $stmt->bindParam(':note_id', $note_id);
        $stmt->bindParam(':note_name', $note_name);
        $stmt->bindParam(':note_tags', $note_tags);
        $stmt->bindParam(':course_ids', $course_ids);
        $stmt->bindParam(':batch_ids', $batch_ids);
        $stmt->bindParam(':created_by', $created_by);
        $stmt->bindParam(':created_at', $created_at);
        $stmt->bindParam(':filePathToSave', $filePathToSave);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update note']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating note: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

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

// Ensure the user is authenticated
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
$service_id = $_SESSION['service_id'];
// Handle POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        
        $note_name = $_POST['note_name'];
        $note_tags = $_POST['note_tags'];
        $course_ids = $_POST['course_id'];
        $batch_ids = $_POST['batch_id'];
        $created_by = $_SESSION['admin_id']; // Assuming this is the current user ID
        $created_at = date('Y-m-d H:i:s');

        // Handle file upload
        if (isset($_FILES['file_address']) && $_FILES['file_address']['error'] == 0) {
            $file_tmp_path = $_FILES['file_address']['tmp_name'];
            $file_name = $_FILES['file_address']['name'];
            $file_store_path = "../uploads/" . basename($file_name); // Change this path as needed
            $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/'.basename($file_name);

            // Move the uploaded file to the desired directory
            if (move_uploaded_file($file_tmp_path, $file_store_path)) {
                $file_address = $file_store_path;

                // Prepare insert statement
                $stmt = $conn->prepare("INSERT INTO notes (note_name, file_address, note_tags, course_id, batch_id, created_by, created_at, service_id) VALUES (:note_name, :filePathToSave, :note_tags, :course_ids, :batch_ids, :created_by, :created_at, :service_id)");
                $stmt->bindParam(':note_name', $note_name);
                $stmt->bindParam(':filePathToSave', $filePathToSave);
                $stmt->bindParam(':note_tags', $note_tags);
                $stmt->bindParam(':course_ids', $course_ids);
                $stmt->bindParam(':batch_ids', $batch_ids);
                $stmt->bindParam(':created_by', $created_by);
                $stmt->bindParam(':created_at', $created_at);
                $stmt->bindParam(':service_id', $service_id);
                
                // Execute the statement
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Note added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add note']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'File upload failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding note: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

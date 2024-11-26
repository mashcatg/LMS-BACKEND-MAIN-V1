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

// Handle POST request for creating a routine
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $routine_name = $_POST['routine_name'];
        $course_id = $_POST['course_id']; // Assume single course for simplicity
        $batch_id = $_POST['batch_id']; // Assume single batch for simplicity
        $created_by = $_SESSION['admin_id']; // Current user ID
        $created_at = date('Y-m-d H:i:s');

        // Handle file upload (if required)
        if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $file_name = $_FILES['file']['name'];
            $file_store_path = "../uploads/" . basename($file_name); 
            $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/'.basename($file_name);
            // Move the uploaded file to the desired directory
            if (move_uploaded_file($file_tmp_path, $file_store_path)) {
                $file_address = $file_store_path;

                // Prepare insert statement for routine
                $stmt = $conn->prepare("INSERT INTO routine (routine_name, file_address, course_id, batch_id, created_by, created_at, service_id) VALUES (:routine_name, :filePathToSave, :course_id, :batch_id, :created_by, :created_at, :service_id)");
                $stmt->bindParam(':routine_name', $routine_name);
                $stmt->bindParam(':filePathToSave', $filePathToSave);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->bindParam(':batch_id', $batch_id);
                $stmt->bindParam(':created_by', $created_by);
                $stmt->bindParam(':created_at', $created_at);
                $stmt->bindParam(':service_id', $service_id);
                
                // Execute the statement
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Routine added successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add routine']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'File upload failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding routine: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

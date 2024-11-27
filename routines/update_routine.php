
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve routine_id from query parameters
    $routine_id = $_GET['routine_id'] ?? null;
    $routine_name = $_POST['routine_name'] ?? '';
    $course_ids = $_POST['course_id'] ?? ''; 
    $batch_ids = $_POST['batch_id'] ?? '';  
    $file_address = null; 

    // Validate required fields
    if (empty($routine_id) || empty($routine_name) || empty($course_ids) || empty($batch_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    try {
        // Fetch current routine details
        $stmt = $conn->prepare("SELECT file_address FROM routine WHERE routine_id = :routine_id");
        $stmt->execute([':routine_id' => $routine_id]);
        $currentRoutine = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentRoutine) {
            echo json_encode(['success' => false, 'message' => 'Routine not found']);
            exit();
        }

        // Retain the existing file_address if no new file is uploaded
        $filePathToSave = $currentRoutine['file_address'];

        // If a file is uploaded, handle it
        if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_path = $_FILES['file']['tmp_name'];
            $file_name = $_FILES['file']['name'];
            $file_path = '../uploads/' . basename($file_name); 
            $filePathToSave = 'http://lms.ennovat.com/lms-admin/uploads/'.basename($file_name);
            // Move the uploaded file to the desired directory
            if (move_uploaded_file($file_tmp_path, $file_path)) {
                $file_address = $file_path;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error uploading file']);
                exit();
            }
        }

        // Prepare SQL query to update routine details
        $stmt = $conn->prepare("
            UPDATE routine 
            SET 
                routine_name = :routine_name, 
                course_id = :course_ids, 
                batch_id = :batch_ids, 
                file_address = :filePathToSave
            WHERE routine_id = :routine_id
        ");

        // Execute the query
        $stmt->execute([
            ':routine_name' => $routine_name,
            ':course_ids' => $course_ids,
            ':batch_ids' => $batch_ids,
            ':filePathToSave' => $filePathToSave,
            ':routine_id' => $routine_id,
        ]);

        echo json_encode(['success' => true, 'message' => 'Routine updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating routine: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

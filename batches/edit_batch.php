<?php
ini_set('display_errors', 0);
error_reporting(0);
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    // Read the input data
    $input = json_decode(file_get_contents('php://input'), true);
    $batch_id = $input['batch_id'];
    $batch_name = $input['batch_name'];
    $course_id = $input['course_id'];
    $branch_id = (int) $input['branch_id']; 
    $accepting_admission = $input['accepting_admission'];

    try {
        $stmt = $conn->prepare("UPDATE batches SET batch_name = ?, course_id = ?, branch_id = ?, accepting_admission = ? WHERE batch_id = ?");
        $stmt->execute([$batch_name, $course_id, $branch_id, $accepting_admission, $batch_id]);

        $updated_batch = [
            'batch_id' => $batch_id,
            'batch_name' => $batch_name,
            'course_id' => $course_id,
            'branch_id' => $branch_id,
            'accepting_admission' => $accepting_admission
        ];

        echo json_encode(['success' => true, 'message' => 'Batch updated successfully', 'batch' => $updated_batch]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating batch: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

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
    $data = json_decode(file_get_contents("php://input"), true);
    $batch_name = $data['batch_name'];
    $course_id = $data['course_id'];
    $branch_id = $data['branch_id'];
    $accepting_admission = $data['is_accepting_admission'];
    
    $service_id = $_SESSION['service_id'];

    try {
        $stmt = $conn->prepare("INSERT INTO batches (batch_name, course_id, branch_id, accepting_admission, service_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$batch_name, $course_id, $branch_id, $accepting_admission, $service_id]);

        $batch_id = $conn->lastInsertId();
        $new_batch = [
            'batch_name' => $batch_name,
            'course_id' => $course_id,
            'branch_id' => $branch_id,
            'accepting_admission' => $accepting_admission,
            'service_id' => $service_id
        ];

        echo json_encode(['success' => true, 'message' => 'Batch added successfully', 'batch' => $new_batch]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding batch: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
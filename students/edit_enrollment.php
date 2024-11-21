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

// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$enrollment_id = $_GET['enrollment_id'];
$student_number = $data['student_number'];
$student_index = $data['student_index'];
$course_id = $data['course_id'];
$batch_id = $data['batch_id'];
// $paid_amount = $data['paid_amount'];
// $discounted_amount = $data['discounted_amount'];
// $payment_method = 'offline';
try {
    // Fetch student details
    $selectEnrollments = $conn->prepare("SELECT * FROM enrollments WHERE enrollment_id = ? AND service_id = ? LIMIT 1");
    $selectEnrollments->execute([$enrollment_id, $_SESSION['service_id']]);
    $rowCountEnrollments = $selectEnrollments->rowCount();

    if ($rowCountEnrollments > 0) {
        $stmt = $conn->prepare("UPDATE enrollments SET student_index = ?, course_id = ?, batch_id = ? WHERE enrollment_id = ?");
        $stmt->execute([$student_index, $course_id, $batch_id, $enrollment_id]);
        echo json_encode([
            'success' => true,
            'message' => 'Enrollment updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid enrollment ID']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating enrollment: ' . $e->getMessage()]);
}
?>

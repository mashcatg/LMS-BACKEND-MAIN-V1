<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php'; // Assuming this is checking if the user is authenticated

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
} 

// Ensure course_id is passed properly
if (!isset($_POST['course_id'])) {
    echo json_encode(['success' => false, 'message' => 'No course ID provided']);
    exit();
}

$req_course_id = $_POST['course_id'];
$student_id = $_SESSION['student_id'];

try {
    // Fetch course_id and student_index from enrollments
    $stmt = $conn->prepare("SELECT course_id, student_index, batch_id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id");
    $stmt->execute(['student_id' => $student_id, 'course_id' => $req_course_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch a single row

    if (!$enrollment) {
        echo json_encode(['success' => false, 'message' => 'No enrollment found']); // No enrollments found
        exit();
    }

    // Set course_id and student_index in session
    $_SESSION['course_id'] = $enrollment['course_id'];
    $_SESSION['batch_id'] = $enrollment['batch_id'];
    $_SESSION['student_index'] = $enrollment['student_index']; // Store in session

    echo json_encode(['success' => true, 'course_id' => $_SESSION['course_id'], 'student_index' => $_SESSION['student_index'], 'batch_id' => $_SESSION['batch_id']]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

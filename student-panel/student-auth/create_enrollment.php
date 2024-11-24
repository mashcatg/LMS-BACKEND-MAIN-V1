<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
} 

$data = json_decode(file_get_contents('php://input'), true);

$student_id = $data['student_id'] ?? $_SESSION['student_id'] ?? '';
$course_id = $data['course_id'] ?? '';
$batch_id = $data['batch_id'] ?? '';
$student_index = $data['student_index'] ?? '';
$service_id = $data['service_id'] ?? '';

if (empty($student_id) || empty($course_id) || empty($batch_id) || empty($student_index)) {
    echo json_encode(['success' => false, 'message' => 'Student ID, Course ID, Batch ID, or Student Index is missing.']);
    exit();
}

// Check if the student is already enrolled in this course
$stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
$existing_enrollment_count = $stmt->fetchColumn();

if ($existing_enrollment_count > 0) {
    echo json_encode(['success' => false, 'message' => 'The student is already enrolled in this course.']);
    exit();
}

$enrollment_time = date('Y-m-d H:i:s');

try {
    $conn->beginTransaction();

    // Insert data into the enrollments table
    $stmt = $conn->prepare("
        INSERT INTO enrollments 
        (student_id, course_id, batch_id, student_index, enrollment_time, notification_read_status, service_id) 
        VALUES (?, ?, ?, ?, ?, 'unread', ?)
    ");
    $stmt->execute([$student_id, $course_id, $batch_id, $student_index, $enrollment_time, $service_id]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Enrollment created successfully']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error creating enrollment: ' . $e->getMessage()]);
}
?>

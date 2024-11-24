<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ?? '61545';
$student_id = $_SESSION['student_id'] ?? '1';
$course_id = $_SESSION['course_id'] ?? '1';
$student_index = $_SESSION['student_index'] ?? '1234';
try {
    // Query to fetch attendance data
    $stmt = $conn->prepare("
        SELECT
            a.attendance_id,
            a.attendance_date,
            a.student_index,
            a.created_at,
            a.created_by,
            e.student_id,
            e.course_id,
            e.batch_id,
            c.course_name,
            b.batch_name,
            s.student_name
        FROM
            attendance a
        JOIN
            enrollments e ON a.student_index = e.student_index
        JOIN
            courses c ON e.course_id = c.course_id
        JOIN
            batches b ON e.batch_id = b.batch_id
        JOIN
            students s ON e.student_id = s.student_id
        WHERE
            a.service_id = :service_id AND a.student_index = :student_index
    ");

    // Execute the attendance query
    $stmt->execute(['service_id' => $service_id, 'student_index' => $student_index]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total attendance count for the student
    $total_attendances = count($attendance);

    // Send the response with attendance data and total counts
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'total_attendances' => $total_attendances
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

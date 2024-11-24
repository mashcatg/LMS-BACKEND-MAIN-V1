<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
} 

$student_id = $_SESSION['student_id'];

try {
    // Fetch courses directly with a JOIN on enrollments
    $stmt = $conn->prepare("
        SELECT c.*
        FROM courses c
        JOIN enrollments e ON c.course_id = e.course_id
        WHERE e.student_id = :student_id
    ");
    
    $stmt->execute(['student_id' => $student_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the filtered courses
    echo json_encode(['courses' => $courses]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
}
?>

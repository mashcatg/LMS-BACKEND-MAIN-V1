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
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE student_id = :student_id");
    $stmt->execute(['student_id' => $student_id]);
    $enrollments = $stmt->fetchAll();

    echo json_encode(['enrollments' => $enrollments]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
}
?>
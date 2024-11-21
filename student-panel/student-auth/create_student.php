<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include '../../db.php';

$data = json_decode(file_get_contents('php://input'), true);

$student_name = $data['student_name'] ?? '';
$student_image = $data['student_image'] ?? '';
$student_number = $data['student_number'] ?? '';
$student_password = $data['student_password'] ?? '';
$father_name = $data['father_name'] ?? '';
$father_number = $data['father_number'] ?? '';
$mother_name = $data['mother_name'] ?? '';
$mother_number = $data['mother_number'] ?? '';
$student_institution = $data['student_institution'] ?? '';
$student_address = $data['student_address'] ?? '';
$student_date_of_birth = $data['student_date_of_birth'] ?? '';
$service_id = $data['service_id'] ?? '';

if (empty($student_name) || empty($student_number) || empty($student_password)) {
    echo json_encode(['success' => false, 'message' => 'Student name, phone number, or password is missing.']);
    exit();
}

// Check if student number already exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
$stmt->execute([$student_number]);
$existing_student_count = $stmt->fetchColumn();

if ($existing_student_count > 0) {
    echo json_encode(['success' => false, 'message' => 'This phone number is already existed.']);
    exit();
}

// Hash the password
$hashed_password = password_hash($student_password, PASSWORD_BCRYPT);
$created_at = date('Y-m-d H:i:s');

try {
    $conn->beginTransaction();

    // Insert student data into the students table
    $stmt = $conn->prepare("
        INSERT INTO students 
        (student_name, student_image, student_number, student_password, father_name, father_number, mother_name, mother_number, student_institution, student_address, student_date_of_birth, created_at, service_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$student_name, $student_image, $student_number, $hashed_password, $father_name, $father_number, $mother_name, $mother_number, $student_institution, $student_address, $student_date_of_birth, $created_at, $service_id]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Student created successfully']);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error creating student: ' . $e->getMessage()]);
}
?>

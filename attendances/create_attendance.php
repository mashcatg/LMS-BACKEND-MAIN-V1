<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit; // End the script for OPTIONS requests
}

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

$attendance_date = $_POST['attendance_date'] ?? '';
$student_index = $_POST['student_index'] ?? '';

// Validate form data
if (empty($attendance_date) || empty($student_index)) {
    echo json_encode(['success' => false, 'message' => 'Required field is missing.']);
    exit();
}

if (!isset($_SESSION['service_id']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$service_id = $_SESSION['service_id'];
$created_by = $_SESSION['admin_id'];
$time = date('Y-m-d H:i:s');

try {
    // Check if the student is enrolled
    $enroll_check = $conn->prepare("SELECT student_index FROM enrollments WHERE service_id = ? AND student_index = ?");
    $enroll_check->execute([$service_id, $student_index]);

    if ($enroll_check->rowCount() == 0) {
        echo json_encode(['success' => false, 'message' => 'Student not enrolled in this service.']);
        exit();
    }

    // Check if the attendance already exists for this student on the same date
    $attendance_check = $conn->prepare("SELECT * FROM attendance WHERE student_index = ? AND attendance_date = ? AND service_id = ?");
    $attendance_check->execute([$student_index, $attendance_date, $service_id]);

    if ($attendance_check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Attendance already exists for this student on this date.']);
        exit();
    }

    // Insert the attendance record into the database
    $stmt = $conn->prepare("INSERT INTO attendance (student_index, attendance_date, created_at, created_by, service_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$student_index, $attendance_date, $time, $created_by, $service_id]);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Attendance created successfully'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating attendance: ' . $e->getMessage()]);
    error_log($e->getMessage()); // Log the error message for debugging
}

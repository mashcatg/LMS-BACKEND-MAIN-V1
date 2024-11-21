<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle OPTIONS preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
// Check if the database connection is established
if (!isset($conn)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check authentication
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    $service_id = $_SESSION['service_id'];
    $created_by = $_SESSION['admin_id'];
    $created_at = date('Y-m-d H:i:s'); // Current time

    // Directly access the POST data
    $exam_name = $_POST['exam_name'] ?? null;
    $exam_date = $_POST['exam_date'] ?? null;
    $mcq_marks = $_POST['mcq_marks'] ?? null;
    $cq_marks = $_POST['cq_marks'] ?? null;
    $practical_marks = $_POST['practical_marks'] ?? null;
    $bonus_marks = $_POST['bonus_marks'] ?? null;
    $student_visibility = $_POST['student_visibility'] ?? null;
    $course_ids = $_POST['course_id'] ?? null;

    // Validate required fields
    if (!$exam_name || !$exam_date || !$mcq_marks || !$cq_marks || !$student_visibility || !$course_ids) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing input']);
        exit();
    }

    // Insert into database
    try {
        $stmt = $conn->prepare("INSERT INTO exams (service_id, course_id, exam_name, exam_date, mcq_marks, cq_marks, practical_marks, bonus_marks, student_visibility, created_at, created_by) 
                                VALUES (:service_id, :course_id, :exam_name, :exam_date, :mcq_marks, :cq_marks, :practical_marks, :bonus_marks, :student_visibility, :created_at, :created_by)");

        $stmt->execute([
            ':service_id' => $service_id,
            ':course_id' => $course_ids,
            ':exam_name' => $exam_name,
            ':exam_date' => $exam_date,
            ':mcq_marks' => $mcq_marks,
            ':cq_marks' => $cq_marks,
            ':practical_marks' => $practical_marks,
            ':bonus_marks' => $bonus_marks,
            ':student_visibility' => $student_visibility,
            ':created_at' => $created_at,
            ':created_by' => $created_by,
        ]);

        echo json_encode(['success' => true, 'message' => 'Exam added successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

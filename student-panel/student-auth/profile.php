<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow cross-origin requests from your frontend app
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Include authentication check
include 'check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Get student_id and service_id from session
$student_id = $_SESSION['student_id'] ?? '1';
$service_id = $_SESSION['service_id'] ?? '61545';

if (!$student_id || !$service_id) {
    echo json_encode(['error' => 'Invalid session']);
    exit();
}

try {
    // Prepare SQL to fetch the student's profile
    $stmt = $conn->prepare("
        SELECT 
            student_name, student_image, student_number, father_name, 
            father_number, mother_name, mother_number, student_institution,
            student_address, student_date_of_birth, student_number 
        FROM students 
        WHERE student_id = :student_id AND service_id = :service_id
    ");
    $stmt->execute([':student_id' => $student_id, ':service_id' => $service_id]);

    // Fetch student data
    $studentProfile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($studentProfile) {
        echo json_encode(['success' => true, 'student' => $studentProfile]);
    } else {
        echo json_encode(['error' => 'Profile not found']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>

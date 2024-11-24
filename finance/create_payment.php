<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
include '../sms.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Get data from the frontend
$data = json_decode(file_get_contents("php://input"), true);
$discounted_amount = $data['discounted_amount'] ?? null;
$paid_amount = $data['paid_amount'] ?? null;
$student_index = $data['student_index'] ?? null;
$method = "offline";

if (is_null($discounted_amount) || is_null($paid_amount) || is_null($student_index)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Get service_id from session
    $service_id = $_SESSION['service_id'];
    $admin_id = $created_by = $_SESSION['admin_id'];
    // Get enrollment_id from enrollments table
    $enrollmentStmt = $conn->prepare("SELECT enrollment_id, student_id FROM enrollments WHERE student_index = ? AND service_id = ?");
    $enrollmentStmt->execute([$student_index, $service_id]);
    $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$enrollment) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
        exit();
    }

    // Select student details
    $studentDetails = $conn->prepare("SELECT student_name, student_number FROM students WHERE student_id = ? AND service_id = ?");
    $studentDetails->execute([$enrollment['student_id'], $service_id]);
    $students = $studentDetails->fetch(PDO::FETCH_ASSOC);

    if (!$students) {
        echo json_encode(['success' => false, 'message' => 'Student details not found']);
        exit();
    }

    // Insert payment record
    $stmt = $conn->prepare("INSERT INTO payments (student_index, payment_time, paid_amount, method, discounted_amount, enrollment_id, created_by, service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$student_index, date('Y-m-d H:i:s'), $paid_amount, $method, $discounted_amount, $enrollment['enrollment_id'], $created_by, $service_id]);

    // Send SMS notification
    $sms_text = "Hi, {$students['student_name']}. Your payment of amount {$paid_amount} has been received. Please, check your account for more details.";
    $receiver_type = "student";
    $smsResponse = calculateSmsCost($sms_text, $students['student_number'], $receiver_type);

    echo json_encode(['success' => true, 'message' => 'Payment created successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating payment: ' . $e->getMessage()]);
}
?>

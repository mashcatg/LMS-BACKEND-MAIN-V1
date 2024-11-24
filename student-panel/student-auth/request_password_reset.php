<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include '../../db.php';
include '../../sms.php';
$data = json_decode(file_get_contents('php://input'), true);
$student_number = $data['student_number'] ?? '';

if (empty($student_number)) {
    echo json_encode(['success' => false, 'message' => 'Student phone number is required.']);
    exit();
}

// Generate a 6-digit OTP
$otp = rand(100000, 999999);
// Set OTP expiration time (e.g., 10 minutes from now)
$otp_expiry_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));

try {
    // Check if the student exists
    $stmt = $conn->prepare("SELECT student_id, student_name FROM students WHERE student_number = ?");
    $stmt->execute([$student_number]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }

    // Update student record with OTP and expiration time
    $stmt = $conn->prepare("UPDATE students SET student_otp = ?, student_otp_expiry_time = ? WHERE student_number = ?");
    $stmt->execute([$otp, $otp_expiry_time, $student_number]);

    // SMS functionality - MASHRAF
    // For now, we'll just return the OTP in the response for testing
    $sms_type = 'student';
    $numbers = $student_number;
    $text = "Hi " . $student['student_name'] . ", your OTP is $otp";
    calculateSmsCost($text, $numbers, $sms_type);
    echo json_encode(['success' => true, 'message' => 'OTP sent to your phone number.', 'otp' => $otp]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error generating OTP: ' . $e->getMessage()]);
}
?>

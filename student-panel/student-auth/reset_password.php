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
$student_number = $data['student_number'] ?? '';
$new_password = $data['new_password'] ?? '';
$otp = $data['otp'] ?? '';

if (empty($student_number) || empty($new_password) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Phone number, new password, or OTP is missing.']);
    exit();
}

try {
    // Fetch student and OTP details
    $stmt = $conn->prepare("SELECT student_otp, student_otp_expiry_time FROM students WHERE student_number = ?");
    $stmt->execute([$student_number]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
        exit();
    }

    // Check if OTP matches and is not expired
    if ($student['student_otp'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
        exit();
    }

    if (new DateTime() > new DateTime($student['student_otp_expiry_time'])) {
        echo json_encode(['success' => false, 'message' => 'OTP has expired.']);
        exit();
    }

    // Hash the new password
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Update the student password
    $stmt = $conn->prepare("UPDATE students SET student_password = ?, student_otp = NULL, student_otp_expiry_time = NULL WHERE student_number = ?");
    $stmt->execute([$hashed_password, $student_number]);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()]);
}
?>

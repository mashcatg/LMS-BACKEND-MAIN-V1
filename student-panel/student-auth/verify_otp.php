<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

session_start();
include '../../db.php';

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Log the incoming data to check if it's correct
file_put_contents('php://stderr', json_encode($data) . "\n");

$student_number = $data['phone'] ?? '';
$otp = $data['otp'] ?? '';
$service_id = $_COOKIE['service_id'];
if (empty($student_number) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Phone number or OTP is missing.']);
    exit();
}

if (!$service_id) {
    echo json_encode(['success' => false, 'message' => 'Service ID is missing or invalid.']);
    exit();
}
// Normalize phone number
    if (preg_match('/^01[3-9]\d{8}$/', $student_number)) {
        // Phone starts with '01'
        $student_number = '88' . $student_number;
    } elseif (preg_match('/^1\d{10}$/', $student_number)) {
        // Phone starts with '1'
        $student_number = '880' . substr($student_number, 1); // Remove the '1' and prepend '880'
    } elseif (!preg_match('/^8801[3-9]\d{8}$/', $student_number)) {
        // Invalid phone number format
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
        exit;
    }
try {
    // Fetch student and OTP details
    $stmt = $conn->prepare("SELECT student_id, student_otp, student_otp_expiry_time, student_password FROM students WHERE student_number = ? AND service_id = ?");
    $stmt->execute([$student_number, $service_id]);
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

    // Generate a unique authentication token after OTP verification
    $auth_token = bin2hex(random_bytes(32));  // Generate a random auth token

    // Set the expiry date for the token (6 months)
    $expiry_date = date('Y-m-d H:i:s', strtotime('+6 months'));

    // Store the auth token in the database, this is important for logout functionality
    $stmt = $conn->prepare("
        INSERT INTO student_logins (student_id, auth_token, expiry_date, service_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$student['student_id'], $auth_token, $expiry_date, $service_id]);

    
    setcookie('student_auth', $auth_token, time() + (180 * 24 * 60 * 60), '/', '', false, true);

    // Fetch additional enrollment information
    $stmt = $conn->prepare("
        SELECT enrollment_id, student_index FROM enrollments WHERE service_id = ? AND student_id = ?
    ");
    $stmt->execute([$service_id, $student['student_id']]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Store student and session details
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['enrollment_id'] = $enrollment['enrollment_id'] ?? null;
    $_SESSION['student_index'] = $enrollment['student_index'] ?? null;
    $_SESSION['auth_token'] = $auth_token;

    // You can return more useful data here if needed, such as student name, or student number
    echo json_encode([
        'success' => true,
        'message' => 'OTP is valid and login successful.',
        'student_id' => $student['student_id'],
        'student_auth_token' => $auth_token,
        'enrollment_id' => $enrollment['enrollment_id'] ?? null,
        'student_index' => $enrollment['student_index'] ?? null
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error verifying OTP: ' . $e->getMessage()]);
}
?>

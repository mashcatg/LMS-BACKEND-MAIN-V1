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

// Check if user is authenticated

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$student_number = $data['student_number'] ?? null;
$student_index = $data['student_index'] ?? null;
$course_id = $data['course_id'] ?? null;
$batch_id = $data['batch_id'] ?? null;
$paid_amount = $data['paid_amount'] ?? null;
$course_fee = $data['course_fee'] ?? null;
$discounted_amount = $data['discounted_amount'] ?? null;
$payment_method = 'offline';

// Ensure all required fields are present
if (!$student_number || !$student_index || !$course_id || !$paid_amount || !$course_fee || !$discounted_amount) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate phone number
if (!validatePhoneNumber($student_number)) {
    echo json_encode(['success' => false, 'message' => 'Invalid student phone number. It must start with 8801 and be 13 digits long.']);
    exit();
}

try {
    $service_id = $_SESSION['service_id'];
    $admin_id = $created_by = $_SESSION['admin_id'];

    // Fetch the admin phone number (ad_phone) from the services table
    $selectService = $conn->prepare("SELECT company_name, sub_domain, ad_phone FROM services WHERE service_id = ? LIMIT 1");
    $selectService->execute([$service_id]);
    $serviceDetails = $selectService->fetch(PDO::FETCH_ASSOC);

    if (!$serviceDetails) {
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }

    $ad_phone = $serviceDetails['ad_phone'];
    $subdomain = $serviceDetails['sub_domain'];

    // Fetch student details
    $selectStudents = $conn->prepare("SELECT * FROM students WHERE student_number = ? AND service_id = ? LIMIT 1");
    $selectStudents->execute([$student_number, $service_id]);
    $studentDetails = $selectStudents->fetch(PDO::FETCH_ASSOC);

    if (!$studentDetails) {// Generate a 6-digit alphanumeric password
    $generated_password = substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 6)), 0, 6);

    // Hash the password
    $hashed_password = password_hash($generated_password, PASSWORD_DEFAULT);
        // Create new student record
        $stmt = $conn->prepare("INSERT INTO students (student_number, service_id, student_password) VALUES (?, ?, ?)");
        $stmt->execute([$student_number, $service_id, $hashed_password]);
        $studentDetails['student_id'] = $conn->lastInsertId();
        
        // Prepare SMS text
        $sms_text = "Hello $student_name, your student account has been created in $company_name.
        Your password is $generated_password. Please log in to your student portal at $subdomain.ennovat.com using this phone number and password. Change your password to keep your login details secure.
        Thank you.";

        // Send SMS to both the student and the admin
        $receiver_type = 'student';
        $phone_numbers = "$ad_phone, $student_number"; // List of numbers to send the SMS
        $smsResponse = calculateSmsCost($sms_text, $phone_numbers, $receiver_type);
    }

    // Insert a new enrollment
    $stmt = $conn->prepare("INSERT INTO enrollments (student_id, student_index, course_id, batch_id, course_fee, created_by, enrollment_time, service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$studentDetails['student_id'], $student_index, $course_id, $batch_id, $course_fee, $created_by, date('Y-m-d H:i:s'), $service_id]);

    // Fetch the last inserted enrollment ID
    $enrollment_id = $conn->lastInsertId();

    // Prepare and insert a new payment
    $stmt1 = $conn->prepare("INSERT INTO payments (student_index, payment_time, enrollment_id, method, paid_amount, created_by, discounted_amount, service_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt1->execute([$student_index, date('Y-m-d H:i:s'), $enrollment_id, $payment_method, $paid_amount, $created_by, $discounted_amount, $service_id]);

    // Fetch course details
    $selectCourse = $conn->prepare("SELECT * FROM courses WHERE course_id = ? AND service_id = ? LIMIT 1");
    $selectCourse->execute([$course_id, $service_id]);
    $courseDetails = $selectCourse->fetch(PDO::FETCH_ASSOC);

    // Fetch batch name
    $selectBatch = $conn->prepare("SELECT batch_name FROM batches WHERE batch_id = ? LIMIT 1");
    $selectBatch->execute([$batch_id]);
    $batchDetails = $selectBatch->fetch(PDO::FETCH_ASSOC);

// Construct SMS text for enrollment confirmation
$sms_text = "Hello {$studentDetails['student_name']}, thanks for enrolling in our course {$courseDetails['course_name']}. " . 
"Your payment was {$paid_amount} and total due is " . ($course_fee - $paid_amount - $discounted_amount) . ". " . 
"Your Student index is {$student_index}. " . 
"Please log in to your student portal at {$subdomain}.ennovat.com using phone number and password. " . 
"Thank you.";

// Send SMS to both ad_phone and student_number
$receiver_type = 'student';
$phone_numbers = "$ad_phone, $student_number"; // List of numbers to send the SMS

$smsResponse = calculateSmsCost($sms_text, $phone_numbers, $receiver_type);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Enrollment created successfully',
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating enrollment: ' . $e->getMessage()]);
}

// Function to validate phone numbers
function validatePhoneNumber($number) {
    // Remove any non-numeric characters
    $number = preg_replace('/[^0-9]/', '', $number);
    // Check if the number starts with '8801' and is 13 digits long
    return (substr($number, 0, 4) === '8801' && strlen($number) === 13);
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
include '../sms.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Ensure the user is authenticated
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from request
    $notice = $_POST['notice'] ?? '';
    $notice_type_string = $_POST['notice_type'] ?? 'Public';
    $notice_types = explode(',', $notice_type_string);
    $notice_types = array_map('trim', $notice_types);

    // Get service_id from the session
    $service_id = $_SESSION['service_id'];
    
    if (empty($notice)) {
        echo json_encode(['success' => false, 'message' => 'Notice content is required']);
        exit();
    }

    try {
        $admin_id = $_SESSION['admin_id'];
        $notice_time = date("Y-m-d H:i:s");
        $sms_numbers = []; // Initialize an array to hold all student numbers
    
        // Select enrollments based on notice type
        if (in_array('Public', $notice_types)) {
            // If notice type includes 'Public', select all students
            $stmt = $conn->prepare("SELECT student_id FROM enrollments WHERE service_id = :service_id");
            $stmt->execute([':service_id' => $service_id]);
            $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $enrollments = []; // Initialize an empty array for non-public cases
            // For specific notice types, loop through them
            foreach ($notice_types as $notice_type) {
                $stmt = $conn->prepare("SELECT student_id FROM enrollments WHERE service_id = :service_id AND enrollment_id = :notice_type");
                $stmt->execute([':service_id' => $service_id, ':notice_type' => $notice_type]);
                $enrollments = array_merge($enrollments, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    
        // Collect SMS numbers
        foreach ($enrollments as $enrollment) {
            $student_id = $enrollment['student_id'];
            $stmt = $conn->prepare("SELECT student_number FROM students WHERE student_id = :student_id");
            $stmt->execute([':student_id' => $student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                $sms_numbers[] = trim($student['student_number']);
            }
        }
    
        // Insert into the notices table
        $stmt = $conn->prepare("INSERT INTO notices (notice, notice_type, notice_by, notice_time, service_id) 
                                VALUES (:notice, :notice_type, :notice_by, :notice_time, :service_id)");
        $stmt->execute([
            ':notice' => $notice,
            ':notice_type' => $notice_type_string,
            ':notice_by' => $admin_id,
            ':notice_time' => $notice_time,
            ':service_id' => $service_id
        ]);
    
        // Join student numbers with a comma and ensure uniqueness
        $sms_number = implode(',', array_unique($sms_numbers));
        $receiver_type = 'student';
        // Send SMS
        $smsResponse = calculateSmsCost($notice, $sms_number, $receiver_type);
    
        echo json_encode(['success' => true, 'message' => 'Notice created successfully', 'sms_number' => $sms_number]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

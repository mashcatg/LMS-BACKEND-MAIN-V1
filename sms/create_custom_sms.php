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
    $notice = $_POST['sms'] ?? '';
    $receiver = $_POST['receiver'] ?? '';
    $service_id = $_SESSION['service_id'];
    
    if (empty($notice)) {
        echo json_encode(['success' => false, 'message' => 'Notice content is required']);
        exit();
    }

    try {
        $admin_id = $_SESSION['admin_id'];
        $sms_numbers = []; // Array to hold all student numbers

        // Process the receiver input to handle multiple IDs
        $receiver_ids = array_map('intval', explode(',', $receiver));
        $placeholders = implode(',', array_fill(0, count($receiver_ids), '?'));

        // Prepare the SQL statement to fetch student IDs based on enrollment IDs
        $stmt = $conn->prepare("SELECT student_id FROM enrollments WHERE service_id = ? AND enrollment_id IN ($placeholders)");
        $stmt->execute(array_merge([$service_id], $receiver_ids));

        // Fetch all enrollments
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log(print_r($enrollments, true)); // Log the fetched enrollments for debugging
        
        foreach ($enrollments as $enrollment) {
            $student_id = $enrollment['student_id'];
            $stmt = $conn->prepare("SELECT student_number FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                $sms_numbers[] = trim($student['student_number']); // Add student number to the array
            }
        }

        // Remove duplicates and join student numbers with a comma
        $sms_numbers = array_unique($sms_numbers);
        $sms_number = implode(',', $sms_numbers);
        
        // Send SMS (mocked here)
        $receiver_type = 'student';
        $smsResponse = calculateSmsCost($notice, $sms_number, $receiver_type);

        echo json_encode(['success' => true, 'message' => 'SMS Sent Successfully', 'sms_numbers' => $sms_number]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

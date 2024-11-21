<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

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

$service_id = $_SESSION['service_id'];

try {
    // Prepare the SQL query to fetch sms related to the current service
    $stmt = $conn->prepare("SELECT * FROM sms WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $smsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($smsList as &$sms) { // Use reference to modify $sms directly
        $receivers = explode(',', $sms['receiver']);
        $studentIndexes = []; // Array to hold student indexes for this sms

        foreach ($receivers as $receiver) {
            $receiver = trim($receiver); // Trim whitespace

            if ($sms['sms_type'] == 'student') {
                // Select student_id from students table where student_number = $receiver
                $selectStudents = $conn->prepare("SELECT student_id FROM students WHERE student_number = ?");
                $selectStudents->execute([$receiver]);
                $student = $selectStudents->fetch(PDO::FETCH_ASSOC);

                if ($student) {
                    $student_id = $student['student_id'];

                    // Query the enrollments table to get the student_index for each receiver
                    $enrollmentStmt = $conn->prepare("SELECT student_index FROM enrollments WHERE student_id = ?");
                    $enrollmentStmt->execute([$student_id]);
                    $enrollmentResult = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

                    // If a student_index is found, add it to the array
                    if ($enrollmentResult) {
                        $studentIndexes[] = $enrollmentResult['student_index'];
                    }
                }
            } else {
                // If the sms_type is not student, fetch admin_name from the admins db where admin_number = $receiver
                $fetchAdmin = $conn->prepare("SELECT admin_name FROM admins WHERE admin_number = ?");
                $fetchAdmin->execute([$receiver]);
                $fetchAdminResult = $fetchAdmin->fetch(PDO::FETCH_ASSOC);

                if ($fetchAdminResult) {
                    $studentIndexes[] = $fetchAdminResult['admin_name'];
                }
            }
        }

        // Add the student indexes or admin names to the sms entry
        $sms['student_indexes'] = $studentIndexes;
    }

    // Fetch the sms_credit for the current admin
    $creditStmt = $conn->prepare("SELECT sms_credit FROM services WHERE service_id = ?");
    $creditStmt->execute([$service_id]);
    $creditResult = $creditStmt->fetch(PDO::FETCH_ASSOC);

    // Get the sms_credit or set it to 0 if not found
    $smsCredit = $creditResult ? $creditResult['sms_credit'] : 0;

    // Return sms and corresponding student indexes, along with sms_credit as a JSON response
    echo json_encode([
        'success' => true,
        'sms' => $smsList,
        'sms_credit' => $smsCredit
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching sms: ' . $e->getMessage()]);
}

?>

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
    // Prepare the SQL query to fetch payments related to the current service
    $stmt = $conn->prepare("SELECT * FROM payments WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare an array to store payments with student details
    $paymentsWithEnrollmentDetails = [];

    // Loop through each enrollment and fetch student details from the students table
    foreach ($payments as $payment) {
        $enrollmentStmt = $conn->prepare("SELECT * FROM enrollments WHERE enrollment_id = ? AND service_id = ?");
        $enrollmentStmt->execute([$payment['enrollment_id'], $service_id]);
        $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$enrollment) {
            continue; // Skip to the next iteration if no enrollment is found
        }
        // Prepare and execute a query to fetch student details based on student_id
        $studentStmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND service_id = ?");
        $studentStmt->execute([$enrollment['student_id'], $service_id]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            continue; // Skip if no student is found
        }
        // // Fetch course and batch details
        // $courseStmt = $conn->prepare("SELECT course_name, course_id FROM courses WHERE course_id = ?");
        // $courseStmt->execute([$enrollment['course_id']]);
        // $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

        // $batchStmt = $conn->prepare("SELECT batch_name, batch_id FROM batches WHERE batch_id = ?");
        // $batchStmt->execute([$enrollment['batch_id']]);
        // $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

        // Merge enrollment data with student details, course details and batch details
        if ($enrollment && $student) {
            $enrollmentsWithStudentDetails[] = array_merge($payment, $enrollment, $student);
            // $paid_amount = $payment['paid_amount'];
            // $payment_time = $payment['payment_time'];
            // $method = $payment['method'];
            // $discounted_amount = $payment['discounted_amount'];
            // // $course_id = $enrollment['course_id'];
            // // $batch_id = $enrollment['batch_id'];
            // $student_id = $enrollment['student_id'];
            // $student_index = $enrollment['student_index'];
            // $student_name = $student['student_name'];
        }


    }

    // Return the enrollments with student details as a JSON response
    echo json_encode(['success' => true, 'payments' => $enrollmentsWithStudentDetails]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching enrollments: ' . $e->getMessage()]);
}
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include 'due_count.php';
include '../check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

try {
    // Fetch all enrollments
    $stmt = $conn->prepare("SELECT * FROM enrollments WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enrollmentsWithDetails = [];

    foreach ($enrollments as $enrollment) {
        // fetch the admin_name from admin_id
    $selectAdmin = $conn->prepare("SELECT admin_name FROM admins WHERE admin_id =? LIMIT 1");
    $selectAdmin->execute([$enrollment['created_by']]);
    //fetch the admin_name
    $admin_name = $selectAdmin->fetchColumn();
        // Get student details
        $studentStmt = $conn->prepare("SELECT student_name, student_institution, student_address, request_status, student_number FROM students WHERE student_id = ? AND service_id = ?");
        $studentStmt->execute([$enrollment['student_id'], $service_id]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        // Get course details
        $courseStmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
        $courseStmt->execute([$enrollment['course_id']]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

        // Get batch details
        $batchStmt = $conn->prepare("SELECT * FROM batches WHERE batch_id = ?");
        $batchStmt->execute([$enrollment['batch_id']]);
        $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

        // Get all payment details
        $paymentStmt = $conn->prepare("SELECT * FROM payments WHERE enrollment_id = ? AND service_id = ?");
        $paymentStmt->execute([$enrollment['enrollment_id'], $service_id]);
        $payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate the total paid and total discounted amounts
        $totalPaid = 0;
        $totalDiscounted = 0;

        // Loop through payments to fetch admin names
        foreach ($payments as &$payment) {
            // Fetch admin_name for each payment
            $selectAdmin = $conn->prepare("SELECT admin_name FROM admins WHERE admin_id = ? LIMIT 1");
            $selectAdmin->execute([$payment['created_by']]);
            $payment_admin_name = $selectAdmin->fetchColumn();

            // Add admin_name to each payment record
            $payment['payment_admin_name'] = $payment_admin_name;

            // Calculate totals
            $totalPaid += $payment['paid_amount'];
            $totalDiscounted += $payment['discounted_amount'];
        }

        // Prepare the payment details array with totals
        $paymentDetails = [
            'total_paid' => $totalPaid,
            'total_discounted' => $totalDiscounted,
            'payment_records' => $payments
        ];

        // Get service details
        $serviceStmt = $conn->prepare("SELECT company_name, sub_domain, facebook, instagram, youtube, logo, address, ad_phone FROM services WHERE service_id = ?");
        $serviceStmt->execute([$service_id]);
        $serviceDetails = $serviceStmt->fetch(PDO::FETCH_ASSOC);

        // Compile all details into one array
        if ($student && $course && $batch && $paymentDetails) {
            $enrollment['student'] = $student;
            $enrollment['course'] = $course;
            $enrollment['batch'] = $batch;
            $enrollment['payments'] = $paymentDetails;
            $enrollment['service_details'] = $serviceDetails;
            $enrollment['enrolled_by'] = $admin_name;
            $enrollment['due_amount_details'] = calculateDueAmount($conn, $enrollment['enrollment_id'], $service_id, $course['course_id']);
        }

        $enrollmentsWithDetails[] = $enrollment;
    }

    echo json_encode(['enrollments' => $enrollmentsWithDetails]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching enrollments: ' . $e->getMessage()]);
}
?>

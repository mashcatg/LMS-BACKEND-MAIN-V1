<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';
include '../payments/due_count.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ;
$student_id = $_SESSION['student_id'] ?? '1';

try {
    // Fetch all course_ids for the student
    $stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE student_id = :student_id AND service_id = :service_id");
    $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $courses = [];
    $totalDueAmount = 0; // Initialize the total due amount

    // Loop through each course_id and fetch course details
    foreach ($enrollments as $enrollment) {
        $course_id = $enrollment['course_id'];

        $courseStmt = $conn->prepare("SELECT course_id, course_name, course_banner FROM courses WHERE course_id = :course_id");
        $courseStmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $courseStmt->execute();

        $courseDetails = $courseStmt->fetch(PDO::FETCH_ASSOC);

        if ($courseDetails) {
            $courses[] = $courseDetails;

            // Calculate total due amount across all courses
            $dueAmounts = calculateDueAmount($conn, $student_id, $service_id, $courseDetails['course_id']);
            if (is_array($dueAmounts)) {
                foreach ($dueAmounts as $due) {
                    $totalDueAmount += $due['monthly_due'] ?? 0; // Sum up all monthly dues
                }
            }
        }
    }

    // Return the course details along with total due amount
    echo json_encode([
        'success' => true,
        'coursesEnrolled' => $courses,
        'total_due_amount' => $totalDueAmount // Add total due amount
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
}
?>

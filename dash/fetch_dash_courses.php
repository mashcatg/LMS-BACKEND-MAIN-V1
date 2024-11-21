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

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE service_id = :service_id");
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Array to hold course data with counts
    $courseDetails = [];

    foreach ($courses as $course) {
        $course_id = $course['course_id'];

        // Fetch batches for the current course
        $selectBatches = $conn->prepare("SELECT COUNT(*) FROM batches WHERE course_id = :course_id AND service_id = :service_id");
        $selectBatches->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $selectBatches->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $selectBatches->execute();
        $total_batches = $selectBatches->fetchColumn();

        // Fetch enrollments for the current course
        $selectEnrollments = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = :course_id AND service_id = :service_id");
        $selectEnrollments->bindParam(':course_id', $course_id, PDO::PARAM_INT);
        $selectEnrollments->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $selectEnrollments->execute();
        $total_students = $selectEnrollments->fetchColumn();

        // Add course data with totals to the result array
        $courseDetails[] = [
            'course' => $course,
            'total_batches' => $total_batches,
            'total_students' => $total_students
        ];
    }

    // Return the course details with totals
    echo json_encode([
        'courses' => $courseDetails
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
}
?>

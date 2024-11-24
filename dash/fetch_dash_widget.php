<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
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
    $rowCOuntCourse = $stmt->rowCount();
    
        // Fetch batches for the current course
        $selectBatches = $conn->prepare("SELECT COUNT(*) FROM batches WHERE service_id = :service_id");
        $selectBatches->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $selectBatches->execute();
        $total_batches = $selectBatches->fetchColumn();

        //total students from student db
        $selectStudents = $conn->prepare("SELECT COUNT(*) FROM students WHERE service_id = :service_id");
        $selectStudents->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $selectStudents->execute();
        $total_students = $selectStudents->fetchColumn();

        // Fetch enrollments for the current course
        $selectEnrollments = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE service_id = :service_id");
        $selectEnrollments->bindParam(':service_id', $service_id, PDO::PARAM_INT);
        $selectEnrollments->execute();
        $total_enrollments = $selectEnrollments->fetchColumn();

        // Add course data with totals to the result array
        $widgetTotals[] = [
            'total_courses' => $rowCOuntCourse,
            'total_batches' => $total_batches,
            'total_enrollments' => $total_enrollments,
            'total_students' => $total_students
        ];
    

    // Return the course details with totals
    echo json_encode([
        'widgets' => $widgetTotals
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching courses: ' . $e->getMessage()]);
}
?>

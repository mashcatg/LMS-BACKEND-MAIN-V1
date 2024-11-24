<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];

        // Fetch exams and join courses to get the course_name(s)
        $stmt = $conn->prepare("
            SELECT exams.*, GROUP_CONCAT(courses.course_name SEPARATOR ', ') AS course_names
            FROM exams 
            JOIN courses ON FIND_IN_SET(courses.course_id, exams.course_id) > 0 
            WHERE exams.service_id = :service_id 
            GROUP BY exams.exam_id 
            ORDER BY exams.exam_date DESC
        ");
        $stmt->execute([':service_id' => $service_id]);
        $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'exams' => $exams]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

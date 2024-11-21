<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'];
$student_id = $_SESSION['student_id'];
$course_id = $_SESSION['course_id'];
$student_index = $_SESSION['student_index'];

try {
        // Prepare the SQL query to fetch cards related to the current service and course
    $stmt = $conn->prepare("SELECT * FROM cards WHERE service_id = ? AND course_id = ?");
    $stmt->execute([$service_id, $course_id]);

    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return cards as a JSON response
    echo json_encode(['success' => true, 'cards' => $cards]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching cards: ' . $e->getMessage()]);
}
?>

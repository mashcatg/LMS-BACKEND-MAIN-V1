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

// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

// Get `card_id` from the URL
if (!isset($_GET['card_id'])) {
    echo json_encode(['error' => 'Card ID not provided']);
    exit();
}

$card_id = $_GET['card_id'];

try {
    // 1. Fetch the `course_id` from `cards` table using `card_id`
    $stmt = $conn->prepare("SELECT course_id FROM cards WHERE card_id = ? AND service_id = ?");
    $stmt->execute([$card_id, $service_id]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        echo json_encode(['error' => 'No course found for the provided card ID']);
        exit();
    }

    $course_id = $card['course_id'];

    // 2. Fetch the `student_id` from the `enrollments` table where `course_id` matches
    $stmt = $conn->prepare("
        SELECT students.* 
        FROM enrollments 
        JOIN students ON enrollments.student_id = students.student_id 
        WHERE enrollments.course_id = ? AND students.service_id = ?
    ");
    $stmt->execute([$course_id, $service_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$students) {
        echo json_encode(['error' => 'No students found for the selected course']);
        exit();
    }

    // 3. Return students as a JSON response
    echo json_encode(['students' => $students]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching students: ' . $e->getMessage()]);
}
?>
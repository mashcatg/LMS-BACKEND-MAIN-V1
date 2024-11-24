<?php
ini_set('display_errors', 0);
error_reporting(0);
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }
    $service_id = $_SESSION['service_id'];

    try {
        // SQL query to fetch questions for a specific service
        $stmt = $conn->prepare("
            SELECT q.*, quiz.quiz_name 
            FROM quiz_questions q
            LEFT JOIN quizzes quiz ON q.quiz_id = quiz.quiz_id
            WHERE q.service_id = ?
        ");
        $stmt->execute([$service_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'questions' => $questions]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching questions: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

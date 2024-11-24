<?php
ini_set('display_errors', 0);
error_reporting(0);
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $quiz_id = $data['quiz_id'];
    $question = $data['question'];
    $option_1 = $data['option_1'];
    $option_2 = $data['option_2'];
    $option_3 = $data['option_3'];
    $correct_option_4 = $data['correct_option'];
    $follow_up_question = $data['follow_up_question'] ?? null;
    $service_id = $_SESSION['service_id'];

    if (!$quiz_id || !$question || !$option_1 || !$option_2 || !$option_3 || !$correct_option_4) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be provided']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO quiz_questions (quiz_id, question, option_1, option_2, option_3, correct_option_4, follow_up_question, service_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$quiz_id, $question, $option_1, $option_2, $option_3, $correct_option_4, $follow_up_question, $service_id]);

        echo json_encode(['success' => true, 'message' => 'Question added successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding question: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

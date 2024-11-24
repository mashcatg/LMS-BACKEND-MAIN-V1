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

    $data = json_decode(file_get_contents("php://input"), true); // For POST requests
    $quiz_id = $data['quiz_id'];
    $quiz_name = $data['quiz_name'];
    $quiz_description = $data['quiz_description'];
    $available_from = $data['available_from'];
    $available_to = $data['available_to'];
    $quiz_duration = $data['quiz_duration'];
    $questions_per_quiz = $data['questions_per_quiz'];
    $marks_per_question = $data['marks_per_question'];
    $negative_marks = $data['negative_marks'];
    $multiple_availability = isset($data['is_multiple_availability']) ? $data['is_multiple_availability'] : '0'; // Default to '0' if not set
    $course_id = $data['course_id'];

    try {
        $stmt = $conn->prepare("UPDATE quizzes SET quiz_name = ?, quiz_description = ?, available_from = ?, available_to = ?, quiz_duration = ?, questions_per_quiz = ?, marks_per_question = ?, negative_marks = ?, multiple_availability = ?, course_id = ? WHERE quiz_id = ?");
        $stmt->execute([$quiz_name, $quiz_description, $available_from, $available_to, $quiz_duration, $questions_per_quiz, $marks_per_question, $negative_marks, $multiple_availability, $course_id, $quiz_id]);

        echo json_encode(['success' => true, 'message' => 'Quiz updated successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating quiz: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ?? '61545';  // Default service_id
$course_id = $_SESSION['course_id'] ?? 1;         // Default course_id
$batch_id = $_SESSION['batch_id'] ?? '1';         // Default batch_id
$quiz_id = $_GET['quiz_id']; 
try {
    // Fetch quizzes where student_visibility is 1 and filter by service_id, course_id, and batch_id
    $stmt = $conn->prepare("
        SELECT
            quiz.quiz_id,
            quiz.quiz_name,
            quiz.quiz_description,
            quiz.available_from,
            quiz.available_to,
            quiz.quiz_duration,
            quiz.questions_per_quiz,
            quiz.marks_per_question,
            quiz.negative_marks,
            quiz.student_visibility,
            quiz.multiple_availability,
            quiz.course_id,
            quiz.service_id
        FROM
            quizzes AS quiz
        WHERE
            quiz.service_id = :service_id
        AND quiz.student_visibility = 1
        AND quiz_id = :quiz_id
        AND FIND_IN_SET(:course_id, quiz.course_id)
        ORDER BY
            quiz.quiz_id DESC
    ");

    $stmt->execute([':service_id' => $service_id, ':course_id' => $course_id, 'quiz_id' => $quiz_id]);

    // Fetch quizzes data
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response
    echo json_encode(['success' => true, 'quizzes' => $quizzes]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

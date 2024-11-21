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

$service_id = $_SESSION['service_id'] ?? '61545'; // Fetch service_id from session
$quiz_id = $_GET['quiz_id']; // Fetch quiz_id from GET request
$enrollment_id = $_SESSION['enrollment_id'] ?? '1'; // Fetch enrollment_id from session (default to '1' if not set)

if (!$quiz_id || !$service_id) {
    echo json_encode(['error' => 'quiz_id or service_id is missing']);
    exit();
}

try {
    // Step 1: Fetch quiz details from the quizzes table
    $stmt = $conn->prepare("
        SELECT
            quiz.quiz_id,
            quiz.quiz_name,
            quiz.quiz_description,
            quiz.available_from,
            quiz.available_to,
            quiz.quiz_duration,
            quiz.questions_per_quiz,
            quiz.student_visibility,
            quiz.multiple_availability
        FROM quizzes AS quiz
        WHERE quiz.quiz_id = :quiz_id AND quiz.service_id = :service_id
    ");
    $stmt->execute([':quiz_id' => $quiz_id, ':service_id' => $service_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    // If the quiz does not exist
    if (!$quiz) {
        echo json_encode(['error' => 'Quiz not found']);
        exit();
    }

    // Step 2: Check if student_visibility is 1
    if ($quiz['student_visibility'] != 1) {
        echo json_encode(['error' => 'Quiz is not visible to students']);
        exit();
    }

    // Step 3: If multiple_availability is 0, check if the student has already submitted the quiz
    if ($quiz['multiple_availability'] == 0) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM quiz_submissions
            WHERE quiz_id = :quiz_id AND enrollment_id = :enrollment_id
        ");
        $stmt->execute([':quiz_id' => $quiz_id, ':enrollment_id' => $enrollment_id]);
        $rowCount = $stmt->fetchColumn();

        // If a submission already exists, prevent further participation
        if ($rowCount > 0) {
            echo json_encode(['error' => 'You have already submitted this quiz']);
            exit();
        }
    }

    // Step 4: Compare the available_from and available_to with current time
    $currentTime = new DateTime(); // Get the current time
    $availableFrom = new DateTime($quiz['available_from']);
    $availableTo = new DateTime($quiz['available_to']);

    if ($currentTime < $availableFrom || $currentTime > $availableTo) {
        echo json_encode(['error' => 'Quiz is not available at this time']);
        exit();
    }

    // Step 5: Get quiz details (duration, questions_per_quiz) and fetch the questions
    $quizDuration = $quiz['quiz_duration'];
    $questionsPerQuiz = $quiz['questions_per_quiz'];

    // Step 6: Select the specified number of questions for the quiz
    $stmt = $conn->prepare("
        SELECT question_id, question, option_1, option_2, option_3, correct_option_4, follow_up_question
        FROM quiz_questions
        WHERE quiz_id = :quiz_id
        LIMIT :questions_per_quiz
    ");
    $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
    $stmt->bindParam(':questions_per_quiz', $questionsPerQuiz, PDO::PARAM_INT);
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the quiz details along with questions
    echo json_encode([
        'success' => true,
        'quiz' => [
            'quiz_id' => $quiz['quiz_id'],
            'quiz_name' => $quiz['quiz_name'],
            'quiz_description' => $quiz['quiz_description'],
            'quiz_duration' => $quizDuration,
            'questions_per_quiz' => $questionsPerQuiz,
            'available_from' => $availableFrom->format('Y-m-d H:i:s'), // Format time for frontend
            'available_to' => $availableTo->format('Y-m-d H:i:s')  // Format time for frontend
        ],
        'questions' => $questions
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for CORS
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

session_start();
include '../db.php';

// Check if the user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

// Check if the service ID is set in the session
if (!isset($_SESSION['service_id'])) {
    echo json_encode(['error' => 'Service ID not found']);
    exit();
}

$service_id = $_SESSION['service_id'];

// Validate the quiz_id parameter
if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
    echo json_encode(['error' => 'Invalid or missing quiz ID']);
    exit();
}

$quiz_id = (int) $_GET['quiz_id'];

try {
    // Get quiz details
    $stmt = $conn->prepare("
        SELECT questions_per_quiz, marks_per_question, negative_marks 
        FROM quizzes 
        WHERE quiz_id = :quiz_id
    ");
    $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
    $stmt->execute();
    $quizDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quizDetails) {
        echo json_encode(['error' => 'Quiz not found']);
        exit();
    }

    $questionsPerQuiz = $quizDetails['questions_per_quiz'];
    $marksPerQuestion = $quizDetails['marks_per_question'];
    $negativeMarks = $quizDetails['negative_marks'];

    // Get all quiz submissions for this quiz
    $stmt = $conn->prepare("
        SELECT qs.enrollment_id, qs.submitted_answer, qq.correct_option_4
        FROM quiz_submissions qs
        INNER JOIN quiz_questions qq ON qs.question_id = qq.question_id
        WHERE qq.quiz_id = :quiz_id
    ");
    $stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count correct and wrong answers for each unique enrollment
    $results = [];
    foreach ($submissions as $submission) {
        $enrollmentId = $submission['enrollment_id'];
        $isCorrect = $submission['submitted_answer'] === $submission['correct_option_4'];

        if (!isset($results[$enrollmentId])) {
            $results[$enrollmentId] = ['correct' => 0, 'wrong' => 0];
        }

        if ($isCorrect) {
            $results[$enrollmentId]['correct']++;
        } else {
            $results[$enrollmentId]['wrong']++;
        }
    }

    // Calculate marks for each student
    $leaderboard = [];
    foreach ($results as $enrollmentId => $counts) {
        $correctAnswers = $counts['correct'];
        $wrongAnswers = $counts['wrong'];
        $marks = ($correctAnswers * $marksPerQuestion) - ($wrongAnswers * $negativeMarks);

        // Get student details
        $stmt = $conn->prepare("
            SELECT e.student_index, s.student_name, s.student_institution, s.student_number
            FROM enrollments e
            INNER JOIN students s ON e.student_id = s.student_id
            WHERE e.enrollment_id = :enrollment_id
        ");
        $stmt->bindParam(':enrollment_id', $enrollmentId, PDO::PARAM_INT);
        $stmt->execute();
        $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        $leaderboard[] = [
            'student_index' => $studentDetails['student_index'],
            'student_name' => $studentDetails['student_name'],
            'student_institution' => $studentDetails['student_institution'],
            'student_number' => $studentDetails['student_number'],
            'marks' => $marks
        ];
    }

    // Sort leaderboard by marks in descending order
    usort($leaderboard, function ($a, $b) {
        return $b['marks'] <=> $a['marks'];
    });

    // Assign positions based on the number of students with higher marks
    $previousMarks = null;
    $position = 0;
    $rank = 0;

    foreach ($leaderboard as $index => &$entry) {
        $rank++;

        // If the current marks are different from the previous, update the position
        if ($entry['marks'] !== $previousMarks) {
            $position = $rank;
            $previousMarks = $entry['marks'];
        }

        $entry['position'] = $position;
    }

    // Return the leaderboard as a JSON response
    echo json_encode([
        'success' => true,
        'leaderboard' => $leaderboard
    ]);


} catch (Exception $e) {
    // Return error message on failure
    echo json_encode([
        'success' => false,
        'message' => 'Error generating leaderboard: ' . $e->getMessage()
    ]);
}
?>
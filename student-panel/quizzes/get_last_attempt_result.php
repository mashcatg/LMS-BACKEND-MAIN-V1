<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Allow cross-origin requests from your frontend app
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Include authentication check
include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Get enrollment and service_id from session
$enrollment_id = $_SESSION['enrollment_id'] ?? '1';
$service_id = $_SESSION['service_id'] ?? '61545';
$quiz_id = $_GET['quiz_id'];

// Get quiz details (negative_marks, question_per_quiz, marks_per_question)
$quizQuery = "SELECT * FROM quizzes WHERE quiz_id = ? AND service_id = ?";
$stmt = $conn->prepare($quizQuery);
$stmt->bindParam(1, $quiz_id, PDO::PARAM_INT);  // Bind parameter for quiz_id
$stmt->bindParam(2, $service_id, PDO::PARAM_INT);  // Bind parameter for service_id
$stmt->execute();
$quizResult = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quizResult) {
    echo json_encode(["error" => "Quiz not found or not available for this service"]);
    exit;
}

$negative_marks = $quizResult['negative_marks'];
$marks_per_question = $quizResult['marks_per_question'];
$questions_per_quiz = $quizResult['questions_per_quiz'];

// Fetch the latest quiz submission for the student
$sql = "SELECT * FROM quiz_submissions WHERE quiz_id = :quiz_id AND enrollment_id = :enrollment_id ORDER BY submission_id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':quiz_id', $quiz_id, PDO::PARAM_INT);
$stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
$stmt->execute();
$submissionResult = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submissionResult) {
    echo json_encode(["error" => "No submission found for this quiz"]);
    exit;
}

$form_id = $submissionResult['form_id'];

// Fetch the student's answers for the specific form_id
$answersQuery = "SELECT submitted_answer, question_id, option1, option2, option3, option4 FROM quiz_submissions WHERE form_id = ? ORDER BY submission_id ASC";
$stmt = $conn->prepare($answersQuery);
$stmt->bindParam(1, $form_id, PDO::PARAM_INT);  // Bind parameter for form_id
$stmt->execute();
$answersResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
$answers = [];

// Function to clean the submitted_answer by trimming unwanted spaces and characters
function clean_answer($answer) {
    // Trim leading and trailing spaces
    $answer = ltrim($answer);
    
    // Remove HTML tags (except for <b>, <i>, <u>, etc., if needed)
    $answer = strip_tags($answer); 
    
    // Optionally, remove unwanted characters (e.g., non-printable characters)
    $answer = preg_replace('/[^\x20-\x7E]/', '', $answer);  // Keeps only printable characters

    return $answer;
}

foreach ($answersResult as $row) {
    // Map options to A, B, C, D based on the first two letters (a., b., c., d.)
    $options = [
        'A' => $row['option1'],
        'B' => $row['option2'],
        'C' => $row['option3'],
        'D' => $row['option4']
    ];

    // Check for the "a." prefix and rearrange the options
    $option_keys = ['A', 'B', 'C', 'D'];
    foreach ($option_keys as $key) {
        if (substr($options[$key], 0, 2) === 'a.') {
            // Move the option with 'a.' to the first position
            $correct_option = $key;
            break;
        }
    }

    // Clean and remove the "a." prefix from the correct answer option for comparison
    $correct_option_value = clean_answer(substr($options[$correct_option], 2));

    // Clean and remove the "a." prefix from the submitted answer
    $submitted_answer = clean_answer(substr($row['submitted_answer'], 2));

    // Store the cleaned options and the submitted answer
    $answers[$row['question_id']] = [
        'submitted_answer' => $submitted_answer,
        'options' => $options,
        'correct_option_value' => $correct_option_value
    ];
}

// Get the questions, correct answers, and quiz solutions
$questionsQuery = "SELECT question_id, question, correct_option_4, quiz_solution FROM quiz_questions WHERE quiz_id = ?";
$stmt = $conn->prepare($questionsQuery);
$stmt->bindParam(1, $quiz_id, PDO::PARAM_INT);  // Bind parameter for quiz_id
$stmt->execute();
$questionsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
$questions = [];

foreach ($questionsResult as $row) {
    $question_id = $row['question_id'];
    $correct_answer = $row['correct_option_4'];
    $quiz_solution = $row['quiz_solution'];

    // Include the solution along with the question data
    $questions[] = [
        'question_id' => $question_id,
        'question' => $row['question'],
        'correct_answer' => clean_answer($correct_answer), // Clean the correct answer
        'submitted_answer' => $answers[$question_id]['submitted_answer'],
        'options' => $answers[$question_id]['options'],
        'quiz_solution' => $quiz_solution // Add the quiz solution
    ];
}

// Calculate scores
$total_obtained_marks = 0;
$correct_answers = 0;
$incorrect_answers = 0;

foreach ($questions as &$question) {
    $submitted_answer = $question['submitted_answer'];
    $correct_answer = $question['correct_answer'];

    // Check if the cleaned answers are correct
    if ($submitted_answer !== null && $submitted_answer === $correct_answer) {
        $correct_answers++;
        $total_obtained_marks += $marks_per_question; // Correct marks
    } else {
        $incorrect_answers++;
        $total_obtained_marks -= $negative_marks; // Negative marks for wrong answers
    }

    // Add user's submitted answer to question data
    $question['submitted_answer'] = $submitted_answer;
}

// Total possible marks
$total_marks = $marks_per_question * $questions_per_quiz;

// Prepare result data
$result = [
    'total_marks' => $total_marks,
    'total_obtained_marks' => $total_obtained_marks,
    'correct_answers' => $correct_answers,
    'incorrect_answers' => $incorrect_answers,
    'questions' => $questions, // Send the questions and answers to frontend
    'marks_per_question' => $marks_per_question
];

// Send JSON response
echo json_encode($result);
exit;
?>

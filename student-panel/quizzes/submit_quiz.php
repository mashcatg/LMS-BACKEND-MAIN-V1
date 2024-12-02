<?php
// submit_quiz.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Include your authentication check (example, modify it according to your needs)
include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Get the data from the frontend
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($data['quiz_id'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

$quiz_id = $data['quiz_id'];
$enrollment_id = $_SESSION['enrollment_id']; // Default to 1 for testing
$service_id = $_SESSION['service_id'] ; // Default service_id for testing
$answers = $data['answers'];

// Generate a unique form ID
$form_id = rand(111111, 999999) + time();

try {
    // Start transaction
    $conn->beginTransaction();

    // Loop through each answer and insert into the database
    foreach ($answers as $answer) {
        // Extract question and answers (including full options with labels)
        $question_id = $answer['question_id'];
        $submitted_answer = $answer['submitted_answer']; // The answer with the label (e.g., 'a. option1')

        // Extract options
        $option1 = $answer['options'][0];
        $option2 = $answer['options'][1];
        $option3 = $answer['options'][2];
        $option4 = $answer['options'][3];

        // Insert data into quiz_submissions table
        $stmt = $conn->prepare("
            INSERT INTO quiz_submissions (quiz_id, question_id, enrollment_id, option1, option2, option3, option4, submitted_answer, service_id, form_id)
            VALUES (:quiz_id, :question_id, :enrollment_id, :option1, :option2, :option3, :option4, :submitted_answer, :service_id, :form_id)
        ");
        $stmt->execute([
            ':quiz_id' => $quiz_id,
            ':question_id' => $question_id,
            ':enrollment_id' => $enrollment_id,
            ':option1' => $option1,
            ':option2' => $option2,
            ':option3' => $option3,
            ':option4' => $option4,
            ':submitted_answer' => $submitted_answer, // Store the full answer with the label
            ':service_id' => $service_id,
            ':form_id' => $form_id,
        ]);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Quiz submitted successfully']);
} catch (Exception $e) {
    // Rollback transaction in case of error
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

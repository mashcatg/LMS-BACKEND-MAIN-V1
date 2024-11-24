<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set headers for CORS
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Ensure the user is authenticated
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$service_id = $_SESSION['service_id'];

// Validate the quiz_id parameter
if (!isset($_POST['quiz_id']) || !is_numeric($_POST['quiz_id'])) {
    echo json_encode(['error' => 'Invalid or missing quiz ID']);
    exit();
}

$quiz_id = (int) $_POST['quiz_id'];

try {
    // Get quiz details
    $stmt = $conn->prepare("
        SELECT questions_per_quiz, marks_per_question, negative_marks, course_id 
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
    $course_id = $quizDetails['course_id'];

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

    // Check if submissions exist
    if (empty($submissions)) {
        echo json_encode(['success' => true, 'message' => 'No submissions found for this quiz.']);
        exit();
    }

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
            SELECT e.student_index, s.student_name, s.student_number, s.father_number, s.mother_number 
            FROM enrollments e
            INNER JOIN students s ON e.student_id = s.student_id
            WHERE e.enrollment_id = :enrollment_id
        ");
        $stmt->bindParam(':enrollment_id', $enrollmentId, PDO::PARAM_INT);
        $stmt->execute();
        $studentDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($studentDetails) {
            $leaderboard[] = [
                'student_index' => $studentDetails['student_index'],
                'student_name' => $studentDetails['student_name'],
                'student_number' => $studentDetails['student_number'],
                'father_number' => $studentDetails['father_number'],
                'mother_number' => $studentDetails['mother_number'],
                'marks' => $marks
            ];
        }
    }

    // Get all students enrolled in the course
    $stmt = $conn->prepare("
        SELECT e.student_index, s.student_name, s.student_number, s.father_number, s.mother_number 
        FROM enrollments e
        INNER JOIN students s ON e.student_id = s.student_id
        WHERE e.course_id = :course_id
    ");
    $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
    $stmt->execute();
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark absent students
    $absentStudents = [];
    foreach ($allStudents as $student) {
        $absentStudents[$student['student_index']] = [
            'student_name' => $student['student_name'],
            'student_number' => $student['student_number'],
            'father_number' => $student['father_number'],
            'mother_number' => $student['mother_number'],
            'marks' => 'Absent'
        ];
    }

    // Update absent students in the leaderboard
    foreach ($leaderboard as $entry) {
        if (isset($absentStudents[$entry['student_index']])) {
            $absentStudents[$entry['student_index']]['marks'] = $entry['marks'];
        }
    }

    // Prepare final leaderboard including absent students
    $finalLeaderboard = array_values($absentStudents);

    // Sort final leaderboard by marks in descending order
    usort($finalLeaderboard, function ($a, $b) {
        if ($a['marks'] === 'Absent') return 1; // Absent students go last
        if ($b['marks'] === 'Absent') return -1;
        return $b['marks'] <=> $a['marks'];
    });

    // Calculate total marks, highest marks, and average marks for SMS response
    $totalMarksPossible = ($questionsPerQuiz * $marksPerQuestion) - ($questionsPerQuiz * $negativeMarks);
    $highestMarks = count($leaderboard) > 0 ? max(array_column($leaderboard, 'marks')) : 0;
    $averageMarks = count($finalLeaderboard) > 0 ? array_sum(array_column($leaderboard, 'marks')) / count($finalLeaderboard) : 0;

    // Prepare SMS response
    $smsMessages = [];
    foreach ($finalLeaderboard as $entry) {
        $totalObtainedMarks = $entry['marks'] === 'Absent' ? 'Absent' : $entry['marks'];
        $position = $entry['marks'] === 'Absent' ? 'N/A' : array_search($entry, $finalLeaderboard) + 1;

        $smsMessages[] = [
            'student_number' => $entry['student_number'],
            'father_number' => $entry['father_number'],
            'mother_number' => $entry['mother_number'],
            'student_name' => $entry['student_name'],
            'total_obtained_marks' => $totalObtainedMarks,
            'position' => $position,
            'total_quiz_marks' => $totalMarksPossible,
            'highest_marks' => $highestMarks,
            'average_marks' => $averageMarks,
        ];
    }

    // Send SMS to each student and their parents
    foreach ($smsMessages as $entry) {
        $studentMessage = "Hello " . $entry['student_name'] . ", your marks for the quiz are: " . ($entry['total_obtained_marks'] === 'Absent' ? 'Absent' : $entry['total_obtained_marks']) . ".";
        sendSms($studentMessage, $entry['student_number'], $service_id);

        $parentMessage = "Hello, your child " . $entry['student_name'] . " scored: " . ($entry['total_obtained_marks'] === 'Absent' ? 'Absent' : $entry['total_obtained_marks']) . ".";
        sendSms($parentMessage, $entry['father_number'], $service_id);
        sendSms($parentMessage, $entry['mother_number'], $service_id);
    }

    // Return the leaderboard as a JSON response
    echo json_encode([
        'success' => true,
        'leaderboard' => $finalLeaderboard,
    ]);

} catch (Exception $e) {
    // Return error message on failure
    echo json_encode([
        'success' => false,
        'message' => 'Error generating leaderboard: ' . $e->getMessage()
    ]);
}

// Function to send SMS
function sendSms($message, $number, $serviceId) {
    // Validate the phone number before sending
    if (!validateNumber($number)) {
        return; // Skip if the number is not valid
    }

    $smsResponse = sms_send($message, $number, 1, $serviceId, 'student');
    if (json_decode($smsResponse, true)['error']) {
        echo json_encode(['success' => false, 'message' => 'Error sending SMS: ' . json_decode($smsResponse, true)['error']]);
        exit();
    }
}

// SMS sending function
function sms_send($textInput, $number, $totalSms, $serviceId, $sms_type) {
    global $conn;
    $url = "http://bulksmsbd.net/api/smsapi";
    $api_key = "x6Cup2Oa7raosVD4kt29"; // Your API key
    $senderid = "8809617612925"; // Your sender ID

    $data = [
        "api_key" => $api_key,
        "senderid" => $senderid,
        "number" => $number,
        "message" => $textInput
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        updateSmsCredits($totalSms, $serviceId, $conn);
        return json_encode(['success' => "Message sent successfully to $number."]);
    } else {
        return json_encode(['error' => "Failed to send message to $number."]);
    }
}

// Function to update SMS credits after sending
function updateSmsCredits($totalSms, $serviceId, $conn) {
    $updateCredit = "UPDATE services SET sms_credit = sms_credit - :total_sms WHERE service_id = :service_id";
    $stmt = $conn->prepare($updateCredit);
    $stmt->bindParam(":total_sms", $totalSms, PDO::PARAM_INT);
    $stmt->bindParam(":service_id", $serviceId, PDO::PARAM_INT);
    $stmt->execute();
}

// Validate phone number function
function validateNumber($number) {
    // Check if the number is valid and starts with '8801'
    return preg_match('/^8801\d{7,}$/', $number);
}
?>

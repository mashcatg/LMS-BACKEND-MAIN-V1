<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
include 'db.php'; // Include your database connection file

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Get and validate input
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['exam_id']) || !is_numeric($data['exam_id'])) {
        echo json_encode(['success' => false, 'message' => 'Valid Exam ID is required']);
        exit();
    }

    $exam_id = (int)$data['exam_id'];

    try {
        $service_id = $_SESSION['service_id'];

        // Query to fetch exam details including course_id
        $stmt = $conn->prepare("
            SELECT
                e.exam_id,
                e.exam_name,
                e.exam_date,
                e.bonus_marks,
                e.mcq_marks,
                e.cq_marks,
                e.practical_marks,
                e.course_id 
            FROM
                exams e
            WHERE
                e.exam_id = :exam_id
        ");

        $stmt->execute([':exam_id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit();
        }

        $course_id = $exam['course_id'];

        // Fetch all student indices for the given course_id
        $stmt = $conn->prepare("
            SELECT
                en.student_index,
                s.student_name,
                s.student_number,
                s.father_number,
                s.mother_number
            FROM
                enrollments en
            JOIN
                students s ON en.student_id = s.student_id
            WHERE
                en.course_id = :course_id
        ");
        $stmt->execute([':course_id' => $course_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize variables for highest and average marks
        $totalMarks = $exam['mcq_marks'] + $exam['cq_marks'] + $exam['practical_marks'] + $exam['bonus_marks'];
        $highestMarks = 0;
        $totalObtainedMarks = 0;
        $studentCount = count($students);
        $marksArray = [];

        // Prepare the response array
        $response = [];

        // Generate SMS for each student
        foreach ($students as $student) {
            $stmt = $conn->prepare("
                SELECT
                    m.mcq_marks,
                    m.cq_marks,
                    m.practical_marks
                FROM
                    marks m
                WHERE
                    m.student_index = :student_index
                    AND m.exam_id = :exam_id
                    AND m.service_id = :service_id
            ");
            $stmt->execute([
                ':student_index' => $student['student_index'],
                ':exam_id' => $exam_id,
                ':service_id' => $service_id
            ]);

            $marks = $stmt->fetch(PDO::FETCH_ASSOC);
            $mcq_marks = $marks['mcq_marks'] ?? 0;
            $cq_marks = $marks['cq_marks'] ?? 0;
            $practical_marks = $marks['practical_marks'] ?? 0;

            // Calculate total gained marks
            $studentTotalMarks = $mcq_marks + $cq_marks + $practical_marks + $exam['bonus_marks'];

            if ($marks) {
                // Store total marks for ranking
                $marksArray[] = $studentTotalMarks;

                // Prepare SMS response for present students
                $response[] = [
                    'student_index' => $student['student_index'],
                    'student_name' => $student['student_name'],
                    'exam_name' => $exam['exam_name'],
                    'exam_total_marks' => $totalMarks,
                    'student_total_obtained_marks' => $studentTotalMarks,
                    'student_number' => $student['student_number'],
                    'father_number' => $student['father_number'],
                    'mother_number' => $student['mother_number'],
                    'highest_marks' => $highestMarks,
                    'average_marks' => $studentCount > 0 ? $totalObtainedMarks / $studentCount : 0,
                    'student_position' => null, // Placeholder for position
                    'sms_message' => "Hello {$student['student_name']}, your total marks for {$exam['exam_name']} are: {$studentTotalMarks} out of {$totalMarks}. (cq: $cq_marks, mcq: $mcq_marks, practical: $practical_marks, bonus: {$exam['bonus_marks']})."
                ];
            } else {
                // If no marks found, indicate absence
                $response[] = [
                    'student_index' => $student['student_index'],
                    'student_name' => $student['student_name'],
                    'exam_name' => $exam['exam_name'],
                    'exam_total_marks' => $totalMarks,
                    'student_total_obtained_marks' => "You didn't attend the exam",
                    'student_number' => $student['student_number'],
                    'father_number' => $student['father_number'],
                    'mother_number' => $student['mother_number'],
                    'highest_marks' => $highestMarks,
                    'average_marks' => $studentCount > 0 ? $totalObtainedMarks / $studentCount : 0,
                    'student_position' => "N/A", // Absentees have no position
                    'sms_message' => "Hello {$student['student_name']}, you didn't attend the {$exam['exam_name']} Exam."
                ];
            }
        }

        // Calculate the highest total obtained marks
        foreach ($response as $student) {
            if (is_numeric($student['student_total_obtained_marks'])) {
                $totalObtainedMarks += $student['student_total_obtained_marks'];
                if ($student['student_total_obtained_marks'] > $highestMarks) {
                    $highestMarks = $student['student_total_obtained_marks'];
                }
            }
        }

        // Update highest marks and average marks in the response
        foreach ($response as &$student) {
            $student['highest_marks'] = $highestMarks;
            $student['average_marks'] = $studentCount > 0 ? $totalObtainedMarks / $studentCount : 0;
        }

        // Calculate positions based on total obtained marks
        usort($response, function($a, $b) {
            $aMarks = is_numeric($a['student_total_obtained_marks']) ? $a['student_total_obtained_marks'] : 0;
            $bMarks = is_numeric($b['student_total_obtained_marks']) ? $b['student_total_obtained_marks'] : 0;
            return $bMarks - $aMarks;
        });

        foreach ($response as $key => &$student) {
            if (isset($student['student_total_obtained_marks']) && is_numeric($student['student_total_obtained_marks'])) {
                $student['student_position'] = $key + 1; // Position is index + 1
            } else {
                $student['student_position'] = "N/A"; // For absentees
            }
        }

        // Prepare phone numbers for SMS
        $phoneNumbers = [];
        foreach ($response as $student) {
            $phoneNumbers = array_merge($phoneNumbers, getValidatedNumbers($student));
        }

        // Send SMS to validated numbers
        foreach ($phoneNumbers as $number) {
            $smsResponse = calculateSmsCost($number['message'], $number['numbers'], 'student'); // Sending SMS
            if (json_decode($smsResponse, true)['error']) {
                echo json_encode(['success' => false, 'message' => 'Error sending SMS: ' . json_decode($smsResponse, true)['error']]);
                exit();
            }
        }

        // Send the final response
        echo json_encode(['success' => true, 'students' => $response]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to get validated phone numbers
function getValidatedNumbers($student) {
    $numbers = [];
    $message = $student['sms_message'];

    if (validateNumber($student['student_number'])) {
        $numbers[] = ['number' => $student['student_number'], 'message' => $message];
    }
    if (validateNumber($student['father_number'])) {
        $numbers[] = ['number' => $student['father_number'], 'message' => $message];
    }
    if (validateNumber($student['mother_number'])) {
        $numbers[] = ['number' => $student['mother_number'], 'message' => $message];
    }

    return $numbers;
}

// SMS cost calculation function
function calculateSmsCost($text, $numbers, $sms_type) {
    // Split the numbers by comma
    $numberArray = explode(',', $numbers);
    $validNumbers = [];
    foreach ($numberArray as $number) {
        if (!validateNumber($number, $sms_type)) {
            continue; // Skip invalid numbers
        }
        $validNumbers[] = $number;
    }

    $length = mb_strlen($text);
    $totalSms = calculateTotalSms($length);

    if ($totalSms === false) {
        return json_encode(['error' => "The message exceeds the maximum allowed length of 765 characters."]);
    }

    // Here, you would add the logic to actually send the SMS, using your SMS gateway API

    // Assuming SMS is sent successfully, return a success message
    return json_encode(['success' => true, 'total_sms' => $totalSms]);
}

// Function to calculate the total SMS required based on the message length
function calculateTotalSms($length) {
    if ($length > 0 && $length <= 160) {
        return 1; // 1 SMS for 1-160 characters
    } elseif ($length >= 161 && $length <= 300) {
        return 2; // 2 SMS for 161-300 characters
    } elseif ($length >= 301 && $length <= 440) {
        return 3; // 3 SMS for 301-440 characters
    } elseif ($length > 440 && $length <= 765) {
        return 5; // 5 SMS for 441-765 characters
    } else {
        return false; // Exceeds maximum allowed length
    }
}

// Validate phone number function
function validateNumber($number) {
    // Check if the number is valid and starts with '8801'
    return preg_match('/^8801\d{7,}$/', $number);
}
?>

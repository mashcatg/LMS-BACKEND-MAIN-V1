<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

// Check user authentication
if ($checkAuthMessage !== 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Fetch session variables and validate them
$service_id = isset($_SESSION['service_id']) ? (int)$_SESSION['service_id'] : 61545; // Default service ID
$course_id = isset($_SESSION['course_id']) ? (int)$_SESSION['course_id'] : 1; // Default course ID
$student_index = isset($_SESSION['student_index']) ? $_SESSION['student_index'] : '1234'; // Default student index

// Validate session values
if (!is_int($service_id) || !is_int($course_id) || !is_string($student_index)) {
    echo json_encode(['error' => 'Invalid session data']);
    exit();
}

try {
    // Fetch exams for the student, ensuring visibility and valid course and service IDs
    $stmt = $conn->prepare("
        SELECT exams.*, GROUP_CONCAT(courses.course_name SEPARATOR ', ') AS course_names
        FROM exams
        JOIN courses ON FIND_IN_SET(courses.course_id, exams.course_id) > 0
        WHERE exams.service_id = :service_id
        AND FIND_IN_SET(:course_id, exams.course_id) > 0
        AND exams.student_visibility = 1
        GROUP BY exams.exam_id
        ORDER BY exams.exam_date DESC
    ");
    
    $stmt->execute([':service_id' => $service_id, ':course_id' => $course_id]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($exams)) {
        echo json_encode(['success' => false, 'message' => 'No exams found for the given service and course.']);
        exit();
    }

    // Prepare an array to store exams with marks
    $exams_with_marks = [];

    // Fetch marks for the student for each exam
    foreach ($exams as $exam) {
        $exam_id = $exam['exam_id'];

        // Get the marks for this exam and student from the 'marks' table
        $marksStmt = $conn->prepare("
            SELECT mcq_marks, cq_marks, practical_marks
            FROM marks 
            WHERE exam_id = :exam_id 
            AND student_index = :student_index
        ");
        $marksStmt->execute([':exam_id' => $exam_id, ':student_index' => $student_index]);
        $marks = $marksStmt->fetch(PDO::FETCH_ASSOC);

        // If no marks are found, use default values
        if (!$marks) {
            $marks = [
                'mcq_marks' => '-',
                'cq_marks' => '-',
                'practical_marks' => '-',
                'message' => 'Not Attended'
            ];
        }

        // Total marks calculated from the 'exams' table
        $total_exam_marks = $exam['mcq_marks'] + $exam['cq_marks'] + $exam['practical_marks'];

        // Exam data array
        $exam_data = [
            'exam_id' => $exam['exam_id'],
            'exam_name' => $exam['exam_name'],
            'exam_date' => $exam['exam_date'],
            'total_exam_marks' => $total_exam_marks,
            'bonus_marks' => $exam['bonus_marks'],
        ];

        // Merge exam data with marks data
        $exams_with_marks[] = array_merge($exam_data, $marks);
    }

    // Return the response with exams and corresponding marks
    echo json_encode(['success' => true, 'exams' => $exams_with_marks]);

} catch (Exception $e) {
    // Log the error for debugging purposes (in production, log to a file)
    error_log($e->getMessage());
    
    // Return a generic error message
    echo json_encode(['success' => false, 'message' => 'Error: An unexpected error occurred.']);
}
?>

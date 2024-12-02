<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$service_id = $_SESSION['service_id'] ;
$course_id = $_SESSION['course_id']; // Get course ID from session or default to 1
$student_index = $_SESSION['student_index']; // For testing, replace with real session data

if (!$student_index) {
    echo json_encode(['error' => 'Student index not found']);
    exit();
}

try {
    // Fetch exams for the given course_id using FIND_IN_SET to check course_id list in exams table
    $stmt_exams = $conn->prepare("
        SELECT exam_id, exam_name, mcq_marks, cq_marks, practical_marks, bonus_marks, exam_date, course_id
        FROM exams 
        WHERE service_id = :service_id
        AND FIND_IN_SET(:course_id, course_id) > 0
        AND student_visibility = 1
    ");
    $stmt_exams->execute([':course_id' => $course_id, ':service_id' => $service_id]);
    $exams = $stmt_exams->fetchAll(PDO::FETCH_ASSOC);

    // Debug: Print out the fetched exams to see if multiple rows are returned
    if (empty($exams)) {
        echo json_encode(['error' => 'No exams found for the specified course_id.']);
        exit();
    } else {
        // For debugging purposes: log the fetched exams
        error_log(print_r($exams, true));
    }

    $exam_details = [];

    foreach ($exams as $exam) {
        $exam_id = $exam['exam_id'];
        $bonus_marks = $exam['bonus_marks'];

        // Fetch marks for each exam and student
        $stmt_marks = $conn->prepare("
            SELECT mcq_marks, cq_marks, practical_marks
            FROM marks
            WHERE exam_id = :exam_id AND student_index = :student_index
        ");
        $stmt_marks->execute([':exam_id' => $exam_id, ':student_index' => $student_index]);
        $marks = $stmt_marks->fetch(PDO::FETCH_ASSOC);

        if ($marks) {
            // Assign marks or default to 0
            $mcq_marks = $marks['mcq_marks'] ?? 0;
            $cq_marks = $marks['cq_marks'] ?? 0;
            $practical_marks = $marks['practical_marks'] ?? 0;

            // Fetch the maximum marks for each category from the exams table
            $max_mcq_marks = $exam['mcq_marks'];
            $max_cq_marks = $exam['cq_marks'];
            $max_practical_marks = $exam['practical_marks'];

            // Total possible marks without bonus
            $max_total_marks = $max_mcq_marks + $max_cq_marks + $max_practical_marks;

            // Calculate obtained total marks
            $obtained_marks = $mcq_marks + $cq_marks + $practical_marks + $bonus_marks;

            // Calculate percentage
            $percentage = ($obtained_marks / $max_total_marks) * 100;

            // Add exam details to response with percentage
            $exam_details[] = [
                'exam_id' => $exam_id,
                'exam_name' => $exam['exam_name'],
                'bonus_marks' => $bonus_marks,
                'percentage' => round($percentage, 2), // Rounded to 2 decimal places
                'exam_date' => $exam['exam_date'] // Include exam date in the response
            ];
        }
    }

    // Return response
    echo json_encode([
        'success' => true,
        'exam_details' => $exam_details
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

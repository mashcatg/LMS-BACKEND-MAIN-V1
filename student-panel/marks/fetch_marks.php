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

$service_id = $_SESSION['service_id'];
$exam_id = $_GET['exam_id'];
$student_index = $_SESSION['student_index'];


// Check if 'exam_id' is passed in the request (GET parameter)
if (!isset($_GET['exam_id'])) {
    echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
    exit();
}

try {
    // Query to fetch exam details, including course_id
    $stmt = $conn->prepare("
        SELECT
            e.exam_id,
            e.exam_name,
            e.exam_date,
            e.bonus_marks,
            e.course_id
        FROM
            exams e
        WHERE
            e.exam_id = :exam_id AND e.student_visibility = '1'
    ");

    $stmt->execute([':exam_id' => $exam_id]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        echo json_encode(['success' => false, 'message' => 'Result is not visible for students']);
        exit();
    }

    // Get the course IDs and split them by comma
    $course_ids = explode(',', $exam['course_id']);

    // Fetch students enrolled in the courses
    $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
    $stmt = $conn->prepare("
        SELECT
            en.student_index,
            s.student_name,
            en.course_id,
            en.batch_id,
            b.batch_name
        FROM
            enrollments en
        JOIN
            students s ON en.student_id = s.student_id
        JOIN
            batches b ON en.batch_id = b.batch_id
        WHERE
            en.course_id IN ($placeholders)
    ");

    $stmt->execute($course_ids);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        echo json_encode(['success' => false, 'message' => 'No students found for the courses']);
        exit();
    }

    // Fetch marks for the current student
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
        ':student_index' => $student_index,
        ':exam_id' => $exam_id,
        ':service_id' => $service_id
    ]);

    $marks = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare marks data for the response
    $marksData = [
        'mcq_marks' => $marks['mcq_marks'] ?? 0,
        'cq_marks' => $marks['cq_marks'] ?? 0,
        'practical_marks' => $marks['practical_marks'] ?? 0,
        'total_marks' => ($marks['mcq_marks'] ?? 0) + ($marks['cq_marks'] ?? 0) + ($marks['practical_marks'] ?? 0) + $exam['bonus_marks']
    ];

    // Send response with exam details and current student's marks
    echo json_encode([
        'success' => true,
        'marks' => [
            'exam_name' => $exam['exam_name'],
            'exam_date' => $exam['exam_date'],
            'bonus_marks' => $exam['bonus_marks'],
            'student_index' => $student_index,
            'student_marks' => $marksData
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

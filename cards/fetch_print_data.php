<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

// Get `card_id` from the URL
if (!isset($_GET['card_id'])) {
    echo json_encode(['error' => 'Card ID not provided']);
    exit();
}

$card_id = $_GET['card_id'];
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;

try {
    // 1. Fetch the `course_id`, `card_title`, and `date` from `cards` table using `card_id`
    $stmt = $conn->prepare("SELECT course_id, card_title, date FROM cards WHERE card_id = ? AND service_id = ?");
    $stmt->execute([$card_id, $service_id]);
    $card = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$card) {
        echo json_encode(['error' => 'No card found for the provided card ID']);
        exit();
    }

    $course_id = $card['course_id'];
    $card_title = $card['card_title'];
    $card_date = $card['date'];

    if ($student_id) {
        // If `student_id` is provided, fetch details for that specific student
        $stmt = $conn->prepare("SELECT student_name, student_number, student_date_of_birth, student_institution FROM students WHERE student_id = ? AND service_id = ?");
        $stmt->execute([$student_id, $service_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['error' => 'No student found for the provided student ID']);
            exit();
        }

        // Fetch the `batch_id` from `enrollments` where `course_id` and `student_id` match
        $stmt = $conn->prepare("SELECT batch_id FROM enrollments WHERE course_id = ? AND student_id = ?");
        $stmt->execute([$course_id, $student_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment) {
            echo json_encode(['error' => 'No enrollment found for the provided course and student']);
            exit();
        }

        $batch_id = $enrollment['batch_id'];

        // Fetch `course_name` from `courses` table using `course_id`
        $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            echo json_encode(['error' => 'No course found for the provided course ID']);
            exit();
        }

        $course_name = $course['course_name'];

        // Fetch `batch_name` from `batches` table using `batch_id`
        $stmt = $conn->prepare("SELECT batch_name FROM batches WHERE batch_id = ?");
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            echo json_encode(['error' => 'No batch found for the provided batch ID']);
            exit();
        }

        $batch_name = $batch['batch_name'];

        // Return the combined data as a JSON response
        $response = [
            'card_id' => $card_id,
            'course_id' => $course_id,
            'card_title' => $card_title,
            'card_date' => $card_date,
            'student_name' => $student['student_name'],
            'student_number' => $student['student_number'],
            'student_date_of_birth' => $student['student_date_of_birth'],
            'student_institution' => $student['student_institution'],
            'course_name' => $course_name,
            'batch_name' => $batch_name,
        ];

        echo json_encode($response);

    } else {
        // If `student_id` is not provided, fetch all students for the course
        $stmt = $conn->prepare("
            SELECT 
                students.student_id, students.student_name, students.student_number, 
                students.student_date_of_birth, students.student_institution, enrollments.batch_id
            FROM enrollments 
            JOIN students ON enrollments.student_id = students.student_id 
            WHERE enrollments.course_id = ? AND students.service_id = ?
        ");
        $stmt->execute([$course_id, $service_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$students) {
            echo json_encode(['error' => 'No students found for the provided course ID']);
            exit();
        }

        // Fetch `course_name` from `courses` table using `course_id`
        $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        $course_name = $course['course_name'];

        // Fetch batch names for all students
        $student_data = [];
        foreach ($students as $student) {
            $batch_id = $student['batch_id'];

            // Fetch `batch_name` from `batches` table using `batch_id`
            $stmt = $conn->prepare("SELECT batch_name FROM batches WHERE batch_id = ?");
            $stmt->execute([$batch_id]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            $batch_name = $batch ? $batch['batch_name'] : null;

            $student_data[] = [
                'student_name' => $student['student_name'],
                'student_number' => $student['student_number'],
                'student_date_of_birth' => $student['student_date_of_birth'],
                'student_institution' => $student['student_institution'],
                'course_name' => $course_name,
                'batch_name' => $batch_name,
                'card_title' => $card_title,
                'card_date' => $card_date,
            ];
        }

        // Return all students' data
        echo json_encode(['students' => $student_data]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
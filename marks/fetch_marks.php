<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    // Check if 'exam_id' is passed in the request (GET parameter)
    if (!isset($_GET['exam_id'])) {
        echo json_encode(['success' => false, 'message' => 'Exam ID is required']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];
        $exam_id = $_GET['exam_id']; // Assuming exam_id is passed in the URL

        // Step 1: Get course_id and bonus_marks from exams table
        $stmt = $conn->prepare("SELECT course_id, exam_name, exam_date, bonus_marks FROM exams WHERE exam_id = :exam_id");
        $stmt->execute([':exam_id' => $exam_id]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exam) {
            echo json_encode(['success' => false, 'message' => 'Exam not found']);
            exit();
        }

        // Step 2: Get course IDs as an array
        $course_ids = explode(',', $exam['course_id']);
        $placeholders = implode(',', array_fill(0, count($course_ids), '?'));

        // Step 3: Get students for the specific course IDs
        $stmt = $conn->prepare("
            SELECT
                en.student_id,
                en.student_index
            FROM
                enrollments en
            WHERE
                en.course_id IN ($placeholders)
        ");
        $stmt->execute($course_ids);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($enrollments)) {
            echo json_encode(['success' => false, 'message' => 'No students found for the courses']);
            exit();
        }

        // Step 4: Get student names from the students table using student_id
        $student_ids = array_column($enrollments, 'student_id');
        $student_placeholders = implode(',', array_fill(0, count($student_ids), '?'));

        $stmt = $conn->prepare("
            SELECT
                s.student_id,
                s.student_name,
                e.student_index
            FROM
                students s
            JOIN
                enrollments e ON s.student_id = e.student_id
            WHERE
                s.student_id IN ($student_placeholders)
                AND e.course_id IN ($placeholders)
        ");
        $stmt->execute(array_merge($student_ids, $course_ids));
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Step 5: Get marks for the selected students based on student_index
        $student_indexes = array_column($students, 'student_index');
        $marks_placeholders = implode(',', array_fill(0, count($student_indexes), '?'));

        $stmt = $conn->prepare("
            SELECT
                m.student_index,
                m.mcq_marks,
                m.cq_marks,
                m.practical_marks
            FROM
                marks m
            WHERE
                m.student_index IN ($marks_placeholders)
                AND m.exam_id = ?
                AND m.service_id = ?
        ");
        $stmt->execute(array_merge($student_indexes, [$exam_id, $service_id]));

        // Fetch marks and prepare for merging with student data
        $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $marksAssoc = [];
        foreach ($marks as $mark) {
            $marksAssoc[$mark['student_index']] = $mark;
        }

        // Combine student data with marks
        foreach ($students as $key => $student) {
            $student_marks = $marksAssoc[$student['student_index']] ?? [];
            $students[$key]['mcq_marks'] = $student_marks['mcq_marks'] ?? 0;
            $students[$key]['cq_marks'] = $student_marks['cq_marks'] ?? 0;
            $students[$key]['practical_marks'] = $student_marks['practical_marks'] ?? 0;

            // Calculate total marks including bonus marks
            $totalMarks = $students[$key]['mcq_marks'] + $students[$key]['cq_marks'] + $students[$key]['practical_marks'] + $exam['bonus_marks'];
            $students[$key]['total_marks'] = $totalMarks;
        }

        // Sort students by total marks in descending order
        usort($students, function($a, $b) {
            return $b['total_marks'] - $a['total_marks'];
        });

        // Send response with exam details and students' data
        echo json_encode([
            'success' => true,
            'marks' => [
                'exam_name' => $exam['exam_name'],
                'exam_date' => $exam['exam_date'],
                'bonus_marks' => $exam['bonus_marks']
            ],
            'students' => $students
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

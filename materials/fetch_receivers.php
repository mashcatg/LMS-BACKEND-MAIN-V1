<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
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

// Get `material_id` from the URL
if (!isset($_GET['material_id'])) {
    echo json_encode(['error' => 'Material ID not provided']);
    exit();
}

$material_id = $_GET['material_id'];

try {
    // 1. Fetch the `course_id` from the `materials` table using `material_id`
    $stmt = $conn->prepare("SELECT course_id FROM materials WHERE material_id = ? AND service_id = ?");
    $stmt->execute([$material_id, $service_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        echo json_encode(['error' => 'No course found for the provided material ID']);
        exit();
    }

    $course_id = $material['course_id'];

    // 2. Fetch all `enrollment_id` and `student_index` from `material_receivers` table using `material_id`
    $stmt = $conn->prepare("SELECT enrollment_id FROM material_receivers WHERE material_id = ?");
    $stmt->execute([$material_id]);
    $material_receivers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$material_receivers) {
        echo json_encode(['error' => 'No material receivers found for the provided material ID']);
        exit();
    }

    $student_details = [];

    // 3. Loop through each `enrollment_id` and fetch the corresponding `student_id`, `course_id`, and `batch_id` from `enrollments` table
    foreach ($material_receivers as $receiver) {
        $enrollment_id = $receiver['enrollment_id'];

        // Fetch `student_id`, `course_id`, and `batch_id` from the `enrollments` table using `enrollment_id`
        $stmt = $conn->prepare("SELECT student_id, course_id, batch_id, student_index FROM enrollments WHERE enrollment_id = ? AND service_id = ?");
        $stmt->execute([$enrollment_id, $service_id]);
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($enrollment) {
            $student_id = $enrollment['student_id'];
            $course_id = $enrollment['course_id'];
            $batch_id = $enrollment['batch_id'];

            // 4. Fetch student details from the `students` table using `student_id`
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND service_id = ?");
            $stmt->execute([$student_id, $service_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($student) {
                // 5. Fetch course name from `courses` table using `course_id`
                $stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ? AND service_id = ?");
                $stmt->execute([$course_id, $service_id]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                // 6. Fetch batch name from `batches` table using `batch_id`
                $stmt = $conn->prepare("SELECT batch_name FROM batches WHERE batch_id = ? AND service_id = ?");
                $stmt->execute([$batch_id, $service_id]);
                $batch = $stmt->fetch(PDO::FETCH_ASSOC);

                // Add student details, course name, batch name, and student_index to the result
                $student['student_index'] = $enrollment['student_index'];
                $student['course_name'] = $course ? $course['course_name'] : null;
                $student['batch_name'] = $batch ? $batch['batch_name'] : null;
                $student_details[] = $student;
            }
        }
    }

    if (empty($student_details)) {
        echo json_encode(['error' => 'No student details found']);
        exit();
    }

    // 7. Return student details as a JSON response
    echo json_encode(['receivers' => $student_details]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching students: ' . $e->getMessage()]);
}
?>
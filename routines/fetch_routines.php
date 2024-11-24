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

    try {
        $service_id = $_SESSION['service_id'];

        // Fetch routines
        $stmt = $conn->prepare("
            SELECT
                routine.routine_id,
                routine.routine_name,
                routine.file_address,
                routine.course_id,
                routine.batch_id,
                routine.created_by,
                routine.created_at
            FROM
                routine
            WHERE
                routine.service_id = :service_id
            ORDER BY routine_id DESC
        ");
        $stmt->execute([':service_id' => $service_id]);

        $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($routines as &$routine) {
            // Fetch course names based on the comma-separated course_ids
            if (!empty($routine['course_id'])) {
                $course_ids = explode(',', $routine['course_id']);
                $course_placeholder = implode(',', array_fill(0, count($course_ids), '?'));
                $course_stmt = $conn->prepare("
                    SELECT GROUP_CONCAT(course_name ORDER BY course_name ASC SEPARATOR ', ') AS course_names
                    FROM courses
                    WHERE course_id IN ($course_placeholder)
                ");
                $course_stmt->execute($course_ids);
                $course_result = $course_stmt->fetch(PDO::FETCH_ASSOC);
                $routine['course_names'] = $course_result['course_names'];
            }

            // Fetch batch names based on the comma-separated batch_ids
            if (!empty($routine['batch_id'])) {
                $batch_ids = explode(',', $routine['batch_id']);
                $batch_placeholder = implode(',', array_fill(0, count($batch_ids), '?'));
                $batch_stmt = $conn->prepare("
                    SELECT GROUP_CONCAT(batch_name ORDER BY batch_name ASC SEPARATOR ', ') AS batch_names
                    FROM batches
                    WHERE batch_id IN ($batch_placeholder)
                ");
                $batch_stmt->execute($batch_ids);
                $batch_result = $batch_stmt->fetch(PDO::FETCH_ASSOC);
                $routine['batch_names'] = $batch_result['batch_names'];
            }

            // Truncate course_names and batch_names to 80 characters if they exceed the limit
            if (strlen($routine['course_names']) > 80) {
                $routine['course_names'] = substr($routine['course_names'], 0, 77) . '...';
            }
            if (strlen($routine['batch_names']) > 80) {
                $routine['batch_names'] = substr($routine['batch_names'], 0, 77) . '...';
            }
        }

        echo json_encode(['success' => true, 'routines' => $routines]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

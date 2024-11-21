<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
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

        // Query to fetch attendance data
        $stmt = $conn->prepare("
            SELECT
                a.attendance_id,
                a.attendance_date,
                a.student_index,
                a.created_at,
                a.created_by,
                e.student_id,
                e.course_id,
                e.batch_id,
                c.course_name,
                b.batch_name,
                s.student_name
            FROM
                attendance a
            JOIN
                enrollments e ON a.student_index = e.student_index
            JOIN
                courses c ON e.course_id = c.course_id
            JOIN
                batches b ON e.batch_id = b.batch_id
            JOIN
                students s ON e.student_id = s.student_id
            WHERE
                a.service_id = :service_id
        ");
        
        $stmt->execute([':service_id' => $service_id]);

        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Query to get total rows of enrollments for the given service_id
        $enrollment_count_stmt = $conn->prepare("
            SELECT COUNT(*) as total_enrollments 
            FROM enrollments 
            WHERE service_id = :service_id
        ");
        $enrollment_count_stmt->execute([':service_id' => $service_id]);
        $total_enrollments = $enrollment_count_stmt->fetch(PDO::FETCH_ASSOC)['total_enrollments'];
        $today = date('Y-m-d');

        // Prepare the SQL query to get attendance count for today
        $attendance_count_stmt = $conn->prepare("
            SELECT COUNT(*) as total_attendances 
            FROM attendance 
            WHERE service_id = :service_id
            AND DATE(attendance_date) = :today
        ");

        // Execute the query with service_id and today's date
        $attendance_count_stmt->execute([
            ':service_id' => $service_id,
            ':today' => $today
        ]);

        // Fetch the result
        $total_attendances = $attendance_count_stmt->fetch(PDO::FETCH_ASSOC)['total_attendances'];
        // Send the response with attendance data and total counts
        echo json_encode([
            'success' => true,
            'attendance' => $attendance,
            'total_enrollments' => $total_enrollments,
            'total_attendances' => $total_attendances
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

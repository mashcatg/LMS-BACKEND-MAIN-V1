<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Ensure session variables are set
// if (!isset($_SESSION['service_id'], $_SESSION['course_id'], $_SESSION['batch_id'])) {
//     echo json_encode(['success' => false, 'message' => 'Missing session variables.']);
//     exit();
// }

$service_id = $_SESSION['service_id'] ?? '61545';
$course_id = $_SESSION['course_id'] ?? '1';
$batch_id = $_SESSION['batch_id'] ?? '1';

try {
    // Fetch notes using FIND_IN_SET for course_id and batch_id
    $stmt = $conn->prepare("
        SELECT
            notes.note_id,
            notes.note_name,
            notes.file_address,
            notes.note_tags
        FROM
            notes
        WHERE
            notes.service_id = :service_id AND 
            FIND_IN_SET(:course_id, notes.course_id) AND 
            FIND_IN_SET(:batch_id, notes.batch_id)
        ORDER BY 
            note_id DESC
    ");
    $stmt->execute([':service_id' => $service_id, ':course_id' => $course_id, ':batch_id' => $batch_id]);

    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'notes' => $notes]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

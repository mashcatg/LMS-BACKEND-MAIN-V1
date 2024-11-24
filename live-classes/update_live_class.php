<?php
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve live_class_id from query parameters
    $live_class_id = $_GET['live_class_id'] ?? null;
    $live_class_name = $_POST['live_class_name'] ?? '';
    $live_class_desc = $_POST['live_class_desc'] ?? '';
    $course_ids = $_POST['course_id'] ?? '';
    $batch_ids = $_POST['batch_id'] ?? '';

    if (empty($live_class_id) || empty($live_class_name) || empty($course_ids) || empty($batch_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    try {
        // Prepare SQL query to update live class details
        $stmt = $conn->prepare("
            UPDATE live_classes 
            SET 
                live_class_name = :live_class_name,
                live_class_desc = :live_class_desc,
                course_id = :course_ids,
                batch_id = :batch_ids
            WHERE live_class_id = :live_class_id
        ");

        // Bind parameters and execute the query
        $stmt->execute([
            ':live_class_name' => $live_class_name,
            ':live_class_desc' => $live_class_desc,
            ':course_ids' => $course_ids, // Expecting comma-separated values
            ':batch_ids' => $batch_ids,     // Expecting comma-separated values
            ':live_class_id' => $live_class_id,
        ]);

        echo json_encode(['success' => true, 'message' => 'Live class updated successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

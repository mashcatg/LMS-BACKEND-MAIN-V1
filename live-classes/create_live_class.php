<?php

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use raw form data, since it looks like you're sending form data, not JSON
    $live_class_name = $_POST['live_class_name'] ?? '';
    $live_class_desc = $_POST['live_class_desc'] ?? '';
    $course_ids = $_POST['course_id'] ?? '';
    $batch_ids = $_POST['batch_id'] ?? '';
    $service_id = $_SESSION['service_id'] ?? null; // Assuming the service_id is stored in session

    if (empty($live_class_name) || empty($course_ids) || empty($batch_ids)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit();
    }

    try {
        // Prepare SQL query to insert live class details
        $stmt = $conn->prepare("
            INSERT INTO live_classes (live_class_name, live_class_desc, course_id, batch_id, service_id)
            VALUES (:live_class_name, :live_class_desc, :course_ids, :batch_ids, :service_id)
        ");

        // Execute the query with the provided data
        $stmt->execute([
            ':live_class_name' => $live_class_name,
            ':live_class_desc' => $live_class_desc,
            ':course_ids' => $course_ids, // Expecting comma-separated values
            ':batch_ids' => $batch_ids,   // Expecting comma-separated values
            ':service_id' => $service_id
        ]);

        echo json_encode(['success' => true, 'message' => 'Live class added successfully.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

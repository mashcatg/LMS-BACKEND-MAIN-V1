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

try {
    // Prepare the SQL query to fetch batches related to the current service
    $stmt = $conn->prepare("SELECT * FROM batches WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Iterate through each batch and fetch the number of students enrolled
    foreach ($batches as &$batch) {
        $batch_id = $batch['batch_id'];

        // Query to count enrollments for the current batch
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total_students FROM enrollments WHERE batch_id = ?");
        $countStmt->execute([$batch_id]);
        $enrollmentCount = $countStmt->fetch(PDO::FETCH_ASSOC);

        // Add the total_students count to each batch
        $batch['total_students'] = $enrollmentCount['total_students'] ?? 0;
    }

    // Return batches with the total_students count as a JSON response
    echo json_encode(['batches' => $batches]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching batches: ' . $e->getMessage()]);
}
?>

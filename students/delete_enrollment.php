<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$enrollment_ids = $data['ids'] ?? [];

if (empty($enrollment_ids) || !is_array($enrollment_ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid enrollment IDs provided']);
    exit();
}

try {
    // Prepare the SQL query to delete enrollments
    $placeholders = rtrim(str_repeat('?,', count($enrollment_ids)), ',');
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id IN ($placeholders)");
    $stmt->execute($enrollment_ids);

    // Check the number of affected rows
    $deletedCount = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => "$deletedCount enrollment(s) deleted successfully."]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting enrollments: ' . $e->getMessage()]);
}
?>

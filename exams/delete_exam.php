<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
exit(0);
}
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];

if (empty($_GET['exam_id'])) {
    echo json_encode(['error' => 'Missing exam ID']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = :exam_id AND service_id = :service_id");
    $stmt->bindParam(':exam_id', $_GET['exam_id'], PDO::PARAM_INT);
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Exam deleted successfully']);

} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to delete exam: ' . $e->getMessage()]);
}
?>

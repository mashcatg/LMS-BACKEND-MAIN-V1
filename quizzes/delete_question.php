<?php
ini_set('display_errors', 0);
error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
exit(0);
}
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $_GET['question_id'];
    $service_id = $_SESSION['service_id'];

    if (!$question_id) {
        echo json_encode(['success' => false, 'message' => 'Question ID is required']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            DELETE FROM quiz_questions
            WHERE question_id = ? AND service_id = ?
        ");
        $stmt->execute([$question_id, $service_id]);

        echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting question: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

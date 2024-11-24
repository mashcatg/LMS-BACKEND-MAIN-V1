<?php
ini_set('display_errors', 0);
error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
    header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    exit(0); // Terminate the script to avoid further processing for OPTIONS requests
}


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

    $data = json_decode(file_get_contents("php://input"), true);
    $quiz_id = $data['quiz_id'];

    try {
        $stmt = $conn->prepare("DELETE FROM quizzes WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);

        echo json_encode(['success' => true, 'message' => 'Quiz deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting quiz: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

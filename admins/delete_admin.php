<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
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

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = $data['admin_id'];

try {
    $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
    $stmt->execute([$admin_id]);

    echo json_encode(['success' => true, 'message' => 'Admin deleted successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting admin: ' . $e->getMessage()]);
}
?>

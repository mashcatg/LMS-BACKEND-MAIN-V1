<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
// Check if user is authenticated
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$payment_id = $data['payment_id'] ?? null;

if (!$payment_id) {
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit();
}

try {
    // Get service_id from session
    $service_id = $_SESSION['service_id'];

    // Delete payment record
    $stmt = $conn->prepare("DELETE FROM payments WHERE payment_id = ? AND service_id = ?");
    $stmt->execute([$payment_id, $service_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting payment: ' . $e->getMessage()]);
}
?>

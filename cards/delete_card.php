<?php
// Enable error reporting
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

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

$card_id = $_GET['card_id'] ?? '';

if (empty($card_id)) {
    echo json_encode(['success' => false, 'message' => 'Card ID is required.']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM cards WHERE card_id = ?");
    $stmt->execute([$card_id]);

    echo json_encode(['success' => true, 'message' => 'Card deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting card: ' . $e->getMessage()]);
}
}
?>

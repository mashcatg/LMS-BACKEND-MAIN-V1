<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $sector_name = $data['sector_name'] ?? null;
    $service_id = $_SESSION['service_id'] ?? null;

    if (!$sector_name || !$service_id) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    try {
        $stmt = $conn->prepare("INSERT INTO expense_sectors (sector_name, service_id) VALUES (?, ?)");
        $stmt->execute([$sector_name, $service_id]);

        $new_sector_id = $conn->lastInsertId();

        echo json_encode(['success' => true, 'message' => 'Expense sector added successfully', 'sector_id' => $new_sector_id]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error adding expense sector: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>

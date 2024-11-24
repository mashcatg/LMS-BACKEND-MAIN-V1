<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['admin_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit();
    }

    $service_id = $_SESSION['service_id'] ?? null;

    try {
        $stmt = $conn->prepare("
            SELECT e.*, es.sector_name 
            FROM expenses e
            JOIN expense_sectors es ON e.sector_id = es.sector_id
            WHERE e.service_id = ?
            ORDER BY e.expense_time DESC
        ");
        $stmt->execute([$service_id]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'expenses' => $expenses]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching expenses: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

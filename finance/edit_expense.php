<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
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

    $data = json_decode(file_get_contents('php://input'), true);
    $expense_id = $data['expense_id'];
    $sector_id = $data['sector_id'];
    $amount = $data['amount'];
    $details = $data['details'];

    if (empty($expense_id) || empty($sector_id) || empty($amount) || empty($details)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }

    try {
        $stmt = $conn->prepare("
            UPDATE expenses 
            SET sector_id = ?, expensed_amount = ?, expense_details = ?
            WHERE expense_id = ?
        ");
        $stmt->execute([$sector_id, $amount, $details, $expense_id]);

        echo json_encode(['success' => true, 'message' => 'Expense updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating expense: ' . $e->getMessage()]);
    }
}
<?php
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

try {
    $service_id = $_SESSION['service_id'];

    $stmt = $conn->prepare("SELECT * FROM expense_sectors WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'sectors' => $sectors]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching sectors: ' . $e->getMessage()]);
}
?>

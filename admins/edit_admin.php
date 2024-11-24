<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
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

// Fetch and sanitize input data
$data = json_decode(file_get_contents('php://input'), true);
$admin_id = $data['admin_id'];
$admin_name = $data['name'];
$admin_number = $data['number'];
$permissions = $data['permissions'];

// Convert permissions to a comma-separated string
$permissions_string = !empty($permissions) ? implode(',', array_map(fn($p) => $p['value'], $permissions)) : '';

try {
    $stmt = $conn->prepare("UPDATE admins SET admin_name = ?, admin_number = ?, admin_permissions = ? WHERE admin_id = ?");
    $stmt->execute([$admin_name, $admin_number, $permissions_string, $admin_id]);

    echo json_encode(['success' => true, 'message' => 'Admin updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating admin: ' . $e->getMessage()]);
}
?>

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include 'check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

// Retrieve new values from the POST request
$admin_name = $data['adminName'] ?? ''; // Default to an empty string
$admin_phone = $data['adminNumber'] ?? ''; // Default to an empty string
$admin_password = $data['adminPassword'] ?? ''; // Default to an empty string
$admin_id = $_SESSION['admin_id'];
$service_id = $_SESSION['service_id'];

// Fetch the stored password hash from the database
$stmt = $conn->prepare("SELECT admin_password FROM admins WHERE admin_id = ? AND service_id = ?");
$stmt->execute([$admin_id, $service_id]);
$storedPasswordHash = $stmt->fetchColumn();

if (!$storedPasswordHash) {
    echo json_encode(['error' => 'Admin not found']);
    exit();
}

// Verify the old password
if (!password_verify($admin_password, $storedPasswordHash)) {
    echo json_encode(['error' => "Password is incorrect"]);
    exit();
}

// Update the name and phone number in the database
$updateStmt = $conn->prepare("UPDATE admins SET admin_name = ?, admin_number = ? WHERE admin_id = ? AND service_id = ?");
$updateStmt->execute([$admin_name, $admin_phone, $admin_id, $service_id]);

echo json_encode(['message' => 'Profile updated successfully']);
?>

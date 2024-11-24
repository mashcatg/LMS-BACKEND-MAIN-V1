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
// Retrieve new and old passwords from the POST request
$newPassword = $data['newPassword'];
$oldPassword = $data['oldPass'];
$confirmNewPassword = $data['confirmNewPass'];
$admin_id = $_SESSION['admin_id'];
$service_id = $_SESSION['service_id'];

if($newPassword!=$confirmNewPassword){
    echo json_encode(['error' => "Passwords didn't match"]);
    exit();
}
// Fetch the stored password hash from the database
$stmt = $conn->prepare("SELECT admin_password FROM admins WHERE admin_id = ? AND service_id = ?");
$stmt->execute([$admin_id, $service_id]);
$storedPasswordHash = $stmt->fetchColumn();

if (!$storedPasswordHash) {
    echo json_encode(['error' => 'Admin not found']);
    exit();
}


// Verify the old password
if (!password_verify($oldPassword, $storedPasswordHash)) {
    echo json_encode(['error' => "Old password is incorrect"]);
    exit();
}

// Check if the new password is the same as the old password
if (password_verify($newPassword, $storedPasswordHash)) {
    echo json_encode(['error' => 'New password cannot be the same as the old password']);
    exit();
}

// Hash the new password
$hashed_new_pass = password_hash($newPassword, PASSWORD_DEFAULT);

// Update the password in the database
$updateStmt = $conn->prepare("UPDATE admins SET admin_password = ? WHERE admin_id = ? AND service_id = ?");
$updateStmt->execute([$hashed_new_pass, $admin_id, $service_id]);

echo json_encode(['message' => 'Password updated successfully']);
?>

<?php
ini_set('display_errors', 0);
error_reporting(0);

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

    $branch_id = $_POST['branch_id'];
    $branch_name = $_POST['branch_name'];
    $branch_details = $_POST['branch_details'];
    $branch_location = $_POST['branch_location'];

    try {
        $stmt = $conn->prepare("UPDATE branches SET branch_name = ?, branch_details = ?, branch_location = ? WHERE branch_id = ?");
        $stmt->execute([$branch_name, $branch_details, $branch_location, $branch_id]);

        $updated_branch = [
            'branch_id' => $branch_id,
            'branch_name' => $branch_name,
            'branch_details' => $branch_details,
            'branch_location' => $branch_location,
        ];

        echo json_encode(['success' => true, 'message' => 'Branch updated successfully', 'branch' => $updated_branch]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating branch: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
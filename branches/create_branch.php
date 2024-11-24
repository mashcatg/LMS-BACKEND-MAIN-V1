<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

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

// Get the form data
$branch_name = $_POST['branch_name'] ?? '';
$branch_details = $_POST['branch_details'] ?? '';
$branch_location = $_POST['branch_location'] ?? '';

// Validate form data
if (empty($branch_name) || empty($branch_details)) {
    echo json_encode(['success' => false, 'message' => 'Branch name or branch details is missing.']);
    exit();
}

if (!isset($_SESSION['service_id']) || !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

$service_id = $_SESSION['service_id'];
$created_by = $_SESSION['admin_id'];
$time = date('Y-m-d H:i:s'); // Current timestamp for "time"

try {
    // Insert the new branch into the database
    $stmt = $conn->prepare("INSERT INTO branches (branch_name, branch_details, branch_location, service_id, created_by, time) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$branch_name, $branch_details, $branch_location, $service_id, $created_by, $time]);

    // Return success response with the newly created branch
    echo json_encode([
        'success' => true,
        'branch' => [
            'branch_id' => $conn->lastInsertId(),
            'branch_name' => $branch_name,
            'branch_details' => $branch_details,
            'branch_location' => $branch_location,
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating branch: ' . $e->getMessage()]);
}
?>
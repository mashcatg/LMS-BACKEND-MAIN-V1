<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include 'check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$service_id = $_SESSION['service_id'];
try {
    // Prepare and execute the SQL statement to fetch admin profile
    $stmt = $conn->prepare("SELECT admin_name, admin_number FROM admins WHERE admin_id = ? AND service_id=?");
    $stmt->execute([$admin_id, $service_id]);

    // Fetch the result
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure there is data before encoding
    if ($profile) {
        echo json_encode($profile);
    } else {
        echo json_encode(['error' => 'Profile data not found']);
    }

} catch (Exception $e) {
    // Return an error message in JSON format
    echo json_encode(['error' => 'Error fetching profile data: ' . $e->getMessage()]);
    exit();
}

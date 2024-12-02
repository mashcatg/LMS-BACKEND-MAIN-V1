<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
include '../sms.php';

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
$admin_name = $data['name'];
$admin_number = $data['number'];
$service_id = $_SESSION['service_id'];
$permissions = $data['permissions'];

// Generate a random 6-digit alphanumeric password
$admin_password = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(6/strlen($x)))),1,6);

// Hash the password
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
//ger company details from services table
$stmt = $conn->prepare("SELECT sub_domain FROM services WHERE service_id = ? LIMIT 1");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);
$subdomain = $service['sub_domain'];
// Convert permissions to a comma-separated string
$permissions_string = !empty($permissions) ? implode(',', array_map(fn($p) => $p['value'], $permissions)) : '';
$sms_text = "Hi, $admin_name. Your admin panel password is $admin_password. Please login to $subdomain.ennovat.com";
$sms_number = $admin_number;
$revceiver_type = 'admin';
try {
    // Prepare the insert statement for the admins table
    $stmt = $conn->prepare("INSERT INTO admins (admin_name, admin_number, admin_password, admin_permissions, service_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$admin_name, $admin_number, $hashed_password, $permissions_string, $service_id]);

    // Send SMS and handle response
     $smsResponse = calculateSmsCost($sms_text, $sms_number, $revceiver_type); 
    echo json_encode([
        'success' => true,
        'message' => "Admin added successfully",
        'smsResponse' => json_decode($smsResponse, true) // Decode to include in response
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error adding admin: ' . $e->getMessage()]);
}
?>

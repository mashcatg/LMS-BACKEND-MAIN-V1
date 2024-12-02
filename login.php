<?php
// CORS headers
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

// Start session
session_start();
include 'db.php'; // Include your database connection file

// Check if already logged in
if (isset($_SESSION['admin_id'])) {
    echo json_encode(['logged_in' => true, 'redirect' => '/admin/']);
    exit();
}

// Get phone and password from POST data
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

// Validate phone number
$phone = validatePhoneNumber($phone);

if ($phone === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit();
}

if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is missing.']);
    exit();
}

try {
    // Fetch user from database by phone number
    $stmt = $conn->prepare("SELECT * FROM admins WHERE admin_number = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify user credentials
    if ($user && password_verify($password, $user['admin_password'])) {
        // Set session variables
        $_SESSION['admin_id'] = $user['admin_id'];
        $_SESSION['admin_name'] = $user['admin_name'];
        $_SESSION['service_id'] = $user['service_id'];

        // Generate a new token
        $token = bin2hex(random_bytes(32));
        $expiry_date = date("Y-m-d H:i:s", strtotime('+6 months')); // 6 months expiration

        // Set the token in cookies with HttpOnly and SameSite attributes
        setcookie("admin_token", $token, time() + (6 * 30 * 24 * 60 * 60), "/", "", false, true); // 6 months

        // Insert login details into the admin_logins table
        $stmt = $conn->prepare("INSERT INTO admin_logins (expiry_date, admin_token, admin_id, service_id) VALUES (:expiry_date, :token, :admin_id, :service_id)");
        $stmt->execute([
            ':expiry_date' => $expiry_date,
            ':token' => $token,
            ':admin_id' => $user['admin_id'],
            ':service_id' => $user['service_id']
        ]);

        // Update the admin's token in the admins table
        $update_stmt = $conn->prepare("UPDATE admins SET admin_token = :token WHERE admin_id = :admin_id");
        $update_stmt->execute([
            ':token' => $token,
            ':admin_id' => $user['admin_id']
        ]);

        // Respond with success
        echo json_encode(['success' => true, 'message' => 'Login successful', 'logged_in' => true, 'redirect' => '/admin/']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number or password.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Function to validate phone numbers
function validatePhoneNumber($number) {
    // Remove any non-numeric characters
    $number = preg_replace('/[^0-9]/', '', $number);
    
    if (strlen($number) === 0) {
        return false; // Empty number
    }

    // Check the prefix and adjust accordingly
    if (substr($number, 0, 4) === '8801' && strlen($number) === 13) {
        return $number; // Valid format, return as is
    } elseif (substr($number, 0, 2) === '01' && strlen($number) === 11) {
        return '88' . substr($number, 1); // Add '88' before the number
    } elseif (substr($number, 0, 1) === '1' && strlen($number) === 10) {
        return '880' . $number; // Add '880' before the number
    } else {
        return false; // Invalid format
    }
}
?>

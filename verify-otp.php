<?php
// CORS headers
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight request (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require 'db.php'; // Include your database connection file

$phone = $_GET['phone'] ?? ''; // Get phone number from query parameter
$phone = validatePhoneNumber($phone); // Validate the phone number

if ($phone === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'] ?? '';

    // Ensure OTP is provided
    if (empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'OTP is required.']);
        exit();
    }

    // Check if the phone and OTP are valid and if OTP has not expired
    $query = "SELECT * FROM admins WHERE admin_number = :phone AND admin_otp = :otp";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':otp', $otp);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Check if OTP has expired
        $current_time = date("Y-m-d H:i:s");
        if ($result['admin_otp_expiry_time'] > $current_time) {
            // OTP is correct and has not expired

            // Set session variables
            $_SESSION['admin_id'] = $result['admin_id'];
            $_SESSION['admin_name'] = $result['admin_name'];
            $_SESSION['service_id'] = $result['service_id'];

            // Generate a random token
            $new_token = bin2hex(random_bytes(32));

            // Set the token in cookies with HttpOnly and SameSite attributes
            setcookie("admin_token", $new_token, time() + (6 * 30 * 24 * 60 * 60), "/", "", false, true); // 6 months expiration, HTTP only

            // Update the admin token in the database
            $update_query = "UPDATE admins SET admin_token = :token WHERE admin_id = :admin_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':token', $new_token);
            $update_stmt->bindParam(':admin_id', $result['admin_id']);
            $update_stmt->execute();

            // Insert login details into the admin_logins table
            $expiry_date = date("Y-m-d H:i:s", strtotime('+6 months')); // Expiry date for the token
            $insert_query = "INSERT INTO admin_logins (expiry_date, admin_token, admin_id, service_id) VALUES (:expiry_date, :token, :admin_id, :service_id)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->execute([
                ':expiry_date' => $expiry_date,
                ':token' => $new_token,
                ':admin_id' => $result['admin_id'],
                ':service_id' => $result['service_id']
            ]);

            // Respond with success and redirect to /admin/
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'logged_in' => true,
                'redirect' => '/admin/'
            ]);
        } else {
            // OTP expired
            echo json_encode(['success' => false, 'message' => 'OTP has expired.']);
        }
    } else {
        // Invalid OTP
        echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
    }
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

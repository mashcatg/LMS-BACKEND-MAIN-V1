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

require 'db.php'; 
include 'sms.php';

if (isset($_SESSION['admin_id'])) {
    echo json_encode(['logged_in' => true, 'redirect' => '/admin/']);
    exit();
}

// Function to generate a random OTP
function generateOTP($length = 6) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

// Set OTP expiry time to 1 hour
$otp_expiry_time = date("Y-m-d H:i:s", strtotime('+1 hour'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = $_POST['phone'];

    // Validate phone number
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required.']);
        exit;
    }

    // Normalize phone number
    if (preg_match('/^01[3-9]\d{8}$/', $phone)) {
        // Phone starts with '01'
        $phone = '88' . $phone;
    } elseif (preg_match('/^1\d{10}$/', $phone)) {
        // Phone starts with '1'
        $phone = '880' . substr($phone, 1); // Remove the '1' and prepend '880'
    } elseif (!preg_match('/^8801[3-9]\d{8}$/', $phone)) {
        // Invalid phone number format
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format.']);
        exit;
    }

    // Check if admin exists in the database
    $query = "SELECT * FROM admins WHERE admin_number = :phone";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':phone', $phone);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Admin exists, generate OTP
        $otp = generateOTP();
        $update_query = "UPDATE admins SET admin_otp = :otp, admin_otp_expiry_time = :otp_expiry_time WHERE admin_number = :phone";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':otp', $otp);
        $update_stmt->bindParam(':otp_expiry_time', $otp_expiry_time);
        $update_stmt->bindParam(':phone', $phone);

        if ($update_stmt->execute()) {
            // OTP and expiry time saved successfully
            // Optionally, you can send the OTP via SMS here
            $sms_text = "Hi, {$result['admin_name']}. Your OTP is $otp."; // Assuming admin_name exists
            $receiver_type = 'admin';
            $smsResponse = calculateSmsCost($sms_text, $phone, $receiver_type);

            echo json_encode(['success' => true, 'message' => 'OTP sent successfully.', 'phone' => $phone]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save OTP. Please try again.']);
            exit;
        }
    } else {
        // Admin not found
        echo json_encode(['success' => false, 'message' => 'Phone number not found.']);
        exit;
    }
}
?>

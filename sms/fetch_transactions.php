<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

$service_id = $_SESSION['service_id'];

try {
    // Prepare the SQL query to fetch sms transactions related to the current service
    $stmt = $conn->prepare("SELECT * FROM sms_transactions WHERE service_id = ?");
    $stmt->execute([$service_id]);
    $smsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loop through each SMS transaction to fetch the corresponding admin name
    foreach ($smsList as &$sms) {
        $created_by = $sms['created_by'];

        // Query the admins table to get the admin_name for each created_by
        $adminStmt = $conn->prepare("SELECT admin_name FROM admins WHERE admin_id = ?");
        $adminStmt->execute([$created_by]); // Assuming created_by corresponds to admin_id
        $adminResult = $adminStmt->fetch(PDO::FETCH_ASSOC);

        // If an admin name is found, add it to the SMS transaction
        if ($adminResult) {
            $sms['admin_name'] = $adminResult['admin_name'];
        } else {
            $sms['admin_name'] = null; // or some default value if not found
        }
    }

    // Return SMS transactions along with the corresponding admin names as a JSON response
    echo json_encode(['success' => true, 'sms_transaction' => $smsList]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching sms transactions: ' . $e->getMessage()]);
}
?>

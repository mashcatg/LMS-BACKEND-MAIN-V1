<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
session_start();

// Include database connection
include 'db.php';

// Check if admin_token exists in cookies
$auth_token = $_COOKIE['admin_token'] ?? '';

// Initialize checkAuthMessage
$checkAuthMessage = 'Not authenticated';

// Validate the auth token and check expiry
if (!empty($auth_token)) {
    $stmt = $conn->prepare("SELECT admin_id, service_id, expiry_date FROM admin_logins WHERE admin_token = ? AND expiry_date > NOW()");
    $stmt->execute([$auth_token]);
    $login = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($login) {
        // Set session variables if the token is valid
        $_SESSION['admin_id'] = $login['admin_id'];
        $_SESSION['service_id'] = $login['service_id'];

        // Select admin's permissions
        $stmt = $conn->prepare("SELECT admin_permissions FROM admins WHERE admin_id = ? AND service_id = ?");
        $stmt->execute([$login['admin_id'], $login['service_id']]);
        $fetchPermission = $stmt->fetch(PDO::FETCH_ASSOC);

        // Store fetched permissions in session
        $_SESSION['admin_permissions'] = $fetchPermission['admin_permissions'] ?? null;
        $checkAuthMessage = 'success';
    } else {
        $checkAuthMessage = 'Invalid Session';
    }
}

// Log visit only if authenticated
$admin_id = $_SESSION['admin_id'] ?? null;
$service_id = $_SESSION['service_id'] ?? null;

// Get the user's IP address
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0';

// Use POST data to get the referring page
$from_page = $_POST['from_page'] ?? 'unknown';

$response = []; // Initialize response array

if ($checkAuthMessage === 'success') {
    $user_type = 'admin';
    
    // Get admin permissions
    $admin_permissions = $_SESSION['admin_permissions'];

    // Check if from_page is set
    if (isset($_POST['from_page'])) {
        try {
            // Prepare and execute SQL statement to log visit
            $sql = "INSERT INTO visits (user_id, ip, from_page, visit_time, service_id, user_type) VALUES (?, ?, ?, NOW(), ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$admin_id, $ip_address, $from_page, $service_id, $user_type]);

            // Return success response for visit logging
            $response['visitSuccess'] = true;
            $response['message'] = $from_page;
        } catch (Exception $e) {
            error_log("Error adding visit: " . $e->getMessage());
            $response['visitSuccess'] = false;
            $response['message'] = 'Error adding visit: ' . $e->getMessage();
        }

        // Check permissions only if admin_permissions is not null
        if ($admin_permissions !== null) {
            $permissions = explode(', ', $admin_permissions);
            $hasPermission = false;

            foreach ($permissions as $permission) {
                if (stripos($from_page, $permission) !== false) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                $response['permission'] = false;
                echo json_encode($response);
                exit();
            }
        }
    }

    // Return authentication success response]
    $response['logged_in'] = true;
    $response['redirect'] = '/admin/';
} else {
    // Return authentication failure response
    $response['logged_in'] = false;
    $response['redirect'] = '/auth/admin/login/';
    $response['error'] = $checkAuthMessage;
}

// Output the JSON response
echo json_encode($response);
?>

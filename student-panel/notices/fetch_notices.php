<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Setting up the CORS headers to allow requests from localhost:3000
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../student-auth/check_auth_backend.php';

// Checking if the user is authenticated
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
} 

// Fallback values for service_id and enrollment_id if not set in session
$service_id = $_SESSION['service_id'] ?? '61545';
$enrollment_id = $_SESSION['enrollment_id'] ?? '1';

try {
    // Prepare the SQL query with a corrected condition for 'notice_type'
    $stmt = $conn->prepare("
        SELECT * 
        FROM notices 
        WHERE service_id = :service_id 
        AND (notice_type = :enrollment_id OR notice_type = 'Public')
    ");
    
    // Bind the parameters to the SQL statement
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->bindParam(':enrollment_id', $enrollment_id, PDO::PARAM_INT);
    
    // Execute the query
    $stmt->execute();
    
    // Fetch the results
    $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the filtered notices in JSON format
    echo json_encode([
        'success' => true,
        'notices' => $notices
    ]);

} catch (Exception $e) {
    // Log the error message for debugging (optional, might want to log to a file or error management system)
    error_log("Error fetching notices: " . $e->getMessage());
    
    // Return a failure response to the client
    echo json_encode(['success' => false, 'message' => 'Error fetching notices: ' . $e->getMessage()]);
}
?>

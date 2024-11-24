<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

$service_id = $_SESSION['service_id'];
try {
    // Prepare and execute the SQL query
    $sql = "
        SELECT DATE(visit_time) AS date, 
               user_type, 
               COUNT(*) AS visits_count 
        FROM visits 
        WHERE visit_time >= NOW() - INTERVAL 30 DAY 
        GROUP BY DATE(visit_time), user_type 
        ORDER BY DATE(visit_time) DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Fetch results
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize the chart data array
    $chartData = [];

    // Process results into the desired format
    foreach ($results as $row) {
        $date = $row['date'];
        $userType = $row['user_type'];
        $visitsCount = $row['visits_count'];
        
        // Initialize the date entry if it doesn't exist
        if (!isset($chartData[$date])) {
            $chartData[$date] = [
                'date' => $date,
                'admin' => 0,
                'student' => 0,
            ];
        }
        
        // Sum visits based on user type
        if ($userType === 'admin') {
            $chartData[$date]['admin'] += $visitsCount;
        } elseif ($userType === 'student') {
            $chartData[$date]['student'] += $visitsCount;
        }
    }

    // Re-index the array to get a clean array structure
    $chartData = array_values($chartData);

    // Output the result
    echo json_encode($chartData);
} catch (Exception $e) {
    error_log("Error fetching visits data: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching visits data']);
}
?>

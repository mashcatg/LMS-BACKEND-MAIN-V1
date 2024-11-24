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
    $stmt = $conn->prepare("SELECT payment_time, paid_amount FROM payments WHERE service_id = :service_id ORDER BY payment_time DESC");
    $stmt->bindParam(':service_id', $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $chartData = [];
    
    // Process each payment record
    foreach ($payments as $payment) {
        $date = date('Y-m', strtotime($payment['payment_time']));
        $amount = (float)$payment['paid_amount']; // Ensure amount is treated as a float

        // If the date is already in the chart data, sum the amounts
        if (isset($chartData[$date])) {
            $chartData[$date]['income'] += $amount;
        } else {
            $chartData[$date] = ['date' => $date, 'income' => $amount];
        }
    }

    // Reset array to numeric index
    $chartData = array_values($chartData);

    // Sort the chart data by date
    usort($chartData, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });

    echo json_encode([
        'payments' => $chartData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching payments: ' . $e->getMessage()]);
}
?>

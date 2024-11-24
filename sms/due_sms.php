<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers
header("Access-Control-Allow-Origin: http://lms.ennovat.com:3002");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
include '../students/due_count.php'; // Include your due calculation logic

// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

// Ensure the user is authenticated
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get data from request
    $receiver = $_POST['receiver'] ?? '';
    $service_id = $_SESSION['service_id'];

    // Validate receiver input
    if (empty($receiver)) {
        echo json_encode(['success' => false, 'message' => 'Receiver IDs are required']);
        exit();
    }

    try {
        $admin_id = $_SESSION['admin_id'];

        // Process the receiver input to handle multiple IDs
        $receiver_ids = array_map('intval', explode(',', $receiver));
        $placeholders = implode(',', array_fill(0, count($receiver_ids), '?'));

        // Prepare the SQL statement to fetch student IDs based on enrollment IDs
        $stmt = $conn->prepare("SELECT student_id, enrollment_id FROM enrollments WHERE service_id = ? AND enrollment_id IN ($placeholders)");
        $stmt->execute(array_merge([$service_id], $receiver_ids));

        // Fetch all enrollments
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = []; // Prepare response array

        // Collect SMS data for all enrollments
        foreach ($enrollments as $enrollment) {
            $student_id = $enrollment['student_id'];
            $enrollment_id = $enrollment['enrollment_id'];

            // Fetch student number
            $stmt = $conn->prepare("SELECT student_number, student_name FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                $student_number = trim($student['student_number']);
                $student_name = trim($student['student_name']);
                $due_amounts = calculateDueAmount($conn, $enrollment_id, $service_id, null); // Fetch all course dues
                
                // If due amounts return an array, take the first value
                $due_amount = $due_amounts[0]['monthly_due'] ?? 0;

                // Only prepare the SMS if due amount is greater than 0
                if ($due_amount > 0) {
                    $response[] = [
                        'student_number' => $student_number,
                        'student_name' => $student_name,
                        'due_amount' => $due_amount,
                    ];
                }
            }
        }

        // Send SMS for due amounts
        if (!empty($response)) {
            sendDueSms($response, $service_id, $conn);
        }

        // Return the response with student numbers and their due amounts
        echo json_encode(['success' => true, 'message' => 'Retrieved successfully', 'data' => $response]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to send SMS for multiple recipients
function sendDueSms($response, $serviceId, $conn) {
    $sms_numbers = [];
    $totalSms = 0;

    foreach ($response as $data) {
        $student_number = $data['student_number'];
        $student_name = $data['student_name'];
        $due_amount = $data['due_amount'];
        
        $message = "Hi $student_name, you have a due amount of $due_amount.";
        $sms_numbers[] = [
            'to' => $student_number,
            'message' => $message
        ];

        // Calculate total SMS needed
        $totalSms += calculateTotalSms(mb_strlen($message));
    }

    // Validate and format numbers
    $validNumbers = validateAndFormatNumbers(array_column($sms_numbers, 'to'));

    // Filter SMS messages based on valid numbers
    $validSmsNumbers = array_filter($sms_numbers, function($sms) use ($validNumbers) {
        return in_array($sms['to'], $validNumbers);
    });

    // Send SMS only if there are valid numbers
    if (!empty($validSmsNumbers)) {
        $smsResponse = sms_send($validSmsNumbers, $totalSms, $serviceId, $conn);
        return $smsResponse; // You can handle the response as needed
    }
}

// Function to send SMS to multiple recipients
function sms_send($messages, $totalSms, $serviceId, $conn) {
    $url = "http://bulksmsbd.net/api/smsapimany";
    $api_key = "x6Cup2Oa7raosVD4kt29"; // Replace with your actual API key
    $senderid = "8809617612925"; // Replace with your actual sender ID

    $data = [
        "api_key" => $api_key,
        "senderid" => $senderid,
        "messages" => json_encode($messages)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    curl_close($ch);

    // Log each SMS and update credits
    foreach ($messages as $message) {
        logSms($message['message'], $message['to'], $totalSms, 'notification', $serviceId, $conn);
    }
    
    updateSmsCredits($totalSms, $serviceId, $conn); // Update SMS credits after sending

    return $response; // Return response from the SMS API
}

function updateSmsCredits($totalSms, $serviceId, $conn) {
    $updateCredit = "UPDATE services SET sms_credit = sms_credit - :total_sms WHERE service_id = :service_id";
    $stmt = $conn->prepare($updateCredit);
    $stmt->bindParam(":total_sms", $totalSms, PDO::PARAM_INT);
    $stmt->bindParam(":service_id", $serviceId, PDO::PARAM_INT);
    $stmt->execute();
}

function logSms($textInput, $number, $totalSms, $sms_type, $serviceId, $conn) {
    $insertSMS = "INSERT INTO sms (sms_text, receiver, sms_time, sms_type, used_credit, service_id) 
                  VALUES (:textInput, :number, NOW(), :sms_type, :totalSms, :service_id)";
    $stmt = $conn->prepare($insertSMS);
    $stmt->bindParam(":textInput", $textInput, PDO::PARAM_STR);
    $stmt->bindParam(":number", $number, PDO::PARAM_STR);
    $stmt->bindParam(":sms_type", $sms_type, PDO::PARAM_STR);
    $stmt->bindParam(":totalSms", $totalSms, PDO::PARAM_INT);
    $stmt->bindParam(":service_id", $serviceId, PDO::PARAM_INT);
    $stmt->execute();
}

// Function to calculate total SMS based on message length
function calculateTotalSms($length) {
    if ($length > 0 && $length <= 160) {
        return 1;
    } elseif ($length > 160 && $length <= 306) {
        return 2;
    } elseif ($length > 306 && $length <= 459) {
        return 3;
    } elseif ($length > 459 && $length <= 612) {
        return 4;
    } elseif ($length > 612 && $length <= 765) {
        return 5;
    } else {
        return false; // Exceeds maximum limit of 765 characters
    }
}

// Function to validate and format phone numbers
function validateAndFormatNumbers($numberArray) {
    $validNumbers = [];

    foreach ($numberArray as $number) {
        $number = trim($number);
        // Remove any non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Ensure the number starts with '8801' and is 13 digits long
        if (substr($number, 0, 4) !== '8801') {
            if (strlen($number) === 11 && substr($number, 0, 2) === '01') {
                // Prepend '880' if it starts with '01'
                $number = '88' . $number;
            } elseif (strlen($number) === 10 && substr($number, 0, 1) === '0') {
                // If it starts with '0', convert to '8801'
                $number = '88' . substr($number, 1);
            } else {
                continue; // Skip this number
           
            }}}}
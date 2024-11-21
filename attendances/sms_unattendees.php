<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

include '../check_auth_backend.php';
// Ensure that authentication is successful
if ($checkAuthMessage != 'success') {
    echo json_encode(['error' => $checkAuthMessage]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure the user is authenticated
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['service_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

    try {
        $service_id = $_SESSION['service_id'];
        $date = $_POST['date'];
        $course_id = $_POST['course_id'];
        $batch_id = $_POST['batch_id'];

        // Fetch all enrollments for the specified course_id and batch_id
        $enrollment_stmt = $conn->prepare("
            SELECT
                e.enrollment_id,
                e.student_id,
                e.student_index
            FROM
                enrollments e
            WHERE
                e.service_id = ?
                AND e.course_id = ?
                AND e.batch_id = ?
        ");
        $enrollment_stmt->execute([$service_id, $course_id, $batch_id]);
        $enrolled_students = $enrollment_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Extract student indexes for attendance check
        $student_indexes = array_column($enrolled_students, 'student_index');

        // If there are enrolled students, fetch their attendance records
        if (!empty($student_indexes)) {
            // Prepare the SQL query to get attendance records for the specified date
            $attendance_placeholders = implode(',', array_fill(0, count($student_indexes), '?'));
            $attendance_stmt = $conn->prepare("
                SELECT
                    a.student_index
                FROM
                    attendance a
                WHERE
                    a.service_id = ?
                    AND DATE(a.attendance_date) = ?
                    AND a.student_index IN ($attendance_placeholders)
            ");
            $attendance_stmt->execute(array_merge([$service_id, $date], $student_indexes));
            $attended_students = $attendance_stmt->fetchAll(PDO::FETCH_COLUMN);

            // Identify students who were not present
            $absent_students = array_filter($enrolled_students, function ($student) use ($attended_students) {
                return !in_array($student['student_index'], $attended_students);
            });

            // Fetch student numbers and names for absent students
            $absent_student_ids = array_column($absent_students, 'student_id');
            $absent_student_info = [];

            if (!empty($absent_student_ids)) {
                $student_number_placeholders = implode(',', array_fill(0, count($absent_student_ids), '?'));
                $student_number_stmt = $conn->prepare("
                    SELECT
                        student_id,
                        student_number,
                        student_name
                    FROM
                        students
                    WHERE
                        student_id IN ($student_number_placeholders)
                ");
                $student_number_stmt->execute($absent_student_ids);
                $student_numbers = $student_number_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Map student numbers and names to absent students
                foreach ($student_numbers as $student_number) {
                    $absent_student_info[$student_number['student_id']] = [
                        'student_number' => $student_number['student_number'],
                        'student_name' => $student_number['student_name']
                    ];
                }
            }

            // Include student numbers and names in the response
            $absent_students_with_info = array_map(function ($student) use ($absent_student_info) {
                $student_info = $absent_student_info[$student['student_id']] ?? ['student_number' => null, 'student_name' => null];
                $student['student_number'] = $student_info['student_number'];
                $student['student_name'] = $student_info['student_name'];
                return $student;
            }, $absent_students);

            // Prepare SMS messages for absent students
            $sms_data = [];
            foreach ($absent_students_with_info as $absent_student) {
                $sms = "{$absent_student['student_name']} was absent on {$date}";
                $sms_data[] = [
                    'student_number' => $absent_student['student_number'],
                    'message' => $sms
                ];
            }

            // Send SMS for absent students
            if (!empty($sms_data)) {
                sendAbsenteeSms($sms_data, $service_id, $conn);
            }

            // Send the response with absent students and their numbers
            echo json_encode([
                'success' => true,
                'absent_students' => $absent_students_with_info
            ]);
        } else {
            // No enrolled students found
            echo json_encode(['success' => true, 'absent_students' => []]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// Function to send SMS for absent students
function sendAbsenteeSms($response, $serviceId, $conn) {
    $sms_numbers = [];
    $totalSms = 0;

    foreach ($response as $data) {
        $student_number = $data['student_number'];
        $message = $data['message'];
        
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
            }
        }
        $validNumbers[] = $number; // Add valid number
    }

    return $validNumbers;
}
?>

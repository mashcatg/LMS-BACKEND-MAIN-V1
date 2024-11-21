<?php

include 'db.php';

function calculateSmsCost($text, $numbers, $sms_type) {
    global $conn;

    // Ensure the text only contains English characters
    if (!preg_match('/^[a-zA-Z0-9\s.,?!\'"-]+$/', $text)) {
        return json_encode(['error' => "The SMS must contain only English characters and can include numbers and punctuation."]);
    }

    // Split the numbers by comma
    $numberArray = explode(',', $numbers);
    $validNumbers = validateAndFormatNumbers($numberArray);

    if (empty($validNumbers)) {
        return json_encode(['error' => "No valid numbers provided."]);
    }

    $length = mb_strlen($text);
    $totalSms = calculateTotalSms($length);

    if ($totalSms === false) {
        return json_encode(['error' => "The SMS length exceeds the maximum limit of 440 characters."]);
    }

    $serviceId = $_SESSION['service_id'] ?? 61545;
    list($fetchCredits, $company_name) = fetchSmsCredits($serviceId, $conn);

    if ($fetchCredits === false || $fetchCredits < $totalSms) {
        return json_encode(['error' => "You don't have sufficient SMS Credit."]);
    }

    $smsText = $text . ' - ' . $company_name;

    // Send SMS to each valid number
    foreach ($validNumbers as $number) {
        $response = sms_send($smsText, $number, $totalSms, $serviceId, $sms_type);
        // if (json_decode($response, true)['error']) {
        //     return $response; // Stop and return error if any number fails
        // }
    }

    return json_encode(['success' => "Message sent successfully to all valid numbers!"]);
}

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

        // Check if the final number is 13 digits long
        if (strlen($number) === 13 && substr($number, 0, 4) === '8801') {
            $validNumbers[] = $number; // Add to valid numbers
        }
    }

    return $validNumbers; // Return the valid numbers
}

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


function fetchSmsCredits($serviceId, $conn) {
    $selectCredits = "SELECT company_name, sms_credit FROM services WHERE service_id = :service_id";
    $stmt = $conn->prepare($selectCredits);
    $stmt->bindParam(":service_id", $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    return [$data['sms_credit'] ?? false, $data['company_name'] ?? 'Unknown Company'];
}

function sms_send($textInput, $number, $totalSms, $serviceId, $sms_type) {
    global $conn;
    $url = "http://bulksmsbd.net/api/smsapi";
    $api_key = "x6Cup2Oa7raosVD4kt29";
    $senderid = "8809617612925";

    $data = [
        "api_key" => $api_key,
        "senderid" => $senderid,
        "number" => $number,
        "message" => $textInput
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        updateSmsCredits($totalSms, $serviceId, $conn);
        logSms($textInput, $number, $totalSms, $sms_type, $serviceId, $conn);
        return json_encode(['success' => "Message sent successfully to $number."]);
    } else {
        return json_encode(['error' => "Failed to send message to $number."]);
    }
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

?>

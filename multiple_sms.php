<?php

include 'db.php';

function calculateSmsCost($text, $numbers, $sms_type) {
    global $conn;

    // Check if the input contains at least one Bangla character
    if (!preg_match('/[\x{0980}-\x{09FF}]/u', $text) || !preg_match('/^[\x{0980}-\x{09FF}a-zA-Z0-9\s.,?!\'"-]+$/u', $text)) {
        return json_encode(['error' => "The SMS must contain Bangla characters and can only mix English, numbers, and punctuation."]);
    }

    // Split the numbers by comma
    $numberArray = explode(',', $numbers);
    $validNumbers = [];
    foreach ($numberArray as $number) {
        $number = trim($number);
        if (!validateNumber($number, $sms_type, $conn)) {
            return json_encode(['error' => "Invalid number: $number for the selected SMS type."]);
        }
        $validNumbers[] = $number;
    }

    $length = mb_strlen($text);
    $perSmsCost = 0.33;
    $totalSms = calculateTotalSms($length);

    if ($totalSms === false) {
        return json_encode(['error' => "The SMS length exceeds the maximum limit of 335 characters."]);
    }

    $serviceId = $_SESSION['service_id'];
    list($fetchCredits, $company_name) = fetchSmsCredits($serviceId, $conn);

    if ($fetchCredits === false || $fetchCredits < $totalSms) {
        return json_encode(['error' => "You don't have sufficient SMS Credit."]);
    }

    $smsText = $text . ' - ' . $company_name;

    // Send SMS to each valid number
    if (count($validNumbers) === 1) {
        // If only one valid number, use the single send function
        $number = $validNumbers[0];
        $response = sms_send($smsText, $number, $totalSms, $serviceId, $sms_type);
    } else {
        // If multiple valid numbers, use the multiple send function
        $response = sms_send_multiple($smsText, $validNumbers, $totalSms, $serviceId, $sms_type);
    }

    return $response;
}

function validateNumber($number, $sms_type, $conn) {
    $table = $sms_type === 'admin' ? 'admins' : 'students';
    $column = $sms_type === 'admin' ? 'admin_number' : 'student_number';

    $stmt = $conn->prepare("SELECT 1 FROM $table WHERE $column = :number");
    $stmt->bindParam(':number', $number, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->fetchColumn() !== false;
}

function calculateTotalSms($length) {
    if ($length > 0 && $length <= 69) {
        return 1;
    } elseif ($length >= 70 && $length <= 133) {
        return 2;
    } elseif ($length >= 134 && $length <= 200) {
        return 3;
    } elseif ($length > 200 && $length <= 335) {
        return 5;
    } else {
        return false;
    }
}

function fetchSmsCredits($serviceId, $conn) {
    $selectAdmins = "SELECT company_name, sms_credit FROM services WHERE service_id = :service_id";
    $stmt = $conn->prepare($selectAdmins);
    $stmt->bindParam(":service_id", $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    return [$data['sms_credit'] ?? false, $data['company_name'] ?? 'Unknown Company'];
}

function sms_send($textInput, $number, $totalSms, $serviceId, $sms_type) {
    global $conn;
    $url = "";
    $api_key = ""; // Your API key
    $senderid = ""; // Your sender ID

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

function sms_send_multiple($textInput, $numbers, $totalSms, $serviceId, $sms_type) {
    global $conn;
    $url = "http://bulksmsbd.net/api/smsapimany";
    $api_key = "x6Cup2Oa7raosVD4kt29"; // Your API key
    $senderid = "8809617612925"; // Your sender ID

    // Prepare messages array
    $messages = [];
    foreach ($numbers as $number) {
        $messages[] = [
            "to" => $number,
            "message" => $textInput
        ];
    }

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

    if ($response) {
        updateSmsCredits($totalSms, $serviceId, $conn);
        foreach ($numbers as $number) {
            logSms($textInput, $number, $totalSms, $sms_type, $serviceId, $conn);
        }
        return json_encode(['success' => "Messages sent successfully to all valid numbers."]);
    } else {
        return json_encode(['error' => "Failed to send messages."]);
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

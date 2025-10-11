<?php
include_once(__DIR__.'/../config/sms_config.php');
include_once(__DIR__.'/../config/db.php');

function sendSMS($user_id, $phone, $message) {
    $ch = curl_init();
    $params = [
        '1' => $phone,
        '2' => $message,
        '3' => ITEXMO_API_CODE,
        'passwd' => ITEXMO_API_PASSWORD
    ];
    
    curl_setopt($ch, CURLOPT_URL, ITEXMO_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifications_log (user_id, recipient_number, message, status, response) VALUES (?, ?, ?, ?, ?)");
    $status = (strpos($response, 'OK') !== false) ? 'sent' : 'failed';
    $stmt->bind_param("issss", $user_id, $phone, $message, $status, $response);
    $stmt->execute();
}
?>

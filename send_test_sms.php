<?php
// Simple SMS test - will actually send SMS
require_once 'config/sms_config.php';

echo "<h2>SMS Test Script</h2>";

// Your test phone number
$test_phone = '09158386852';
$test_message = "Test SMS from your system - " . date('Y-m-d H:i:s');

// Convert phone to correct format
$phone_clean = preg_replace('/[^0-9]/', '', $test_phone);

echo "Original: $test_phone<br>";
echo "Cleaned: $phone_clean<br>";

// Convert to PhilSMS format
if (substr($phone_clean, 0, 2) === '09' && strlen($phone_clean) === 11) {
    $final_phone = '63' . substr($phone_clean, 1);
    echo "Converted to: $final_phone<br>";
} elseif (substr($phone_clean, 0, 2) === '63' && strlen($phone_clean) === 12) {
    $final_phone = $phone_clean;
    echo "Already correct: $final_phone<br>";
} elseif (substr($phone_clean, 0, 1) === '9' && strlen($phone_clean) === 10) {
    $final_phone = '63' . $phone_clean;
    echo "Converted to: $final_phone<br>";
} else {
    die("Invalid phone format!");
}

echo "Message: $test_message<br>";
echo "API Key: " . substr(PHILSMS_API_KEY, 0, 10) . "...<br>";
echo "Sender ID: " . SMS_SENDER_ID . "<br><br>";

// Prepare API request
$data = [
    'recipient' => $final_phone,
    'message' => $test_message,
    'type' => 'plain',
    'sender_id' => SMS_SENDER_ID
];

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . PHILSMS_API_KEY
];

$ch = curl_init(PHILSMS_SMS_ENDPOINT);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Sending to PhilSMS...<br>";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<hr><h3>Results:</h3>";
echo "HTTP Code: $httpCode<br>";
echo "Response: " . htmlspecialchars($response) . "<br>";

$responseData = json_decode($response, true);

if ($httpCode == 200 || $httpCode == 201) {
    echo "<h3 style='color: green;'>✓ SMS sent successfully!</h3>";
    if (isset($responseData['data']['message_id'])) {
        echo "Message ID: " . $responseData['data']['message_id'] . "<br>";
    }
    echo "Check your phone for the message.";
} else {
    echo "<h3 style='color: red;'>✗ SMS failed</h3>";
    if (isset($responseData['message'])) {
        echo "Error: " . $responseData['message'] . "<br>";
    }
}
?>
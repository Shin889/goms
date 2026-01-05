<?php
// test_philsms_correct_sender.php

$api_key = '815|tpwc8LfPn1tE3WjdiDih1eezOp0nczuz4Oc93jSM5c75922a';
$endpoint = 'https://dashboard.philsms.com/api/v3/sms/send';

$phone = '09158386852'; // Format from their error
$sender_id = 'PhilSMS'; // Exactly as shown in your dashboard

echo "<h2>Testing with CORRECT Sender ID: $sender_id</h2>";
echo "<pre>";

$data = [
    'recipient' => $phone,
    'message' => 'Test with correct Sender ID: PhilSMS at ' . date('H:i:s'),
    'sender_id' => $sender_id
];

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
];

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Data sent: " . json_encode($data) . "\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

$responseData = json_decode($response, true);
if ($responseData) {
    if (isset($responseData['status']) && $responseData['status'] === 'success') {
        echo "\n✅ SUCCESS! SMS should be sent!\n";
        echo "Sender ID: $sender_id works!\n";
        echo "Phone format: $phone works!\n";
    } else {
        echo "\nError details:\n";
        print_r($responseData);
    }
}

echo "\n=== Next Steps ===\n";
echo "1. If success: Update SMS_SENDER_ID to 'PhilSMS' in sms_config.php\n";
echo "2. Update cleanPhoneNumber() to output: 9158386852 (no 0)\n";
echo "3. Test with your actual application\n";
echo "</pre>";
?>
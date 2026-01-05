<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Complete SMS Debugging Test</h2>";
echo "<pre>";

// Test the cleanPhoneNumber function directly
function testCleanPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if phone number is valid Philippine format
    if (strlen($phone) < 10 || strlen($phone) > 13) {
        return false;
    }
    
    // Target format: 09158386852
    
    // Case 1: Already in 0915 format
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
        return $phone;
    }
    
    // Case 2: 639... format (12 digits)
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
        return '0' . substr($phone, 2);
    }
    
    // Case 3: +639... (13 digits after removing +)
    if (strlen($phone) === 13 && substr($phone, 0, 2) === '63') {
        return '0' . substr($phone, 2);
    }
    
    // Case 4: 9xxxxxxxxx (10 digits)
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
        return '0' . $phone;
    }
    
    return false;
}

// Test various phone formats
echo "=== Phone Number Format Testing ===\n";
$test_numbers = [
    '09158386852' => '09158386852',
    '+639158386852' => '09158386852',
    '639158386852' => '09158386852',
    '9158386852' => '09158386852',
    '0915-838-6852' => '09158386852',
    '0915 838 6852' => '09158386852',
    '+63 915 838 6852' => '09158386852',
];

foreach ($test_numbers as $input => $expected) {
    $result = testCleanPhoneNumber($input);
    $status = ($result === $expected) ? "✓" : "✗";
    echo sprintf("%-20s -> %-15s %s\n", $input, ($result ?: "INVALID"), $status);
}

// Direct PhilSMS API Test
echo "\n=== Direct PhilSMS API Test ===\n";

$api_key = '815|tpwc8LfPn1tE3WjdiDih1eezOp0nczuz4Oc93jSM5c75922a';
$endpoint = 'https://dashboard.philsms.com/api/v3/sms/send';
$phone = '09158386852'; // CHANGE THIS TO YOUR ACTUAL NUMBER
$message = "GOMS Test SMS: System check at " . date('Y-m-d H:i:s');

$data = [
    'recipient' => $phone,
    'message' => $message,
    'sender_id' => 'PhilSMS'  // Must be PhilSMS
];

echo "Phone: $phone\n";
echo "Message: $message\n";
echo "Sender ID: PhilSMS\n";
echo "Data being sent: " . json_encode($data) . "\n";

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true); // For detailed output

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Get verbose output
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

curl_close($ch);

echo "\n=== CURL Verbose Output ===\n";
echo $verboseLog;

echo "\n=== API Response ===\n";
echo "HTTP Code: $httpCode\n";
echo "CURL Error: " . ($curlError ? $curlError : "None") . "\n";
echo "Response: " . $response . "\n";

// Try to decode JSON response
$response_data = json_decode($response, true);
if ($response_data) {
    echo "\n=== Parsed Response ===\n";
    print_r($response_data);
    
    // Check for common errors
    if (isset($response_data['error'])) {
        echo "\n=== ERROR DETECTED ===\n";
        echo "Error: " . $response_data['error'] . "\n";
        if (isset($response_data['message'])) {
            echo "Message: " . $response_data['message'] . "\n";
        }
    }
    
    // Check for success
    if (isset($response_data['status']) && $response_data['status'] === 'success') {
        echo "\n=== SUCCESS! ===\n";
        echo "Message should be delivered (may have delay)\n";
    }
}

echo "</pre>";

// Additional troubleshooting
echo "<h3>Troubleshooting Tips:</h3>";
echo "<ol>";
echo "<li>Make sure the phone number is correct: $phone</li>";
echo "<li>Check PhilSMS dashboard for balance and sender ID status</li>";
echo "<li>Verify API key is active</li>";
echo "<li>Check if phone carrier is supported by PhilSMS</li>";
echo "<li>Look for SMS in spam folder (if applicable)</li>";
echo "</ol>";

// Check if we should test without sender_id
if (isset($_GET['test_no_sender']) && $_GET['test_no_sender'] == '1') {
    echo "<h3>Testing without sender_id...</h3>";
    echo "<a href='test_sms_complete.php'>Test with sender_id</a>";
} else {
    echo "<h3>Try testing without sender_id:</h3>";
    echo "<a href='test_sms_complete.php?test_no_sender=1'>Test without sender_id</a>";
}
?>
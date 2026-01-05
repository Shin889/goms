<?php
// test_philsms_no_sender.php

$api_key = '815|tpwc8LfPn1tE3WjdiDih1eezOp0nczuz4Oc93jSM5c75922a';
$endpoint = 'https://dashboard.philsms.com/api/v3/sms/send';

echo "<h2>Testing WITHOUT sender_id</h2>";
echo "<pre>";

$test_cases = [
    [
        'phone' => '9158386852', // Without 0
        'message' => 'Test without sender_id format 1'
    ],
    [
        'phone' => '09158386852', // With 0
        'message' => 'Test without sender_id format 2'
    ],
    [
        'phone' => '639158386852', // With 63
        'message' => 'Test without sender_id format 3'
    ],
];

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
];

foreach ($test_cases as $i => $test) {
    echo "\n=== Test Case " . ($i + 1) . ": " . $test['phone'] . " ===\n";
    
    // Try WITHOUT sender_id
    $data1 = [
        'recipient' => $test['phone'],
        'message' => $test['message']
    ];
    
    // Try WITH empty sender_id
    $data2 = [
        'recipient' => $test['phone'],
        'message' => $test['message'],
        'sender_id' => ''
    ];
    
    // Try WITH 'SMS' as sender_id (sometimes default)
    $data3 = [
        'recipient' => $test['phone'],
        'message' => $test['message'],
        'sender_id' => 'SMS'
    ];
    
    $attempts = [$data1, $data2, $data3];
    
    foreach ($attempts as $attempt_num => $data) {
        echo "\nAttempt " . ($attempt_num + 1) . ": ";
        echo json_encode($data) . "\n";
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Code: $httpCode\n";
        echo "Response: " . $response . "\n";
        
        $responseData = json_decode($response, true);
        if ($responseData && isset($responseData['message'])) {
            echo "Message: " . $responseData['message'] . "\n";
            
            // If it's about sender_id, note it
            if (strpos(strtolower($responseData['message']), 'sender') !== false) {
                echo "⚠️ Sender ID issue detected!\n";
            }
        }
        
        // If success, break
        if ($httpCode == 200 || $httpCode == 201) {
            echo "✅ SUCCESS with this format!\n";
            break 2; // Exit both loops
        }
        
        sleep(1); // Wait 1 second between attempts
    }
}

echo "\n=== ANALYSIS ===\n";
echo "If all attempts fail with same error:\n";
echo "1. Account may need activation\n";
echo "2. May need to load credits\n";
echo "3. Phone number might be blocked/invalid\n";
echo "</pre>";
?>
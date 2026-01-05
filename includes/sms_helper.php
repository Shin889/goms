<?php
require_once __DIR__.'/../config/sms_config.php';
require_once __DIR__.'/../config/db.php';

function sendSMS($user_id, $phone, $message) {
    global $conn;

    error_log("sendSMS called with original phone: $phone");
    
    // Clean phone number
    $phone = cleanPhoneNumber($phone);

    error_log("sendSMS cleaned phone: $phone");
    
    if (!$phone) {
        logSMS($user_id, $phone, $message, 'failed', 'Invalid phone number format');
        return false;
    }
    
    // Check if SMS is enabled
    if (!SMS_ENABLED) {
        logSMS($user_id, $phone, $message, 'disabled', 'SMS service is disabled');
        return false;
    }
    
    // Test mode check
    if (SMS_TEST_MODE) {
        logSMS($user_id, $phone, $message, 'test_mode', 'SMS test mode - not sent');
        return true;
    }
    
    // Send via PhilSMS
    $result = sendSMSViaPhilSMS($phone, $message);
    logSMS($user_id, $phone, $message, $result['status'], $result['provider_msg']);
    
    return $result['status'] === 'sent';
}

function sendSMSViaPhilSMS($phone, $message) {
    // Check if API key is configured
    if (!defined('PHILSMS_API_KEY') || PHILSMS_API_KEY === '') {
        return [
            'success' => false,
            'status' => 'failed',
            'provider_msg' => 'PhilSMS API key not configured'
        ];
    }
    
    // Log the attempt
    error_log("Attempting to send SMS to: " . substr($phone, 0, 3) . "****" . substr($phone, -3));
    error_log("Message preview: " . substr($message, 0, 50) . "...");
    
    // Prepare data for PhilSMS API
    $data = [
        'recipient' => $phone,  
        'message' => $message,
        'type' => 'plain'
    ];

    // Add sender_id - MUST BE "PhilSMS" if required
    if (defined('SMS_SENDER_ID') && SMS_SENDER_ID) {
        $data['sender_id'] = SMS_SENDER_ID;
        error_log("Using sender_id: " . SMS_SENDER_ID);
    } else {
        error_log("No sender_id defined, using default");
    }

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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log full response for debugging
    error_log("PhilSMS Response - HTTP Code: $httpCode");
    error_log("PhilSMS Response Body: " . $response);
    
    if ($curlError) {
        error_log("CURL Error: $curlError");
        return [
            'success' => false,
            'status' => 'failed',
            'provider_msg' => "CURL Error: $curlError"
        ];
    }
    
    $responseData = json_decode($response, true);
    
    // Check for success
    if ($httpCode == 200 || $httpCode == 201) {
        // Multiple success formats
        if (isset($responseData['status']) && $responseData['status'] === 'success') {
            $msg_id = isset($responseData['data']['message_id']) ? $responseData['data']['message_id'] : 
                     (isset($responseData['message_id']) ? $responseData['message_id'] : 'unknown');
            error_log("SMS sent successfully! Message ID: $msg_id");
            return [
                'success' => true,
                'status' => 'sent',
                'provider_msg' => $msg_id
            ];
        }
        elseif (isset($responseData['success']) && $responseData['success'] === true) {
            $msg_id = isset($responseData['message_id']) ? $responseData['message_id'] : 'unknown';
            error_log("SMS sent successfully! Message ID: $msg_id");
            return [
                'success' => true,
                'status' => 'sent',
                'provider_msg' => $msg_id
            ];
        }
    }
    
    // Handle errors
    $errorMsg = "Unknown error";
    if (isset($responseData['message'])) {
        $errorMsg = $responseData['message'];
    } elseif (isset($responseData['error'])) {
        $errorMsg = $responseData['error'];
    }
    
    error_log("SMS failed: HTTP $httpCode - $errorMsg");
    return [
        'success' => false,
        'status' => 'failed',
        'provider_msg' => "HTTP $httpCode: $errorMsg"
    ];
}

function cleanPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if phone number is valid Philippine format
    if (strlen($phone) < 10 || strlen($phone) > 13) {
        return false;
    }
    
    // PhilSMS requires: 639158386852 format (NOT 0915...)
    
    // If starts with 0 (09158386852), remove 0 and add 63
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
        return '63' . substr($phone, 1);  // Converts 09158386852 → 639158386852
    }
    
    // If starts with 63 (639158386852), keep as is
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '63') {
        return $phone;
    }
    
    // If 10 digits starting with 9 (9158386852), add 63
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
        return '63' . $phone;  // Converts 9158386852 → 639158386852
    }
    
    // If 13 digits (+639158386852 with + removed), remove + (already done)
    if (strlen($phone) === 13 && substr($phone, 0, 2) === '63') {
        return $phone;  // Already 639158386852
    }
    
    // If 12 digits but starts with 9 (unlikely), add 63
    if (strlen($phone) === 12 && substr($phone, 0, 1) === '9') {
        return '63' . $phone;
    }
    
    return false;
}

function logSMS($user_id, $phone, $message, $status, $provider_msg = '') {
    global $conn;
    
    // Mask phone number for privacy
    $maskedPhone = '';
    if (strlen($phone) > 6) {
        $maskedPhone = substr($phone, 0, 3) . '****' . substr($phone, -3);
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications_log 
        (notification_type, recipient_user_id, recipient_phone, message, status, provider_msg_id, error_message, created_at) 
        VALUES ('sms', ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $error_msg = ($status === 'failed') ? $provider_msg : null;
    $provider_id = ($status === 'sent') ? $provider_msg : null;
    
    $stmt->bind_param("isssss", $user_id, $maskedPhone, $message, $status, $provider_id, $error_msg);
    $stmt->execute();
    
    return $stmt->insert_id;
}

// Function to send appointment reminders
function sendAppointmentReminders() {
    global $conn;
    
    $now = date('Y-m-d H:i:s');
    
    // 24-hour reminders (appointments starting in 24-25 hours)
    $day_later = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $day_later_plus = date('Y-m-d H:i:s', strtotime('+25 hours'));
    
    // 1-hour reminders (appointments starting in 1-1.5 hours)
    $hour_later = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $hour_later_plus = date('Y-m-d H:i:s', strtotime('+1.5 hours'));
    
    // Get appointments needing 24-hour reminders
    $stmt = $conn->prepare("
        SELECT a.id, a.student_id, a.counselor_id, a.start_time,
               s.first_name, s.last_name,
               c.name as counselor_name,
               g.user_id as guardian_user_id, g2.phone as guardian_phone,
               adv.user_id as adviser_user_id, u.phone as adviser_phone
        FROM appointments a
        JOIN students s ON a.student_id = s.id
        JOIN counselors c ON a.counselor_id = c.id
        LEFT JOIN student_guardians sg ON s.id = sg.student_id AND sg.primary_guardian = 1
        LEFT JOIN guardians g ON sg.guardian_id = g.id
        LEFT JOIN users g2 ON g.user_id = g2.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN advisers adv ON sec.adviser_id = adv.id
        LEFT JOIN users u ON adv.user_id = u.id
        WHERE a.status = 'scheduled' 
        AND a.start_time BETWEEN ? AND ?
        AND a.reminder_sent_24h = 0
    ");
    $stmt->bind_param("ss", $day_later, $day_later_plus);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sent_24h = 0;
    while ($appointment = $result->fetch_assoc()) {
        $student_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $counselor_name = $appointment['counselor_name'];
        $time = date('h:i A', strtotime($appointment['start_time']));
        
        // Use template if defined
        if (defined('SMS_REMINDER_24H_TEMPLATE')) {
            $message = str_replace(
                ['[StudentName]', '[CounselorName]', '[Time]'],
                [$student_name, $counselor_name, $time],
                SMS_REMINDER_24H_TEMPLATE
            );
        } else {
            $message = "Reminder: {$student_name} has a counseling session tomorrow at {$time} with {$counselor_name}.";
        }
        
        // Send to guardian
        if ($appointment['guardian_phone']) {
            sendSMS($appointment['guardian_user_id'], $appointment['guardian_phone'], $message);
        }
        
        // Send to adviser
        if ($appointment['adviser_phone']) {
            sendSMS($appointment['adviser_user_id'], $appointment['adviser_phone'], $message);
        }
        
        // Mark as sent
        $update_stmt = $conn->prepare("UPDATE appointments SET reminder_sent_24h = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $appointment['id']);
        $update_stmt->execute();
        
        $sent_24h++;
    }
    
    // Get appointments needing 1-hour reminders
    $stmt = $conn->prepare("
        SELECT a.id, a.student_id, a.counselor_id, a.start_time,
               s.first_name, s.last_name,
               c.name as counselor_name,
               g.user_id as guardian_user_id, g2.phone as guardian_phone,
               adv.user_id as adviser_user_id, u.phone as adviser_phone
        FROM appointments a
        JOIN students s ON a.student_id = s.id
        JOIN counselors c ON a.counselor_id = c.id
        LEFT JOIN student_guardians sg ON s.id = sg.student_id AND sg.primary_guardian = 1
        LEFT JOIN guardians g ON sg.guardian_id = g.id
        LEFT JOIN users g2 ON g.user_id = g2.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN advisers adv ON sec.adviser_id = adv.id
        LEFT JOIN users u ON adv.user_id = u.id
        WHERE a.status = 'scheduled' 
        AND a.start_time BETWEEN ? AND ?
        AND a.reminder_sent_1h = 0
    ");
    $stmt->bind_param("ss", $hour_later, $hour_later_plus);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sent_1h = 0;
    while ($appointment = $result->fetch_assoc()) {
        $student_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $time = date('h:i A', strtotime($appointment['start_time']));
        
        // Use template if defined
        if (defined('SMS_REMINDER_1H_TEMPLATE')) {
            $message = str_replace(
                ['[StudentName]', '[Time]'],
                [$student_name, $time],
                SMS_REMINDER_1H_TEMPLATE
            );
        } else {
            $message = "Reminder: {$student_name} has a counseling session in 1 hour at {$time}.";
        }
        
        // Send to guardian
        if ($appointment['guardian_phone']) {
            sendSMS($appointment['guardian_user_id'], $appointment['guardian_phone'], $message);
        }
        
        // Send to adviser
        if ($appointment['adviser_phone']) {
            sendSMS($appointment['adviser_user_id'], $appointment['adviser_phone'], $message);
        }
        
        // Mark as sent
        $update_stmt = $conn->prepare("UPDATE appointments SET reminder_sent_1h = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $appointment['id']);
        $update_stmt->execute();
        
        $sent_1h++;
    }
    
    return ['24h' => $sent_24h, '1h' => $sent_1h];
}

// REMOVE OR COMMENT OUT THIS DUPLICATE FUNCTION - It exists in functions.php
/*
function sendAppointmentNotification($appointment_id, $notification_type) {
    global $conn;
    
    // Get appointment details
    $stmt = $conn->prepare("
        SELECT a.*, s.first_name, s.last_name, c.name as counselor_name,
               g.user_id as guardian_user_id, u.phone as guardian_phone
        FROM appointments a
        JOIN students s ON a.student_id = s.id
        JOIN counselors c ON a.counselor_id = c.id
        LEFT JOIN student_guardians sg ON s.id = sg.student_id AND sg.primary_guardian = 1
        LEFT JOIN guardians g ON sg.guardian_id = g.id
        LEFT JOIN users u ON g.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($appointment = $result->fetch_assoc()) {
        $student_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $counselor_name = $appointment['counselor_name'];
        $date = date('F j, Y', strtotime($appointment['start_time']));
        $time = date('h:i A', strtotime($appointment['start_time']));
        
        // Determine which template to use
        switch($notification_type) {
            case 'booking':
                $template = defined('SMS_BOOKING_TEMPLATE') ? SMS_BOOKING_TEMPLATE : '';
                $brief_concern = substr($appointment['concern'] ?? 'Not specified', 0, 50);
                $message = str_replace(
                    ['[StudentName]', '[CounselorName]', '[Date]', '[Time]', '[BriefConcern]'],
                    [$student_name, $counselor_name, $date, $time, $brief_concern],
                    $template
                );
                break;
                
            case 'reschedule':
                $template = defined('SMS_RESCHEDULE_TEMPLATE') ? SMS_RESCHEDULE_TEMPLATE : '';
                $message = str_replace(
                    ['[StudentName]', '[Date]', '[Time]'],
                    [$student_name, $date, $time],
                    $template
                );
                break;
                
            case 'cancellation':
                $template = defined('SMS_CANCELLATION_TEMPLATE') ? SMS_CANCELLATION_TEMPLATE : '';
                $message = str_replace(
                    ['[StudentName]', '[Date]', '[Time]'],
                    [$student_name, $date, $time],
                    $template
                );
                break;
                
            default:
                return false;
        }
        
        // Send to guardian
        if ($appointment['guardian_phone']) {
            return sendSMS($appointment['guardian_user_id'], $appointment['guardian_phone'], $message);
        }
    }
    
    return false;
}
*/

// Function to send SMS with retry mechanism
function sendSMSWithRetry($user_id, $phone, $message, $max_retries = 2) {
    $attempts = 0;
    $result = false;
    
    while ($attempts <= $max_retries && !$result) {
        $attempts++;
        
        if ($attempts > 1) {
            error_log("Retry attempt $attempts for SMS to $phone");
            sleep(1); // Wait 1 second between retries
        }
        
        $result = sendSMS($user_id, $phone, $message);
        
        if (!$result) {
            error_log("SMS attempt $attempts failed");
        }
    }
    
    return $result;
}
?>
<?php
require_once __DIR__.'/../config/sms_config.php';
require_once __DIR__.'/../config/db.php';

function sendSMS($user_id, $phone, $message) {
    global $conn;
    
    // Clean phone number
    $phone = cleanPhoneNumber($phone);
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
    
    // Prepare data for PhilSMS API
    // Try different parameter combinations based on common SMS API patterns
    $data_attempts = [
        // Attempt 1: Most common format
        [
            'recipient' => $phone,
            'message' => $message,
            'sender_id' => defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'GOMS'
        ],
        // Attempt 2: Alternative format
        [
            'to' => $phone,
            'text' => $message,
            'from' => defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'GOMS'
        ],
        // Attempt 3: Simple format
        [
            'phone' => $phone,
            'message' => $message
        ]
    ];
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . PHILSMS_API_KEY
    ];
    
    foreach ($data_attempts as $attempt => $data) {
        error_log("PhilSMS Attempt #" . ($attempt + 1) . " with data: " . json_encode($data));
        
        $ch = curl_init(PHILSMS_SMS_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true); // Get headers
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Parse response
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response_headers = substr($response, 0, $header_size);
        $response_body = substr($response, $header_size);
        
        error_log("PhilSMS Attempt #" . ($attempt + 1) . " - HTTP Code: $httpCode");
        error_log("PhilSMS Attempt #" . ($attempt + 1) . " - Response: " . $response_body);
        
        if ($curlError) {
            error_log("PhilSMS Attempt #" . ($attempt + 1) . " - CURL Error: $curlError");
            continue; // Try next data format
        }
        
        $responseData = json_decode($response_body, true);
        
        if ($httpCode == 200 || $httpCode == 201) {
            // Check various success response formats
            if (isset($responseData['status']) && $responseData['status'] === 'success') {
                return [
                    'success' => true,
                    'status' => 'sent',
                    'provider_msg' => isset($responseData['data']['message_id']) ? 
                                     $responseData['data']['message_id'] : 
                                     'Sent via PhilSMS'
                ];
            }
            // Alternative success format
            elseif (isset($responseData['success']) && $responseData['success'] === true) {
                return [
                    'success' => true,
                    'status' => 'sent',
                    'provider_msg' => isset($responseData['message_id']) ? 
                                     $responseData['message_id'] : 
                                     'Sent via PhilSMS'
                ];
            }
        }
        
        // If we got a specific error message, log it
        if (isset($responseData['message'])) {
            error_log("PhilSMS Error: " . $responseData['message']);
        }
        
        // Wait a bit before next attempt
        if ($attempt < count($data_attempts) - 1) {
            usleep(100000); // 100ms delay
        }
    }
    
    // If all attempts failed
    return [
        'success' => false,
        'status' => 'failed',
        'provider_msg' => "All attempts failed. Last HTTP Code: $httpCode"
    ];
}

function cleanPhoneNumber($phone) {
    // Remove all non-numeric characters except plus sign
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Convert to international format for PhilSMS
    // Philippine numbers: 09171234567 -> +639171234567
    
    // If starts with 09, convert to +639
    if (preg_match('/^09(\d{9})$/', $phone, $matches)) {
        return '+639' . $matches[1];
    }
    
    // If starts with 9 and 10 digits, add +63
    if (preg_match('/^9(\d{9})$/', $phone, $matches)) {
        return '+639' . $matches[1];
    }
    
    // If starts with +63, keep as is
    if (preg_match('/^\+63\d{10}$/', $phone)) {
        return $phone;
    }
    
    // If 10 digits without +, assume Philippine number
    if (preg_match('/^(\d{10})$/', $phone, $matches)) {
        return '+63' . $matches[1];
    }
    
    // If already in +639 format
    if (preg_match('/^\+639\d{9}$/', $phone)) {
        return $phone;
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

// New function to send appointment notifications with templates
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
?>
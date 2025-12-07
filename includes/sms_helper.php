<?php
// includes/sms_helper.php
require_once __DIR__.'/../config/sms_config.php';
require_once __DIR__.'/../config/db.php';

function sendSMS($user_id, $phone, $message) {
    global $conn;
    
    // Remove non-numeric characters from phone number
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if phone number is valid Philippine format
    if (strlen($phone) < 10 || strlen($phone) > 13) {
        logSMS($user_id, $phone, $message, 'failed', 'Invalid phone number format');
        return false;
    }
    
    // Add +63 prefix if missing
    if (substr($phone, 0, 2) === '09') {
        $phone = '63' . substr($phone, 1);
    }
    
    // Check SMS configuration
    if (!defined('ITEXMO_API_CODE') || ITEXMO_API_CODE === 'YOUR_ITEXMO_API_CODE') {
        // Log but don't actually send (development mode)
        logSMS($user_id, $phone, $message, 'test_mode', 'SMS API not configured - test mode');
        return true; // Return true in test mode
    }
    
    // Prepare parameters
    $params = [
        '1' => $phone,
        '2' => $message,
        '3' => ITEXMO_API_CODE,
        'passwd' => ITEXMO_API_PASSWORD
    ];
    
    // Send SMS via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, ITEXMO_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development, remove in production
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Determine status
    if ($error) {
        $status = 'failed';
        $provider_msg = $error;
    } elseif (strpos($response, '0') === 0) { // iTexMo returns 0 for success
        $status = 'sent';
        $provider_msg = $response;
    } else {
        $status = 'failed';
        $provider_msg = $response;
    }
    
    // Log the SMS attempt
    logSMS($user_id, $phone, $message, $status, $provider_msg);
    
    return $status === 'sent';
}

function logSMS($user_id, $phone, $message, $status, $provider_msg = '') {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO notifications_log 
        (notification_type, recipient_user_id, recipient_phone, message, status, provider_msg_id, error_message) 
        VALUES ('sms', ?, ?, ?, ?, ?, ?)
    ");
    
    $error_msg = ($status === 'failed') ? $provider_msg : null;
    $provider_id = ($status === 'sent') ? $provider_msg : null;
    
    $stmt->bind_param("isssss", $user_id, $phone, $message, $status, $provider_id, $error_msg);
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
        
        // Send to guardian
        if ($appointment['guardian_phone']) {
            $message = "Reminder: {$student_name} has a counseling session tomorrow at {$time} with {$counselor_name}.";
            sendSMS($appointment['guardian_user_id'], $appointment['guardian_phone'], $message);
        }
        
        // Send to adviser
        if ($appointment['adviser_phone']) {
            $message = "Reminder: Your student {$student_name} has a counseling session tomorrow at {$time}.";
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
        
        // Send to guardian
        if ($appointment['guardian_phone']) {
            $message = "Reminder: {$student_name} has a counseling session in 1 hour at {$time}.";
            sendSMS($appointment['guardian_user_id'], $appointment['guardian_phone'], $message);
        }
        
        // Send to adviser
        if ($appointment['adviser_phone']) {
            $message = "Reminder: Your student {$student_name} has a counseling session in 1 hour at {$time}.";
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
?>
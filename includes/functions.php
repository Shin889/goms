<?php
// includes/functions.php
require_once __DIR__ . '/../config/db.php';

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'];
}

function logAction($user_id, $action, $action_summary, $target_table, $target_id = null, $old_values = null, $new_values = null) {
    global $conn;
    
    $ip = getUserIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Convert values to JSON if they're arrays/objects
    $old_json = is_array($old_values) ? json_encode($old_values) : $old_values;
    $new_json = is_array($new_values) ? json_encode($new_values) : $new_values;
    
    // Create details JSON
    $details = json_encode(['user_agent' => $user_agent]);
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, action_summary, target_table, target_id, old_values, new_values, ip_address, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssissss", $user_id, $action, $action_summary, $target_table, $target_id, $old_json, $new_json, $ip, $details);
    return $stmt->execute();
}

// Helper function to generate unique appointment code
function generateAppointmentCode() {
    global $conn;
    
    $year = date('Y');
    $prefix = "APT-{$year}-";
    
    // Get the last appointment code for this year
    $stmt = $conn->prepare("SELECT appointment_code FROM appointments WHERE appointment_code LIKE ? ORDER BY id DESC LIMIT 1");
    $like_pattern = $prefix . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_code = $result->fetch_assoc()['appointment_code'];
        $last_num = intval(substr($last_code, strlen($prefix)));
        $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $next_num = '0001';
    }
    
    return $prefix . $next_num;
}

// Function to send notifications to both guardian and adviser
function sendAppointmentNotification($appointment_id, $event_type) {
    global $conn;
    
    // Get appointment details
    $stmt = $conn->prepare("
        SELECT a.*, 
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
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    
    if (!$appointment) return false;
    
    // Prepare message templates
    $student_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
    $counselor_name = $appointment['counselor_name'];
    $date = date('M d, Y', strtotime($appointment['start_time']));
    $time = date('h:i A', strtotime($appointment['start_time']));
    
    $messages = [];
    
    // Guardian message
    if ($appointment['guardian_phone']) {
        switch ($event_type) {
            case 'booking':
                $message = "GOMS: Appointment booked for {$student_name} with Counselor {$counselor_name} on {$date} at {$time}. Reply STOP to opt out.";
                break;
            case 'reschedule':
                $message = "GOMS: Appointment for {$student_name} rescheduled to {$date} at {$time}.";
                break;
            case 'cancellation':
                $message = "GOMS: Appointment for {$student_name} on {$date} at {$time} has been cancelled.";
                break;
            default:
                $message = "GOMS: Appointment update for {$student_name} on {$date} at {$time}.";
        }
        $messages[] = [
            'phone' => $appointment['guardian_phone'],
            'user_id' => $appointment['guardian_user_id'],
            'message' => $message
        ];
    }
    
    // Adviser message
    if ($appointment['adviser_phone']) {
        $message = "GOMS: Appointment for your student {$student_name} has been {$event_type}. Date: {$date}, Time: {$time}, Counselor: {$counselor_name}.";
        $messages[] = [
            'phone' => $appointment['adviser_phone'],
            'user_id' => $appointment['adviser_user_id'],
            'message' => $message
        ];
    }
    
    // Send messages
    require_once __DIR__ . '/sms_helper.php';
    $results = [];
    
    foreach ($messages as $msg) {
        $results[] = sendSMS($msg['user_id'], $msg['phone'], $msg['message']);
    }
    
    return $results;
}

// Function to get section options for dropdown
function getSectionOptions($selected_id = null) {
    global $conn;
    
    $options = '';
    $stmt = $conn->prepare("SELECT id, section_name, level FROM sections ORDER BY level, grade_level, section_name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($section = $result->fetch_assoc()) {
        $selected = ($section['id'] == $selected_id) ? 'selected' : '';
        $options .= "<option value='{$section['id']}' {$selected} data-level='{$section['level']}'>
                        {$section['section_name']}
                     </option>";
    }
    
    return $options;
}

// Function to get student options for dropdown (searchable)
function getStudentOptions($selected_id = null, $adviser_id = null) {
    global $conn;
    
    $options = '';
    
    if ($adviser_id) {
        // Get students from adviser's sections
        $stmt = $conn->prepare("
            SELECT s.id, s.student_id, s.first_name, s.last_name, s.middle_name, sec.section_name
            FROM students s
            JOIN sections sec ON s.section_id = sec.id
            WHERE sec.adviser_id = ?
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->bind_param("i", $adviser_id);
    } else {
        // Get all active students
        $stmt = $conn->prepare("
            SELECT s.id, s.student_id, s.first_name, s.last_name, s.middle_name, sec.section_name
            FROM students s
            JOIN sections sec ON s.section_id = sec.id
            WHERE s.status = 'active'
            ORDER BY s.last_name, s.first_name
        ");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($student = $result->fetch_assoc()) {
        $selected = ($student['id'] == $selected_id) ? 'selected' : '';
        $full_name = "{$student['last_name']}, {$student['first_name']} " . ($student['middle_name'] ? substr($student['middle_name'], 0, 1) . '.' : '');
        $display_text = "{$full_name} - {$student['student_id']} ({$student['section_name']})";
        
        $options .= "<option value='{$student['id']}' {$selected} data-section='{$student['section_name']}'>
                        {$display_text}
                     </option>";
    }
    
    return $options;
}

// Function to check if user has access to student
function hasAccessToStudent($user_id, $student_id, $role) {
    global $conn;
    
    switch ($role) {
        case 'adviser':
            $stmt = $conn->prepare("
                SELECT s.id 
                FROM students s
                JOIN sections sec ON s.section_id = sec.id
                JOIN advisers a ON sec.adviser_id = a.id
                WHERE a.user_id = ? AND s.id = ?
            ");
            break;
        case 'guardian':
            $stmt = $conn->prepare("
                SELECT s.id
                FROM students s
                JOIN student_guardians sg ON s.id = sg.student_id
                JOIN guardians g ON sg.guardian_id = g.id
                WHERE g.user_id = ? AND s.id = ?
            ");
            break;
        default:
            return false;
    }
    
    $stmt->bind_param("ii", $user_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    if (empty($data)) return $data;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Function to validate and format phone number
function format_phone_number($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Philippine number
    if (strlen($phone) === 11 && substr($phone, 0, 2) === '09') {
        // Convert 09xxxxxxxxx to +639xxxxxxxx
        $phone = '63' . substr($phone, 1);
    } elseif (strlen($phone) === 10 && substr($phone, 0, 2) === '9') {
        // Convert 9xxxxxxxxx to +639xxxxxxxx
        $phone = '63' . $phone;
    }
    
    return $phone;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
?>
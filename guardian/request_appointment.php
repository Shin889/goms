<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info
$guardian = null;
$guardian_id = 0;
$stmt = $conn->prepare("SELECT g.*, u.full_name FROM guardians g JOIN users u ON g.user_id = u.id WHERE g.user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardian = $result->fetch_assoc();
    $guardian_id = $guardian ? $guardian['id'] : 0;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = trim($_POST['notes'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $code = "APT-" . date("Y") . "-" . rand(1000, 9999);
    
    // Validate dates
    $start_time = strtotime($start);
    $end_time = strtotime($end);
    $now = time();
    
    if ($start_time < $now) {
        $message = 'Start time cannot be in the past.';
        $message_type = 'error';
    } elseif ($end_time <= $start_time) {
        $message = 'End time must be after start time.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("INSERT INTO appointments (appointment_code, requested_by_user_id, student_id, start_time, end_time, mode, notes, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'requested', NOW())");
        
        if ($stmt) {
            $stmt->bind_param("siisssss", $code, $user_id, $student_id, $start, $end, $mode, $notes, $reason);
            
            if ($stmt->execute()) {
                $appointment_id = $stmt->insert_id;
                
                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, action_summary, target_table, target_id, created_at) VALUES (?, 'CREATE', 'Guardian requested appointment', 'appointments', ?, NOW())");
                if ($log_stmt) {
                    $log_stmt->bind_param("ii", $user_id, $appointment_id);
                    $log_stmt->execute();
                }
                
                $message = 'Appointment request submitted successfully! The counselor will review and confirm your appointment.';
                $message_type = 'success';
                
                // Clear form or redirect
                echo "<script>setTimeout(function() { window.location.href = 'appointments.php'; }, 3000);</script>";
            } else {
                $message = 'Error submitting appointment request: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Fetch linked students
$linked_students = [];
if ($guardian_id > 0) {
    $linked_sql = "
        SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level
        FROM student_guardians sg
        JOIN students s ON sg.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE sg.guardian_id = ?
        AND s.status = 'active'
        ORDER BY s.last_name, s.first_name
    ";
    
    $linked_stmt = $conn->prepare($linked_sql);
    if ($linked_stmt) {
        $linked_stmt->bind_param("i", $guardian_id);
        $linked_stmt->execute();
        $result = $linked_stmt->get_result();
        $linked_students = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Set default times (next Monday at 9 AM)
$default_start = date('Y-m-d\T09:00', strtotime('next monday'));
$default_end = date('Y-m-d\T10:00', strtotime('next monday'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Appointment - GOMS Guardian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/request_appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>

        <h2 class="logo">GOMS Guardian</h2>
        <div class="sidebar-user">
            <i class="fas fa-user-shield"></i> Guardian · <?= htmlspecialchars($guardian['full_name'] ?? 'Guardian'); ?>
            <?php if (!empty($linked_students)): ?>
                <br><small>
                    <?= count($linked_students) ?> student<?= count($linked_students) > 1 ? 's' : '' ?> linked
                </small>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
        <a href="link_student.php" class="nav-link">
            <span class="icon"><i class="fas fa-link"></i></span>
            <span class="label">Link Student</span>
        </a>
        
        <a href="appointments.php" class="nav-link">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>
        
        <a href="request_appointment.php" class="nav-link active">
            <span class="icon"><i class="fas fa-plus-circle"></i></span>
            <span class="label">Request Appointment</span>
        </a>
        
        <a href="submit_concern.php" class="nav-link">
            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="label">Submit Concern</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <main class="content" id="mainContent">
        <div class="request-appointment-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Request Appointment</h1>
                <p class="subtitle">Schedule a counseling session for your child</p>
            </div>

            <!-- Message Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                    <?php if ($message_type == 'success'): ?>
                        <br><small>Redirecting to appointments page...</small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Main Form Card -->
            <div class="form-card">
                <?php if (empty($linked_students)): ?>
                    <div class="no-students">
                        <div class="no-students-icon">
                            <i class="fas fa-link"></i>
                        </div>
                        <h3>No Linked Students</h3>
                        <p>You need to link your account to your child's student profile before you can request appointments.</p>
                        <a href="link_student.php" class="btn btn-primary">
                            <i class="fas fa-link"></i> Link a Student
                        </a>
                        <p style="margin-top: 20px; font-size: var(--fs-small); color: var(--clr-muted);">
                            After linking, an administrator will verify and approve the connection.
                        </p>
                    </div>
                <?php else: ?>
                    <h3><i class="fas fa-calendar-plus"></i> New Appointment Request</h3>
                    
                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                        <p>
                            Your appointment request will be reviewed by a counselor. You will receive a confirmation 
                            once the appointment is scheduled. Please allow 1-2 business days for processing.
                        </p>
                    </div>

                    <form method="POST" action="" id="appointmentForm">
                        <!-- Student Selection -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-user-graduate"></i> Select Child
                            </label>
                            <select id="student_id" name="student_id" class="form-control" required>
                                <option value="">-- Choose your child --</option>
                                <?php foreach ($linked_students as $student): ?>
                                    <option value="<?= $student['id'] ?>" <?= isset($_POST['student_id']) && $_POST['student_id'] == $student['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        (ID: <?= htmlspecialchars($student['student_code']) ?>)
                                        <?php if ($student['section_name']): ?>
                                            - Grade <?= htmlspecialchars($student['grade_level']) ?> - <?= htmlspecialchars($student['section_name']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">
                                Select which child needs the counseling appointment.
                            </div>
                        </div>

                        <!-- Time Selection -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="far fa-clock"></i> Appointment Time
                            </label>
                            <div class="time-container">
                                <div>
                                    <label for="start_time" style="font-size: var(--fs-small);">Start Time</label>
                                    <input type="datetime-local" id="start_time" name="start_time" class="form-control" required 
                                           value="<?= isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : $default_start ?>">
                                </div>
                                <div>
                                    <label for="end_time" style="font-size: var(--fs-small);">End Time</label>
                                    <input type="datetime-local" id="end_time" name="end_time" class="form-control" required 
                                           value="<?= isset($_POST['end_time']) ? htmlspecialchars($_POST['end_time']) : $default_end ?>">
                                </div>
                            </div>
                            <div class="form-help">
                                Select your preferred date and time. Appointments are typically 45-60 minutes.
                            </div>
                        </div>

                        <!-- Appointment Mode -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-laptop-house"></i> Appointment Mode
                            </label>
                            <select id="mode" name="mode" class="form-control" required>
                                <option value="in-person" <?= (isset($_POST['mode']) && $_POST['mode'] == 'in-person') ? 'selected' : '' ?>>In-Person Meeting (at school)</option>
                                <option value="online" <?= (isset($_POST['mode']) && $_POST['mode'] == 'online') ? 'selected' : '' ?>>Online Video Call</option>
                                <option value="phone" <?= (isset($_POST['mode']) && $_POST['mode'] == 'phone') ? 'selected' : '' ?>>Phone Call</option>
                            </select>
                            <div class="form-help">
                                Choose how your child will attend the counseling session.
                            </div>
                        </div>

                        <!-- Reason for Appointment -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-clipboard"></i> Reason for Appointment
                            </label>
                            <textarea id="reason" name="reason" class="form-control" required 
                                      placeholder="Please describe the reason for requesting this counseling session. Include any specific concerns, behaviors, or situations you'd like the counselor to address."><?= isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : '' ?></textarea>
                            <div class="form-help">
                                Be specific about your concerns to help the counselor prepare.
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-sticky-note"></i> Additional Notes (Optional)
                            </label>
                            <textarea id="notes" name="notes" class="form-control" 
                                      placeholder="Any other relevant information:
                                      
• Previous counseling history
• Medical or health considerations
• Parent availability for follow-up
• Preferred counselor (if any)
• Special accommodations needed"><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                            <div class="form-help">
                                Include any additional information that might help the counselor.
                            </div>
                        </div>

                        <!-- Form Footer -->
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Appointment Request
                            </button>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Appointments
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Tips Card -->
            <?php if (!empty($linked_students)): ?>
                <div class="form-card" style="margin-top: 30px;">
                    <h3><i class="fas fa-lightbulb"></i> Tips for a Successful Appointment</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-calendar-check"></i> Choose Appropriate Time
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                Select a time when your child is most available and least likely to have conflicts.
                            </p>
                        </div>
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-comment-alt"></i> Be Specific
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                Provide clear details about your concerns to help the counselor prepare effectively.
                            </p>
                        </div>
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-clock"></i> Follow Up
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                Check your appointments page regularly for updates on your request status.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize active nav link
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
            
            // Form validation
            const form = document.getElementById('appointmentForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const startTime = new Date(document.getElementById('start_time').value);
                    const endTime = new Date(document.getElementById('end_time').value);
                    const now = new Date();
                    
                    // Check if start time is in the past
                    if (startTime < now) {
                        e.preventDefault();
                        alert('Start time cannot be in the past. Please select a future time.');
                        document.getElementById('start_time').focus();
                        return false;
                    }
                    
                    // Check if end time is after start time
                    if (endTime <= startTime) {
                        e.preventDefault();
                        alert('End time must be after start time. Please adjust your time selection.');
                        document.getElementById('end_time').focus();
                        return false;
                    }
                    
                    // Check if appointment is at least 30 minutes
                    const duration = (endTime - startTime) / (1000 * 60); // in minutes
                    if (duration < 30) {
                        e.preventDefault();
                        alert('Appointment duration should be at least 30 minutes. Please adjust your time selection.');
                        return false;
                    }
                    
                    // Check if reason is provided
                    const reason = document.getElementById('reason').value.trim();
                    if (reason.length < 10) {
                        e.preventDefault();
                        alert('Please provide a more detailed reason for the appointment (at least 10 characters).');
                        document.getElementById('reason').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Set minimum datetime for time inputs to current time
            const now = new Date();
            const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('start_time').min = localDateTime;
            document.getElementById('end_time').min = localDateTime;
            
            // Auto-update end time when start time changes
            document.getElementById('start_time').addEventListener('change', function() {
                const startTime = new Date(this.value);
                const endTime = new Date(startTime.getTime() + 60 * 60000); // Add 60 minutes
                const endTimeString = endTime.toISOString().slice(0, 16);
                document.getElementById('end_time').value = endTimeString;
                document.getElementById('end_time').min = this.value;
            });
        });
    </script>
</body>
</html>
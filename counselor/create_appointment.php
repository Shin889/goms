<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = $_SESSION['user_id'];
$referral_id = isset($_GET['referral_id']) ? intval($_GET['referral_id']) : 0;

// Validation
if ($referral_id <= 0) {
    $_SESSION['error'] = "Invalid referral ID.";
    header("Location: referrals.php");
    exit;
}

// Get counselor info from counselors table
$stmt = $conn->prepare("
    SELECT c.id as counselor_db_id, u.full_name 
    FROM counselors c 
    JOIN users u ON c.user_id = u.id 
    WHERE u.id = ?
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

if (!$counselor) {
    $_SESSION['error'] = "Counselor information not found.";
    header("Location: referrals.php");
    exit;
}

// Get referral and student info - CORRECTED QUERY
$stmt = $conn->prepare("
    SELECT 
        r.id as referral_id,
        r.student_id,
        r.category,
        r.issue_description,
        r.priority,
        r.status,
        s.first_name,
        s.last_name,
        s.student_id as student_number,
        s.grade_level,
        s.section_id,
        sec.section_name,
        u.full_name as adviser_name
    FROM referrals r
    JOIN students s ON r.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    JOIN users u ON r.adviser_id = u.id
    WHERE r.id = ? AND r.counselor_id = ?
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("ii", $referral_id, $counselor['counselor_db_id']);
$stmt->execute();
$ref_result = $stmt->get_result();
$ref = $ref_result->fetch_assoc();
$stmt->close();

if (!$ref) {
    $_SESSION['error'] = "Referral not found or you don't have permission to access it.";
    header("Location: referrals.php");
    exit;
}

$student_id = $ref['student_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'] ?? '';
    $appointment_code = "APT-" . date("Y") . "-" . rand(1000, 9999);
    
    // Set location and meeting link based on mode
    $location = '';
    $meeting_link = '';
    
    if ($mode === 'in-person') {
        $location = 'Counseling Room A';
    } elseif ($mode === 'online') {
        $location = 'Online Meeting';
        $meeting_link = 'https://meet.example.com/session-' . rand(10000, 99999);
    } elseif ($mode === 'phone') {
        $location = 'Phone Consultation';
    }
    
    $status = 'scheduled'; // Default status
    
    // Check for time conflicts
    $conflict_stmt = $conn->prepare("
        SELECT id 
        FROM appointments 
        WHERE counselor_id = ? 
        AND ((start_time < ? AND end_time > ?) 
             OR (start_time >= ? AND start_time < ?))
        AND status NOT IN ('cancelled', 'completed')
    ");
    
    if ($conflict_stmt) {
        $conflict_stmt->bind_param("issss", 
            $counselor['counselor_db_id'], 
            $end, 
            $start,
            $start,
            $end
        );
        $conflict_stmt->execute();
        $conflict_result = $conflict_stmt->get_result();
        
        if ($conflict_result->num_rows > 0) {
            $error = 'Time conflict: You already have an appointment scheduled during this time.';
        } else {
            // Create appointment - UPDATED TO MATCH YOUR SCHEMA
            $sql = "INSERT INTO appointments (
                        appointment_code, 
                        student_id, 
                        counselor_id, 
                        referral_id,
                        start_time, 
                        end_time, 
                        mode,
                        status,
                        location,
                        meeting_link,
                        notes,
                        created_at,
                        created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                $error = "Prepare failed: " . htmlspecialchars($conn->error);
                error_log($error);
            } else {
                // Bind parameters
                $stmt->bind_param(
                    "siiisssssssi", // 13 characters for 13 parameters
                    $appointment_code,
                    $student_id,
                    $counselor['counselor_db_id'],
                    $referral_id,
                    $start,
                    $end,
                    $mode,
                    $status,
                    $location,
                    $meeting_link,
                    $notes,
                    $counselor_id
                );
                
if ($stmt->execute()) {
    $appointment_id = $conn->insert_id;
    
    // Log the action
    logAction($counselor_id, 'Create Appointment', 'appointments', $appointment_id, "From referral #$referral_id");
    
    // Update referral status
    $update_stmt = $conn->prepare("UPDATE referrals SET status = 'scheduled', updated_at = NOW() WHERE id = ?");
    $update_stmt->bind_param("i", $referral_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Check if guardian table exists before trying to send SMS
    // First, let's check if the student_guardians table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'student_guardians'");
    
    if ($check_table && $check_table->num_rows > 0) {
        // Table exists, try to get guardian info
        $guardian_stmt = $conn->prepare("
            SELECT u.phone  // CORRECT - get phone from users table
            FROM student_guardians sg 
            JOIN guardians g ON sg.guardian_id = g.id 
            JOIN users u ON g.user_id = u.id  // JOIN with users
            WHERE sg.student_id = ? 
            ORDER BY sg.primary_guardian DESC, sg.id ASC 
            LIMIT 1
        ");
        
        if ($guardian_stmt) {
            $guardian_stmt->bind_param("i", $student_id);
            $guardian_stmt->execute();
            $guardian_result = $guardian_stmt->get_result();
            $guardian = $guardian_result->fetch_assoc();
            $guardian_stmt->close();
            
            if ($guardian && !empty($guardian['phone'])) {
                // Use the SMS template instead of hardcoded message
                $student_name = $ref['first_name'] . ' ' . $ref['last_name'];
                $formatted_date = date("F j, Y", strtotime($start));
                $formatted_time = date("g:i A", strtotime($start));
                
                // Build message using template
                $message = str_replace(
                    ['[StudentName]', '[CounselorName]', '[Date]', '[Time]', '[BriefConcern]'],
                    [$student_name, $counselor['full_name'], $formatted_date, $formatted_time, substr($ref['issue_description'], 0, 50)],
                    SMS_BOOKING_TEMPLATE
                );
                
                // Check if sendSMS function exists
                if (function_exists('sendSMS')) {
                    // Log before sending for debugging
                    error_log("Attempting to send SMS to: " . $guardian['phone']);
                    error_log("Message: " . $message);
                    
                    $result = sendSMS($counselor_id, $guardian['phone'], $message);
                    
                    if ($result) {
                        error_log("SMS sent successfully");
                    } else {
                        error_log("SMS sending failed");
                    }
                } else {
                    error_log("sendSMS function not found");
                }
            } else {
                error_log("No guardian found or phone is empty for student ID: " . $student_id);
            }
        } else {
            error_log("Failed to prepare guardian statement: " . $conn->error);
        }
    } else {
        error_log("student_guardians table does not exist");
    }
    
    $_SESSION['success'] = "Appointment created successfully!";
    header("Location: appointments.php");
    exit;
} else {
    $error = "Error creating appointment: " . $stmt->error;
    error_log("Execute error: " . $stmt->error);
}
                $stmt->close();
            }
        }
        $conflict_stmt->close();
    } else {
        $error = "Database error checking conflicts: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Appointment - GOMS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/counselor_create_appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>
        <h2 class="logo">GOMS Counselor</h2>
        <div class="sidebar-user">
            Counselor · <?= htmlspecialchars($counselor['full_name'] ?? 'User'); ?>
        </div>
        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
        </a>
        <a href="referrals.php" class="nav-link">
            <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
        </a>
        <a href="appointments.php" class="nav-link active">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
        </a>
        <a href="sessions.php" class="nav-link">
            <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
        </a>
        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <div class="page-container">
        <a href="referrals.php" class="back-link">← Back to Referrals</a>
        
        <h2 class="page-title">Book Appointment</h2>
        <p class="page-subtitle">Schedule a counseling session for the referred student.</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($ref)): ?>
            <div class="card">
                <!-- Student Information -->
                <div class="referral-info">
                    <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Student Name:</label>
                            <strong><?= htmlspecialchars($ref['first_name'] . ' ' . $ref['last_name']); ?></strong>
                        </div>
                        <div class="info-item">
                            <label>Student ID:</label>
                            <?= htmlspecialchars($ref['student_number'] ?? 'N/A'); ?>
                        </div>
                        <div class="info-item">
                            <label>Grade Level:</label>
                            Grade <?= htmlspecialchars($ref['grade_level']); ?>
                        </div>
                        <div class="info-item">
                            <label>Section:</label>
                            <?= htmlspecialchars($ref['section_name'] ?? 'Not assigned'); ?>
                        </div>
                        <div class="info-item">
                            <label>Adviser:</label>
                            <?= htmlspecialchars($ref['adviser_name']); ?>
                        </div>
                        <div class="info-item">
                            <label>Category:</label>
                            <span class="badge"><?= ucfirst($ref['category']); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Priority:</label>
                            <span class="badge priority-<?= $ref['priority']; ?>">
                                <?= ucfirst($ref['priority']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="issue-summary">
                        <label>Issue Description:</label>
                        <p><?= htmlspecialchars($ref['issue_description']); ?></p>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-body">
                        <h3><i class="fas fa-calendar-plus"></i> Appointment Details</h3>
                        
                        <div class="form-row two-col">
                            <div class="form-group">
                                <label>Start Date & Time</label>
                                <input type="datetime-local" name="start_time" required 
                                       value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>"
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
            
                            <div class="form-group">
                                <label>End Date & Time</label>
                                <input type="datetime-local" name="end_time" required 
                                       value="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>"
                                       min="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Appointment Mode</label>
                                <select name="mode" required>
                                    <option value="in-person" selected>In-person</option>
                                    <option value="online">Online</option>
                                    <option value="phone">Phone</option>
                                </select>
                                <small class="form-help">
                                    • In-person: Counseling Room A<br>
                                    • Online: Meeting link will be generated<br>
                                    • Phone: Counselor will call the provided number
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Additional Notes</label>
                            <textarea name="notes" placeholder="Add any additional notes, specific concerns to address, or special instructions..."></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="referrals.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-calendar-check"></i> Schedule Appointment
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const pageContainer = document.querySelector('.page-container');
            
            function updateContentPadding() {
                if (sidebar.classList.contains('collapsed')) {
                    pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-collapsed-width) + 20px)';
                } else {
                    pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-width) + 20px)';
                }
            }
            
            updateContentPadding();
            
            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                setTimeout(updateContentPadding, 300);
            });
            
            window.addEventListener('resize', updateContentPadding);
            
            // Set minimum datetime to current time
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            const startInput = document.querySelector('input[name="start_time"]');
            const endInput = document.querySelector('input[name="end_time"]');
            
            if (startInput) startInput.min = minDateTime;
            if (endInput) endInput.min = minDateTime;
            
            // Auto-adjust end time when start time changes
            if (startInput && endInput) {
                startInput.addEventListener('change', function() {
                    const start = new Date(this.value);
                    const end = new Date(start.getTime() + 60 * 60 * 1000); // +1 hour
                    endInput.value = end.toISOString().slice(0, 16);
                    endInput.min = this.value;
                });
            }
        });
    </script>
</body>
</html>
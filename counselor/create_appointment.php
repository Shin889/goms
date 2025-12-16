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

// Get counselor info
$stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

// Get student info from referral
$stmt = $conn->prepare("
    SELECT c.student_id, s.first_name, s.last_name, s.student_id as student_number
    FROM referrals r
    JOIN complaints c ON r.complaint_id = c.id
    JOIN students s ON c.student_id = s.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $referral_id);
$stmt->execute();
$ref_result = $stmt->get_result();
$ref = $ref_result->fetch_assoc();
$stmt->close();

if (!$ref) {
    $_SESSION['error'] = "Referral not found.";
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
        $meeting_link = 'https://meet.generic.edu/session-' . rand(10000, 99999);
    } elseif ($mode === 'phone') {
        $location = 'Phone Consultation';
    }
    
    $status = 'scheduled'; // Default status
    
    // Debug: Check connection
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
    
    // EXACT INSERT statement matching your table structure
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
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    // Debug: Show the SQL
    // echo "<pre>SQL: " . htmlspecialchars($sql) . "</pre>";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // Show more detailed error
        $error_msg = "Prepare failed: " . htmlspecialchars($conn->error);
        error_log($error_msg);
        die($error_msg . "<br>Check that all column names match your table structure.");
    }
    
    // Debug: check all values
    error_log("Appointment values: code=$appointment_code, student=$student_id, counselor=$counselor_id, referral=$referral_id, start=$start, end=$end, mode=$mode, status=$status, location=$location, meeting=$meeting_link, notes=$notes, created=$counselor_id");
    
    // Bind parameters - 12 parameters total
    $stmt->bind_param(
        "siiisssssssi", // 12 characters for 12 parameters
        $appointment_code,     // s
        $student_id,           // i
        $counselor_id,         // i
        $referral_id,          // i
        $start,                // s
        $end,                  // s
        $mode,                 // s
        $status,               // s (status)
        $location,             // s
        $meeting_link,         // s
        $notes,                // s
        $counselor_id          // i (created_by)
    );
    
    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;
        
        // Log the action
        logAction($counselor_id, 'Create Appointment', 'appointments', $appointment_id, "From referral #$referral_id");
        
        // Update referral status
        $update_stmt = $conn->prepare("UPDATE referrals SET status = 'scheduled' WHERE id = ?");
        $update_stmt->bind_param("i", $referral_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Send SMS to guardian
        $guardian_stmt = $conn->prepare("
            SELECT g.phone 
            FROM student_guardians sg 
            JOIN guardians g ON sg.guardian_id = g.id 
            WHERE sg.student_id = ? AND sg.primary_guardian = 1
            LIMIT 1
        ");
        $guardian_stmt->bind_param("i", $student_id);
        $guardian_stmt->execute();
        $guardian_result = $guardian_stmt->get_result();
        $guardian = $guardian_result->fetch_assoc();
        $guardian_stmt->close();
        
        if ($guardian && !empty($guardian['phone'])) {
            $formatted_date = date("F j, Y \a\\t g:i A", strtotime($start));
            $msg = "GOMS: Your child " . $ref['first_name'] . " has a counseling appointment scheduled on $formatted_date. Mode: $mode.";
            sendSMS($counselor_id, $guardian['phone'], $msg);
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
            Counselor · <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>
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

        <div class="card">
            <div class="student-info">
                <div class="label">Referred Student</div>
                <div class="value"><?= htmlspecialchars($ref['first_name'] . ' ' . $ref['last_name']); ?></div>
                <div style="font-size: 0.9rem; color: var(--clr-muted); margin-top: 4px;">
                    Student ID: <?= htmlspecialchars($ref['student_number'] ?? 'N/A'); ?>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-body">
                    <div class="form-row two-col">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="datetime-local" name="start_time" required 
                                   value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
                        </div>
        
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="datetime-local" name="end_time" required 
                                   value="<?= date('Y-m-d\TH:i', strtotime('+2 hours')) ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mode</label>
                            <select name="mode" required>
                                <option value="in-person" selected>In-person</option>
                                <option value="online">Online</option>
                                <option value="phone">Phone</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" placeholder="Add any additional notes or instructions, purpose of session, or location details..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-calendar-check"></i> Confirm Appointment
                </button>
            </form>
        </div>
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
            document.querySelector('input[name="start_time"]').min = minDateTime;
            document.querySelector('input[name="end_time"]').min = minDateTime;
            
            // Auto-adjust end time when start time changes
            document.querySelector('input[name="start_time"]').addEventListener('change', function() {
                const start = new Date(this.value);
                const end = new Date(start.getTime() + 60 * 60 * 1000); // +1 hour
                document.querySelector('input[name="end_time"]').value = end.toISOString().slice(0, 16);
                document.querySelector('input[name="end_time"]').min = this.value;
            });
        });
    </script>
</body>
</html>
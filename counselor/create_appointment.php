<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = $_SESSION['user_id'];
$referral_id = isset($_GET['referral_id']) ? intval($_GET['referral_id']) : 0;

// FIX: Proper validation and redirect
if ($referral_id <= 0) {
    $_SESSION['error'] = "Invalid referral ID.";
    header("Location: referrals.php");
    exit;
}

// FIX: Load logged-in user with prepared statement
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

// FIX: Fetch student linked to referral with prepared statement
$stmt = $conn->prepare("
    SELECT c.student_id, s.first_name, s.last_name
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

// FIX: Check if referral exists
if (!$ref) {
    $_SESSION['error'] = "Referral not found.";
    header("Location: referrals.php");
    exit;
}

$student_id = $ref['student_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];
    $appointment_code = "APT-" . date("Y") . "-" . rand(1000,9999);

    // FIX: Prepared statement for appointment creation
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            appointment_code, requested_by_user_id, student_id, 
            counselor_id, start_time, end_time, mode, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)
    ");
    
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "siiissss", 
        $appointment_code, 
        $counselor_id, 
        $student_id, 
        $counselor_id, 
        $start, 
        $end, 
        $mode, 
        $notes
    );

    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;
        logAction($counselor_id, 'Create Appointment', 'appointments', $appointment_id, "From referral #$referral_id");
        
        // FIX: Also update referral status if needed
        $update_stmt = $conn->prepare("
            UPDATE referrals SET status = 'scheduled' WHERE id = ?
        ");
        $update_stmt->bind_param("i", $referral_id);
        $update_stmt->execute();
        $update_stmt->close();

        // ✅ Send SMS with prepared statement
        $guardian_stmt = $conn->prepare("
            SELECT g.phone 
            FROM student_guardians sg 
            JOIN guardians g ON sg.guardian_id = g.id 
            WHERE sg.student_id = ?
            LIMIT 1
        ");
        $guardian_stmt->bind_param("i", $student_id);
        $guardian_stmt->execute();
        $guardian_result = $guardian_stmt->get_result();
        $guardian = $guardian_result->fetch_assoc();
        $guardian_stmt->close();

        if ($guardian && !empty($guardian['phone'])) {
            $formatted_date = date("F j, Y \a\\t g:i A", strtotime($start));
            $msg = "Your child has a counseling appointment set on $formatted_date. Please confirm or be available.";
            sendSMS($counselor_id, $guardian['phone'], $msg);
        }

        // Use session message instead of JavaScript alert
        $_SESSION['success'] = "Appointment created successfully!";
        header("Location: appointments.php");
        exit;
    } else {
        $error = "Error creating appointment: " . $stmt->error;
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
    <style>
        body {
            margin: 0;
            font-family: var(--font-family);
            background: var(--clr-bg);
            color: var(--clr-text);
            min-height: 100vh;
            padding: 40px;
            box-sizing: border-box;
        }

        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding-left: calc(var(--layout-sidebar-width) + 20px);
            transition: padding-left var(--time-transition);
        }

        @media (max-width: 900px) {
            .page-container {
                padding-left: calc(var(--layout-sidebar-collapsed-width) + 20px);
            }
        }

        h2.page-title {
            font-size: var(--fs-heading);
            color: var(--clr-primary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        p.page-subtitle {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            margin-bottom: 24px;
        }

        a.back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--clr-secondary);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--time-transition);
            font-size: var(--fs-small);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
        }

        a.back-link:hover {
            color: var(--clr-primary);
            background: var(--clr-accent);
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 0.95rem;
            border: 1px solid;
        }

        .alert-error {
            background: color-mix(in srgb, var(--clr-error) 10%, var(--clr-bg));
            color: var(--clr-error);
            border-color: var(--clr-error);
        }

        .alert-success {
            background: color-mix(in srgb, var(--clr-success) 10%, var(--clr-bg));
            color: var(--clr-success);
            border-color: var(--clr-success);
        }

        .card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 28px;
            transition: box-shadow var(--time-transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .form-body {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        @media (min-width: 550px) {
            .form-row.two-col {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            display: block;
            color: var(--clr-secondary);
            font-weight: 600;
            font-size: var(--fs-small);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            background: var(--clr-bg);
            color: var(--clr-text);
            font-size: 0.95rem;
            font-family: var(--font-family);
            transition: all var(--time-transition);
            box-sizing: border-box;
        }

        input[type="datetime-local"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 20px;
            background: var(--clr-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--time-transition);
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--clr-secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .student-info {
            background: rgba(16, 185, 129, 0.05);
            border-left: 4px solid var(--clr-primary);
            padding: 16px 20px;
            border-radius: var(--radius-sm);
            margin-bottom: 28px;
        }

        .student-info .label {
            font-size: 0.75rem;
            color: var(--clr-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .student-info .value {
            font-size: 1.1rem;
            color: var(--clr-primary);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>
        <h2 class="logo">GOMS Counselor</h2>
        <div class="sidebar-user">
            Counselor · <?= htmlspecialchars($counselor['username'] ?? ''); ?>
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
        <a href="create_report.php" class="nav-link">
            <span class="icon"><i class="fas fa-file-alt"></i></span><span class="label">Generate Report</span>
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
                <div class="value"><?= htmlspecialchars($ref['first_name'].' '.$ref['last_name']); ?></div>
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
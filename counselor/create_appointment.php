<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = $_SESSION['user_id'];

// FIX: load logged-in user
$counselor = $conn->query("
    SELECT username FROM users WHERE id = $counselor_id
")->fetch_assoc();

$referral_id = $_GET['referral_id'] ?? null;

// Redirect ONLY on this page
if (!$referral_id && basename($_SERVER['PHP_SELF']) === "create_appointment.php") {
    header("Location: referrals.php");
    exit;
}

// Fetch student linked to referral
$ref = $conn->query("
  SELECT c.student_id, s.first_name, s.last_name
  FROM referrals r
  JOIN complaints c ON r.complaint_id = c.id
  JOIN students s ON c.student_id = s.id
  WHERE r.id = $referral_id
")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];
    $student_id = $ref['student_id'];
    $appointment_code = "APT-" . date("Y") . "-" . rand(1000,9999);

    $stmt = $conn->prepare("
      INSERT INTO appointments (appointment_code, requested_by_user_id, student_id, counselor_id, start_time, end_time, mode, status, notes)
      VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)
    ");
    
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("siiiisss", $appointment_code, $counselor_id, $student_id, $counselor_id, $start, $end, $mode, $notes);

    if ($stmt->execute()) {
        logAction($counselor_id, 'Create Appointment', 'appointments', $stmt->insert_id, "From referral #$referral_id");

        // ✅ Send SMS here after appointment creation
        $guardian = $conn->query("
          SELECT g.phone 
          FROM student_guardians sg 
          JOIN guardians g ON sg.guardian_id = g.id 
          WHERE sg.student_id = $student_id
        ")->fetch_assoc();

        if ($guardian && !empty($guardian['phone'])) {
            $msg = "Your child has a counseling appointment set on $start. Please confirm or be available.";
            sendSMS($counselor_id, $guardian['phone'], $msg);
        }

        echo "<script>alert('Appointment created successfully!'); window.location='appointments.php';</script>";
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Counselor Dashboard - GOMS</title>
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
        <a href="/counselor/referrals.php" class="nav-link" data-page="referrals.php">
            <span class="icon">📋</span><span class="label">Referrals</span>
        </a>
        <a href="/counselor/appointments.php" class="nav-link" data-page="appointments.php">
            <span class="icon">📅</span><span class="label">Appointments</span>
        </a>
        <a href="/counselor/sessions.php" class="nav-link" data-page="sessions.php">
            <span class="icon">💬</span><span class="label">Sessions</span>
        </a>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </nav>

    <div class="page-container">
        <a href="referrals.php" class="back-link">← Back to Referrals</a>
        
        <h2 class="page-title">Book Appointment</h2>
        <p class="page-subtitle">Schedule a counseling session for the referred student.</p>

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
                            <input type="datetime-local" name="start_time" required>
                        </div>
        
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="datetime-local" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Mode</label>
                            <select name="mode" required>
                                <option value="in-person">In-person</option>
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

                <button type="submit" class="btn-submit">✓ Confirm Appointment</button>
            </form>
        </div>
    </div>

    <script src="../utils/js/sidebar.js"></script>
    <script src="../utils/js/dashboard.js"></script>
    <script>
        // Handle sidebar collapse effect on content padding
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
            
            // Initial check
            updateContentPadding();
            
            // Listen for sidebar toggle
            document.getElementById('sidebarToggle')?.addEventListener('click', function() {
                // Wait for transition to complete
                setTimeout(updateContentPadding, 300);
            });
            
            // Responsive adjustments
            window.addEventListener('resize', updateContentPadding);
        });
    </script>
</body>
</html>
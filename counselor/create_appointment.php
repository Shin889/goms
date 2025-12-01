<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = $_SESSION['user_id'];
$referral_id = $_GET['referral_id'] ?? null;

if (!$referral_id) {
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
    <title>Book Appointment - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><!-- Global root vars -->
    <style>
        body {
            margin: 0;
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            padding: 40px;
            box-sizing: border-box;
        }

        .page-container {
            max-width: 800px;
            margin: 0 auto;
        }

        h2.page-title {
            font-size: 1.6rem;
            color: var(--color-primary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        p.page-subtitle {
            color: var(--color-muted);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        a.back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--color-secondary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
            font-size: 0.95rem;
        }

        a.back-link:hover {
            color: var(--color-primary);
        }

        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            box-shadow: var(--shadow-sm);
            padding: 30px;
        }

        .form-body {
            display: flex;
            flex-direction: column;
            gap: 20px; /* Space between rows */
        }

        /* NEW: Grid for two-column layout */
        .form-row {
            display: grid;
            grid-template-columns: 1fr; /* Default to single column */
            gap: 20px;
        }

        @media (min-width: 550px) {
            .form-row.two-col {
                grid-template-columns: 1fr 1fr; /* Two columns for scheduling details */
            }
        }

        .form-group {
            margin-bottom: 0; /* Remove old margin, use .form-body gap instead */
            display: flex;
            flex-direction: column;
        }

        label {
            display: block;
            color: var(--color-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--color-border);
            border-radius: 8px;
            background: var(--color-bg);
            color: var(--color-text);
            font-size: 0.95rem;
            font-family: var(--font-family);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        input[type="datetime-local"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 30%, var(--color-bg)); /* Adjusted focus ring */
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        select {
            cursor: pointer;
            appearance: none;
            /* Placeholder SVG for dropdown arrow, using a dynamic color if available */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px 20px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: var(--color-primary-dark, #0056b3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .student-info {
            background: rgba(0, 123, 255, 0.05);
            border-left: 4px solid var(--color-primary);
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .student-info .label {
            font-size: 0.8rem;
            color: var(--color-muted);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .student-info .value {
            font-size: 1.1rem;
            color: var(--color-primary);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <a href="referrals.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Referrals</a>
        
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

                <button type="submit" class="btn-submit"><i class="fas fa-calendar-alt"></i> Confirm Appointment</button>
            </form>
        </div>
    </div>
</body>
</html>
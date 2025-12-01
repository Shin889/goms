<?php
include('../config/db.php');
include('../includes/auth_check.php');
checkRole(['counselor']);

$user_id = $_SESSION['user_id'];

// Fetch counselor info (optional, if you have counselor table later)
$counselor = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle appointment status updates (Confirm, Complete, Cancel, etc.)
if (isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $appointment_id);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "✅ Appointment status updated successfully!";
    } else {
        // Use $stmt->error for error reporting specific to the prepared statement
        $_SESSION['msg'] = "❌ Failed to update status: " . $stmt->error;
    }

    // Always redirect after POST to prevent form resubmission (PRG Pattern)
    header("Location: appointments.php");
    exit();
}


// Handle new appointment creation (Counselor manually books one)
if (isset($_POST['create_appointment'])) {
    $student_id = intval($_POST['student_id']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];

    // generate unique code
    $appointment_code = "APT-" . date("YmdHis") . "-" . strtoupper(substr(md5(uniqid()), 0, 4));

    $stmt = $conn->prepare("INSERT INTO appointments (
        appointment_code, requested_by_user_id, student_id, counselor_id,
        start_time, end_time, mode, status, notes, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())");

    $stmt->bind_param(
        "siiissss",
        $appointment_code,
        $user_id,
        $student_id,
        $user_id, // Counselor books for themselves
        $start_time,
        $end_time,
        $mode,
        $notes
    );

    if ($stmt->execute()) {
        // Use session message for success
        $_SESSION['msg'] = "✅ Appointment created successfully!";
    } else {
        // Use session message for error
        $_SESSION['msg'] = "❌ Error creating appointment: " . $stmt->error;
    }
    
    // Implement PRG pattern: redirect regardless of success/failure
    header("Location: appointments.php");
    exit();
}

// Fetch all appointments assigned to this counselor
$sql = "
SELECT 
    a.id, a.appointment_code, a.start_time, a.end_time, a.mode, a.status, a.notes,
    s.first_name, s.last_name, s.grade_level, s.section
FROM appointments a
LEFT JOIN students s ON a.student_id = s.id
WHERE a.counselor_id = ?
ORDER BY a.start_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments = $stmt->get_result();

// Fetch student list for dropdown (re-fetch as it's outside the POST block)
$students_result = $conn->query("SELECT id, first_name, last_name, grade_level, section FROM students ORDER BY last_name ASC");
$students = [];
if ($students_result) {
    while($stu = $students_result->fetch_assoc()) {
        $students[] = $stu;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Appointments - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        /* This style block should be placed AFTER root.css (if it exists) to use/override variables */

        /* General Layout */
        .content-wrap {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex-grow: 1;
            padding: 20px;
            box-sizing: border-box;
            margin-left: var(--layout-sidebar-width); /* Default spacing for non-collapsed sidebar */
            transition: margin-left var(--time-transition);
        }
        
        /* Mobile/Collapsed Adjustments */
        @media (max-width: 900px) {
            .content-area {
                margin-left: var(--layout-sidebar-collapsed-width);
            }
        }
        
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        
        @media (min-width: 768px) {
            .page-header {
                flex-direction: row;
                align-items: center;
            }
        }

        h1.page-title {
            font-size: var(--fs-heading); /* Using CSS Variable */
            color: var(--clr-primary); /* Using CSS Variable */
            font-weight: 700;
            margin: 0;
        }

        p.page-subtitle {
            color: var(--clr-muted); /* Using CSS Variable */
            font-size: var(--fs-small);
            margin: 4px 0 0 0;
        }

        /* Alert Styling using Status Variables */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm); /* Using CSS Variable */
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: color-mix(in srgb, var(--clr-success) 10%, var(--clr-bg));
            color: var(--clr-success);
            border-color: var(--clr-success);
        }

        .alert-error {
            background: color-mix(in srgb, var(--clr-error) 10%, var(--clr-bg));
            color: var(--clr-error);
            border-color: var(--clr-error);
        }
        
        /* Card component is already defined in root.css, just need .card class */
        .card {
            padding: 1px; /* To prevent padding issue with table inside */
        }

        /* Table Styling */
        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--fs-base);
            min-width: 1100px; 
        }

        th, td {
            padding: 16px 18px; /* Slightly more padding */
            text-align: left;
            border-bottom: 1px solid var(--clr-border); /* Using CSS Variable */
            vertical-align: middle;
        }

        th {
            background: var(--clr-surface); /* Header background */
            color: var(--clr-muted); /* Using CSS Variable */
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem; /* Smaller header font */
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        /* Badge Styling using Status Variables */
        .badge {
            padding: 5px 12px;
            border-radius: 16px; 
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-confirmed { 
            background: color-mix(in srgb, var(--clr-info) 15%, var(--clr-bg)); 
            color: var(--clr-info); 
        }
        .badge-completed { 
            background: color-mix(in srgb, var(--clr-success) 15%, var(--clr-bg)); 
            color: var(--clr-success); 
        }
        .badge-cancelled { 
            background: color-mix(in srgb, var(--clr-error) 15%, var(--clr-bg)); 
            color: var(--clr-error); 
        }
        .badge-pending { 
            background: color-mix(in srgb, var(--clr-warning) 15%, var(--clr-bg)); 
            color: var(--clr-warning); 
        }
        .badge-rescheduled { 
            /* Using a muted color for a calmer rescheduling indicator */
            background: color-mix(in srgb, var(--clr-muted) 15%, var(--clr-bg)); 
            color: var(--clr-muted); 
        } 

        .mode-badge {
            background: var(--clr-accent); /* Using Accent for subtle highlight */
            color: var(--clr-secondary);
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        /* Action Buttons - using base .btn classes */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 120px;
        }
        
        @media (min-width: 1024px) {
            .action-buttons {
                flex-direction: row;
            }
        }
        
        .btn-complete {
            background: var(--clr-success);
            color: #fff;
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: var(--radius-sm);
        }

        .btn-complete:hover {
            background: var(--clr-secondary); /* Darker green on hover */
        }
        
        .btn-cancel {
            background: var(--clr-error);
            color: #fff;
            padding: 8px 14px;
            font-size: 0.85rem;
            border-radius: var(--radius-sm);
        }

        .btn-cancel:hover {
            background: color-mix(in srgb, var(--clr-error) 80%, black); /* Slightly darker red on hover */
        }
        /* New Form Layout Styles */
.form-body {
    display: flex;
    flex-direction: column;
    gap: 15px; /* Spacing between sections */
}

.form-row {
    display: grid;
    grid-template-columns: 1fr; /* Default to single column for small screens */
    gap: 20px;
}

@media (min-width: 480px) {
    .form-row.two-col {
        grid-template-columns: 1fr 1fr; /* Two columns for medium/large screens */
    }
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px; /* Spacing between label and input */
}

/* Ensure inputs/selects/textareas fill their space */
input[type="datetime-local"],
select,
textarea {
    /* ... existing input styles ... */
    width: 100%;
    padding: 10px;
    box-sizing: border-box; /* Important for width: 100% */
}
        /* Modal, Form, and Input Styling using Variables */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(45, 55, 72, 0.8); /* Using text color for modal overlay */
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
            align-items: center;
            justify-content: center;
        }
.modal-header {
    border-bottom: 1px solid var(--clr-border);
    padding-bottom: 15px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
        .modal-content {
            background: var(--clr-surface);
            max-width: 600px;
            width: 95%;
            margin: auto;
            padding: 30px;
            border-radius: var(--radius-lg); /* Larger radius for modal */
            box-shadow: var(--shadow-lg); /* Larger shadow for modal */
        }

        label {
            color: var(--clr-muted);
            text-transform: uppercase;
        }

        input[type="datetime-local"],
        select,
        textarea {
            background: var(--clr-bg);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--clr-primary) 30%, var(--clr-bg)); /* Use mix for focus ring */
        }

        /* Modal Footer Buttons */
        .modal-footer {
            display: flex;
            flex-direction: column; 
            gap: 10px;
            margin-top: 24px;
        }

        
        @media (min-width: 480px) {
            .modal-footer {
                flex-direction: row-reverse;
            }
        }
        
        .modal-footer .btn-primary {
            flex: 1;
            padding: 12px 20px;
            background: var(--clr-primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
        }

        .modal-footer .btn-secondary {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            color: var(--clr-text);
            border: 2px solid var(--clr-border); /* Changed to 2px for outline-style look */
            border-radius: var(--radius-md);
            font-size: 0.95rem;
        }

        .modal-footer .btn-secondary:hover {
            background: var(--clr-accent);
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        /* Empty State */
        .empty-state {
            padding: 60px 20px;
        }
        
    </style>
</head>
<body>

<div class="content-wrap">
    <div class="sidebar">
        <h2 class="logo">GOMS</h2>
        <p class="sidebar-user">Counselor Dashboard</p>
        
        <a href="dashboard.php"><span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span></a>
        <a href="appointments.php" class="active"><span class="icon"><i class="fas fa-calendar-check"></i></span><span class="label">Appointments</span></a>
        <a href="students.php"><span class="icon"><i class="fas fa-user-graduate"></i></span><span class="label">Students</span></a>
        <a href="../logout.php" class="logout-link"><span class="icon"><i class="fas fa-sign-out-alt"></i></span><span class="label">Logout</span></a>
    </div>

    <div class="content-area">
        <div class="page-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Appointments</h1>
                    <p class="page-subtitle">Manage and track counseling appointments assigned to you.</p>
                </div>
                <button onclick="document.getElementById('createModal').style.display='flex'" class="btn">
                    <i class="fas fa-plus-circle"></i> New Appointment
                </button>
            </div>

        <?php if (isset($_SESSION['msg'])): 
            $alert_class = strpos($_SESSION['msg'], '❌') !== false || strpos($_SESSION['msg'], 'Failed') !== false ? 'alert-error' : 'alert-success';
        ?>
            <div class="alert <?= $alert_class ?>"><?= $_SESSION['msg'] ?></div>
            <?php unset($_SESSION['msg']); ?>
        <?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Student</th>
                                <th>Time & Date</th> <th>Mode</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments->num_rows > 0): ?>
                                <?php while ($row = $appointments->fetch_assoc()) { 
                                    $statusClass = 'badge-' . strtolower($row['status']);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['appointment_code']) ?></strong></td>
                                        <td>
                                            <div class="student-info">
                                                <strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>
                                                <span class="student-details"><?= htmlspecialchars('' . $row['grade_level'] . ' - ' . $row['section']) ?></span>
                                            </div>
                                        </td>
                                        <td class="time-cell">
                                            <?= date("M d, Y", strtotime($row['start_time'])) ?><br>
                                            <span style="color: var(--clr-secondary); font-weight: 600;"><?= date("h:i A", strtotime($row['start_time'])) ?></span> - 
                                            <span style="color: var(--clr-secondary); font-weight: 600;"><?= date("h:i A", strtotime($row['end_time'])) ?></span>
                                        </td>
                                        <td><span class="mode-badge"><?= ucfirst($row['mode']) ?></span></td>
                                        <td><span class="badge <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
                                        <td class="notes-cell" title="<?= htmlspecialchars($row['notes']) ?>">
                                            <?= htmlspecialchars($row['notes']) ?>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] !== 'completed' && $row['status'] !== 'cancelled') { ?>
                                                <div class="action-buttons">
                                                    <form class="inline" method="POST">
                                                        <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_status" class="btn btn-complete">Complete</button>
                                                    </form>
                                                    <form class="inline" method="POST">
                                                        <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" name="update_status" class="btn btn-cancel">Cancel</button>
                                                    </form>
                                                </div>
                                            <?php } else { ?>
                                                <span style="color: var(--clr-muted); font-size: 0.85rem;">No actions</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7"> 
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📅</div>
                                            <p style="font-size: 1.1rem; color: var(--clr-muted);">No appointments found. Use the **New Appointment** button to create one.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> </div> 

    <div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="color: var(--clr-primary); margin: 0; font-size: 1.5rem;"><i class="fas fa-calendar-plus"></i> Schedule New Appointment</h2>
            <button type="button" class="btn-close" style="background: none; border: none; cursor: pointer; color: var(--clr-muted); font-size: 1.2rem;" onclick="document.getElementById('createModal').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST">
            <div class="form-body">
                
                <div class="form-row two-col">
                    <div class="form-group">
                        <label for="student_id">Student</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $stu) { ?>
                                <option value="<?= $stu['id'] ?>">
                                    <?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['grade_level'] . ' - ' . $stu['section'] . ')') ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="mode">Mode</label>
                        <select name="mode" id="mode" required>
                            <option value="in-person">In-Person</option>
                            <option value="online">Online</option>
                            <option value="phone">Phone</option>
                        </select>
                    </div>
                </div>

                <div class="form-row two-col">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="datetime-local" id="start_time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="datetime-local" id="end_time" name="end_time" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes/Purpose</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Add any additional notes or purpose for the appointment..."></textarea>
                </div>
                
            </div>

            <div class="modal-footer">
                <button type="submit" name="create_appointment" class="btn-primary"><i class="fas fa-save"></i> Create Appointment</button>
                <button type="button" class="btn-secondary" onclick="document.getElementById('createModal').style.display='none'"><i class="fas fa-times-circle"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('createModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>
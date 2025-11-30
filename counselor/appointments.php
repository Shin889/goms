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
// Note: We need to re-run the student query here since the page might have redirected.
// We must rewind the result set or fetch the data again if needed in the HTML section.
// Since the original script fetches students before any POST/redirect, we are re-running it here for correctness after the POST blocks.
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
    <title>Counselor Appointments - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css">
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        h1.page-title {
            font-size: 1.8rem;
            color: var(--color-primary);
            font-weight: 700;
            margin: 0;
        }

        p.page-subtitle {
            color: var(--color-muted);
            font-size: 0.95rem;
            margin: 4px 0 0 0;
        }

        .btn-create {
            padding: 12px 20px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-create:hover {
            background: var(--color-primary-dark, #0056b3);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-confirmed { background: #e3f2fd; color: #1976d2; }
        .badge-completed { background: #e8f5e9; color: #388e3c; }
        .badge-cancelled { background: #ffebee; color: #d32f2f; }
        .badge-pending { background: #fff3e0; color: #f57c00; }

        .mode-badge {
            background: rgba(0, 123, 255, 0.1);
            color: var(--color-primary);
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 2px;
        }

        .btn-complete {
            background: #28a745;
            color: white;
        }

        .btn-complete:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
            transform: translateY(-1px);
        }

        .action-buttons {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }

        form.inline {
            display: inline;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
        }

        .modal-content {
            background: var(--color-surface);
            max-width: 600px;
            margin: 60px auto;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            margin-bottom: 24px;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--color-primary);
            font-weight: 700;
            margin: 0;
        }

        .form-group {
            margin-bottom: 18px;
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

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn-primary {
            flex: 1;
            padding: 12px 20px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--color-primary-dark, #0056b3);
            transform: translateY(-1px);
        }

        .btn-secondary {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            color: var(--color-text);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--color-primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-muted);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .student-info {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .notes-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="page-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Appointments</h1>
            <p class="page-subtitle">Manage and track counseling appointments</p>
        </div>
        <button onclick="document.getElementById('createModal').style.display='block'" class="btn-create">
            + New Appointment
        </button>
    </div>

<?php if (isset($_SESSION['msg'])): 
    // Determine alert type based on message content for better visual feedback
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
                        <th>Grade & Section</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Mode</th>
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
                                <td class="student-info">
                                    <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($row['grade_level'] . ' - ' . $row['section']) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($row['start_time'])) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($row['end_time'])) ?></td>
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
                                        <span style="color: var(--color-muted); font-size: 0.85rem;">No actions</span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div class="empty-state-icon">📅</div>
                                    <p>No appointments found. Create your first appointment to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for creating new appointment -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Appointment</h2>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Student</label>
                <select name="student_id" required>
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students as $stu) { ?>
                        <option value="<?= $stu['id'] ?>">
                            <?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (' . $stu['grade_level'] . ' - ' . $stu['section'] . ')') ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="form-group">
                <label>Start Time</label>
                <input type="datetime-local" name="start_time" required>
            </div>

            <div class="form-group">
                <label>End Time</label>
                <input type="datetime-local" name="end_time" required>
            </div>

            <div class="form-group">
                <label>Mode</label>
                <select name="mode" required>
                    <option value="in-person">In-Person</option>
                    <option value="online">Online</option>
                    <option value="phone">Phone</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" placeholder="Add any additional notes or instructions..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="submit" name="create_appointment" class="btn-primary">Create Appointment</button>
                <button type="button" class="btn-secondary" onclick="document.getElementById('createModal').style.display='none'">Cancel</button>
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
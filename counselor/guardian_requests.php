<?php
include('../config/db.php');
include('../includes/auth_check.php');
checkRole(['counselor']);

$user_id = $_SESSION['user_id'];

// Fetch counselor info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counselor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle accepting guardian requests
if (isset($_POST['accept_request'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    // Update appointment to assign to this counselor
    $stmt = $conn->prepare("UPDATE appointments SET counselor_id = ?, status = 'scheduled', updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $counselor_db_id, $appointment_id);
    
    if ($stmt->execute()) {
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, action_summary, target_table, target_id, created_at) VALUES (?, 'UPDATE', 'Counselor accepted guardian appointment request', 'appointments', ?, NOW())");
        if ($log_stmt) {
            $log_stmt->bind_param("ii", $user_id, $appointment_id);
            $log_stmt->execute();
        }
        
        $_SESSION['msg'] = "✅ Appointment request accepted successfully!";
    } else {
        $_SESSION['msg'] = "❌ Failed to accept appointment: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: guardian_requests.php");
    exit();
}

// Handle declining guardian requests
if (isset($_POST['decline_request'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $reason = trim($_POST['decline_reason'] ?? '');
    
    // Update appointment to cancelled status
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled', notes = CONCAT(IFNULL(notes, ''), ' Declined by counselor. Reason: ', ?), updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("si", $reason, $appointment_id);
    
    if ($stmt->execute()) {
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, action_summary, target_table, target_id, created_at) VALUES (?, 'UPDATE', 'Counselor declined guardian appointment request', 'appointments', ?, NOW())");
        if ($log_stmt) {
            $log_stmt->bind_param("ii", $user_id, $appointment_id);
            $log_stmt->execute();
        }
        
        $_SESSION['msg'] = "✅ Appointment request declined successfully!";
    } else {
        $_SESSION['msg'] = "❌ Failed to decline appointment: " . $stmt->error;
    }
    
    $stmt->close();
    header("Location: guardian_requests.php");
    exit();
}

// Fetch guardian-requested appointments
$sql = "
SELECT 
    a.id, 
    a.appointment_code, 
    a.start_time, 
    a.end_time, 
    a.mode, 
    a.status, 
    a.notes,
    a.created_by,
    s.first_name, 
    s.last_name, 
    s.grade_level,
    sec.section_name as section,
    gu.full_name as guardian_name,
    gu.phone as guardian_phone,
    TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) as duration_minutes
FROM appointments a
JOIN students s ON a.student_id = s.id
LEFT JOIN sections sec ON s.section_id = sec.id
JOIN users cu ON a.created_by = cu.id
LEFT JOIN student_guardians sg ON s.id = sg.student_id AND sg.primary_guardian = 1
LEFT JOIN guardians g ON sg.guardian_id = g.id
LEFT JOIN users gu ON g.user_id = gu.id
WHERE a.counselor_id IS NULL 
    AND cu.role = 'guardian'
    AND a.status = 'scheduled'
ORDER BY a.start_time ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}
$stmt->execute();
$requests_result = $stmt->get_result();
$guardian_requests = [];
while ($row = $requests_result->fetch_assoc()) {
    $guardian_requests[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardian Appointment Requests - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/counselor_appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button class="toggle-btn" id="sidebarToggle">☰</button>
        
        <h2 class="logo">GOMS Counselor</h2>
        <div class="sidebar-user">
            <i class="fas fa-user-md"></i> Counselor · <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>
        </div>
        
        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        <a href="referrals.php" class="nav-link">
            <span class="icon"><i class="fas fa-clipboard-list"></i></span>
            <span class="label">Referrals</span>
        </a>
        <a href="appointments.php" class="nav-link">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>
        <a href="guardian_requests.php" class="nav-link active">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Appointment Requests</span>
        </a>
        <a href="sessions.php" class="nav-link">
            <span class="icon"><i class="fas fa-comments"></i></span>
            <span class="label">Sessions</span>
        </a>
        
        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <div class="content-wrap">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Guardian Appointment Requests</h1>
                    <p class="page-subtitle">Review and manage appointment requests from guardians</p>
                </div>
                <!-- <a href="appointments.php" class="btn">
                    <i class="fas fa-arrow-left"></i> Back to Appointments
                </a> -->
            </div>

            <?php if (isset($_SESSION['msg'])): 
                $alert_class = strpos($_SESSION['msg'], '❌') !== false || strpos($_SESSION['msg'], 'Failed') !== false ? 'alert-error' : 'alert-success';
            ?>
                <div class="alert <?= $alert_class ?>">
                    <i class="fas <?= $alert_class === 'alert-error' ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                    <?= $_SESSION['msg'] ?>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <!-- Requests Table -->
            <div class="table-container">
                <div class="table-wrapper">
                    <?php if (!empty($guardian_requests)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Appointment Code</th>
                                    <th>Student</th>
                                    <th>Guardian</th>
                                    <th>Time & Date</th>
                                    <th>Mode</th>
                                    <th>Duration</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guardian_requests as $request): 
                                    $duration_hours = floor($request['duration_minutes'] / 60);
                                    $duration_minutes = $request['duration_minutes'] % 60;
                                    $mode_class = 'mode-' . str_replace(' ', '-', strtolower($request['mode']));
                                ?>
                                    <tr>
                                        <td>
                                            <div class="appointment-code">
                                                <?= htmlspecialchars($request['appointment_code']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-name">
                                                    <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                                                </div>
                                                <div class="student-details">
                                                    Grade <?= htmlspecialchars($request['grade_level']) ?> · 
                                                    <?= htmlspecialchars($request['section'] ?? 'No Section') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="guardian-info">
                                                <i class="fas fa-user-shield"></i>
                                                <?= htmlspecialchars($request['guardian_name']) ?>
                                                <?php if ($request['guardian_phone']): ?>
                                                    <div class="contact-info">
                                                        <small><?= htmlspecialchars($request['guardian_phone']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="time-cell">
                                            <div class="time-date">
                                                <?= date("M d, Y", strtotime($request['start_time'])) ?>
                                            </div>
                                            <div class="time-range">
                                                <?= date("h:i A", strtotime($request['start_time'])) ?> - 
                                                <?= date("h:i A", strtotime($request['end_time'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="mode-badge <?= $mode_class ?>">
                                                <?= ucfirst($request['mode']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $duration_hours ?>h <?= $duration_minutes ?>m
                                        </td>
                                        <td class="notes-cell">
                                            <div class="notes-preview" title="<?= htmlspecialchars($request['notes']) ?>">
                                                <?= htmlspecialchars($request['notes'] ?: 'No notes') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <!-- Accept Button -->
                                                <form method="POST" class="inline-form">
                                                    <input type="hidden" name="appointment_id" value="<?= $request['id'] ?>">
                                                    <button type="submit" name="accept_request" class="btn-action btn-primary">
                                                        <i class="fas fa-check-circle"></i>
                                                        Accept
                                                    </button>
                                                </form>
                                                
                                                <!-- Decline Button with Modal -->
                                                <button type="button" class="btn-action btn-cancel" 
                                                        onclick="showDeclineModal(<?= $request['id'] ?>, '<?= htmlspecialchars($request['appointment_code']) ?>')">
                                                    <i class="fas fa-times"></i>
                                                    Decline
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--clr-success);"></i>
                            </div>
                            <h3>No Pending Requests</h3>
                            <p>All guardian appointment requests have been processed.</p>
                            <!-- <a href="appointments.php" class="btn btn-primary">
                                <i class="fas fa-calendar-alt"></i> View Your Appointments
                            </a> -->
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Decline Modal -->
    <div id="declineModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-times-circle"></i>
                    Decline Appointment Request
                </h2>
                <button class="btn-close" onclick="closeDeclineModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="declineForm">
                <input type="hidden" name="appointment_id" id="decline_appointment_id">
                <div class="form-body">
                    <p>You are declining appointment request: <strong id="decline_appointment_code"></strong></p>
                    
                    <div class="form-group">
                        <label class="form-label" for="decline_reason">
                            <i class="fas fa-comment-alt"></i> Reason for Declining (Optional)
                        </label>
                        <textarea class="form-control" name="decline_reason" id="decline_reason" 
                                  placeholder="Provide a reason for declining this request (will be added to notes)..."
                                  rows="3"></textarea>
                        <div class="form-help">
                            This helps guardians understand why their request was declined.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeDeclineModal()">
                        Cancel
                    </button>
                    <button type="submit" name="decline_request" class="btn-cancel">
                        <i class="fas fa-times"></i> Decline Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });

        // Decline Modal Functions
        function showDeclineModal(appointmentId, appointmentCode) {
            document.getElementById('decline_appointment_id').value = appointmentId;
            document.getElementById('decline_appointment_code').textContent = appointmentCode;
            document.getElementById('declineModal').style.display = 'flex';
        }

        function closeDeclineModal() {
            document.getElementById('declineModal').style.display = 'none';
            document.getElementById('decline_reason').value = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('declineModal');
            if (event.target == modal) {
                closeDeclineModal();
            }
        }

        // Confirm before accepting
        document.querySelectorAll('form[name="accept_request"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Accept this appointment request? You will be assigned as the counselor for this session.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
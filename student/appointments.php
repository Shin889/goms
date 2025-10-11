<?php
include('../includes/auth_check.php');
checkRole(['student']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$user_id = intval($_SESSION['user_id']);

// Fetch student ID safely
$student_sql = "SELECT id FROM students WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_id = $student['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];
    $appointment_code = "APT-" . date("Y") . "-" . rand(1000,9999);

    $stmt = $conn->prepare("INSERT INTO appointments (appointment_code, requested_by_user_id, student_id, start_time, end_time, mode, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siissss", $appointment_code, $user_id, $student_id, $start, $end, $mode, $notes);

    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;
        logAction($user_id, 'Request Appointment', 'appointments', $appointment_id, 'Student requested appointment.');

        // Send SMS with prepared statement
        $guardian_sql = "
          SELECT g.phone 
          FROM student_guardians sg 
          JOIN guardians g ON sg.guardian_id = g.id 
          WHERE sg.student_id = ?
        ";
        $guardian_stmt = $conn->prepare($guardian_sql);
        $guardian_stmt->bind_param("i", $student_id);
        $guardian_stmt->execute();
        $guardian_result = $guardian_stmt->get_result();
        $guardian = $guardian_result->fetch_assoc();

        if ($guardian && !empty($guardian['phone'])) {
            $msg = "Your child has requested a counseling appointment on $start. Please take note of the schedule.";
            sendSMS($user_id, $guardian['phone'], $msg);
        }

        echo "<script>alert('Appointment request submitted!'); window.location='appointments.php';</script>";
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}

// Fetch appointments with prepared statement
$appointments_sql = "SELECT * FROM appointments WHERE requested_by_user_id = ? ORDER BY created_at DESC";
$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param("i", $user_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Appointments</title>
  <link rel="stylesheet" href="../utils/css/root.css"> <!-- Global root vars -->
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
      max-width: 1200px;
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
      margin-bottom: 30px;
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 30px;
      margin-bottom: 30px;
    }

    .card h3 {
      margin-top: 0;
      color: var(--color-primary);
      font-size: 1.2rem;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 24px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--color-text);
      font-size: 0.95rem;
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
      font-family: var(--font-family);
      font-size: 0.95rem;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }

    input[type="datetime-local"]:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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
      min-height: 100px;
    }

    .btn-primary {
      display: inline-block;
      background: var(--color-primary);
      color: #fff;
      padding: 12px 28px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: var(--color-secondary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .help-text {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-top: 4px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }

    th, td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--color-border);
      text-align: left;
      white-space: nowrap;
    }

    th {
      background: rgba(255, 255, 255, 0.05);
      color: var(--color-secondary);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.85rem;
    }

    tr:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .badge {
      font-size: 0.8rem;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 10px;
      display: inline-block;
    }

    .status-pending {
      background: rgba(245, 158, 11, 0.15);
      color: #f59e0b;
    }

    .status-approved {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
    }

    .status-rejected {
      background: rgba(239, 68, 68, 0.15);
      color: #ef4444;
    }

    .status-completed {
      background: rgba(59, 130, 246, 0.15);
      color: #3b82f6;
    }

    .mode-badge {
      font-size: 0.8rem;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 10px;
      display: inline-block;
      background: rgba(99, 102, 241, 0.15);
      color: var(--color-primary);
    }

    .datetime {
      color: var(--color-muted);
      font-size: 0.9rem;
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .card {
        padding: 20px;
      }

      table {
        font-size: 0.85rem;
      }

      th, td {
        padding: 10px 8px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Request Appointment</h2>
    <p class="page-subtitle">Schedule a counseling session with a guidance counselor.</p>

    <div class="card">
      <h3>📅 New Appointment Request</h3>
      <form method="POST" action="">
        <div class="form-group">
          <label for="start_time">Start Time</label>
          <input type="datetime-local" id="start_time" name="start_time" required>
          <p class="help-text">Select your preferred appointment start time.</p>
        </div>

        <div class="form-group">
          <label for="end_time">End Time</label>
          <input type="datetime-local" id="end_time" name="end_time" required>
          <p class="help-text">Select your preferred appointment end time.</p>
        </div>

        <div class="form-group">
          <label for="mode">Appointment Mode</label>
          <select id="mode" name="mode">
            <option value="in-person">In-Person Meeting</option>
            <option value="online">Online Video Call</option>
            <option value="phone">Phone Call</option>
          </select>
          <p class="help-text">Choose how you'd like to attend the session.</p>
        </div>

        <div class="form-group">
          <label for="notes">Notes (Optional)</label>
          <textarea id="notes" name="notes" placeholder="Any additional information or concerns"></textarea>
          <p class="help-text">Provide any relevant context for your appointment.</p>
        </div>

        <button type="submit" class="btn-primary">📨 Submit Request</button>
      </form>
    </div>

    <div class="card">
      <h3>📋 Your Appointments</h3>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Mode</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($appointments_result->num_rows > 0): ?>
              <?php while($row = $appointments_result->fetch_assoc()): 
                $status_class = 'status-' . strtolower($row['status']);
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['appointment_code']); ?></strong></td>
                  <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['start_time'])); ?></td>
                  <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['end_time'])); ?></td>
                  <td><span class="mode-badge"><?= ucfirst($row['mode']); ?></span></td>
                  <td><span class="badge <?= $status_class; ?>"><?= ucfirst($row['status']); ?></span></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="empty">No appointments yet. Submit your first request above!</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
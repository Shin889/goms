<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');
include('../includes/functions.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info safely
$guardian_sql = "SELECT * FROM guardians WHERE user_id = ?";
$guardian_stmt = $conn->prepare($guardian_sql);
$guardian_stmt->bind_param("i", $user_id);
$guardian_stmt->execute();
$guardian_result = $guardian_stmt->get_result();
$guardian = $guardian_result->fetch_assoc();
$guardian_id = $guardian['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];
    $code = "APT-" . date("Y") . "-" . rand(1000,9999);

    $stmt = $conn->prepare("INSERT INTO appointments (appointment_code, requested_by_user_id, student_id, start_time, end_time, mode, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siissss", $code, $user_id, $student_id, $start, $end, $mode, $notes);

    if ($stmt->execute()) {
        $appointment_id = $stmt->insert_id;
        logAction($user_id, 'Request Appointment', 'appointments', $appointment_id, "Guardian requested appointment for student #$student_id");
        echo "<script>alert('Appointment request sent!'); window.location='appointments.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// Fetch linked students with prepared statement
$linked_sql = "
  SELECT s.id, s.first_name, s.last_name
  FROM student_guardians sg
  JOIN students s ON sg.student_id = s.id
  WHERE sg.guardian_id = ?
";
$linked_stmt = $conn->prepare($linked_sql);
$linked_stmt->bind_param("i", $guardian_id);
$linked_stmt->execute();
$linked_result = $linked_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Request Appointment</title>
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
      margin-bottom: 30px;
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 30px;
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

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: var(--color-secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: var(--color-primary);
    }

    .help-text {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-top: 4px;
    }

    .info-box {
      background: rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(99, 102, 241, 0.2);
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
    }

    .info-box p {
      margin: 0;
      font-size: 0.9rem;
      color: var(--color-text);
      line-height: 1.6;
    }

    .info-box strong {
      color: var(--color-primary);
    }

    .no-students {
      text-align: center;
      padding: 40px 20px;
    }

    .no-students-icon {
      font-size: 3rem;
      margin-bottom: 16px;
      opacity: 0.5;
    }

    .no-students h3 {
      color: var(--color-text);
      margin-bottom: 8px;
    }

    .no-students p {
      color: var(--color-muted);
      margin-bottom: 20px;
    }

    .btn-link {
      display: inline-block;
      background: var(--color-primary);
      color: #fff;
      padding: 10px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }

    .btn-link:hover {
      background: var(--color-secondary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .card {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">    
    <h2 class="page-title">Request Appointment for Your Child</h2>
    <p class="page-subtitle">Schedule a counseling session for one of your linked students.</p>

    <div class="card">
      <?php if ($linked_result->num_rows > 0): ?>
        <div class="info-box">
          <p><strong>Note:</strong> After submitting, the counselor will review and approve your appointment request. You will be notified of the status.</p>
        </div>

        <form method="POST" action="">
          <div class="form-group">
            <label for="student_id">Select Child</label>
            <select id="student_id" name="student_id" required>
              <option value="">-- Choose Your Child --</option>
              <?php while($s = $linked_result->fetch_assoc()): ?>
                <option value="<?= $s['id']; ?>">
                  <?= htmlspecialchars($s['first_name'].' '.$s['last_name']); ?>
                </option>
              <?php endwhile; ?>
            </select>
            <p class="help-text">Select which child needs the counseling appointment.</p>
          </div>

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
            <p class="help-text">Choose how your child will attend the session.</p>
          </div>

          <div class="form-group">
            <label for="notes">Additional Notes (Optional)</label>
            <textarea id="notes" name="notes" placeholder="Any relevant information or concerns about your child"></textarea>
            <p class="help-text">Provide any context that might help the counselor prepare for the session.</p>
          </div>

          <button type="submit" class="btn-primary">📨 Submit Appointment Request</button>
        </form>
      <?php else: ?>
        <div class="no-students">
          <div class="no-students-icon">🔗</div>
          <h3>No Linked Students</h3>
          <p>You need to link a student to your account before requesting appointments.</p>
          <a href="link_student.php" class="btn-link">🔗 Link a Student</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
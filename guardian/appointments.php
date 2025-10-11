<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian ID safely
$guardian_sql = "SELECT * FROM guardians WHERE user_id = ?";
$guardian_stmt = $conn->prepare($guardian_sql);
$guardian_stmt->bind_param("i", $user_id);
$guardian_stmt->execute();
$guardian_result = $guardian_stmt->get_result();
$guardian = $guardian_result->fetch_assoc();
$guardian_id = $guardian ? $guardian['id'] : 0;

// Get linked students with prepared statement
$linked_sql = "
  SELECT s.id, s.first_name, s.last_name
  FROM student_guardians sg
  JOIN students s ON sg.student_id = s.id
  WHERE sg.guardian_id = ?
";
$linked_stmt = $conn->prepare($linked_sql);
$linked_stmt->bind_param("i", $guardian_id);
$linked_stmt->execute();
$linked_students = $linked_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Child's Appointments</title>
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

    .student-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 2px solid var(--color-border);
    }

    .student-header h3 {
      margin: 0;
      color: var(--color-primary);
      font-size: 1.2rem;
      font-weight: 700;
    }

    .student-icon {
      font-size: 1.5rem;
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
    }

    th {
      background: rgba(255, 255, 255, 0.05);
      color: var(--color-secondary);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.85rem;
      white-space: nowrap;
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
      white-space: nowrap;
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }

    .no-students {
      text-align: center;
      padding: 60px 20px;
    }

    .no-students-icon {
      font-size: 4rem;
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

      table {
        font-size: 0.85rem;
      }

      th, td {
        padding: 10px 8px;
      }

      .student-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Your Child's Appointments</h2>
    <p class="page-subtitle">View all scheduled counseling appointments for your linked students.</p>

    <?php if ($linked_students->num_rows > 0): ?>
      <?php while ($s = $linked_students->fetch_assoc()): 
        $sid = intval($s['id']);
        
        // Fetch appointments for this student with prepared statement
        $appointments_sql = "SELECT * FROM appointments WHERE student_id = ? ORDER BY created_at DESC";
        $appointments_stmt = $conn->prepare($appointments_sql);
        $appointments_stmt->bind_param("i", $sid);
        $appointments_stmt->execute();
        $appointments = $appointments_stmt->get_result();
      ?>
        <div class="card">
          <div class="student-header">
            <span class="student-icon">👤</span>
            <h3><?= htmlspecialchars($s['first_name'].' '.$s['last_name']); ?></h3>
          </div>

          <div style="overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th>Appointment Code</th>
                  <th>Start Time</th>
                  <th>End Time</th>
                  <th>Mode</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($appointments->num_rows > 0): ?>
                  <?php while($a = $appointments->fetch_assoc()): 
                    $status_class = 'status-' . strtolower($a['status']);
                  ?>
                    <tr>
                      <td><strong><?= htmlspecialchars($a['appointment_code']); ?></strong></td>
                      <td class="datetime"><?= date('M d, Y h:i A', strtotime($a['start_time'])); ?></td>
                      <td class="datetime"><?= date('M d, Y h:i A', strtotime($a['end_time'])); ?></td>
                      <td><span class="mode-badge"><?= ucfirst($a['mode']); ?></span></td>
                      <td><span class="badge <?= $status_class; ?>"><?= ucfirst($a['status']); ?></span></td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="5" class="empty">No appointments scheduled for this student yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="card">
        <div class="no-students">
          <div class="no-students-icon">🔗</div>
          <h3>No Linked Students</h3>
          <p>You haven't linked any students to your account yet.</p>
          <a href="link_student.php" class="btn-link">🔗 Link a Student</a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
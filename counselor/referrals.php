<?php
include('../includes/auth_check.php');
checkRole(['counselor']); 

include('../config/db.php');

$sql = "
    SELECT 
        r.id, 
        r.referral_reason, 
        r.priority, 
        r.status, 
        r.created_at,
        c.complaint_code, 
        s.first_name, 
        s.last_name, 
        a.full_name AS adviser_name
    FROM referrals r
    JOIN complaints c ON r.complaint_id = c.id
    JOIN students s ON c.student_id = s.id
    JOIN users a ON r.adviser_id = a.id
    ORDER BY r.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    error_log("Referrals query failed: " . $conn->error);
    $error_message = "Could not load referral data. Please check the logs.";
    $result = false; 
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Referrals</title>
  <link rel="stylesheet" href="../utils/css/root.css"> <
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--clr-bg); 
      color: var(--clr-text); 
      min-height: 100vh;
      padding: var(--padding-lg); 
      box-sizing: border-box;
    }

    .page-container {
      max-width: 1400px;
      margin: 0 auto;
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
      margin-bottom: 25px;
    }

    .card {
      background: var(--clr-surface); 
      border: 1px solid var(--clr-border); 
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 20px;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: var(--fs-normal);
    }

    th, td {
      padding: 15px 14px;
      border-bottom: 1px solid var(--clr-border-light); 
      text-align: left;
      white-space: nowrap;
    }

    th {
      background: var(--clr-bg-light); 
      color: var(--clr-secondary); 
      font-weight: 600;
      text-transform: uppercase;
      font-size: var(--fs-xsmall);
    }

    tr:hover {
      background: var(--clr-hover); 
    }

    .badge {
      font-size: var(--fs-xsmall);
      font-weight: 600;
      padding: 5px 10px;
      border-radius: 12px;
      display: inline-block;
    }

    .priority-high {
      background: rgba(239, 68, 68, 0.15); 
      color: #ef4444;
    }

    .priority-medium {
      background: rgba(245, 158, 11, 0.15); 
      color: #f59e0b;
    }

    .priority-low {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
    }

    /* Status Badges */
    .status-pending {
      background: rgba(245, 158, 11, 0.15);
      color: #f59e0b;
    }

    .status-scheduled {
      background: rgba(59, 130, 246, 0.15); 
      color: #3b82f6;
    }

    .status-completed {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
    }

    .btn-action {
      color: var(--clr-primary); 
      font-weight: 600;
      text-decoration: none;
      transition: color var(--time-transition);
    }

    .btn-action:hover {
      color: var(--clr-secondary);
    }

    .empty {
      text-align: center;
      color: var(--clr-muted); 
      padding: 20px 0;
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Incoming Referrals</h2>
    <p class="page-subtitle">Review and manage student referrals from advisers.</p>

    <div class="card">
      <?php if (isset($error_message)): ?>
          <p class="empty" style="color: var(--clr-error); font-weight: 600;"><?= htmlspecialchars($error_message); ?></p>
      <?php elseif ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Complaint Code</th>
              <th>Student</th>
              <th>Adviser</th>
              <th>Reason</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): 
              $priority_class = 'priority-' . strtolower($row['priority']);
              $status_class = 'status-' . strtolower($row['status']);
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['complaint_code']); ?></strong></td>
                <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
                <td><?= htmlspecialchars($row['adviser_name']); ?></td>
                <td><?= htmlspecialchars($row['referral_reason']); ?></td>
                <td>
                  <span class="badge <?= $priority_class; ?>">
                    <i class="fas fa-flag"></i> <?= ucfirst($row['priority']); ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $status_class; ?>">
                    <i class="fas fa-circle-notch"></i> <?= ucfirst($row['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="create_appointment.php?referral_id=<?= $row['id']; ?>" class="btn-action">
                    <i class="fas fa-calendar-plus"></i> Book Session
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <tr><td colspan="7" class="empty">No referrals found.</td></tr>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
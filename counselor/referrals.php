<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');

// Safe query with error handling
$sql = "
  SELECT r.*, c.complaint_code, s.first_name, s.last_name, a.full_name AS adviser_name
  FROM referrals r
  JOIN complaints c ON r.complaint_id = c.id
  JOIN students s ON c.student_id = s.id
  JOIN users a ON r.adviser_id = a.id
  ORDER BY r.created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Referrals</title>
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
      max-width: 1400px;
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

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 20px;
      overflow-x: auto;
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
      color: var(--color-primary);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .btn-action:hover {
      color: var(--color-secondary);
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Incoming Referrals</h2>
    <p class="page-subtitle">Review and manage student referrals from advisers.</p>

    <div class="card">
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
          <?php if ($result->num_rows > 0): ?>
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
                    <?= ucfirst($row['priority']); ?>
                  </span>
                </td>
                <td>
                  <span class="badge <?= $status_class; ?>">
                    <?= ucfirst($row['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="create_appointment.php?referral_id=<?= $row['id']; ?>" class="btn-action">
                    Book Session
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="empty">No referrals found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
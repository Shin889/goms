<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = $_SESSION['user_id'];

$result = $conn->query("
  SELECT r.*, c.complaint_code, c.content
  FROM referrals r
  LEFT JOIN complaints c ON r.complaint_id = c.id
  WHERE r.adviser_id = $user_id
  ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Referrals - GOMS</title>
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

    .priority-low {
      color: #22c55e;
      font-weight: 600;
    }

    .priority-medium {
      color: #f59e0b;
      font-weight: 600;
    }

    .priority-high {
      color: #ef4444;
      font-weight: 600;
    }

    .priority-urgent {
      color: #dc2626;
      font-weight: 700;
    }

    .status-pending {
      color: #f59e0b;
      font-weight: 600;
    }

    .status-in_progress {
      color: #3b82f6;
      font-weight: 600;
    }

    .status-completed {
      color: #22c55e;
      font-weight: 600;
    }

    .status-closed {
      color: var(--color-muted);
      font-weight: 600;
    }

    .reason-cell {
      max-width: 350px;
      white-space: normal;
      word-wrap: break-word;
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }

    a.back-link {
      display: inline-block;
      margin-bottom: 14px;
      color: var(--color-secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s ease;
    }

    a.back-link:hover {
      color: var(--color-primary);
    }
  </style>
</head>
<body>
  <div class="page-container">    
    <h2 class="page-title">Your Referrals</h2>
    <p class="page-subtitle">Track and manage all referrals you've created.</p>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Referral ID</th>
            <th>Complaint Code</th>
            <th>Reason</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id']; ?></td>
                <td><?= htmlspecialchars($row['complaint_code']); ?></td>
                <td class="reason-cell"><?= htmlspecialchars($row['referral_reason']); ?></td>
                <td>
                  <span class="priority-<?= strtolower($row['priority']); ?>">
                    <?= ucfirst($row['priority']); ?>
                  </span>
                </td>
                <td>
                  <span class="status-<?= strtolower($row['status']); ?>">
                    <?= ucfirst(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </td>
                <td><?= date('M d, Y', strtotime($row['created_at'])); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" class="empty">No referrals found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
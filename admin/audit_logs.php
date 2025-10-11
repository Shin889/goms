<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

$result = $conn->query("SELECT a.*, u.username 
                        FROM audit_logs a 
                        LEFT JOIN users u ON a.user_id = u.id
                        ORDER BY a.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="../utils/css/root.css"> <!-- Use global theme -->
  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--color-bg);
      color: var(--color-text);
      padding: 40px;
      min-height: 100vh;
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
    <h2 class="page-title">Audit Logs</h2>
    <p class="page-subtitle">Monitor system activities and track administrative actions performed across the platform.</p>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Action</th>
            <th>Table</th>
            <th>Target ID</th>
            <th>Details</th>
            <th>IP Address</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($log = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $log['id']; ?></td>
                <td><?= htmlspecialchars($log['username']); ?></td>
                <td><?= htmlspecialchars($log['action']); ?></td>
                <td><?= htmlspecialchars($log['target_table']); ?></td>
                <td><?= htmlspecialchars($log['target_id']); ?></td>
                <td><?= htmlspecialchars($log['details']); ?></td>
                <td><?= htmlspecialchars($log['ip_address']); ?></td>
                <td><?= htmlspecialchars($log['created_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="empty">No audit logs recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

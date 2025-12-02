<?php
include('../includes/auth_check.php');
checkRole(['admin']); 

include('../config/db.php');

$result = $conn->query("
    SELECT 
        a.id, 
        a.action, 
        a.target_table, 
        a.target_id, 
        a.details, 
        a.created_at, 
        u.username 
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="../utils/css/root.css"> 
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>    
    .page-container {
      max-width: 1200px;
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
      white-space: normal; 
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

    .log-details {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: pre-wrap;
        word-break: break-word;
    }
    
    .log-date {
        white-space: nowrap;
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
    }
    
    .badge-action {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        display: inline-block;
        text-transform: uppercase;
    }
    .badge-action.create { background: var(--clr-success-light); color: var(--clr-success); }
    .badge-action.update { background: var(--clr-primary-light); color: var(--clr-primary); }
    .badge-action.delete { background: var(--clr-error-light); color: var(--clr-error); }
    .badge-action.login, .badge-action.logout { background: var(--clr-warning-light); color: var(--clr-warning); }

    .empty {
      text-align: center;
      color: var(--clr-muted);
      padding: 20px 0;
    }
    
    @media (max-width: 900px) {
        .page-container {
            padding: 0 10px;
        }
        th, td {
            padding: 10px 8px;
        }
        .card {
            padding: 10px;
        }
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
            <th>Action Type</th>
            <!-- <th>Target Table</th> -->
           <!--  <th>Target ID</th> -->
            <th>Details</th>
            <th>Date/Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($log = $result->fetch_assoc()): 
                $action_class = strtolower(str_replace(' ', '', $log['action']));
            ?>
              <tr>
                <td><?= $log['id']; ?></td>
                <td><?= htmlspecialchars($log['username'] ?? 'System/Unknown'); ?></td>
                <td><span class="badge-action <?= $action_class; ?>"><?= htmlspecialchars($log['action']); ?></span></td>
               <!--  <td><?= htmlspecialchars($log['target_table'] ?? 'N/A'); ?></td> -->
                <!-- <td><?= htmlspecialchars($log['target_id'] ?? 'N/A'); ?></td> -->
                <td class="log-details"><?= htmlspecialchars($log['details'] ?? 'No details provided'); ?></td>
                <td><span class="log-date"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></span></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="empty">No audit logs recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
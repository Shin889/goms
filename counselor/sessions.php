<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');

$counselor_id = intval($_SESSION['user_id']);

// Safe query with error handling
$sql = "
  SELECT s.*, st.first_name, st.last_name
  FROM sessions s
  JOIN students st ON s.student_id = st.id
  WHERE s.counselor_id = ?
  ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sessions</title>
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

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }

    h2.page-title {
      font-size: 1.6rem;
      color: var(--color-primary);
      font-weight: 700;
      margin: 0;
    }

    p.page-subtitle {
      color: var(--color-muted);
      font-size: 0.95rem;
      margin-bottom: 20px;
      margin-top: 4px;
    }

    .btn-primary {
      display: inline-block;
      background: var(--color-primary);
      color: #fff;
      padding: 10px 20px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: var(--color-secondary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
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

    .status-scheduled {
      background: rgba(59, 130, 246, 0.15);
      color: #3b82f6;
    }

    .status-completed {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
    }

    .status-cancelled {
      background: rgba(239, 68, 68, 0.15);
      color: #ef4444;
    }

    .status-ongoing {
      background: rgba(245, 158, 11, 0.15);
      color: #f59e0b;
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

    .datetime {
      color: var(--color-muted);
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <div class="page-header">
      <div>
        <h2 class="page-title">My Counseling Sessions</h2>
        <p class="page-subtitle">View and manage all your counseling sessions.</p>
      </div>
      <a href="create_session.php" class="btn-primary">+ Create New Session</a>
    </div>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
              $status_class = 'status-' . strtolower($row['status']);
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></strong></td>
                <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['start_time'])); ?></td>
                <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['end_time'])); ?></td>
                <td>
                  <span class="badge <?= $status_class; ?>">
                    <?= ucfirst($row['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="create_report.php?session_id=<?= $row['id']; ?>" class="btn-action">
                    Create Report
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty">No sessions found. Create your first session!</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
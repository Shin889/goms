<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');
include('../includes/functions.php');
$user_id = $_SESSION['user_id'];
// Fetch adviser info (to get their section)
$adviser = $conn->query("SELECT * FROM advisers WHERE user_id = $user_id")->fetch_assoc();
$section = $adviser['section'] ?? '';
$result = $conn->query("
  SELECT c.*, s.section, s.first_name, s.last_name
  FROM complaints c
  JOIN students s ON c.student_id = s.id
  WHERE s.section = '$section'
  ORDER BY c.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Complaints - GOMS</title>
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

    .status-new {
      color: #3b82f6;
      font-weight: 600;
    }

    .status-under_review {
      color: #f59e0b;
      font-weight: 600;
    }

    .status-resolved {
      color: #22c55e;
      font-weight: 600;
    }

    .status-closed {
      color: var(--color-muted);
      font-weight: 600;
    }

    .btn-referral {
      color: var(--color-primary);
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .btn-referral:hover {
      color: var(--color-secondary);
    }

    .badge {
      background: var(--color-primary);
      color: #fff;
      font-size: 0.8rem;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 10px;
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }

    .content-cell {
      max-width: 300px;
      white-space: normal;
      word-wrap: break-word;
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
    <h2 class="page-title">Student Complaints - Section <?= htmlspecialchars($section); ?></h2>
    <p class="page-subtitle">Manage and review complaints from your section.</p>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Student</th>
            <th>Content</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['complaint_code']); ?></td>
                <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></td>
                <td class="content-cell"><?= htmlspecialchars($row['content']); ?></td>
                <td>
                  <span class="status-<?= $row['status']; ?>">
                    <?= ucfirst(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </td>
                <td>
                  <?php if ($row['status'] == 'new' || $row['status'] == 'under_review'): ?>
                    <a href="create_referral.php?complaint_id=<?= $row['id']; ?>" class="btn-referral">
                      Create Referral
                    </a>
                  <?php else: ?>
                    <span style="color: var(--color-muted);">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty">No complaints found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
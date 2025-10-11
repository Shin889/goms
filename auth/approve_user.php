<?php
session_start();
include('../config/db.php');

// Restrict access to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Approve user
if (isset($_GET['approve'])) {
    $user_id = intval($_GET['approve']);
    $admin_id = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("
        UPDATE users 
        SET is_active = 1, 
            approved_by = ?, 
            approved_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $admin_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo "<script>alert('User approved successfully!'); window.location='approve_user.php';</script>";
        } else {
            echo "<script>alert('No user updated. Maybe already active or invalid ID.'); window.location='approve_user.php';</script>";
        }
    } else {
        echo "<script>alert('Error: " . addslashes($stmt->error) . "');</script>";
    }
    exit;
}

// Fetch all pending users
$result = $conn->query("SELECT * FROM users WHERE is_active = 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Approve Users</title>
  <link rel="stylesheet" href="../utils/css/root.css">
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
      max-width: 1000px;
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

    .btn-approve {
      background: var(--color-primary);
      color: #fff;
      padding: 8px 14px;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: background 0.2s ease;
      display: inline-block;
    }

    .btn-approve:hover {
      background: var(--color-secondary);
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
    <h2 class="page-title">Pending User Approvals</h2>
    <p class="page-subtitle">Approve newly registered accounts before they gain access to the system.</p>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Full Name</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><span class="badge"><?= ucfirst($row['role']); ?></span></td>
                <td><?= htmlspecialchars($row['full_name']); ?></td>
                <td><a href="?approve=<?= $row['id']; ?>" class="btn-approve">Approve</a></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="4" class="empty">No pending users at the moment.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

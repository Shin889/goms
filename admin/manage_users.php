<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');
include('../includes/functions.php');

// Handle delete request
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id=$id");
    logAction($_SESSION['user_id'], 'Delete User', 'users', $id, 'Admin deleted user.');
    header("Location: manage_users.php");
    exit;
}

// Fetch all users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Users</title>
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

    .status-active {
      color: #22c55e;
      font-weight: 600;
    }

    .status-pending {
      color: #f59e0b;
      font-weight: 600;
    }

    .btn-delete {
      color: #ef4444;
      font-weight: 600;
      text-decoration: none;
      transition: color 0.2s ease;
    }

    .btn-delete:hover {
      color: #dc2626;
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
    <h2 class="page-title">User Management</h2>
    <p class="page-subtitle">Manage all user accounts in the system.</p>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id']; ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><span class="badge"><?= ucfirst($row['role']); ?></span></td>
                <td><?= htmlspecialchars($row['full_name']); ?></td>
                <td><?= htmlspecialchars($row['email']); ?></td>
                <td><?= htmlspecialchars($row['phone']); ?></td>
                <td>
                  <span class="<?= $row['is_active'] ? 'status-active' : 'status-pending'; ?>">
                    <?= $row['is_active'] ? 'Active' : 'Pending'; ?>
                  </span>
                </td>
                <td>
                  <a href="?delete=<?= $row['id']; ?>" 
                     class="btn-delete" 
                     onclick="return confirm('Delete this user?')">
                     Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="empty">No users found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>

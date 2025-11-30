<?php
include('../includes/auth_check.php');
checkRole(['admin']); 

include('../config/db.php');
include('../includes/functions.php');

$status_message = '';
$status_type = '';

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            if (function_exists('logAction')) {
                logAction($_SESSION['user_id'], 'Delete User', 'users', $id, 'Admin permanently deleted user ID ' . $id);
            }
            $_SESSION['status_message'] = "User ID: {$id} permanently deleted.";
            $_SESSION['status_type'] = 'error'; 
        } else {
            $_SESSION['status_message'] = "User ID: {$id} not found or already deleted.";
            $_SESSION['status_type'] = 'info';
        }
    } else {
        $_SESSION['status_message'] = "Database Error: " . $stmt->error;
        $_SESSION['status_type'] = 'error';
    }
    
    header("Location: manage_users.php");
    exit;
}

if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    $status_type = $_SESSION['status_type'];
    unset($_SESSION['status_message']);
    unset($_SESSION['status_type']);
}

$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users</title>
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
    
    .status-message {
        padding: 12px 20px;
        margin-bottom: 25px;
        border-radius: var(--radius-md);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .status-message.success {
        background-color: var(--clr-success-light);
        color: var(--clr-success);
        border: 1px solid var(--clr-success);
    }
    .status-message.error {
        background-color: var(--clr-error-light);
        color: var(--clr-error);
        border: 1px solid var(--clr-error);
    }
    .status-message.info {
        background-color: var(--clr-warning-light);
        color: var(--clr-warning);
        border: 1px solid var(--clr-warning);
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

    /* Status Badges */
    .status-active {
      color: var(--clr-success);
      font-weight: 600;
    }

    .status-pending {
      color: var(--clr-warning);
      font-weight: 600;
    }

    .btn-delete {
      color: var(--clr-error);
      font-weight: 600;
      text-decoration: none;
      transition: color var(--time-transition);
    }

    .btn-delete:hover {
      color: #dc2626; /* Darker red on hover */
    }

    .badge {
      background: var(--clr-primary);
      color: #fff;
      font-size: var(--fs-xsmall);
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 12px;
      display: inline-block;
    }
    
    .badge.admin {
        background: #10b981; 
    }
    .badge.counselor {
        background: #3b82f6;
    }
    .badge.student {
        background: #a855f7; 
    }

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
    <h2 class="page-title">User Management</h2>
    <p class="page-subtitle">Manage all user accounts and their statuses across the system.</p>
    
    <?php if ($status_message): ?>
        <div class="status-message <?= $status_type; ?>">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars($status_message); ?>
        </div>
    <?php endif; ?>

    <div class="card">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Role</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                $role_class = strtolower($row['role']);
            ?>
              <tr>
                <td><?= $row['id']; ?></td>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><span class="badge <?= $role_class; ?>"><?= ucfirst($row['role']); ?></span></td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                <td><?= htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                <td>
                  <span class="<?= $row['is_active'] ? 'status-active' : 'status-pending'; ?>">
                    <?= $row['is_active'] ? '<i class="fas fa-check-circle"></i> Active' : '<i class="fas fa-clock"></i> Pending'; ?>
                  </span>
                </td>
                <td><?= date('Y-m-d', strtotime($row['created_at'])); ?></td>
                <td>
                  <a href="?delete=<?= $row['id']; ?>" 
                    class="btn-delete" 
                    onclick="return confirm('WARNING: Are you sure you want to PERMANENTLY delete user <?= htmlspecialchars($row['username']); ?>? This action cannot be undone.');">
                    <i class="fas fa-trash-alt"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8" class="empty">No users found in the database.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
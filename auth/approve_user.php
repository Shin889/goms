<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$status_message = '';
$status_type = '';

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
            $_SESSION['status_message'] = "User approved successfully!";
            $_SESSION['status_type'] = 'success';
        } else {
            $_SESSION['status_message'] = "No user updated. Maybe already active or invalid ID.";
            $_SESSION['status_type'] = 'info';
        }
    } else {
        $_SESSION['status_message'] = "Database Error: " . $stmt->error;
        $_SESSION['status_type'] = 'error';
    }
    
    header('Location: approve_user.php');
    exit;
}

if (isset($_GET['disapprove'])) {
    $user_id = intval($_GET['disapprove']);
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_active = 0");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['status_message'] = "User registration disapproved (deleted) successfully.";
            $_SESSION['status_type'] = 'warning';
        } else {
            $_SESSION['status_message'] = "Could not disapprove user. Invalid ID or user is already active.";
            $_SESSION['status_type'] = 'info';
        }
    } else {
        $_SESSION['status_message'] = "Database Error: " . $stmt->error;
        $_SESSION['status_type'] = 'error';
    }
    
    header('Location: approve_user.php');
    exit;
}

if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    $status_type = $_SESSION['status_type'];
    unset($_SESSION['status_message']);
    unset($_SESSION['status_type']);
}


$result = $conn->query("SELECT id, username, role, full_name, created_at FROM users WHERE is_active = 0");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approve Users</title>
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
    .status-message.warning, .status-message.info {
        background-color: var(--clr-warning-light);
        color: var(--clr-warning);
        border: 1px solid var(--clr-warning);
    }

    /* Action Buttons */
    .btn-approve {
      background: var(--clr-primary); 
      color: #fff;
      padding: 8px 14px;
      border-radius: var(--radius-sm);
      font-weight: 600;
      text-decoration: none;
      transition: all var(--time-transition);
      display: inline-block;
      margin-right: 5px;
    }

    .btn-approve:hover {
      background: var(--clr-secondary); 
    }
    
    .btn-disapprove {
        background: var(--clr-error); 
        color: #fff;
        padding: 8px 14px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        text-decoration: none;
        transition: all var(--time-transition);
        display: inline-block;
    }
    
    .btn-disapprove:hover {
        background: #e53935; 
    }
    
    .badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        text-transform: uppercase;
        background-color: var(--clr-primary-light);
        color: var(--clr-primary);
    }

    .empty {
      text-align: center;
      color: var(--clr-muted);
      padding: 20px 0;
    }

    a.back-link {
      display: inline-block;
      margin-bottom: 14px;
      color: var(--clr-secondary); 
      text-decoration: none;
      font-weight: 600;
      transition: color var(--time-transition);
    }

    a.back-link:hover {
      color: var(--clr-primary); 
    }
    
    @media (max-width: 600px) {
        .page-container {
            padding: 0 10px;
        }
        th, td {
            padding: 10px 8px;
        }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Pending User Approvals</h2>
    <p class="page-subtitle">Approve newly registered accounts before they gain access to the system.</p>
    
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
            <th>Username</th>
            <th>Role</th>
            <th>Full Name</th>
            <th>Registered On</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td><span class="badge"><?= ucfirst($row['role']); ?></span></td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                <td><?= date('Y-m-d', strtotime($row['created_at'])); ?></td>
                <td>
                    <a href="?approve=<?= $row['id']; ?>" class="btn-approve" onclick="return confirm('Are you sure you want to approve user: <?= htmlspecialchars($row['username']); ?>?');">Approve</a>
                    <a href="?disapprove=<?= $row['id']; ?>" class="btn-disapprove" onclick="return confirm('Are you sure you want to DISAPPROVE/DELETE user: <?= htmlspecialchars($row['username']); ?>?');">Reject</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="empty">No pending users at the moment.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
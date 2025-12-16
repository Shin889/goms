<?php
include('../config/db.php');
include('../includes/auth_check.php');
include('../includes/functions.php'); 

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
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
            is_approved = 1,
            approved_by = ?, 
            approved_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("ii", $admin_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Get user details for audit log
            $stmt2 = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
            $stmt2->bind_param("i", $user_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $user = $result2->fetch_assoc();
            
            // Log the action - This should now work since we included functions.php
            logAction($admin_id, 'APPROVE', "Approved user registration: {$user['username']} ({$user['role']})", 'users', $user_id);
            
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
    $admin_id = intval($_SESSION['user_id']);
    
    // Get user details before deletion for audit log
    $stmt = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_active = 0 AND is_approved = 0");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Log the action
            if ($user) {
                logAction($admin_id, 'DISAPPROVE', "Disapproved user registration: {$user['username']} ({$user['role']})", 'users', $user_id);
            }
            
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

// Get pending users (not approved and not active) - FIXED: Add is_approved and is_active to SELECT
$result = $conn->query("
    SELECT id, username, role, full_name, email, phone, created_at, 
           is_approved, is_active 
    FROM users 
    WHERE is_approved = 0 OR is_active = 0
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approve Users</title>
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/approve_user.css">
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Pending User Approvals</h2>
    <p class="page-subtitle">Approve or disapprove newly registered accounts. Users need approval before they can access the system.</p>
    
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
            <th>Contact Info</th>
            <th>Status</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                // Check both conditions for status
                $status_class = '';
                $status_text = '';
                
                if ($row['is_approved'] == 0) {
                    $status_class = 'status-pending';
                    $status_text = 'Pending';
                } elseif ($row['is_active'] == 0) {
                    $status_class = 'status-inactive';
                    $status_text = 'Inactive';
                } else {
                    $status_class = 'status-inactive';
                    $status_text = 'Inactive';
                }
            ?>
              <tr>
                <td><?= htmlspecialchars($row['username']); ?></td>
                <td>
                    <span class="badge <?= htmlspecialchars($row['role']); ?>">
                        <?= ucfirst($row['role']); ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></td>
                <td>
                    <div style="font-size: var(--fs-xsmall);">
                        <?= htmlspecialchars($row['email'] ?: 'No email'); ?><br>
                        <?= htmlspecialchars($row['phone'] ?: 'No phone'); ?>
                    </div>
                </td>
                <td>
                    <span class="user-status <?= $status_class; ?>">
                        <?= $status_text; ?>
                    </span>
                </td>
                <td><?= date('M d, Y', strtotime($row['created_at'])); ?></td>
                <td>
                    <a href="?approve=<?= $row['id']; ?>" class="btn-approve" onclick="return confirm('Are you sure you want to approve user: <?= htmlspecialchars($row['username']); ?>?');">Approve</a>
                    <a href="?disapprove=<?= $row['id']; ?>" class="btn-disapprove" onclick="return confirm('Are you sure you want to DISAPPROVE/DELETE user: <?= htmlspecialchars($row['username']); ?>? This action cannot be undone.');">Reject</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" class="empty">No pending users at the moment.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
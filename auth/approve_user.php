<?php
include('../config/db.php');
include('../includes/auth_check.php');
include('../includes/functions.php'); 

// Check if user is admin
if ($_SESSION['role'] != 'admin') {
    header('Location: ../' . $_SESSION['role'] . '/dashboard.php');
    exit;
}

// Get admin info for sidebar
$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

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
            
            // Log the action
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

// Get pending users (not approved and not active)
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
  <!-- Add ALL necessary CSS files -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css"> <!-- IMPORTANT: This contains sidebar styles -->
  <link rel="stylesheet" href="../utils/css/admin_dashboard.css"> <!-- IMPORTANT: This contains admin styles -->
  <link rel="stylesheet" href="../utils/css/approve_user.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Admin</h2>
    <div class="sidebar-user">
      <i class="fas fa-user-shield"></i> Admin · <?= htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>
    </div>

    <a href="../admin/dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-tachometer-alt"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="../admin/manage_users.php" class="nav-link">
      <span class="icon"><i class="fas fa-users"></i></span><span class="label">Manage Users</span>
    </a>
    <a href="approve_user.php" class="nav-link active">
      <span class="icon"><i class="fas fa-user-check"></i></span><span class="label">Approve Accounts</span>
    </a>
   <!--  <a href="../admin/manage_adviser_sections.php" class="nav-link">
      <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span><span class="label">Manage Sections</span>
    </a> -->
    <a href="../admin/audit_logs.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">View Audit Logs</span>
    </a>
    <a href="../admin/reports.php" class="nav-link">
      <span class="icon"><i class="fas fa-chart-bar"></i></span><span class="label">Generate Reports</span>
    </a>
    <a href="../admin/notifications.php" class="nav-link">
      <span class="icon"><i class="fas fa-bell"></i></span><span class="label">Notifications</span>
    </a>

    <a href="logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <main class="content" id="mainContent">
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
  </main>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    // Initialize sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
        }
        
        // Add confirmation for action buttons
        const approveButtons = document.querySelectorAll('.btn-approve');
        const disapproveButtons = document.querySelectorAll('.btn-disapprove');
        
        approveButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to approve this user?')) {
                    e.preventDefault();
                }
            });
        });
        
        disapproveButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('⚠️ WARNING: This will permanently delete the user registration. This action cannot be undone.\n\nAre you sure?')) {
                    e.preventDefault();
                }
            });
        });
    });
  </script>
</body>
</html>
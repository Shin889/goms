<?php
// Get admin info for sidebar if not already loaded
if (!isset($admin) && isset($_SESSION['user_id'])) {
    include('../config/db.php');
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
}

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="sidebar" id="sidebar">
  <button id="sidebarToggle" class="toggle-btn">☰</button>

  <h2 class="logo">GOMS Admin</h2>
  <div class="sidebar-user">
    <i class="fas fa-user-shield"></i> Admin · <?= htmlspecialchars($admin['full_name'] ?? $admin['username'] ?? 'Admin'); ?>
  </div>

  <a href="dashboard.php" class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-tachometer-alt"></i></span><span class="label">Dashboard</span>
  </a>
  <a href="manage_users.php" class="nav-link <?= ($current_page == 'manage_users.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-users"></i></span><span class="label">Manage Users</span>
  </a>
  <a href="../auth/approve_user.php" class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'approve_user.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-user-check"></i></span><span class="label">Approve Accounts</span>
  </a>
  <a href="manage_adviser_sections.php" class="nav-link <?= ($current_page == 'manage_adviser_sections.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span><span class="label">Manage Sections</span>
  </a>
  <a href="audit_logs.php" class="nav-link <?= ($current_page == 'audit_logs.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">View Audit Logs</span>
  </a>
  <a href="reports.php" class="nav-link <?= ($current_page == 'reports.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-chart-bar"></i></span><span class="label">Generate Reports</span>
  </a>
  <a href="notifications.php" class="nav-link <?= ($current_page == 'notifications.php') ? 'active' : ''; ?>">
    <span class="icon"><i class="fas fa-bell"></i></span><span class="label">Notifications</span>
  </a>

  <a href="../auth/logout.php" class="logout-link">
    <i class="fas fa-sign-out-alt"></i> Logout
  </a>
</nav>
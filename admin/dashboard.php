<?php
include('../includes/auth_check.php');
// Use checkRole function instead of require_role
checkRole(['admin']);
include('../config/db.php');

// Get admin info
$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Get dashboard statistics
$stats = [];

// Total pending approvals
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE is_approved = 0 OR is_active = 0");
$stmt->execute();
$result = $stmt->get_result();
$stats['pending_approvals'] = $result->fetch_assoc()['count'];

// Total users by role
$roles = ['admin', 'counselor', 'adviser', 'guardian'];
foreach ($roles as $role) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = ? AND is_active = 1 AND is_approved = 1");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats[$role . '_count'] = $result->fetch_assoc()['count'];
}

// Total students
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$stats['student_count'] = $result->fetch_assoc()['count'];

// Recent audit logs (last 5)
$stmt = $conn->prepare("
    SELECT al.*, u.username, u.full_name 
    FROM audit_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - GOMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Admin</h2>
    <div class="sidebar-user">
      <i class="fas fa-user-shield"></i> Admin · <?= htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>
    </div>

    <a href="#" class="nav-link active" data-page="manage_users.php">
      <span class="icon"><i class="fas fa-users"></i></span><span class="label">Manage Users</span>
    </a>
    <a href="#" class="nav-link" data-page="../auth/approve_user.php">
      <span class="icon"><i class="fas fa-user-check"></i></span><span class="label">Approve Accounts</span>
    </a>
   <!--  <a href="#" class="nav-link" data-page="manage_adviser_sections.php">
      <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span><span class="label">Manage Sections</span>
    </a> -->
    <a href="#" class="nav-link" data-page="audit_logs.php">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">View Audit Logs</span>
    </a>
    <a href="#" class="nav-link" data-page="reports.php">
      <span class="icon"><i class="fas fa-chart-bar"></i></span><span class="label">Generate Reports</span>
    </a>
    <a href="#" class="nav-link" data-page="notifications.php">
      <span class="icon"><i class="fas fa-bell"></i></span><span class="label">Notifications</span>
    </a>

    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <main class="content" id="mainContent">
    <div class="dashboard-content">
      <!-- Welcome Section -->
      <div class="welcome-section">
        <h1>Welcome, <?= htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>!</h1>
        <p>Administrator Dashboard - Guidance Office Management System</p>
      </div>
      
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card pending">
          <div class="stat-header">
            <h3 class="stat-title">Pending Approvals</h3>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
          </div>
          <div class="stat-value"><?= $stats['pending_approvals']; ?></div>
          <div class="stat-change">
            <i class="fas fa-exclamation-circle"></i>
            Accounts awaiting approval
          </div>
        </div>
        
        <div class="stat-card users">
          <div class="stat-header">
            <h3 class="stat-title">Total Users</h3>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
          </div>
          <div class="stat-value">
            <?= $stats['admin_count'] + $stats['counselor_count'] + $stats['adviser_count'] + $stats['guardian_count']; ?>
          </div>
          <div class="stat-change">
            <i class="fas fa-user-check"></i>
            Active system users
          </div>
        </div>
        
        <div class="stat-card students">
          <div class="stat-header">
            <h3 class="stat-title">Students</h3>
            <div class="stat-icon"><i class="fas fa-graduation-cap"></i></div>
          </div>
          <div class="stat-value"><?= $stats['student_count']; ?></div>
          <div class="stat-change">
            <i class="fas fa-user-graduate"></i>
            Active student records
          </div>
        </div>
        
        <div class="stat-card counselors">
          <div class="stat-header">
            <h3 class="stat-title">Counselors</h3>
            <div class="stat-icon"><i class="fas fa-user-md"></i></div>
          </div>
          <div class="stat-value"><?= $stats['counselor_count']; ?></div>
          <div class="stat-change">
            <i class="fas fa-hands-helping"></i>
            Active counselors
          </div>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="actions-grid">
          <a href="manage_users.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-user-cog"></i></div>
            <div class="action-label">Manage Users</div>
          </a>
          
          <a href="../auth/approve_user.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-user-check"></i></div>
            <div class="action-label">Approve Accounts</div>
          </a>
          
          <a href="manage_adviser_sections.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="action-label">Manage Sections</div>
          </a>
          
          <a href="audit_logs.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-clipboard-list"></i></div>
            <div class="action-label">View Audit Logs</div>
          </a>
          
          <a href="reports.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="action-label">Generate Reports</div>
          </a>
          
          <a href="notifications.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-bell"></i></div>
            <div class="action-label">Notifications</div>
          </a>
        </div>
      </div>
      
      <!-- Recent Activity Section -->
      <div class="dashboard-summary">
        <div class="recent-activity">
          <h2><i class="fas fa-history"></i> Recent Activity</h2>
          <div class="activity-list">
            <?php if ($recent_logs->num_rows > 0): ?>
              <?php while($log = $recent_logs->fetch_assoc()): 
                // Determine icon based on action
                $icon = 'fas fa-info-circle';
                $icon_color = 'var(--clr-info)';
                
                if (strpos($log['action'], 'APPROVE') !== false) $icon = 'fas fa-user-check';
                elseif (strpos($log['action'], 'LOGIN') !== false) $icon = 'fas fa-sign-in-alt';
                elseif (strpos($log['action'], 'CREATE') !== false) $icon = 'fas fa-plus-circle';
                elseif (strpos($log['action'], 'UPDATE') !== false) $icon = 'fas fa-edit';
                elseif (strpos($log['action'], 'DELETE') !== false) $icon = 'fas fa-trash-alt';
              ?>
                <div class="activity-item">
                  <div class="activity-icon" style="background: <?= $icon_color; ?>20; color: <?= $icon_color; ?>;">
                    <i class="<?= $icon; ?>"></i>
                  </div>
                  <div class="activity-content">
                    <div class="activity-title"><?= htmlspecialchars($log['action_summary']); ?></div>
                    <div class="activity-details">
                      By: <?= htmlspecialchars($log['full_name'] ?: $log['username']); ?> | 
                      Table: <?= htmlspecialchars($log['target_table']); ?>
                    </div>
                    <div class="activity-time">
                      <i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                    </div>
                  </div>
                </div>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="activity-item">
                <div class="activity-content">
                  <div class="activity-title">No recent activity</div>
                  <div class="activity-details">System activity will appear here</div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        
        <!-- System Status -->
        <div class="recent-activity">
          <h2><i class="fas fa-server"></i> System Status</h2>
          <div class="activity-list">
            <div class="activity-item">
              <div class="activity-icon" style="background: var(--clr-success-light); color: var(--clr-success);">
                <i class="fas fa-database"></i>
              </div>
              <div class="activity-content">
                <div class="activity-title">Database Connected</div>
                <div class="activity-details">MySQL connection active</div>
              </div>
            </div>
            
            <div class="activity-item">
              <div class="activity-icon" style="background: var(--clr-success-light); color: var(--clr-success);">
                <i class="fas fa-user-shield"></i>
              </div>
              <div class="activity-content">
                <div class="activity-title">Authentication Active</div>
                <div class="activity-details">Role-based access enabled</div>
              </div>
            </div>
            
            <div class="activity-item">
              <div class="activity-icon" style="background: var(--clr-info-light); color: var(--clr-info);">
                <i class="fas fa-bell"></i>
              </div>
              <div class="activity-content">
                <div class="activity-title">SMS Notifications</div>
                <div class="activity-details">Ready for sending alerts</div>
              </div>
            </div>
            
            <div class="activity-item">
              <div class="activity-icon" style="background: var(--clr-success-light); color: var(--clr-success);">
                <i class="fas fa-file-alt"></i>
              </div>
              <div class="activity-content">
                <div class="activity-title">Audit Logging</div>
                <div class="activity-details">All actions are being logged</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    // Initialize dashboard functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Remove loading indicators
      document.querySelector('.loading')?.remove();
      
      // Handle sidebar navigation
      const navLinks = document.querySelectorAll('.nav-link');
      navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const page = this.getAttribute('data-page');
          
          // Update active state
          navLinks.forEach(l => l.classList.remove('active'));
          this.classList.add('active');
          
          // Load page content (if using AJAX)
          if (typeof loadPageContent === 'function') {
            loadPageContent(page);
          } else {
            // Fallback: navigate directly
            window.location.href = page;
          }
        });
      });
    });
  </script>
</body>
</html>
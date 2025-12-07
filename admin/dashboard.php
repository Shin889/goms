<?php
// admin/dashboard.php
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Dashboard specific styles */
    .dashboard-content {
      padding: 30px;
      max-width: 1400px;
      margin: 0 auto;
    }
    
    .welcome-section {
      margin-bottom: 30px;
    }
    
    .welcome-section h1 {
      color: var(--clr-primary);
      font-size: var(--fs-heading);
      margin-bottom: 8px;
    }
    
    .welcome-section p {
      color: var(--clr-muted);
      font-size: var(--fs-normal);
    }
    
    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 40px;
    }
    
    .stat-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      padding: 25px;
      box-shadow: var(--shadow-sm);
      transition: all var(--time-transition);
    }
    
    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
      border-color: var(--clr-primary);
    }
    
    .stat-card.pending {
      border-left: 4px solid var(--clr-warning);
    }
    
    .stat-card.users {
      border-left: 4px solid var(--clr-primary);
    }
    
    .stat-card.students {
      border-left: 4px solid var(--clr-success);
    }
    
    .stat-card.counselors {
      border-left: 4px solid var(--clr-info);
    }
    
    .stat-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }
    
    .stat-icon {
      font-size: 24px;
      color: var(--clr-muted);
    }
    
    .stat-title {
      font-size: var(--fs-small);
      color: var(--clr-muted);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--clr-primary);
      margin: 5px 0;
    }
    
    .stat-change {
      font-size: var(--fs-xsmall);
      color: var(--clr-success);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    /* Quick Actions */
    .quick-actions {
      margin-bottom: 40px;
    }
    
    .quick-actions h2 {
      font-size: var(--fs-subheading);
      color: var(--clr-secondary);
      margin-bottom: 20px;
    }
    
    .actions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
    }
    
    .action-btn {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      padding: 20px;
      text-decoration: none;
      color: var(--clr-text);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      transition: all var(--time-transition);
    }
    
    .action-btn:hover {
      background: var(--clr-primary);
      color: white;
      border-color: var(--clr-primary);
      transform: translateY(-2px);
    }
    
    .action-icon {
      font-size: 24px;
      margin-bottom: 10px;
    }
    
    .action-label {
      font-weight: 500;
      font-size: var(--fs-normal);
    }
    
    /* Recent Activity */
    .recent-activity {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      padding: 25px;
      margin-bottom: 30px;
    }
    
    .recent-activity h2 {
      font-size: var(--fs-subheading);
      color: var(--clr-secondary);
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .activity-list {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }
    
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: 15px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--clr-border-light);
    }
    
    .activity-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    
    .activity-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--clr-bg-light);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      color: var(--clr-primary);
      flex-shrink: 0;
    }
    
    .activity-content {
      flex: 1;
    }
    
    .activity-title {
      font-weight: 600;
      margin-bottom: 4px;
      color: var(--clr-text);
    }
    
    .activity-details {
      font-size: var(--fs-small);
      color: var(--clr-muted);
      margin-bottom: 5px;
    }
    
    .activity-time {
      font-size: var(--fs-xsmall);
      color: var(--clr-muted-light);
    }
    
    /* Dashboard summary */
    .dashboard-summary {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 30px;
      margin-top: 40px;
    }
    
    @media (max-width: 1024px) {
      .dashboard-summary {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .dashboard-content {
        padding: 20px;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .actions-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
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
    <a href="#" class="nav-link" data-page="manage_adviser_sections.php">
      <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span><span class="label">Manage Sections</span>
    </a>
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
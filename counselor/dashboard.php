<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');

// Get current counselor ID
$user_id = intval($_SESSION['user_id']);
$counselor_id = getCurrentCounselorId();

if (!$counselor_id) {
    die("Counselor profile not found. Please contact administrator.");
}

// Get counselor info
$stmt = $conn->prepare("
    SELECT u.*, c.specialty, c.license_number, c.years_of_experience
    FROM users u
    JOIN counselors c ON u.id = c.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();

// Get upcoming appointments (newest first, limit 10)
$appointments_stmt = $conn->prepare("
    SELECT 
        a.*,
        s.first_name,
        s.last_name,
        s.grade_level,
        sec.section_name,
        r.priority as referral_priority
    FROM appointments a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN referrals r ON a.referral_id = r.id
    WHERE a.counselor_id = ? 
    AND a.status IN ('scheduled', 'confirmed')
    AND a.start_time >= NOW()
    ORDER BY a.start_time ASC
    LIMIT 10
");
$appointments_stmt->bind_param("i", $counselor_id);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();

// Get today's appointments
$today = date('Y-m-d');
$today_stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM appointments
    WHERE counselor_id = ?
    AND DATE(start_time) = ?
    AND status IN ('scheduled', 'confirmed')
");
$today_stmt->bind_param("is", $counselor_id, $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_appointments = $today_result->fetch_assoc()['count'];

// Get assigned referrals (with Junior/Senior filtering capability)
$referrals_stmt = $conn->prepare("
    SELECT 
        r.*,
        c.content as complaint_content,
        c.category as complaint_category,
        c.urgency_level,
        s.first_name,
        s.last_name,
        s.grade_level,
        sec.level as section_level,
        a.full_name as adviser_name,
        a.username as adviser_username
    FROM referrals r
    JOIN complaints c ON r.complaint_id = c.id
    JOIN students s ON c.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN advisers adv ON r.adviser_id = adv.id
    JOIN users a ON adv.user_id = a.id
    WHERE r.counselor_id = ?
    AND r.status IN ('open', 'scheduled')
    ORDER BY 
        CASE r.priority 
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        r.created_at DESC
    LIMIT 10
");
$referrals_stmt->bind_param("i", $counselor_id);
$referrals_stmt->execute();
$referrals_result = $referrals_stmt->get_result();

// Get session statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_sessions,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_sessions,
        AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as avg_duration_minutes
    FROM sessions
    WHERE counselor_id = ?
    AND DATE(start_time) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats_stmt->bind_param("i", $counselor_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Get monthly report count
$reports_stmt = $conn->prepare("
    SELECT COUNT(*) as monthly_reports
    FROM reports
    WHERE counselor_id = ?
    AND DATE(submission_date) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$reports_stmt->bind_param("i", $counselor_id);
$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();
$reports_count = $reports_result->fetch_assoc()['monthly_reports'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Counselor Dashboard - GOMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* Dashboard Content */
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
      margin-bottom: 15px;
    }
    
    .counselor-info {
      display: flex;
      align-items: center;
      gap: 20px;
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      padding: 20px;
      margin-bottom: 30px;
    }
    
    .counselor-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: var(--clr-primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 600;
    }
    
    .counselor-details {
      flex: 1;
    }
    
    .counselor-name {
      font-size: var(--fs-subheading);
      font-weight: 700;
      color: var(--clr-text);
      margin-bottom: 5px;
    }
    
    .counselor-specialty {
      color: var(--clr-primary);
      font-weight: 500;
      margin-bottom: 10px;
    }
    
    .counselor-meta {
      display: flex;
      gap: 20px;
      font-size: var(--fs-small);
      color: var(--clr-muted);
    }
    
    .meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
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
    
    .stat-card.today {
      border-left: 4px solid var(--clr-primary);
    }
    
    .stat-card.sessions {
      border-left: 4px solid var(--clr-success);
    }
    
    .stat-card.referrals {
      border-left: 4px solid var(--clr-warning);
    }
    
    .stat-card.reports {
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
      display: flex;
      align-items: center;
      gap: 10px;
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
    
    /* Upcoming Appointments */
    .appointments-section {
      margin-bottom: 40px;
    }
    
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .section-title {
      font-size: var(--fs-subheading);
      color: var(--clr-secondary);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .view-all {
      color: var(--clr-primary);
      text-decoration: none;
      font-weight: 500;
      font-size: var(--fs-small);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .appointments-list {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    
    .appointment-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px;
      border-bottom: 1px solid var(--clr-border-light);
      transition: background var(--time-transition);
    }
    
    .appointment-item:hover {
      background: var(--clr-hover);
    }
    
    .appointment-item:last-child {
      border-bottom: none;
    }
    
    .appointment-info {
      flex: 1;
    }
    
    .student-name {
      font-weight: 600;
      color: var(--clr-text);
      margin-bottom: 5px;
    }
    
    .appointment-details {
      font-size: var(--fs-small);
      color: var(--clr-muted);
      margin-bottom: 5px;
    }
    
    .appointment-time {
      font-size: var(--fs-xsmall);
      color: var(--clr-muted-light);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .start-session-btn {
      background: var(--clr-primary);
      color: white;
      border: none;
      border-radius: var(--radius-sm);
      padding: 8px 16px;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all var(--time-transition);
    }
    
    .start-session-btn:hover {
      background: var(--clr-secondary);
      transform: translateY(-1px);
    }
    
    .start-session-btn:disabled {
      background: var(--clr-muted);
      cursor: not-allowed;
      transform: none;
    }
    
    /* Referrals List */
    .referrals-section {
      margin-bottom: 40px;
    }
    
    .referrals-list {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }
    
    .referral-item {
      padding: 20px;
      border-bottom: 1px solid var(--clr-border-light);
      transition: background var(--time-transition);
    }
    
    .referral-item:hover {
      background: var(--clr-hover);
    }
    
    .referral-item:last-child {
      border-bottom: none;
    }
    
    .referral-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 10px;
    }
    
    .student-info {
      font-weight: 600;
      color: var(--clr-text);
      margin-bottom: 5px;
    }
    
    .referral-category {
      font-size: var(--fs-small);
      color: var(--clr-muted);
      margin-bottom: 10px;
    }
    
    .referral-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 15px;
    }
    
    .referral-meta {
      display: flex;
      gap: 15px;
      font-size: var(--fs-xsmall);
      color: var(--clr-muted);
    }
    
    .meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .priority-badge {
      padding: 4px 10px;
      border-radius: 12px;
      font-size: var(--fs-xsmall);
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .priority-critical {
      background: #fee2e2;
      color: #dc2626;
    }
    
    .priority-high {
      background: #fef3c7;
      color: #d97706;
    }
    
    .priority-medium {
      background: #dbeafe;
      color: #1d4ed8;
    }
    
    .priority-low {
      background: #f3f4f6;
      color: #6b7280;
    }
    
    .level-badge {
      padding: 3px 8px;
      border-radius: 10px;
      font-size: var(--fs-xxsmall);
      font-weight: 500;
      background: var(--clr-bg-light);
      color: var(--clr-muted);
    }
    
    .level-junior {
      background: #dbeafe;
      color: #1d4ed8;
    }
    
    .level-senior {
      background: #f0f9ff;
      color: #0369a1;
    }
    
    /* Empty States */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--clr-muted);
    }
    
    .empty-state i {
      font-size: 2rem;
      margin-bottom: 15px;
      opacity: 0.5;
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
      
      .appointment-item {
        flex-direction: column;
        gap: 15px;
        text-align: center;
      }
      
      .referral-header {
        flex-direction: column;
        gap: 10px;
      }
      
      .referral-footer {
        flex-direction: column;
        gap: 10px;
        text-align: center;
      }
      
      .referral-meta {
        flex-wrap: wrap;
        justify-content: center;
      }
      
      .counselor-info {
        flex-direction: column;
        text-align: center;
      }
      
      .counselor-meta {
        justify-content: center;
      }
    }
  </style>
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      <i class="fas fa-user-md"></i> Counselor · <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>
    </div>

    <a href="dashboard.php" class="nav-link active">
      <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="referrals.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
    </a>
    <a href="appointments.php" class="nav-link">
      <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
    </a>
    <a href="sessions.php" class="nav-link">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
    </a>
    <!-- <a href="create_report.php" class="nav-link">
      <span class="icon"><i class="fas fa-file-alt"></i></span><span class="label">Generate Report</span>
    </a> -->

    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <main class="content" id="mainContent">
    <div class="dashboard-content">
      <!-- Welcome Section -->
      <div class="welcome-section">
        <h1>Welcome, <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>!</h1>
        <p>Counselor Dashboard - Track your appointments, referrals, and sessions</p>
      </div>
      
      <!-- Counselor Info Card -->
      <div class="counselor-info">
        <div class="counselor-avatar">
          <?= strtoupper(substr($counselor['full_name'] ?? $counselor['username'], 0, 1)); ?>
        </div>
        <div class="counselor-details">
          <div class="counselor-name"><?= htmlspecialchars($counselor['full_name']); ?></div>
          <div class="counselor-specialty">
            <i class="fas fa-certificate"></i> <?= htmlspecialchars($counselor['specialty'] ?? 'Guidance Counselor'); ?>
          </div>
          <div class="counselor-meta">
            <div class="meta-item">
              <i class="fas fa-id-card"></i> 
              License: <?= htmlspecialchars($counselor['license_number'] ?? 'Pending'); ?>
            </div>
            <div class="meta-item">
              <i class="fas fa-briefcase"></i> 
              Experience: <?= $counselor['years_of_experience'] ?? '0'; ?> years
            </div>
            <div class="meta-item">
              <i class="fas fa-calendar-check"></i> 
              Today: <?= $today_appointments; ?> appointments
            </div>
          </div>
        </div>
      </div>
      
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card today">
          <div class="stat-header">
            <h3 class="stat-title">Today's Appointments</h3>
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
          </div>
          <div class="stat-value"><?= $today_appointments; ?></div>
          <div class="stat-change">
            <i class="fas fa-clock"></i>
            Scheduled for today
          </div>
        </div>
        
        <div class="stat-card sessions">
          <div class="stat-header">
            <h3 class="stat-title">Monthly Sessions</h3>
            <div class="stat-icon"><i class="fas fa-comments"></i></div>
          </div>
          <div class="stat-value"><?= $stats['total_sessions'] ?? 0; ?></div>
          <div class="stat-change">
            <i class="fas fa-chart-line"></i>
            <?= $stats['completed_sessions'] ?? 0; ?> completed
          </div>
        </div>
        
        <div class="stat-card referrals">
          <div class="stat-header">
            <h3 class="stat-title">Active Referrals</h3>
            <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
          </div>
          <div class="stat-value"><?= $referrals_result->num_rows; ?></div>
          <div class="stat-change">
            <i class="fas fa-user-friends"></i>
            Assigned to you
          </div>
        </div>
        
        <div class="stat-card reports">
          <div class="stat-header">
            <h3 class="stat-title">Monthly Reports</h3>
            <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
          </div>
          <div class="stat-value"><?= $reports_count; ?></div>
          <div class="stat-change">
            <i class="fas fa-pen"></i>
            Submitted this month
          </div>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="actions-grid">
          <a href="appointments.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-calendar-plus"></i></div>
            <div class="action-label">Schedule Appointment</div>
          </a>
          
          <a href="create_session.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-comment-medical"></i></div>
            <div class="action-label">Start New Session</div>
          </a>
          
          <a href="create_report.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-file-medical"></i></div>
            <div class="action-label">Generate Report</div>
          </a>
          
          <a href="referrals.php" class="action-btn">
            <div class="action-icon"><i class="fas fa-user-clock"></i></div>
            <div class="action-label">View All Referrals</div>
          </a>
        </div>
      </div>
      
      <!-- Upcoming Appointments -->
      <div class="appointments-section">
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-calendar-alt"></i> Upcoming Appointments
          </div>
          <a href="appointments.php" class="view-all">
            View All <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        
        <div class="appointments-list">
          <?php if ($appointments_result->num_rows > 0): ?>
            <?php while($appointment = $appointments_result->fetch_assoc()): 
                $start_time = new DateTime($appointment['start_time']);
                $is_today = $start_time->format('Y-m-d') === date('Y-m-d');
                $can_start = $is_today && strtotime($appointment['start_time']) <= time() + 3600; // Can start 1 hour before
            ?>
              <div class="appointment-item">
                <div class="appointment-info">
                  <div class="student-name">
                    <?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                    <?php if ($appointment['grade_level']): ?>
                      <small>(Grade <?= htmlspecialchars($appointment['grade_level']); ?>)</small>
                    <?php endif; ?>
                  </div>
                  <div class="appointment-details">
                    <?= htmlspecialchars($appointment['section_name'] ?? 'No section'); ?> • 
                    <?= ucfirst($appointment['mode']); ?> session
                  </div>
                  <div class="appointment-time">
                    <i class="far fa-clock"></i>
                    <?= $start_time->format('M d, Y • h:i A'); ?>
                    <?php if ($is_today): ?>
                      <span style="color: var(--clr-success);">• Today</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div>
                  <?php if ($appointment['status'] === 'scheduled' && $can_start): ?>
                    <a href="create_session.php?appointment_id=<?= $appointment['id']; ?>" class="start-session-btn">
                      <i class="fas fa-play"></i> Start Session
                    </a>
                  <?php else: ?>
                    <button class="start-session-btn" disabled>
                      <i class="fas fa-clock"></i> 
                      <?= $appointment['status'] === 'confirmed' ? 'Confirmed' : 'Scheduled'; ?>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-calendar-times"></i>
              <h3>No Upcoming Appointments</h3>
              <p>You don't have any scheduled appointments.</p>
              <a href="appointments.php" class="start-session-btn" style="margin-top: 15px;">
                <i class="fas fa-calendar-plus"></i> Schedule Now
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Recent Referrals -->
      <div class="referrals-section">
        <div class="section-header">
          <div class="section-title">
            <i class="fas fa-clipboard-list"></i> Recent Referrals
          </div>
          <a href="referrals.php" class="view-all">
            View All <i class="fas fa-arrow-right"></i>
          </a>
        </div>
        
        <div class="referrals-list">
          <?php if ($referrals_result->num_rows > 0): ?>
            <?php while($referral = $referrals_result->fetch_assoc()): ?>
              <div class="referral-item">
                <div class="referral-header">
                  <div>
                    <div class="student-info">
                      <?= htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']); ?>
                      <span class="level-badge level-<?= strtolower($referral['section_level']); ?>">
                        <?= $referral['section_level']; ?> (Grade <?= $referral['grade_level']; ?>)
                      </span>
                    </div>
                    <div class="referral-category">
                      <i class="fas fa-tag"></i> <?= ucfirst($referral['complaint_category']); ?> • 
                      <i class="fas fa-user-tie"></i> Referred by: <?= htmlspecialchars($referral['adviser_name']); ?>
                    </div>
                  </div>
                  <div class="priority-badge priority-<?= strtolower($referral['priority']); ?>">
                    <?= ucfirst($referral['priority']); ?>
                  </div>
                </div>
                
                <div class="referral-content">
                  <p style="font-size: var(--fs-small); color: var(--clr-text); margin: 10px 0;">
                    <?= htmlspecialchars(substr($referral['complaint_content'], 0, 150)); ?>
                    <?php if (strlen($referral['complaint_content']) > 150): ?>...<?php endif; ?>
                  </p>
                </div>
                
                <div class="referral-footer">
                  <div class="referral-meta">
                    <div class="meta-item">
                      <i class="fas fa-exclamation-circle"></i>
                      Urgency: <?= ucfirst($referral['urgency_level']); ?>
                    </div>
                    <div class="meta-item">
                      <i class="far fa-clock"></i>
                      <?= date('M d, Y', strtotime($referral['created_at'])); ?>
                    </div>
                    <div class="meta-item">
                      <i class="fas fa-user-md"></i>
                      Assigned to you
                    </div>
                  </div>
                  <div>
                    <a href="referrals.php?id=<?= $referral['id']; ?>" class="start-session-btn">
                      <i class="fas fa-eye"></i> View Details
                    </a>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="empty-state">
              <i class="fas fa-clipboard-check"></i>
              <h3>No Active Referrals</h3>
              <p>You don't have any referrals assigned to you.</p>
            </div>
          <?php endif; ?>
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
      
      // Handle session starting
      const startSessionButtons = document.querySelectorAll('.start-session-btn:not([disabled])');
      startSessionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
          if (!this.href) return;
          
          if (!confirm('Start session now? This will mark the appointment as in-progress.')) {
            e.preventDefault();
          }
        });
      });
      
      // Update time displays every minute
      function updateTimes() {
        document.querySelectorAll('.appointment-time').forEach(el => {
          const timeText = el.textContent;
          // Could add real-time updates here
        });
      }
      
      setInterval(updateTimes, 60000);
    });
  </script>
</body>
</html>
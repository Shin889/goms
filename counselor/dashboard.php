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
  <link rel="stylesheet" href="../utils/css/counselor_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
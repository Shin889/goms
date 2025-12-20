<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

// Get filter parameters
$report_type = $_GET['type'] ?? 'summary';
$period = $_GET['period'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$year = $_GET['year'] ?? date('Y');
$format = $_GET['format'] ?? '';

// Handle export request
if ($format && in_array($format, ['pdf', 'csv', 'excel'])) {
    header("Location: export_report.php?type=$report_type&format=$format&period=$period&start_date=$start_date&end_date=$end_date&year=$year");
    exit;
}

// Validate dates
if (!empty($start_date) && !empty($end_date)) {
    $start_date = date('Y-m-d', strtotime($start_date));
    $end_date = date('Y-m-d', strtotime($end_date));
} else {
    // Default to current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Initialize summary array with empty values
$summary = [
    'referral_cases' => ['new_referrals' => 0, 'ongoing_referrals' => 0, 'completed_referrals' => 0],
    'referrals' => 0,
    'appointments' => 0,
    'sessions' => 0,
    'reports' => 0,
    'by_grade' => [],
    'by_category' => [],
    'counselor_activity' => []
];

// 1. REFERRAL STATISTICS
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN id END) as new_referrals,
            COUNT(DISTINCT CASE WHEN status NOT IN ('completed', 'cancelled') THEN id END) as ongoing_referrals,
            COUNT(DISTINCT CASE WHEN status = 'completed' AND DATE(updated_at) BETWEEN ? AND ? THEN id END) as completed_referrals
        FROM referrals
    ");
    if ($stmt) {
        $stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $summary['referral_cases'] = $result->fetch_assoc();
        }
    }
} catch (Exception $e) {
    error_log("Error in referral stats: " . $e->getMessage());
}

// Total referrals
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM referrals 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $summary['referrals'] = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error in total referrals: " . $e->getMessage());
}

// Appointments scheduled
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM appointments 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status != 'cancelled'
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $summary['appointments'] = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error in appointments: " . $e->getMessage());
}

// Sessions conducted
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM sessions 
        WHERE DATE(start_time) BETWEEN ? AND ?
        AND status = 'completed'
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $summary['sessions'] = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error in sessions: " . $e->getMessage());
}

// Reports submitted
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total
        FROM reports 
        WHERE DATE(submission_date) BETWEEN ? AND ?
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $summary['reports'] = $row['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Error in reports: " . $e->getMessage());
}

// 2. REFERRAL DISTRIBUTION
try {
    $stmt = $conn->prepare("
        SELECT s.grade_level, COUNT(r.id) as count
        FROM referrals r
        JOIN students s ON r.student_id = s.id
        WHERE DATE(r.created_at) BETWEEN ? AND ?
        GROUP BY s.grade_level
        ORDER BY s.grade_level
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $summary['by_grade'] = [];
            while ($row = $result->fetch_assoc()) {
                $summary['by_grade'][] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error in by_grade: " . $e->getMessage());
}

// By referral category
try {
    $stmt = $conn->prepare("
        SELECT category, COUNT(*) as count
        FROM referrals
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY category
        ORDER BY count DESC
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $summary['by_category'] = [];
            while ($row = $result->fetch_assoc()) {
                $summary['by_category'][] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error in by_category: " . $e->getMessage());
}

// 3. COUNSELOR ACTIVITY
try {
    $stmt = $conn->prepare("
        SELECT 
            u.full_name as counselor_name,
            COUNT(DISTINCT s.id) as sessions_count,
            COUNT(DISTINCT r.id) as reports_count
        FROM counselors co
        JOIN users u ON co.user_id = u.id
        LEFT JOIN sessions s ON co.id = s.counselor_id AND DATE(s.start_time) BETWEEN ? AND ?
        LEFT JOIN reports r ON s.id = r.session_id
        GROUP BY co.id, u.full_name
        ORDER BY sessions_count DESC
    ");
    if ($stmt) {
        $stmt->bind_param("ss", $start_date, $end_date);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $summary['counselor_activity'] = [];
            while ($row = $result->fetch_assoc()) {
                $summary['counselor_activity'][] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error in counselor activity: " . $e->getMessage());
}

// Calculate totals with null safety
$summary['total_new_cases'] = $summary['referral_cases']['new_referrals'] ?? 0;
$summary['total_ongoing'] = $summary['referral_cases']['ongoing_referrals'] ?? 0;
$summary['total_resolved'] = $summary['referral_cases']['completed_referrals'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports & Exports - GOMS</title>
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css">
  <link rel="stylesheet" href="../utils/css/reports.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
   <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Admin</h2>
    <div class="sidebar-user">
      <i class="fas fa-user-shield"></i> Admin · <?= htmlspecialchars($admin['full_name'] ?? $admin['username']); ?>
    </div>

    <a href="dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-tachometer-alt"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="manage_users.php" class="nav-link">
      <span class="icon"><i class="fas fa-users"></i></span><span class="label">Manage Users</span>
    </a>
    <a href="../auth/approve_user.php" class="nav-link">
      <span class="icon"><i class="fas fa-user-check"></i></span><span class="label">Approve Accounts</span>
    </a>
    <!-- <a href="manage_adviser_sections.php" class="nav-link">
      <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span><span class="label">Manage Sections</span>
    </a> -->
    <a href="audit_logs.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">View Audit Logs</span>
    </a>
    <a href="reports.php" class="nav-link active">
      <span class="icon"><i class="fas fa-chart-bar"></i></span><span class="label">Generate Reports</span>
    </a>
    <a href="notifications.php" class="nav-link">
      <span class="icon"><i class="fas fa-bell"></i></span><span class="label">Notifications</span>
    </a>

    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

    <main class="content" id="mainContent">
  <div class="page-container">
    <h2 class="page-title">Reports & Exports</h2>
    <p class="page-subtitle">Generate comprehensive reports in Guidance Office format and export for DepEd submission.</p>
    
    <!-- Report Type Tabs -->
    <div class="report-type-tabs">
      <a href="?type=summary&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
         class="report-tab <?= ($report_type == 'summary') ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i> Summary Report
      </a>
      <a href="?type=audit_logs&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
         class="report-tab <?= ($report_type == 'audit_logs') ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i> Audit Logs
      </a>
      <a href="?type=user_activity&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" 
         class="report-tab <?= ($report_type == 'user_activity') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> User Activity
      </a>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-card">
      <div class="filter-title">
        <i class="fas fa-filter"></i> Report Filters
      </div>
      
      <form method="GET" action="" class="filter-form">
        <input type="hidden" name="type" value="<?= $report_type ?>">
        
        <div class="form-group">
          <label class="form-label">Report Period</label>
          <select name="period" class="form-select" onchange="this.form.submit()">
            <option value="daily" <?= ($period == 'daily') ? 'selected' : ''; ?>>Daily</option>
            <option value="weekly" <?= ($period == 'weekly') ? 'selected' : ''; ?>>Weekly</option>
            <option value="monthly" <?= ($period == 'monthly') ? 'selected' : ''; ?>>Monthly</option>
            <option value="quarterly" <?= ($period == 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
            <option value="yearly" <?= ($period == 'yearly') ? 'selected' : ''; ?>>Yearly</option>
            <option value="custom" <?= ($period == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Start Date</label>
          <input type="date" name="start_date" class="form-input" 
                 value="<?= htmlspecialchars($start_date); ?>"
                 <?= ($period != 'custom') ? 'disabled' : ''; ?>>
        </div>
        
        <div class="form-group">
          <label class="form-label">End Date</label>
          <input type="date" name="end_date" class="form-input" 
                 value="<?= htmlspecialchars($end_date); ?>"
                 <?= ($period != 'custom') ? 'disabled' : ''; ?>>
        </div>
        
        <div class="form-group">
          <label class="form-label">Year</label>
          <select name="year" class="form-select" onchange="this.form.submit()">
            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
              <option value="<?= $y; ?>" <?= ($year == $y) ? 'selected' : ''; ?>>
                <?= $y; ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
      </form>
      
      <div class="filter-buttons">
        <button type="submit" class="btn-filter" onclick="document.forms[0].submit();">
          <i class="fas fa-filter"></i> Apply Filters
        </button>
      </div>
    </div>
    
<?php if ($report_type == 'summary'): ?>
  <!-- Summary Report -->
  <div class="summary-grid">
    <div class="summary-card cases">
      <div class="summary-header">
        <div class="summary-title">Referral Statistics</div>
        <div class="summary-icon"><i class="fas fa-folder-open"></i></div>
      </div>
      <div class="summary-value"><?= $summary['total_new_cases']; ?></div>
      <div class="summary-details">
        <div>New Referrals: <?= $summary['total_new_cases']; ?></div>
        <div>Ongoing: <?= $summary['total_ongoing']; ?></div>
        <div>Completed: <?= $summary['total_resolved']; ?></div>
      </div>
    </div>
    
    <div class="summary-card activity">
      <div class="summary-header">
        <div class="summary-title">Activity Summary</div>
        <div class="summary-icon"><i class="fas fa-chart-line"></i></div>
      </div>
      <div class="summary-value"><?= $summary['appointments']; ?></div>
      <div class="summary-details">
        <div>Appointments: <?= $summary['appointments']; ?></div>
        <div>Sessions: <?= $summary['sessions']; ?></div>
        <div>Reports: <?= $summary['reports']; ?></div>
      </div>
    </div>
    
   <!-- In the distribution summary card -->
<div class="summary-card distribution">
  <div class="summary-header">
    <div class="summary-title">Referral Distribution</div>
    <div class="summary-icon"><i class="fas fa-layer-group"></i></div>
  </div>
  <div class="summary-value"><?= is_array($summary['by_grade']) ? count($summary['by_grade']) : 0; ?></div>
  <div class="summary-details">
    <div>Grade Levels: <?= is_array($summary['by_grade']) ? count($summary['by_grade']) : 0; ?></div>
    <div>Categories: <?= is_array($summary['by_category']) ? count($summary['by_category']) : 0; ?></div>
    <div>Total Referrals: <?= $summary['referrals']; ?></div>
  </div>
</div>
    
    <div class="summary-card counselors">
      <div class="summary-header">
        <div class="summary-title">Counselor Activity</div>
        <div class="summary-icon"><i class="fas fa-user-md"></i></div>
      </div>
      <div class="summary-value"><?= count($summary['counselor_activity']); ?></div>
      <div class="summary-details">
        <div>Active Counselors: <?= count($summary['counselor_activity']); ?></div>
        <div>Total Sessions: <?= $summary['sessions']; ?></div>
        <div>Total Reports: <?= $summary['reports']; ?></div>
      </div>
    </div>
      
      <!-- Detailed Reports -->
<div class="detail-cards">
  <div class="detail-card">
    <div class="detail-title">
      <i class="fas fa-graduation-cap"></i> By Grade Level
    </div>
    <div class="detail-list">
      <?php if (!empty($summary['by_grade']) && is_array($summary['by_grade'])): ?>
        <?php foreach ($summary['by_grade'] as $grade): ?>
          <div class="detail-item">
            <span class="item-label">Grade <?= htmlspecialchars($grade['grade_level']); ?></span>
            <span class="item-value"><?= $grade['count']; ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-chart-pie"></i>
          <p>No grade level data available</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="detail-card">
    <div class="detail-title">
      <i class="fas fa-tags"></i> By Concern Category
    </div>
    <div class="detail-list">
      <?php if (!empty($summary['by_category']) && is_array($summary['by_category'])): ?>
        <?php foreach ($summary['by_category'] as $category): ?>
          <div class="detail-item">
            <span class="item-label"><?= ucfirst(htmlspecialchars($category['category'])); ?></span>
            <span class="item-value"><?= $category['count']; ?></span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-tags"></i>
          <p>No category data available</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="detail-card">
    <div class="detail-title">
      <i class="fas fa-user-md"></i> Counselor Performance
    </div>
    <div class="detail-list">
      <?php if (!empty($summary['counselor_activity']) && is_array($summary['counselor_activity'])): ?>
        <?php foreach ($summary['counselor_activity'] as $counselor): ?>
          <div class="detail-item">
            <span class="item-label"><?= htmlspecialchars($counselor['counselor_name']); ?></span>
            <span class="item-badge"><?= $counselor['sessions_count']; ?> sessions</span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-user-md"></i>
          <p>No counselor activity data</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
  </div>
    <?php endif; ?>
    
    <!-- Export Options -->
    <div class="export-options">
      <div class="export-title">
        <i class="fas fa-download"></i> Export Report
      </div>
      <div class="export-buttons">
        <a href="?type=<?= $report_type ?>&format=pdf&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&year=<?= $year ?>" 
           class="export-btn pdf">
          <i class="fas fa-file-pdf"></i> Export as PDF
        </a>
        <a href="?type=<?= $report_type ?>&format=csv&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&year=<?= $year ?>" 
           class="export-btn csv">
          <i class="fas fa-file-csv"></i> Export as CSV
        </a>
        <a href="?type=<?= $report_type ?>&format=excel&period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&year=<?= $year ?>" 
           class="export-btn excel">
          <i class="fas fa-file-excel"></i> Export as Excel
        </a>
      </div>
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
        
        // Your existing auto-submit code continues...
        const filterSelects = document.querySelectorAll('.filter-select, .filter-input');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                setTimeout(() => {
                    document.forms[0].submit();
                }, 300);
            });
        });
    });
  </script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Handle period selection
      const periodSelect = document.querySelector('select[name="period"]');
      const startDateInput = document.querySelector('input[name="start_date"]');
      const endDateInput = document.querySelector('input[name="end_date"]');
      
      function updateDateInputs() {
        const period = periodSelect.value;
        const today = new Date();
        
        if (period !== 'custom') {
          startDateInput.disabled = true;
          endDateInput.disabled = true;
          
          // Set dates based on period
          let startDate = new Date(today);
          
          switch (period) {
            case 'daily':
              startDate.setDate(today.getDate() - 1);
              break;
            case 'weekly':
              startDate.setDate(today.getDate() - 7);
              break;
            case 'monthly':
              startDate.setMonth(today.getMonth() - 1);
              break;
            case 'quarterly':
              startDate.setMonth(today.getMonth() - 3);
              break;
            case 'yearly':
              startDate.setFullYear(today.getFullYear() - 1);
              break;
          }
          
          startDateInput.value = startDate.toISOString().split('T')[0];
          endDateInput.value = today.toISOString().split('T')[0];
        } else {
          startDateInput.disabled = false;
          endDateInput.disabled = false;
        }
      }
      
      periodSelect.addEventListener('change', updateDateInputs);
      updateDateInputs(); // Initialize
    });
  </script>
</body>
</html>
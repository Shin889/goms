<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

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

// Fetch summary statistics
$summary = [];

// 1. CASE STATISTICS
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN DATE(created_at) BETWEEN ? AND ? THEN id END) as new_cases,
        COUNT(DISTINCT CASE WHEN status != 'closed' THEN id END) as ongoing_cases,
        COUNT(DISTINCT CASE WHEN status = 'closed' AND DATE(updated_at) BETWEEN ? AND ? THEN id END) as resolved_cases
    FROM complaints
");
$stmt->bind_param("ssss", $start_date, $end_date, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['cases'] = $result->fetch_assoc();

// Total referrals
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM referrals 
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['referrals'] = $result->fetch_assoc()['total'];

// Appointments scheduled
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM appointments 
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status != 'cancelled'
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['appointments'] = $result->fetch_assoc()['total'];

// Sessions conducted
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM sessions 
    WHERE DATE(start_time) BETWEEN ? AND ?
    AND status = 'completed'
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['sessions'] = $result->fetch_assoc()['total'];

// Reports submitted
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM reports 
    WHERE DATE(submission_date) BETWEEN ? AND ?
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['reports'] = $result->fetch_assoc()['total'];

// 2. CASELOAD DISTRIBUTION
$stmt = $conn->prepare("
    SELECT s.grade_level, COUNT(c.id) as count
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    WHERE DATE(c.created_at) BETWEEN ? AND ?
    GROUP BY s.grade_level
    ORDER BY s.grade_level
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['by_grade'] = [];
while ($row = $result->fetch_assoc()) {
    $summary['by_grade'][] = $row;
}

// By concern category
$stmt = $conn->prepare("
    SELECT category, COUNT(*) as count
    FROM complaints
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY category
    ORDER BY count DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['by_category'] = [];
while ($row = $result->fetch_assoc()) {
    $summary['by_category'][] = $row;
}

// 3. COUNSELOR ACTIVITY - FIXED
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
if (!$stmt) {
    die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$summary['counselor_activity'] = [];
while ($row = $result->fetch_assoc()) {
    $summary['counselor_activity'][] = $row;
}

// Calculate totals
$summary['total_new_cases'] = $summary['cases']['new_cases'] ?? 0;
$summary['total_ongoing'] = $summary['cases']['ongoing_cases'] ?? 0;
$summary['total_resolved'] = $summary['cases']['resolved_cases'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports & Exports - GOMS</title>
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css">
  <link rel="stylesheet" href="../utils/css/reports.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
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
            <div class="summary-title">Case Statistics</div>
            <div class="summary-icon"><i class="fas fa-folder-open"></i></div>
          </div>
          <div class="summary-value"><?= $summary['total_new_cases']; ?></div>
          <div class="summary-details">
            <div>New Cases: <?= $summary['total_new_cases']; ?></div>
            <div>Ongoing: <?= $summary['total_ongoing']; ?></div>
            <div>Resolved: <?= $summary['total_resolved']; ?></div>
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
        
        <div class="summary-card distribution">
          <div class="summary-header">
            <div class="summary-title">Caseload Distribution</div>
            <div class="summary-icon"><i class="fas fa-layer-group"></i></div>
          </div>
          <div class="summary-value"><?= count($summary['by_grade']); ?></div>
          <div class="summary-details">
            <div>Grade Levels: <?= count($summary['by_grade']); ?></div>
            <div>Concern Categories: <?= count($summary['by_category']); ?></div>
            <div>Referrals: <?= $summary['referrals']; ?></div>
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
      </div>
      
      <!-- Detailed Reports -->
      <div class="detail-cards">
        <div class="detail-card">
          <div class="detail-title">
            <i class="fas fa-graduation-cap"></i> By Grade Level
          </div>
          <div class="detail-list">
            <?php if (!empty($summary['by_grade'])): ?>
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
            <?php if (!empty($summary['by_category'])): ?>
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
            <?php if (!empty($summary['counselor_activity'])): ?>
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
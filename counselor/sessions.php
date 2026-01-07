<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$counselor_id = intval($_SESSION['user_id']);

// First, get the counselor's database ID from the counselors table
$stmt = $conn->prepare("
    SELECT c.id as counselor_db_id, u.full_name 
    FROM counselors c 
    JOIN users u ON c.user_id = u.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

if (!$counselor) {
    die("Counselor information not found.");
}

$counselor_db_id = $counselor['counselor_db_id'];

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_student = isset($_GET['student']) ? intval($_GET['student']) : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Debug: Check what filters we have
error_log("Filter status: " . $filter_status);
error_log("Filter student: " . $filter_student);
error_log("Counselor DB ID: " . $counselor_db_id);

// Build WHERE clauses - Use counselor_db_id from counselors table
$where_clauses = ["s.counselor_id = ?"];
$params = [$counselor_db_id];
$param_types = "i";

if ($filter_status !== 'all') {
    $where_clauses[] = "s.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_student > 0) {
    $where_clauses[] = "s.student_id = ?";
    $params[] = $filter_student;
    $param_types .= "i";
}

if ($filter_date_from) {
    $where_clauses[] = "DATE(s.start_time) >= ?";
    $params[] = $filter_date_from;
    $param_types .= "s";
}

if ($filter_date_to) {
    $where_clauses[] = "DATE(s.start_time) <= ?";
    $params[] = $filter_date_to;
    $param_types .= "s";
}

if ($filter_type !== 'all') {
    $where_clauses[] = "s.session_type = ?";
    $params[] = $filter_type;
    $param_types .= "s";
}

if (!empty($search)) {
    $where_clauses[] = "(st.first_name LIKE ? OR st.last_name LIKE ? OR s.location LIKE ? OR s.notes_draft LIKE ? OR s.issues_discussed LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sssss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Debug: Log the SQL
error_log("WHERE SQL: " . $where_sql);
error_log("Params: " . print_r($params, true));

// Get session statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN s.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN s.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        AVG(TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time)) as avg_duration
    FROM sessions s
    $where_sql
";

error_log("Stats SQL: " . $stats_sql);

$stats_stmt = $conn->prepare($stats_sql);
if ($stats_stmt) {
    if (!empty($params)) {
        $stats_stmt->bind_param($param_types, ...$params);
    }
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    $stats_stmt->close();
    
    // Debug: Check stats
    error_log("Stats: " . print_r($stats, true));
} else {
    error_log("Stats prepare error: " . $conn->error);
    $stats = ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'scheduled' => 0, 'avg_duration' => 0];
}

// Get sessions with referral info
$sql = "
    SELECT 
        s.*,
        st.first_name, 
        st.last_name,
        st.grade_level,
        sec.section_name,
        sec.level as section_level,
        r.id as referral_id,
        r.category as referral_category,
        r.priority as referral_priority,
        TIMESTAMPDIFF(MINUTE, s.start_time, s.end_time) as duration_minutes
    FROM sessions s
    JOIN students st ON s.student_id = st.id
    LEFT JOIN sections sec ON st.section_id = sec.id
    LEFT JOIN appointments a ON s.appointment_id = a.id
    LEFT JOIN referrals r ON a.referral_id = r.id
    WHERE s.counselor_id = ?
    ORDER BY s.start_time DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_db_id);
$stmt->execute();
$result = $stmt->get_result();

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $error = "Database error: " . $conn->error;
    error_log("Prepare error: " . $error);
} else {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug: Check result
    error_log("Num rows: " . ($result ? $result->num_rows : 0));
}

// Get student list for filter
$students_stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.first_name, s.last_name, s.grade_level
    FROM students s
    JOIN sessions se ON s.id = se.student_id
    WHERE se.counselor_id = ?
    ORDER BY s.last_name, s.first_name
");
$students_stmt->bind_param("i", $counselor_db_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$students_stmt->close();

// Debug: Check students
error_log("Students count: " . count($students));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sessions - GOMS Counselor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/sessions.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($counselor['full_name'] ?? 'User'); ?>
    </div>
    <a href="dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="referrals.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
    </a>
    <a href="appointments.php" class="nav-link">
      <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
    </a>
     <a href="guardian_requests.php" class="nav-link">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Appointment Requests</span>
        </a>
    <a href="sessions.php" class="nav-link active">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
    </a>
    <!-- <a href="create_report.php" class="nav-link">
      <span class="icon"><i class="fas fa-file-alt"></i></span><span class="label">Generate Report</span>
    </a> -->
    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <div class="page-container">
    <div class="page-header">
      <div>
        <h2 class="page-title">My Counseling Sessions</h2>
        <p class="page-subtitle">View and manage all your counseling sessions.</p>
      </div>
      <a href="create_session.php" class="btn-primary">
        <i class="fas fa-plus-circle"></i> Create New Session
      </a>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?= $stats['total'] ?? 0; ?></div>
        <div class="stat-label">Total Sessions</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['completed'] ?? 0; ?></div>
        <div class="stat-label">Completed</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $stats['in_progress'] ?? 0; ?></div>
        <div class="stat-label">In Progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">
          <?= isset($stats['avg_duration']) && $stats['avg_duration'] ? round($stats['avg_duration']) : '0'; ?> min
        </div>
        <div class="stat-label">Avg Duration</div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <form method="GET" action="">
        <div class="filters-grid">
          <div class="filter-group">
            <label class="filter-label">Status</label>
            <select name="status" class="filter-select">
              <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
              <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
              <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
              <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
              <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Student</label>
            <select name="student" class="filter-select">
              <option value="0">All Students</option>
              <?php foreach ($students as $student): ?>
                <option value="<?= $student['id']; ?>" <?= $filter_student == $student['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (Grade ' . $student['grade_level'] . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Session Type</label>
            <select name="type" class="filter-select">
              <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
              <option value="regular" <?= $filter_type === 'regular' ? 'selected' : '' ?>>Regular</option>
              <option value="initial" <?= $filter_type === 'initial' ? 'selected' : '' ?>>Initial Assessment</option>
              <option value="followup" <?= $filter_type === 'followup' ? 'selected' : '' ?>>Follow-up</option>
              <option value="crisis" <?= $filter_type === 'crisis' ? 'selected' : '' ?>>Crisis Intervention</option>
              <option value="group" <?= $filter_type === 'group' ? 'selected' : '' ?>>Group Session</option>
            </select>
          </div>
          
          <!-- <div class="filter-group">
            <label class="filter-label">Date Range</label>
            <div class="filter-row">
              <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($filter_date_from); ?>" placeholder="From">
              <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($filter_date_to); ?>" placeholder="To">
            </div>
          </div> -->
          
          <!-- <div class="filter-group">
            <label class="filter-label">Search</label>
            <div class="filter-row">
              <input type="text" name="search" class="filter-input" placeholder="Search notes, location..." value="<?= htmlspecialchars($search); ?>">
              <button type="submit" class="btn-filter">Filter</button>
              <a href="sessions.php" class="btn-reset">Reset</a>
            </div>
          </div> -->
        </div>
      </form>
    </div>

    <!-- Sessions Table -->
    <div class="card">
      <?php if (isset($error)): ?>
        <div class="empty-state">
          <i class="fas fa-exclamation-circle"></i>
          <h3>Database Error</h3>
          <p><?= htmlspecialchars($error); ?></p>
        </div>
      <?php elseif ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Date & Time</th>
              <th>Duration</th>
              <th>Type & Location</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): 
              $status_class = 'status-' . strtolower($row['status']);
              $type_class = 'type-' . strtolower($row['session_type'] ?? 'regular');
              $level_class = 'level-' . strtolower($row['section_level'] ?? 'unknown');
            ?>
              <tr>
                <td>
                  <div class="student-name"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></div>
                  <div style="display: flex; gap: 5px; margin-top: 5px;">
                    <span class="badge level-badge <?= $level_class; ?>">
                      <?= ucfirst($row['section_level'] ?? 'N/A'); ?>
                    </span>
                    <span style="color: var(--clr-muted); font-size: 0.85rem;">
                      Grade <?= htmlspecialchars($row['grade_level']); ?>
                    </span>
                  </div>
                </td>
                <td>
                  <div class="datetime"><?= date('M d, Y', strtotime($row['start_time'])); ?></div>
                  <div class="datetime"><?= date('h:i A', strtotime($row['start_time'])) . ' - ' . date('h:i A', strtotime($row['end_time'])); ?></div>
                </td>
                <td>
                  <span class="duration"><?= $row['duration_minutes']; ?> min</span>
                </td>
                <td>
                  <div style="display: flex; flex-direction: column; gap: 5px;">
                    <span class="badge <?= $type_class; ?>">
                      <?= ucfirst($row['session_type'] ?: 'Regular'); ?>
                    </span>
                    <span style="color: var(--clr-muted); font-size: 0.85rem;">
                      <i class="fas fa-map-marker-alt"></i> 
                      <?= htmlspecialchars($row['location'] ?: 'Not specified'); ?>
                      • 
                      <i class="fas fa-video"></i> 
                      <?= ucfirst(str_replace('-', ' ', $row['mode'] ?? 'in-person')); ?>
                    </span>
                    <?php if ($row['issues_discussed']): ?>
                      <div class="notes-preview" title="<?= htmlspecialchars($row['issues_discussed']) ?>">
                        <small>Issues: <?= htmlspecialchars(substr($row['issues_discussed'], 0, 30)) . (strlen($row['issues_discussed']) > 30 ? '...' : ''); ?></small>
                      </div>
                    <?php endif; ?>
                    <?php if ($row['follow_up_needed']): ?>
                      <div style="color: var(--clr-warning); font-size: 0.8rem;">
                        <i class="fas fa-calendar-check"></i> 
                        Follow-up: <?= $row['follow_up_date'] ? date('M d, Y', strtotime($row['follow_up_date'])) : 'Date not set'; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="badge <?= $status_class; ?>">
                    <?= ucfirst(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </td>
                <td>
                  <!-- In the table actions column in sessions.php -->
<!-- In the table actions column in sessions.php -->
<div class="action-buttons">
    <?php if ($row['status'] === 'completed'): ?>
        <?php 
        // Check if report exists for this session
        $report_check = $conn->prepare("SELECT id FROM reports WHERE session_id = ?");
        $report_check->bind_param("i", $row['id']);
        $report_check->execute();
        $report_result = $report_check->get_result();
        $has_report = $report_result->num_rows > 0;
        $report_check->close();
        ?>
        
        <?php if (!$has_report): ?>
            <a href="create_report.php?session_id=<?= $row['id']; ?>" class="btn-action btn-action-primary">
                <i class="fas fa-file-medical"></i> Create Report
            </a>
        <?php else: ?>
            <a href="view_report.php?session_id=<?= $row['id']; ?>" class="btn-action btn-action-secondary">
                <i class="fas fa-eye"></i> View Report
            </a>
        <?php endif; ?>
        
    <?php elseif ($row['status'] === 'in_progress'): ?>
        <a href="session_details.php?id=<?= $row['id']; ?>" class="btn-action btn-action-primary">
            <i class="fas fa-check-circle"></i> Complete Session
        </a>
    <?php elseif ($row['status'] === 'scheduled'): ?>
        <a href="session_details.php?id=<?= $row['id']; ?>" class="btn-action btn-action-primary">
            <i class="fas fa-play-circle"></i> Start Session
        </a>
    <?php endif; ?>
    
    <a href="session_details.php?id=<?= $row['id']; ?>" class="btn-action btn-action-secondary">
        <i class="fas fa-info-circle"></i> Details
    </a>
</div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-comments"></i>
          <h3>No Sessions Found</h3>
          <p>No counseling sessions match your current filters. Try adjusting your search criteria or create a new session.</p>
          <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="create_session.php" class="btn-action btn-action-primary">
              <i class="fas fa-plus-circle"></i> Create New Session
            </a>
            <?php if ($filter_status !== 'all' || $filter_student || $filter_date_from || $filter_date_to || $filter_type !== 'all' || $search): ?>
              <a href="sessions.php" class="btn-action">
                <i class="fas fa-redo"></i> Clear Filters
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Handle sidebar toggle for content padding
      const sidebar = document.getElementById('sidebar');
      const pageContainer = document.querySelector('.page-container');
      
      function updateContentPadding() {
        if (sidebar.classList.contains('collapsed')) {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-collapsed-width) + 20px)';
        } else {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-width) + 20px)';
        }
      }
      
      updateContentPadding();
      
      document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        setTimeout(updateContentPadding, 300);
      });
      
      // Set today as default for date_to
      const today = new Date().toISOString().split('T')[0];
      const dateToInput = document.querySelector('input[name="date_to"]');
      if (dateToInput && !dateToInput.value) {
        dateToInput.value = today;
      }
      
      // Set 30 days ago as default for date_from
      const dateFromInput = document.querySelector('input[name="date_from"]');
      if (dateFromInput && !dateFromInput.value) {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
        dateFromInput.value = thirtyDaysAgo.toISOString().split('T')[0];
      }
      
      // Auto-submit filters on select change
      document.querySelectorAll('select.filter-select').forEach(select => {
        select.addEventListener('change', function() {
          this.form.submit();
        });
      });
    });
  </script>
</body>
</html>

<?php
if (isset($stmt)) $stmt->close();
$conn->close();
?>
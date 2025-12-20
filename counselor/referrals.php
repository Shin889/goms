<?php
include('../includes/auth_check.php');
checkRole(['counselor']); 
include('../config/db.php');
include('../includes/functions.php');

$counselor_id = intval($_SESSION['user_id']);

// Get counselor info for sidebar
$stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
if ($stmt) $stmt->close();

// Filter parameters
$filter_level = isset($_GET['level']) ? $_GET['level'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clauses
$where_clauses = ["r.counselor_id = ?"];
$params = [$counselor_id];
$param_types = "i";

if ($filter_level !== 'all') {
    $where_clauses[] = "sec.level = ?";
    $params[] = $filter_level;
    $param_types .= "s";
}

if ($filter_status !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_priority !== 'all') {
    $where_clauses[] = "r.priority = ?";
    $params[] = $filter_priority;
    $param_types .= "s";
}

if (!empty($search)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR r.referral_reason LIKE ? OR r.issue_description LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "ssss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get referral count for stats - SIMPLIFIED
$count_sql = "SELECT COUNT(*) as total FROM referrals r $where_sql";
$count_stmt = $conn->prepare($count_sql);

$total_count = 0;
$result = null;
$error_message = null;

if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'] ?? 0;
    $count_stmt->close();
} else {
    error_log("Count prepare failed: " . $conn->error);
}

// Get referrals with filters - UPDATED for direct referral system
$sql = "
    SELECT 
        r.id, 
        r.category,
        r.issue_description,
        r.referral_reason, 
        r.priority, 
        r.status, 
        r.created_at,
        r.adviser_id,
        r.notes,
        s.first_name, 
        s.last_name, 
        s.grade_level,
        sec.section_name,
        sec.level as section_level,
        u.full_name AS adviser_name,
        u.username as adviser_username,
        (SELECT COUNT(*) FROM appointments app WHERE app.referral_id = r.id) as appointment_count
    FROM referrals r
    JOIN students s ON r.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN users u ON r.adviser_id = u.id
    $where_sql
    ORDER BY 
        CASE r.priority 
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END,
        r.created_at DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    error_log("Prepare failed: " . $conn->error);
    $error_message = "Database error. Please try again later.";
}

// Get stats for cards (simplified queries)
$open_count = 0;
$scheduled_count = 0;
$critical_count = 0;

$open_sql = "SELECT COUNT(*) as count FROM referrals WHERE counselor_id = ? AND status = 'open'";
if ($open_stmt = $conn->prepare($open_sql)) {
    $open_stmt->bind_param("i", $counselor_id);
    $open_stmt->execute();
    $open_result = $open_stmt->get_result();
    $open_count = $open_result->fetch_assoc()['count'] ?? 0;
    $open_stmt->close();
}

$scheduled_sql = "SELECT COUNT(*) as count FROM referrals WHERE counselor_id = ? AND status = 'scheduled'";
if ($scheduled_stmt = $conn->prepare($scheduled_sql)) {
    $scheduled_stmt->bind_param("i", $counselor_id);
    $scheduled_stmt->execute();
    $scheduled_result = $scheduled_stmt->get_result();
    $scheduled_count = $scheduled_result->fetch_assoc()['count'] ?? 0;
    $scheduled_stmt->close();
}

$critical_sql = "SELECT COUNT(*) as count FROM referrals WHERE counselor_id = ? AND priority = 'critical'";
if ($critical_stmt = $conn->prepare($critical_sql)) {
    $critical_stmt->bind_param("i", $counselor_id);
    $critical_stmt->execute();
    $critical_result = $critical_stmt->get_result();
    $critical_count = $critical_result->fetch_assoc()['count'] ?? 0;
    $critical_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Referrals - GOMS Counselor</title>
  <link rel="stylesheet" href="../utils/css/root.css"> 
  <link rel="stylesheet" href="../utils/css/dashboard.css"> 
  <link rel="stylesheet" href="../utils/css/counselor_referrals.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>
    </div>
    <a href="dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="referrals.php" class="nav-link active">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
    </a>
    <a href="appointments.php" class="nav-link">
      <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
    </a>
     <a href="guardian_requests.php" class="nav-link">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Appointment Requests</span>
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

  <div class="page-container">
    <h2 class="page-title">Incoming Referrals</h2>
    <p class="page-subtitle">Review and manage student referrals from advisers.</p>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card">
        <div class="stat-value"><?= $total_count; ?></div>
        <div class="stat-label">Total Referrals</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $open_count; ?></div>
        <div class="stat-label">Open</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $scheduled_count; ?></div>
        <div class="stat-label">Scheduled</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= $critical_count; ?></div>
        <div class="stat-label">Critical</div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="filters-header">
        <div class="filters-title">Filter Referrals</div>
        <a href="referrals.php" class="btn-reset">Reset Filters</a>
      </div>
      <form method="GET" action="">
        <div class="filters-grid">
          <div class="filter-group">
            <label class="filter-label">Grade Level</label>
            <select name="level" class="filter-select">
              <option value="all" <?= $filter_level === 'all' ? 'selected' : '' ?>>All Levels</option>
              <option value="junior" <?= $filter_level === 'junior' ? 'selected' : '' ?>>Junior High</option>
              <option value="senior" <?= $filter_level === 'senior' ? 'selected' : '' ?>>Senior High</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Status</label>
            <select name="status" class="filter-select">
              <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
              <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Open</option>
              <option value="scheduled" <?= $filter_status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
              <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Priority</label>
            <select name="priority" class="filter-select">
              <option value="all" <?= $filter_priority === 'all' ? 'selected' : '' ?>>All Priorities</option>
              <option value="critical" <?= $filter_priority === 'critical' ? 'selected' : '' ?>>Critical</option>
              <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
              <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
              <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Search</label>
            <div class="search-box">
              <input type="text" name="search" class="search-input" placeholder="Student name, complaint code..." value="<?= htmlspecialchars($search) ?>">
              <button type="submit" class="btn-filter">Apply</button>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Referrals Table -->
    <div class="card">
      <?php if ($error_message): ?>
        <div class="empty-state">
          <i class="fas fa-exclamation-circle"></i>
          <h3>Database Error</h3>
          <p><?= htmlspecialchars($error_message); ?></p>
        </div>
      <?php elseif ($result && $result->num_rows > 0): ?>
        <table>
          <thead>
    <tr>
        <th>Category</th>  
        <th>Student</th>
        <th>Level</th>
        <th>Adviser</th>
        <th>Issue Description</th>   
        <th>Priority</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
          <tbody>
           <?php while($row = $result->fetch_assoc()): 
    $priority_class = 'priority-' . strtolower($row['priority']);
    $status_class = 'status-' . strtolower($row['status']);
    $level_class = 'level-' . strtolower($row['section_level']);
?>
    <tr>
        <td>
            <span class="badge category-badge">
                <?= ucfirst($row['category']); ?>
            </span>
            <div class="issue-preview" title="<?= htmlspecialchars($row['issue_description']) ?>">
                <?= htmlspecialchars(substr($row['issue_description'], 0, 50)) . (strlen($row['issue_description']) > 50 ? '...' : '') ?>
            </div>
        </td>
        <td>
            <strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></strong><br>
            <small style="color: var(--clr-muted);">Grade <?= htmlspecialchars($row['grade_level']); ?></small>
        </td>
        <td>
            <span class="badge level-badge <?= $level_class; ?>">
                <?= ucfirst($row['section_level']); ?>
            </span>
        </td>
        <td><?= htmlspecialchars($row['adviser_name']); ?></td>
        <td>
            <?= htmlspecialchars($row['issue_description']); ?><br>
            <small style="color: var(--clr-muted);">Reason: <?= htmlspecialchars($row['referral_reason']); ?></small>
        </td>
        <td>
            <span class="badge <?= $priority_class; ?>">
                <i class="fas fa-flag"></i> <?= ucfirst($row['priority']); ?>
            </span>
        </td>
        <td>
            <span class="badge <?= $status_class; ?>">
                <i class="fas fa-circle-notch"></i> <?= ucfirst($row['status']); ?>
            </span>
            <?php if ($row['appointment_count'] > 0): ?>
                <br><small style="color: var(--clr-muted);"><?= $row['appointment_count']; ?> appt(s)</small>
            <?php endif; ?>
        </td>
        <td>
            <div class="action-buttons">
                <?php if ($row['status'] !== 'closed' && $row['status'] !== 'completed'): ?>
                    <a href="create_appointment.php?referral_id=<?= $row['id']; ?>" class="btn-action btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Session
                    </a>
                <?php else: ?>
                    <span class="btn-action btn-disabled">
                        <i class="fas fa-lock"></i> <?= ucfirst($row['status']); ?>
                    </span>
                <?php endif; ?>
                <a href="referral_details.php?id=<?= $row['id']; ?>" class="btn-action btn-secondary">
                    <i class="fas fa-eye"></i> View Details
                </a>
            </div>
        </td>
    </tr>
<?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-clipboard-list"></i>
          <h3>No Referrals Found</h3>
          <p>No referrals match your current filters. Try adjusting your search criteria.</p>
          <?php if ($filter_level !== 'all' || $filter_status !== 'all' || $filter_priority !== 'all' || !empty($search)): ?>
            <a href="referrals.php" class="btn-action btn-primary" style="margin-top: 15px;">
              <i class="fas fa-redo"></i> Clear Filters
            </a>
          <?php endif; ?>
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
      
      // Auto-submit filters on select change (except search)
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
// Safely close statements only if they exist
if (isset($stmt) && $stmt !== false) {
    $stmt->close();
}
if (isset($conn) && $conn !== false) {
    $conn->close();
}
?>
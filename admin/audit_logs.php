<?php
include('../includes/auth_check.php');
checkRole(['admin']); 
include('../config/db.php');

// Get filter parameters
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : null;
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build query with filters
$query = "
    SELECT 
        a.id, 
        a.action, 
        a.action_summary,
        a.target_table, 
        u.username,
        u.full_name,
        a.created_at
    FROM audit_logs a 
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_user) {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user;
    $types .= 'i';
}

if ($filter_action) {
    $query .= " AND a.action LIKE ?";
    $params[] = "%$filter_action%";
    $types .= 's';
}

if ($filter_table) {
    $query .= " AND a.target_table = ?";
    $params[] = $filter_table;
    $types .= 's';
}

if ($start_date) {
    $query .= " AND DATE(a.created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if ($end_date) {
    $query .= " AND DATE(a.created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

$query .= " ORDER BY a.created_at DESC";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get distinct tables for filter dropdown
$table_result = $conn->query("SELECT DISTINCT target_table FROM audit_logs WHERE target_table IS NOT NULL ORDER BY target_table");
$tables = $table_result->fetch_all(MYSQLI_ASSOC);

// Get distinct actions for filter dropdown
$action_result = $conn->query("SELECT DISTINCT action FROM audit_logs WHERE action IS NOT NULL ORDER BY action");
$actions = $action_result->fetch_all(MYSQLI_ASSOC);

// Get users for filter dropdown
$users_result = $conn->query("SELECT id, username, full_name FROM users ORDER BY username");
$users = $users_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Logs</title>
  <link rel="stylesheet" href="../utils/css/root.css"> 
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <style>    
    .page-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    h2.page-title {
      font-size: var(--fs-heading); 
      color: var(--clr-primary); 
      font-weight: 700;
      margin-bottom: 4px;
    }

    p.page-subtitle {
      color: var(--clr-muted); 
      font-size: var(--fs-small);
      margin-bottom: 25px;
    }

    /* Filter Section */
    .filter-card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 20px;
      margin-bottom: 25px;
    }
    
    .filter-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .filter-title {
      font-size: var(--fs-subheading);
      color: var(--clr-secondary);
      font-weight: 600;
    }
    
    .filter-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    .filter-label {
      font-size: var(--fs-small);
      color: var(--clr-muted);
      font-weight: 500;
    }
    
    .filter-input, .filter-select {
      padding: 10px 12px;
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      font-size: var(--fs-normal);
      background: white;
      color: var(--clr-text);
    }
    
    .filter-input:focus, .filter-select:focus {
      outline: none;
      border-color: var(--clr-primary);
      box-shadow: 0 0 0 2px var(--clr-accent);
    }
    
    .filter-buttons {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 10px;
    }
    
    .btn-filter, .btn-reset {
      padding: 10px 20px;
      border-radius: var(--radius-md);
      font-weight: 500;
      cursor: pointer;
      border: none;
      transition: all var(--time-transition);
    }
    
    .btn-filter {
      background: var(--clr-primary);
      color: white;
    }
    
    .btn-filter:hover {
      background: var(--clr-secondary);
    }
    
    .btn-reset {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      color: var(--clr-text);
    }
    
    .btn-reset:hover {
      background: var(--clr-bg-light);
    }
    
    /* Logs Table */
    .card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 20px;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: var(--fs-normal);
    }

    th, td {
      padding: 15px 14px;
      border-bottom: 1px solid var(--clr-border-light); 
      text-align: left;
      vertical-align: top;
    }

    th {
      background: var(--clr-bg-light);
      color: var(--clr-secondary);
      font-weight: 600;
      text-transform: uppercase;
      font-size: var(--fs-xsmall);
      white-space: nowrap;
    }

    tr:hover {
      background: var(--clr-hover);
    }

    .log-details {
        max-width: 400px;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word;
        font-size: var(--fs-small);
        color: var(--clr-text);
    }
    
    .log-summary {
        font-weight: 500;
        margin-bottom: 4px;
    }
    
    .log-table {
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
        background: var(--clr-bg-light);
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 3px;
    }
    
    .log-date {
        white-space: nowrap;
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
    }
    
    .log-user {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .user-name {
        font-weight: 500;
    }
    
    .user-username {
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
    }
    
    .badge-action {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        display: inline-block;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .badge-action.CREATE { background: var(--clr-success-light); color: var(--clr-success); }
    .badge-action.UPDATE { background: var(--clr-primary-light); color: var(--clr-primary); }
    .badge-action.DELETE { background: var(--clr-error-light); color: var(--clr-error); }
    .badge-action.LOGIN { background: var(--clr-info-light); color: var(--clr-info); }
    .badge-action.LOGOUT { background: var(--clr-info-light); color: var(--clr-info); }
    .badge-action.APPROVE { background: var(--clr-success-light); color: var(--clr-success); }
    .badge-action.DISAPPROVE { background: var(--clr-warning-light); color: var(--clr-warning); }
    .badge-action.REGISTER { background: var(--clr-primary-light); color: var(--clr-primary); }
    
    .empty {
      text-align: center;
      color: var(--clr-muted);
      padding: 40px 0;
      font-size: var(--fs-normal);
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        justify-content: flex-end;
    }
    
    .btn-export {
        padding: 8px 16px;
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        color: var(--clr-text);
        text-decoration: none;
        font-size: var(--fs-small);
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all var(--time-transition);
    }
    
    .btn-export:hover {
        background: var(--clr-bg-light);
        border-color: var(--clr-primary);
    }
    
    @media (max-width: 900px) {
        .page-container {
            padding: 15px;
        }
        
        .filter-form {
            grid-template-columns: 1fr;
        }
        
        th, td {
            padding: 10px 8px;
            font-size: var(--fs-xsmall);
        }
        
        .card {
            padding: 15px;
        }
        
        .export-buttons {
            justify-content: flex-start;
            flex-wrap: wrap;
        }
    }
    
    /* Hide columns on very small screens */
    @media (max-width: 768px) {
        .mobile-hide {
            display: none;
        }
    }
  </style>
</head>
<body>
  
  <div class="page-container">
    <h2 class="page-title">Audit Logs</h2>
    <p class="page-subtitle">Monitor system activities and track administrative actions performed across the platform.</p>
    
    <!-- Export Buttons -->
    <div class="export-buttons">
        <a href="export_report.php?type=audit_logs&format=csv" class="btn-export">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
        <a href="export_report.php?type=audit_logs&format=pdf" class="btn-export">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <div class="filter-header">
            <div class="filter-title">Filter Logs</div>
            <div style="font-size: var(--fs-xsmall); color: var(--clr-muted);">
                Showing <?= $result->num_rows; ?> records
            </div>
        </div>
        
        <form method="GET" action="" class="filter-form">
            <div class="filter-group">
                <label class="filter-label">User</label>
                <select name="user" class="filter-select">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id']; ?>" <?= ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Action Type</label>
                <select name="action" class="filter-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= $action['action']; ?>" <?= ($filter_action == $action['action']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($action['action']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Target Table</label>
                <select name="table" class="filter-select">
                    <option value="">All Tables</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= $table['target_table']; ?>" <?= ($filter_table == $table['target_table']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($table['target_table']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Start Date</label>
                <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($start_date); ?>">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">End Date</label>
                <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($end_date); ?>">
            </div>
        </form>
        
        <div class="filter-buttons">
            <button type="submit" class="btn-filter" onclick="document.forms[0].submit();">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="audit_logs.php" class="btn-reset">
                <i class="fas fa-redo"></i> Reset Filters
            </a>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="card">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Action</th>
            <th class="mobile-hide">Summary</th>
            <th class="mobile-hide">Target Table</th>
            <th>Date/Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($log = $result->fetch_assoc()): 
                $action_class = strtoupper($log['action']);
            ?>
              <tr>
                <td><?= $log['id']; ?></td>
                <td>
                    <div class="log-user">
                        <div class="user-name"><?= htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'System'); ?></div>
                        <?php if ($log['username']): ?>
                            <div class="user-username">@<?= htmlspecialchars($log['username']); ?></div>
                        <?php endif; ?>
                    </div>
                </td>
                <td>
                    <span class="badge-action <?= $action_class; ?>">
                        <?= htmlspecialchars($log['action']); ?>
                    </span>
                </td>
                <td class="mobile-hide">
                    <div class="log-details">
                        <div class="log-summary"><?= htmlspecialchars($log['action_summary'] ?? 'No summary'); ?></div>
                    </div>
                </td>
                <td class="mobile-hide">
                    <span class="log-table"><?= htmlspecialchars($log['target_table'] ?? 'system'); ?></span>
                </td>
                <td>
                    <span class="log-date">
                        <i class="far fa-clock"></i>
                        <?= date('M d, Y', strtotime($log['created_at'])); ?><br>
                        <small><?= date('h:i A', strtotime($log['created_at'])); ?></small>
                    </span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
                <td colspan="6" class="empty">
                    <i class="fas fa-clipboard-list" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i><br>
                    No audit logs found.
                    <?php if ($filter_user || $filter_action || $filter_table || $start_date || $end_date): ?>
                        <br><small>Try adjusting your filters.</small>
                    <?php endif; ?>
                </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <script>
    // Auto-submit form on filter change
    document.addEventListener('DOMContentLoaded', function() {
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
</body>
</html>
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

// Handle SMS test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sms'])) {
    $phone = trim($_POST['test_phone']);
    $message = trim($_POST['test_message']);

    if (empty($phone) || empty($message)) {
        $_SESSION['error_message'] = "Phone number and message are required.";
    } else {
        require_once('../includes/sms_helper.php');

        // Use admin user ID for testing
        $admin_id = $_SESSION['user_id'];

        if (sendSMS($admin_id, $phone, $message)) {
            $_SESSION['success_message'] = "Test SMS sent successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to send test SMS. Check SMS configuration.";
        }
    }

    header('Location: notifications.php');
    exit;
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search_phone = $_GET['search_phone'] ?? '';

// Build query with filters
$query = "
    SELECT 
        nl.*,
        u.username,
        u.full_name,
        u.role
    FROM notifications_log nl
    LEFT JOIN users u ON nl.recipient_user_id = u.id
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_status && $filter_status !== 'all') {
    $query .= " AND nl.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_type && $filter_type !== 'all') {
    $query .= " AND nl.notification_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($start_date) {
    $query .= " AND DATE(nl.created_at) >= ?";
    $params[] = $start_date;
    $types .= 's';
}

if ($end_date) {
    $query .= " AND DATE(nl.created_at) <= ?";
    $params[] = $end_date;
    $types .= 's';
}

if ($search_phone) {
    $query .= " AND nl.recipient_phone LIKE ?";
    $params[] = "%$search_phone%";
    $types .= 's';
}

$query .= " ORDER BY nl.created_at DESC LIMIT 100";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN notification_type = 'sms' THEN 1 ELSE 0 END) as sms,
    SUM(CASE WHEN notification_type = 'email' THEN 1 ELSE 0 END) as email,
    SUM(CASE WHEN notification_type = 'in_app' THEN 1 ELSE 0 END) as in_app
FROM notifications_log";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Notifications Log - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard_layout.css">
    <link rel="stylesheet" href="../utils/css/notifications.css">
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
    <a href="reports.php" class="nav-link">
      <span class="icon"><i class="fas fa-chart-bar"></i></span><span class="label">Generate Reports</span>
    </a>
    <a href="notifications.php" class="nav-link active">
      <span class="icon"><i class="fas fa-bell"></i></span><span class="label">Notifications</span>
    </a>

    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

   <main class="content" id="mainContent">
    <div class="page-container">
        <h2 class="page-title">Notifications Log</h2>
        <p class="page-subtitle">Monitor SMS and system notifications sent to users.</p>

        <?php if ($success_message): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Total Notifications</div>
                    <div class="stat-icon"><i class="fas fa-bell"></i></div>
                </div>
                <div class="stat-value"><?= $stats['total'] ?? 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-chart-line"></i>
                    All notifications sent
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Successful</div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-value"><?= $stats['sent'] ?? 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-check"></i>
                    <?= $stats['total'] > 0 ? round(($stats['sent'] / $stats['total']) * 100, 1) : 0; ?>% success rate
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Failed</div>
                    <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                </div>
                <div class="stat-value"><?= $stats['failed'] ?? 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-times"></i>
                    <?= $stats['total'] > 0 ? round(($stats['failed'] / $stats['total']) * 100, 1) : 0; ?>% failure rate
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">SMS Notifications</div>
                    <div class="stat-icon"><i class="fas fa-sms"></i></div>
                </div>
                <div class="stat-value"><?= $stats['sms'] ?? 0; ?></div>
                <div class="stat-change">
                    <i class="fas fa-mobile-alt"></i>
                    SMS messages sent
                </div>
            </div>
        </div>

        <!-- Test SMS Form -->
        <div class="test-sms-card">
            <div class="test-sms-title">
                <i class="fas fa-vial"></i> Test SMS Configuration
            </div>
            <form method="POST" action="" class="test-form">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="test_phone" class="form-input" placeholder="09171234567" required
                        value="09170000000">
                </div>

                <div class="form-group">
                    <label class="form-label">Test Message</label>
                    <textarea name="test_message" class="form-textarea"
                        required>Test SMS from GOMS Admin Panel. System time: <?= date('Y-m-d H:i:s'); ?></textarea>
                </div>

                <div class="form-group">
                    <button type="submit" name="test_sms" class="btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Test SMS
                    </button>
                </div>
            </form>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <div class="filter-header">
                <div class="filter-title">Filter Notifications</div>
                <div style="font-size: var(--fs-xsmall); color: var(--clr-muted);">
                    Showing <?= $result->num_rows; ?> records
                </div>
            </div>

            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="">All Status</option>
                        <option value="sent" <?= ($filter_status === 'sent') ? 'selected' : ''; ?>>Sent</option>
                        <option value="failed" <?= ($filter_status === 'failed') ? 'selected' : ''; ?>>Failed</option>
                        <option value="pending" <?= ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-input">
                        <option value="">All Types</option>
                        <option value="sms" <?= ($filter_type === 'sms') ? 'selected' : ''; ?>>SMS</option>
                        <option value="email" <?= ($filter_type === 'email') ? 'selected' : ''; ?>>Email</option>
                        <option value="in_app" <?= ($filter_type === 'in_app') ? 'selected' : ''; ?>>In-App</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-input"
                        value="<?= htmlspecialchars($start_date); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($end_date); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Search Phone</label>
                    <input type="text" name="search_phone" class="form-input" placeholder="Search by phone number"
                        value="<?= htmlspecialchars($search_phone); ?>">
                </div>
            </form>

            <div class="filter-buttons">
                <button type="submit" class="btn-filter" onclick="document.forms[1].submit();">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <a href="notifications.php" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset Filters
                </a>
            </div>
        </div>

        <!-- Notifications Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Recipient</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Provider ID</th>
                        <th>Error Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="date-time">
                                        <div class="date"><?= date('M d, Y', strtotime($row['created_at'])); ?></div>
                                        <div class="time"><?= date('h:i A', strtotime($row['created_at'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="recipient-info">
                                        <?php if ($row['full_name'] || $row['username']): ?>
                                            <div class="recipient-name">
                                                <?= htmlspecialchars($row['full_name'] ?: $row['username']); ?>
                                            </div>
                                            <div class="recipient-details">
                                                <?= htmlspecialchars($row['recipient_phone'] ?: $row['recipient_email']); ?>
                                                <?php if ($row['role']): ?>
                                                    · <?= ucfirst($row['role']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="recipient-name">
                                                <?= htmlspecialchars($row['recipient_phone'] ?: $row['recipient_email']); ?>
                                            </div>
                                            <div class="recipient-details">
                                                Direct notification
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="type-badge type-<?= $row['notification_type']; ?>">
                                        <?= strtoupper($row['notification_type']); ?>
                                    </span>
                                </td>
                                <td class="message-cell">
                                    <?= htmlspecialchars(substr($row['message'], 0, 50)); ?>
                                    <?php if (strlen($row['message']) > 50): ?>
                                        ...
                                        <div class="message-full">
                                            <?= nl2br(htmlspecialchars($row['message'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $row['status']; ?>">
                                        <?= ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['provider_msg_id']): ?>
                                        <span style="font-size: var(--fs-xsmall); font-family: monospace;">
                                            <?= substr($row['provider_msg_id'], 0, 10); ?>...
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--clr-muted); font-size: var(--fs-xsmall);">
                                            N/A
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['error_message']): ?>
                                        <span style="color: var(--clr-error); font-size: var(--fs-xsmall);">
                                            <?= htmlspecialchars(substr($row['error_message'], 0, 30)); ?>
                                            <?php if (strlen($row['error_message']) > 30): ?>...<?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--clr-muted); font-size: var(--fs-xsmall);">
                                            No error
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty">
                                <i class="fas fa-bell-slash"></i>
                                <h3>No Notifications Found</h3>
                                <p>No notifications have been sent yet matching your criteria.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
        // Auto-submit filters on change
        document.addEventListener('DOMContentLoaded', function () {
            const filterSelects = document.querySelectorAll('.filter-form select, .filter-form input');
            filterSelects.forEach(select => {
                select.addEventListener('change', function () {
                    setTimeout(() => {
                        document.forms[1].submit();
                    }, 300);
                });
            });

            // Show full message on click for mobile
            const messageCells = document.querySelectorAll('.message-cell');
            messageCells.forEach(cell => {
                cell.addEventListener('click', function (e) {
                    if (window.innerWidth <= 768) {
                        const messageFull = this.querySelector('.message-full');
                        if (messageFull) {
                            messageFull.style.display =
                                messageFull.style.display === 'block' ? 'none' : 'block';
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>
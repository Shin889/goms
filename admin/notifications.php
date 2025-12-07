<?php
// admin/notifications.php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

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
    
    /* Messages */
    .message {
        padding: 12px 20px;
        margin-bottom: 25px;
        border-radius: var(--radius-md);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .success-message {
        background-color: var(--clr-success-light);
        color: var(--clr-success);
        border: 1px solid var(--clr-success);
    }
    
    .error-message {
        background-color: var(--clr-error-light);
        color: var(--clr-error);
        border: 1px solid var(--clr-error);
    }
    
    /* Statistics Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-lg);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        transition: all var(--time-transition);
    }
    
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .stat-title {
        font-size: var(--fs-small);
        color: var(--clr-muted);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .stat-icon {
        font-size: 20px;
        color: var(--clr-muted);
    }
    
    .stat-value {
        font-size: 1.8rem;
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
    
    /* Test SMS Form */
    .test-sms-card {
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-lg);
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: var(--shadow-sm);
    }
    
    .test-sms-title {
        font-size: var(--fs-subheading);
        color: var(--clr-secondary);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .test-form {
        display: grid;
        grid-template-columns: 1fr 2fr 1fr;
        gap: 15px;
        align-items: end;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .form-label {
        font-size: var(--fs-small);
        color: var(--clr-muted);
        font-weight: 500;
    }
    
    .form-input, .form-textarea {
        padding: 10px 12px;
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        font-size: var(--fs-normal);
        background: white;
        color: var(--clr-text);
        font-family: inherit;
    }
    
    .form-input:focus, .form-textarea:focus {
        outline: none;
        border-color: var(--clr-primary);
        box-shadow: 0 0 0 2px var(--clr-accent);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .btn-primary {
        background: var(--clr-primary);
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: all var(--time-transition);
        display: flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
        height: 42px;
    }
    
    .btn-primary:hover {
        background: var(--clr-secondary);
        transform: translateY(-1px);
    }
    
    /* Filters */
    .filter-card {
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-lg);
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
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
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
        display: flex;
        align-items: center;
        gap: 8px;
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
    
    /* Notifications Table */
    .table-container {
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-lg);
        padding: 25px;
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
    
    /* Status Badges */
    .status-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .status-sent {
        background-color: var(--clr-success-light);
        color: var(--clr-success);
    }
    
    .status-failed {
        background-color: var(--clr-error-light);
        color: var(--clr-error);
    }
    
    .status-pending {
        background-color: var(--clr-warning-light);
        color: var(--clr-warning);
    }
    
    /* Type Badges */
    .type-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .type-sms {
        background-color: #dbeafe;
        color: #1d4ed8;
    }
    
    .type-email {
        background-color: #f0f9ff;
        color: #0369a1;
    }
    
    .type-in_app {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    /* Message Cell */
    .message-cell {
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
        position: relative;
    }
    
    .message-full {
        display: none;
        position: absolute;
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        padding: 15px;
        box-shadow: var(--shadow-md);
        z-index: 100;
        max-width: 400px;
        white-space: normal;
        word-wrap: break-word;
        font-size: var(--fs-small);
        line-height: 1.4;
    }
    
    .message-cell:hover .message-full {
        display: block;
    }
    
    /* Recipient Info */
    .recipient-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .recipient-name {
        font-weight: 500;
        color: var(--clr-text);
    }
    
    .recipient-details {
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
    }
    
    /* Date Format */
    .date-time {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .date {
        font-weight: 500;
    }
    
    .time {
        font-size: var(--fs-xsmall);
        color: var(--clr-muted);
    }
    
    .empty {
        text-align: center;
        color: var(--clr-muted);
        padding: 40px 0;
        font-size: var(--fs-normal);
    }
    
    .empty i {
        font-size: 2rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
        .page-container {
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .test-form {
            grid-template-columns: 1fr;
        }
        
        .filter-form {
            grid-template-columns: 1fr;
        }
        
        th, td {
            padding: 10px 8px;
            font-size: var(--fs-xsmall);
        }
        
        .table-container {
            padding: 15px;
        }
        
        .message-full {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 90%;
        }
    }
  </style>
</head>
<body>
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
                <input type="text" name="test_phone" class="form-input" 
                       placeholder="09171234567" required
                       value="09170000000">
            </div>
            
            <div class="form-group">
                <label class="form-label">Test Message</label>
                <textarea name="test_message" class="form-textarea" required>Test SMS from GOMS Admin Panel. System time: <?= date('Y-m-d H:i:s'); ?></textarea>
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
                <input type="date" name="start_date" class="form-input" value="<?= htmlspecialchars($start_date); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($end_date); ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Search Phone</label>
                <input type="text" name="search_phone" class="form-input" 
                       placeholder="Search by phone number" value="<?= htmlspecialchars($search_phone); ?>">
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
                    <?php while($row = $result->fetch_assoc()): ?>
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
  
  <script>
    // Auto-submit filters on change
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelects = document.querySelectorAll('.filter-form select, .filter-form input');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                setTimeout(() => {
                    document.forms[1].submit();
                }, 300);
            });
        });
        
        // Show full message on click for mobile
        const messageCells = document.querySelectorAll('.message-cell');
        messageCells.forEach(cell => {
            cell.addEventListener('click', function(e) {
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
<?php
include('../includes/auth_check.php');
checkRole(['admin']); 
include('../config/db.php');
include('../includes/functions.php');

$status_message = '';
$status_type = '';

// Handle user status updates
if (isset($_GET['action']) && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    $action = $_GET['action'];
    $admin_id = $_SESSION['user_id'];
    
    if ($action === 'approve') {
        // Approve user (make active and approved)
        $stmt = $conn->prepare("UPDATE users SET is_active = 1, is_approved = 1, approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Get user details for logging
                $stmt2 = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $user = $result->fetch_assoc();
                
                // Log the action
                logAction($admin_id, 'APPROVE', "Approved user: {$user['username']} ({$user['role']})", 'users', $user_id);
                
                $_SESSION['status_message'] = "User approved successfully!";
                $_SESSION['status_type'] = 'success';
            } else {
                $_SESSION['status_message'] = "User not found or already approved.";
                $_SESSION['status_type'] = 'info';
            }
        } else {
            $_SESSION['status_message'] = "Database Error: " . $stmt->error;
            $_SESSION['status_type'] = 'error';
        }
        
    } elseif ($action === 'disapprove') {
        // Disapprove/Deactivate user
        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Get user details for logging
                $stmt2 = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $user = $result->fetch_assoc();
                
                // Log the action
                logAction($admin_id, 'DISAPPROVE', "Disapproved user: {$user['username']} ({$user['role']})", 'users', $user_id);
                
                $_SESSION['status_message'] = "User disapproved (deactivated) successfully.";
                $_SESSION['status_type'] = 'warning';
            } else {
                $_SESSION['status_message'] = "User not found or already inactive.";
                $_SESSION['status_type'] = 'info';
            }
        } else {
            $_SESSION['status_message'] = "Database Error: " . $stmt->error;
            $_SESSION['status_type'] = 'error';
        }
        
    } elseif ($action === 'delete') {
        // Permanent deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // Log the action
                logAction($admin_id, 'DELETE', "Deleted user ID: {$user_id}", 'users', $user_id);
                
                $_SESSION['status_message'] = "User permanently deleted.";
                $_SESSION['status_type'] = 'error';
            } else {
                $_SESSION['status_message'] = "Cannot delete user. User not found or is an admin.";
                $_SESSION['status_type'] = 'info';
            }
        } else {
            $_SESSION['status_message'] = "Database Error: " . $stmt->error;
            $_SESSION['status_type'] = 'error';
        }
    }
    
    header("Location: manage_users.php");
    exit;
}

if (isset($_SESSION['status_message'])) {
    $status_message = $_SESSION['status_message'];
    $status_type = $_SESSION['status_type'];
    unset($_SESSION['status_message']);
    unset($_SESSION['status_type']);
}

// Get all users except admins for management (admins are managed separately)
$result = $conn->query("
    SELECT 
        u.*,
        CASE 
            WHEN u.is_active = 1 AND u.is_approved = 1 THEN 'Active'
            WHEN u.is_approved = 0 THEN 'Pending Approval'
            WHEN u.is_active = 0 THEN 'Inactive'
            ELSE 'Unknown'
        END as status_display
    FROM users u 
    WHERE u.role != 'admin'  -- Don't show admin accounts
    ORDER BY 
        CASE 
            WHEN u.is_approved = 0 THEN 1  -- Pending first
            WHEN u.is_active = 0 THEN 2    -- Inactive next
            ELSE 3                          -- Active last
        END,
        u.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users</title>
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
    
    /* Status Messages */
    .status-message {
        padding: 12px 20px;
        margin-bottom: 25px;
        border-radius: var(--radius-md);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .status-message.success {
        background-color: var(--clr-success-light);
        color: var(--clr-success);
        border: 1px solid var(--clr-success);
    }
    .status-message.error {
        background-color: var(--clr-error-light);
        color: var(--clr-error);
        border: 1px solid var(--clr-error);
    }
    .status-message.warning {
        background-color: var(--clr-warning-light);
        color: var(--clr-warning);
        border: 1px solid var(--clr-warning);
    }
    .status-message.info {
        background-color: var(--clr-info-light);
        color: var(--clr-info);
        border: 1px solid var(--clr-info);
    }

    /* Filters */
    .filter-section {
        background: var(--clr-surface);
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-lg);
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .filter-title {
        font-size: var(--fs-subheading);
        color: var(--clr-secondary);
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .filter-grid {
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
    
    .filter-select {
        padding: 10px 12px;
        border: 1px solid var(--clr-border);
        border-radius: var(--radius-md);
        font-size: var(--fs-normal);
        background: white;
        color: var(--clr-text);
        cursor: pointer;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--clr-primary);
        box-shadow: 0 0 0 2px var(--clr-accent);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
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

    /* Users Table */
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
      vertical-align: middle;
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
    
    .status-active {
        background-color: var(--clr-success-light);
        color: var(--clr-success);
    }
    
    .status-pending {
        background-color: var(--clr-warning-light);
        color: var(--clr-warning);
    }
    
    .status-inactive {
        background-color: var(--clr-error-light);
        color: var(--clr-error);
    }

    /* Role Badges */
    .role-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: var(--fs-xsmall);
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }
    
    .role-counselor {
        background-color: #dbeafe;
        color: #1d4ed8;
    }
    
    .role-adviser {
        background-color: #f0f9ff;
        color: #0369a1;
    }
    
    .role-guardian {
        background-color: #fef3c7;
        color: #92400e;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .btn-action {
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        font-size: var(--fs-xsmall);
        font-weight: 600;
        text-decoration: none;
        transition: all var(--time-transition);
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: none;
        cursor: pointer;
    }
    
    .btn-approve {
        background: var(--clr-success);
        color: white;
    }
    
    .btn-approve:hover {
        background: #059669;
        transform: translateY(-1px);
    }
    
    .btn-disapprove {
        background: var(--clr-warning);
        color: white;
    }
    
    .btn-disapprove:hover {
        background: #d97706;
        transform: translateY(-1px);
    }
    
    .btn-delete {
        background: var(--clr-error);
        color: white;
    }
    
    .btn-delete:hover {
        background: #dc2626;
        transform: translateY(-1px);
    }
    
    .btn-action:active {
        transform: translateY(0);
    }

    .user-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    
    .user-name {
        font-weight: 600;
        color: var(--clr-text);
    }
    
    .user-details {
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
    
    @media (max-width: 900px) {
        .page-container {
            padding: 15px;
        }
        
        .filter-grid {
            grid-template-columns: 1fr;
        }
        
        th, td {
            padding: 10px 8px;
            font-size: var(--fs-xsmall);
        }
        
        .card {
            padding: 15px;
        }
        
        .action-buttons {
            flex-direction: column;
            gap: 5px;
        }
        
        .btn-action {
            width: 100%;
            justify-content: center;
        }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">User Management</h2>
    <p class="page-subtitle">Manage all user accounts, approve pending registrations, and monitor user status.</p>
    
    <?php if ($status_message): ?>
        <div class="status-message <?= $status_type; ?>">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars($status_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Filters Section -->
    <div class="filter-section">
        <div class="filter-title">Filter Users</div>
        <form method="GET" action="" class="filter-grid">
            <div class="filter-group">
                <label class="filter-label">Role</label>
                <select name="role" class="filter-select">
                    <option value="">All Roles</option>
                    <option value="counselor">Counselor</option>
                    <option value="adviser">Adviser</option>
                    <option value="guardian">Guardian</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="pending">Pending Approval</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Sort By</label>
                <select name="sort" class="filter-select">
                    <option value="newest">Newest First</option>
                    <option value="oldest">Oldest First</option>
                    <option value="name">Name (A-Z)</option>
                </select>
            </div>
        </form>
        
        <div class="filter-buttons">
            <button type="submit" class="btn-filter" onclick="document.forms[0].submit();">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="manage_users.php" class="btn-reset">
                <i class="fas fa-redo"></i> Reset Filters
            </a>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
      <table>
        <thead>
          <tr>
            <th>User Information</th>
            <th>Role</th>
            <th>Status</th>
            <th>Registration Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): 
                // Determine status class
                $status_class = 'status-inactive';
                if ($row['is_active'] == 1 && $row['is_approved'] == 1) {
                    $status_class = 'status-active';
                } elseif ($row['is_approved'] == 0) {
                    $status_class = 'status-pending';
                }
                
                // Determine status text
                $status_text = 'Inactive';
                if ($row['is_active'] == 1 && $row['is_approved'] == 1) {
                    $status_text = 'Active';
                } elseif ($row['is_approved'] == 0) {
                    $status_text = 'Pending Approval';
                }
            ?>
              <tr>
                <td>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($row['full_name']); ?></div>
                        <div class="user-details">
                            <div><i class="fas fa-user"></i> @<?= htmlspecialchars($row['username']); ?></div>
                            <?php if ($row['email']): ?>
                                <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($row['email']); ?></div>
                            <?php endif; ?>
                            <?php if ($row['phone']): ?>
                                <div><i class="fas fa-phone"></i> <?= htmlspecialchars($row['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="role-badge role-<?= $row['role']; ?>">
                        <?= ucfirst($row['role']); ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge <?= $status_class; ?>">
                        <?= $status_text; ?>
                    </span>
                </td>
                <td>
                    <?= date('M d, Y', strtotime($row['created_at'])); ?><br>
                    <small><?= date('h:i A', strtotime($row['created_at'])); ?></small>
                </td>
                <td>
                    <div class="action-buttons">
                        <?php if ($row['is_approved'] == 0): ?>
                            <!-- Pending Approval - Show Approve button -->
                            <a href="?action=approve&id=<?= $row['id']; ?>" 
                               class="btn-action btn-approve"
                               onclick="return confirm('Approve user <?= htmlspecialchars(addslashes($row['full_name'])); ?>? This will activate their account.');">
                                <i class="fas fa-check"></i> Approve
                            </a>
                        <?php elseif ($row['is_active'] == 1): ?>
                            <!-- Active - Show Disapprove button -->
                            <a href="?action=disapprove&id=<?= $row['id']; ?>" 
                               class="btn-action btn-disapprove"
                               onclick="return confirm('Disapprove user <?= htmlspecialchars(addslashes($row['full_name'])); ?>? This will deactivate their account.');">
                                <i class="fas fa-times"></i> Disapprove
                            </a>
                        <?php else: ?>
                            <!-- Inactive - Show Approve button -->
                            <a href="?action=approve&id=<?= $row['id']; ?>" 
                               class="btn-action btn-approve"
                               onclick="return confirm('Activate user <?= htmlspecialchars(addslashes($row['full_name'])); ?>? This will approve their account.');">
                                <i class="fas fa-check"></i> Activate
                            </a>
                        <?php endif; ?>
                        
                        <!-- Delete button (always available except for admins) -->
                        <a href="?action=delete&id=<?= $row['id']; ?>" 
                           class="btn-action btn-delete"
                           onclick="return confirm('WARNING: Permanently delete user <?= htmlspecialchars(addslashes($row['full_name'])); ?>? This action cannot be undone.');">
                            <i class="fas fa-trash-alt"></i> Delete
                        </a>
                    </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
                <td colspan="5" class="empty">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Users Found</h3>
                    <p>There are no users in the system matching your criteria.</p>
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
        const filterSelects = document.querySelectorAll('.filter-select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                setTimeout(() => {
                    document.forms[0].submit();
                }, 300);
            });
        });
        
        // Add confirmation for all action buttons
        const actionButtons = document.querySelectorAll('.btn-action');
        actionButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                const actionType = this.classList.contains('btn-delete') ? 'delete' : 
                                 this.classList.contains('btn-approve') ? 'approve' : 'disapprove';
                
                if (actionType === 'delete') {
                    if (!confirm('⚠️ WARNING: This will permanently delete the user. This action cannot be undone.\n\nAre you sure?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    });
  </script>
</body>
</html>
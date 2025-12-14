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
  <link rel="stylesheet" href="../utils/css/manage_users.css"> 
  <link rel="stylesheet" href="../utils/css/dashboard_layout.css"> 
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
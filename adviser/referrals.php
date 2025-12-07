<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = $_SESSION['user_id'];

// Get user info for sidebar
$user_info = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc();

// Use prepared statement for security
$stmt = $conn->prepare("
  SELECT r.*, c.complaint_code, c.content
  FROM referrals r
  LEFT JOIN complaints c ON r.complaint_id = c.id
  WHERE r.adviser_id = ?
  ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Referrals - GOMS Adviser</title>
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <style>
    :root {
      --table-header-bg: rgba(247, 247, 247, 0.8);
    }

    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--clr-bg);
      color: var(--clr-text);
      min-height: 100vh;
    }

    .page-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 40px 20px;
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
      margin-bottom: 24px;
    }

    .card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      padding: 24px;
      overflow-x: auto;
      transition: box-shadow var(--time-transition);
    }

    .card:hover {
      box-shadow: var(--shadow-md);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
      min-width: 800px;
    }

    thead {
      background: var(--table-header-bg);
      border-bottom: 2px solid var(--clr-border);
    }

    th {
      padding: 14px 16px;
      color: var(--clr-secondary);
      font-weight: 600;
      text-transform: uppercase;
      font-size: var(--fs-small);
      letter-spacing: 0.5px;
      text-align: left;
    }

    td {
      padding: 16px;
      border-bottom: 1px solid var(--clr-border);
      text-align: left;
      vertical-align: top;
    }

    tbody tr {
      transition: background-color var(--time-transition);
    }

    tbody tr:hover {
      background: var(--clr-accent);
    }

    tbody tr:last-child td {
      border-bottom: none;
    }

    .priority-low {
      color: var(--clr-success);
      font-weight: 600;
      background: rgba(5, 150, 105, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .priority-medium {
      color: var(--clr-warning);
      font-weight: 600;
      background: rgba(251, 191, 36, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .priority-high {
      color: var(--clr-error);
      font-weight: 600;
      background: rgba(248, 113, 113, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .priority-urgent {
      color: #dc2626;
      font-weight: 700;
      background: rgba(220, 38, 38, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
      border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .status-pending {
      color: var(--clr-warning);
      font-weight: 600;
      background: rgba(251, 191, 36, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .status-in_progress {
      color: var(--clr-info);
      font-weight: 600;
      background: rgba(59, 130, 246, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .status-completed {
      color: var(--clr-success);
      font-weight: 600;
      background: rgba(5, 150, 105, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .status-closed {
      color: var(--clr-muted);
      font-weight: 600;
      background: rgba(113, 128, 150, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .reason-cell {
      max-width: 300px;
      white-space: normal;
      word-wrap: break-word;
      line-height: 1.5;
    }

    .empty {
      text-align: center;
      color: var(--clr-muted);
      padding: 40px 20px;
      font-size: 0.95rem;
    }

    .referral-id {
      font-family: monospace;
      background: rgba(16, 185, 129, 0.05);
      padding: 4px 8px;
      border-radius: var(--radius-sm);
      font-size: 0.9rem;
      font-weight: 600;
    }

    .complaint-code-cell {
      font-family: monospace;
      color: var(--clr-primary);
      font-weight: 500;
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 20px 16px;
      }
      
      table {
        font-size: 0.9rem;
      }
      
      th, td {
        padding: 12px 8px;
      }
      
      .reason-cell {
        max-width: 200px;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar for Adviser -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Adviser</h2>
    <div class="sidebar-user">
      Adviser · <?= htmlspecialchars($user_info['username'] ?? ''); ?>
    </div>
    <a href="/adviser/complaints.php" class="nav-link" data-page="complaints.php">
      <span class="icon">📝</span><span class="label">Complaints</span>
    </a>
    <a href="/adviser/referrals.php" class="nav-link active" data-page="referrals.php">
      <span class="icon">📤</span><span class="label">My Referrals</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <div class="page-container">    
    <h2 class="page-title">Your Referrals</h2>
    <p class="page-subtitle">Track and manage all referrals you've created.</p>

    <div class="card">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Referral ID</th>
              <th>Complaint Code</th>
              <th>Reason</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <span class="referral-id">#<?= $row['id']; ?></span>
                </td>
                <td class="complaint-code-cell">
                  <?= htmlspecialchars($row['complaint_code']); ?>
                </td>
                <td class="reason-cell"><?= htmlspecialchars($row['referral_reason']); ?></td>
                <td>
                  <span class="priority-<?= strtolower($row['priority']); ?>">
                    <?= ucfirst($row['priority']); ?>
                  </span>
                </td>
                <td>
                  <span class="status-<?= strtolower($row['status']); ?>">
                    <?= ucwords(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </td>
                <td>
                  <span style="color: var(--clr-muted); font-size: 0.9rem;">
                    <?= date('M d, Y', strtotime($row['created_at'])); ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty">
          <p>No referrals found. Create your first referral from the Complaints page.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    // Set active link in sidebar
    document.addEventListener('DOMContentLoaded', function() {
      const currentPage = 'referrals.php';
      const navLinks = document.querySelectorAll('.nav-link');
      
      navLinks.forEach(link => {
        if (link.getAttribute('data-page') === currentPage) {
          link.classList.add('active');
        }
      });
      
      // Handle sidebar toggle for content padding
      const sidebar = document.getElementById('sidebar');
      const pageContainer = document.querySelector('.page-container');
      
      function updateContentPadding() {
        if (sidebar.classList.contains('collapsed')) {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-collapsed-width) + 40px)';
        } else {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-width) + 40px)';
        }
      }
      
      // Initial padding
      updateContentPadding();
      
      // Listen for sidebar toggle
      document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        setTimeout(updateContentPadding, 300);
      });
      
      // Responsive adjustments
      window.addEventListener('resize', function() {
        if (window.innerWidth <= 900) {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-collapsed-width) + 20px)';
        } else {
          updateContentPadding();
        }
      });
    });
  </script>
</body>
</html>
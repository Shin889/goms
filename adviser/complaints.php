<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');
include('../includes/functions.php');
$user_id = $_SESSION['user_id'];

// Fetch adviser info (to get their section)
$adviser = $conn->query("SELECT * FROM advisers WHERE user_id = $user_id")->fetch_assoc();
$section = $adviser['section'] ?? '';

// Fetch user info for sidebar
$user_info = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc();

// Prepare statement for security
$stmt = $conn->prepare("
  SELECT c.*, s.section, s.first_name, s.last_name
  FROM complaints c
  JOIN students s ON c.student_id = s.id
  WHERE s.section = ?
  ORDER BY c.created_at DESC
");
$stmt->bind_param("s", $section);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Complaints - GOMS Adviser</title>
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

    .section-badge {
      display: inline-block;
      background: rgba(16, 185, 129, 0.15);
      color: var(--clr-primary);
      padding: 6px 14px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.9rem;
      margin-left: 8px;
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

    .status-new {
      color: var(--clr-info);
      font-weight: 600;
      background: rgba(59, 130, 246, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .status-under_review {
      color: var(--clr-warning);
      font-weight: 600;
      background: rgba(251, 191, 36, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      display: inline-block;
    }

    .status-resolved {
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

    .btn-referral {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: var(--clr-primary);
      color: #fff;
      padding: 8px 16px;
      border-radius: var(--radius-sm);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.9rem;
      transition: all var(--time-transition);
      border: none;
    }

    .btn-referral:hover {
      background: var(--clr-secondary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-sm);
    }

    .empty {
      text-align: center;
      color: var(--clr-muted);
      padding: 40px 20px;
      font-size: 0.95rem;
    }

    .content-cell {
      max-width: 300px;
      white-space: normal;
      word-wrap: break-word;
      line-height: 1.5;
    }

    .student-name {
      font-weight: 600;
      color: var(--clr-text);
    }

    .complaint-code {
      font-family: monospace;
      background: rgba(16, 185, 129, 0.05);
      padding: 4px 8px;
      border-radius: var(--radius-sm);
      font-size: 0.9rem;
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
      
      .content-cell {
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
    <a href="/adviser/complaints.php" class="nav-link active" data-page="complaints.php">
      <span class="icon">📝</span><span class="label">Complaints</span>
    </a>
    <a href="/adviser/referrals.php" class="nav-link" data-page="referrals.php">
      <span class="icon">📤</span><span class="label">My Referrals</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <div class="page-container">
    <h2 class="page-title">Student Complaints 
      <span class="section-badge">Section <?= htmlspecialchars($section); ?></span>
    </h2>
    <p class="page-subtitle">Manage and review complaints from your section.</p>

    <div class="card">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Student</th>
              <th>Content</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td>
                  <span class="complaint-code"><?= htmlspecialchars($row['complaint_code']); ?></span>
                </td>
                <td>
                  <div class="student-name"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></div>
                </td>
                <td class="content-cell"><?= htmlspecialchars($row['content']); ?></td>
                <td>
                  <span class="status-<?= $row['status']; ?>">
                    <?= ucwords(str_replace('_', ' ', $row['status'])); ?>
                  </span>
                </td>
                <td>
                  <?php if ($row['status'] == 'new' || $row['status'] == 'under_review'): ?>
                    <a href="create_referral.php?complaint_id=<?= $row['id']; ?>" class="btn-referral">
                      📤 Create Referral
                    </a>
                  <?php else: ?>
                    <span style="color: var(--clr-muted); font-size: 0.9rem;">No action needed</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty">
          <p>No complaints found for your section.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    // Set active link in sidebar
    document.addEventListener('DOMContentLoaded', function() {
      const currentPage = 'complaints.php';
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
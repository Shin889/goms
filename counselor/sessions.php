<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');

$counselor_id = intval($_SESSION['user_id']);

// Get counselor info for sidebar
$counselor = $conn->query("
    SELECT username FROM users WHERE id = $counselor_id
")->fetch_assoc();

// Safe query with error handling
$sql = "
  SELECT s.*, st.first_name, st.last_name
  FROM sessions s
  JOIN students st ON s.student_id = st.id
  WHERE s.counselor_id = ?
  ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sessions - GOMS Counselor</title>
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

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    h2.page-title {
      font-size: var(--fs-heading);
      color: var(--clr-primary);
      font-weight: 700;
      margin: 0;
    }

    p.page-subtitle {
      color: var(--clr-muted);
      font-size: var(--fs-small);
      margin-top: 4px;
      margin-bottom: 0;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--clr-primary);
      color: #fff;
      padding: 10px 20px;
      border-radius: var(--radius-md);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all var(--time-transition);
      border: none;
      cursor: pointer;
      box-shadow: var(--shadow-sm);
      white-space: nowrap;
    }

    .btn-primary:hover {
      background: var(--clr-secondary);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-primary:active {
      transform: translateY(0);
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
      min-width: 600px;
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

    .badge {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
      display: inline-block;
      letter-spacing: 0.3px;
    }

    .status-scheduled {
      background: rgba(59, 130, 246, 0.1);
      color: var(--clr-info);
      border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .status-completed {
      background: rgba(5, 150, 105, 0.1);
      color: var(--clr-success);
      border: 1px solid rgba(5, 150, 105, 0.2);
    }

    .status-cancelled {
      background: rgba(248, 113, 113, 0.1);
      color: var(--clr-error);
      border: 1px solid rgba(248, 113, 113, 0.2);
    }

    .status-ongoing {
      background: rgba(251, 191, 36, 0.1);
      color: var(--clr-warning);
      border: 1px solid rgba(251, 191, 36, 0.2);
    }

    .btn-action {
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

    .btn-action:hover {
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

    .datetime {
      color: var(--clr-muted);
      font-size: 0.9rem;
      white-space: nowrap;
    }

    .student-name {
      font-weight: 600;
      color: var(--clr-text);
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 20px 16px;
      }
      
      .page-header {
        flex-direction: column;
        align-items: stretch;
      }
      
      .btn-primary {
        align-self: flex-start;
      }
      
      table {
        font-size: 0.9rem;
      }
      
      th, td {
        padding: 12px 8px;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($counselor['username'] ?? ''); ?>
    </div>
    <a href="/counselor/referrals.php" class="nav-link" data-page="referrals.php">
      <span class="icon">📋</span><span class="label">Referrals</span>
    </a>
    <a href="/counselor/appointments.php" class="nav-link" data-page="appointments.php">
      <span class="icon">📅</span><span class="label">Appointments</span>
    </a>
    <a href="/counselor/sessions.php" class="nav-link active" data-page="sessions.php">
      <span class="icon">💬</span><span class="label">Sessions</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <div class="page-container">
    <div class="page-header">
      <div>
        <h2 class="page-title">My Counseling Sessions</h2>
        <p class="page-subtitle">View and manage all your counseling sessions.</p>
      </div>
      <a href="create_session.php" class="btn-primary">+ Create New Session</a>
    </div>

    <div class="card">
      <?php if ($result->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): 
              $status_class = 'status-' . strtolower($row['status']);
            ?>
              <tr>
                <td>
                  <div class="student-name"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></div>
                </td>
                <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['start_time'])); ?></td>
                <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['end_time'])); ?></td>
                <td>
                  <span class="badge <?= $status_class; ?>">
                    <?= ucfirst($row['status']); ?>
                  </span>
                </td>
                <td>
                  <a href="create_report.php?session_id=<?= $row['id']; ?>" class="btn-action">
                    Create Report
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty">
          <p>No sessions found. Create your first session!</p>
          <a href="create_session.php" class="btn-primary" style="margin-top: 12px;">Create First Session</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    // Set active link in sidebar
    document.addEventListener('DOMContentLoaded', function() {
      const currentPage = 'sessions.php';
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
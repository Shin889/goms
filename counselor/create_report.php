<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = intval($_SESSION['user_id']);
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

// Get counselor info for sidebar
$counselor = $conn->query("
    SELECT username FROM users WHERE id = $counselor_id
")->fetch_assoc();

// Get session info for context
$session_info = $conn->query("
    SELECT s.start_time, s.end_time, stu.first_name, stu.last_name
    FROM sessions s
    JOIN students stu ON s.student_id = stu.id
    WHERE s.id = $session_id
")->fetch_assoc();

if (!$session_id) {
    header("Location: sessions.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $summary = $_POST['summary'];
    $content = $_POST['content'];

    // Insert report with prepared statement
    $stmt = $conn->prepare("INSERT INTO reports (session_id, counselor_id, title, summary, content) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $session_id, $counselor_id, $title, $summary, $content);

    if ($stmt->execute()) {
        $report_id = $stmt->insert_id;
        
        // Log action
        logAction($counselor_id, 'Create Report', 'reports', $report_id, "Report created for session #$session_id");

        // Fetch guardian's phone (linked to the student in this session)
        $guardian_sql = "
          SELECT g.phone 
          FROM student_guardians sg 
          JOIN guardians g ON sg.guardian_id = g.id
          JOIN sessions s ON s.student_id = sg.student_id
          WHERE s.id = ?
        ";
        $guardian_stmt = $conn->prepare($guardian_sql);
        $guardian_stmt->bind_param("i", $session_id);
        $guardian_stmt->execute();
        $guardian_result = $guardian_stmt->get_result();
        $guardian = $guardian_result->fetch_assoc();

        // Send SMS notification
        if ($guardian && !empty($guardian['phone'])) {
          $msg = "Guidance Update: A counseling report has been completed for your child.";
          sendSMS($counselor_id, $guardian['phone'], $msg);
        }

        echo "<script>alert('Report submitted and locked!'); window.location='reports.php';</script>";
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Report - GOMS Counselor</title>
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--clr-bg);
      color: var(--clr-text);
      min-height: 100vh;
    }

    .page-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 40px 20px;
    }

    a.back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 20px;
      color: var(--clr-secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color var(--time-transition);
      font-size: var(--fs-small);
      padding: 8px 12px;
      border-radius: var(--radius-sm);
    }

    a.back-link:hover {
      color: var(--clr-primary);
      background: var(--clr-accent);
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
      margin-bottom: 28px;
    }

    .card {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      padding: 28px;
      transition: box-shadow var(--time-transition);
    }

    .card:hover {
      box-shadow: var(--shadow-md);
    }

    .session-info {
      background: rgba(16, 185, 129, 0.05);
      border-left: 4px solid var(--clr-primary);
      padding: 16px 20px;
      border-radius: var(--radius-sm);
      margin-bottom: 28px;
    }

    .session-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
    }

    .info-item .label {
      font-size: 0.75rem;
      color: var(--clr-muted);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .info-item .value {
      font-size: 1rem;
      color: var(--clr-text);
      font-weight: 600;
    }

    .form-group {
      margin-bottom: 24px;
    }

    label {
      display: block;
      color: var(--clr-secondary);
      font-weight: 600;
      font-size: var(--fs-small);
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-sm);
      background: var(--clr-bg);
      color: var(--clr-text);
      font-family: var(--font-family);
      font-size: 0.95rem;
      transition: all var(--time-transition);
      box-sizing: border-box;
    }

    input[type="text"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--clr-primary);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 120px;
      line-height: 1.5;
    }

    textarea.large {
      min-height: 240px;
    }

    .btn-primary {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: var(--clr-primary);
      color: #fff;
      padding: 14px 28px;
      border-radius: var(--radius-md);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      cursor: pointer;
      transition: all var(--time-transition);
      box-shadow: var(--shadow-sm);
      width: 100%;
      justify-content: center;
    }

    .btn-primary:hover {
      background: var(--clr-secondary);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .help-text {
      font-size: 0.85rem;
      color: var(--clr-muted);
      margin-top: 6px;
      font-style: italic;
    }

    .session-badge {
      display: inline-block;
      background: rgba(16, 185, 129, 0.15);
      color: var(--clr-primary);
      padding: 6px 14px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 20px 16px;
      }
      
      .card {
        padding: 20px;
      }
      
      .session-info-grid {
        grid-template-columns: 1fr;
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
    <a href="sessions.php" class="back-link">← Back to Sessions</a>
    
    <h2 class="page-title">Create Counseling Report</h2>
    <p class="page-subtitle">
      Creating report for <span class="session-badge">Session #<?= htmlspecialchars($session_id); ?></span>
    </p>

    <?php if ($session_info): ?>
    <div class="session-info">
      <div class="session-info-grid">
        <div class="info-item">
          <div class="label">Student</div>
          <div class="value"><?= htmlspecialchars($session_info['first_name'] . ' ' . $session_info['last_name']); ?></div>
        </div>
        <div class="info-item">
          <div class="label">Session Date</div>
          <div class="value"><?= date('M d, Y', strtotime($session_info['start_time'])); ?></div>
        </div>
        <div class="info-item">
          <div class="label">Session Time</div>
          <div class="value"><?= date('h:i A', strtotime($session_info['start_time'])) . ' - ' . date('h:i A', strtotime($session_info['end_time'])); ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" action="">
        <div class="form-group">
          <label for="title">Report Title</label>
          <input type="text" id="title" name="title" required placeholder="Enter report title (e.g., 'Progress Review', 'Initial Assessment', 'Follow-up Session')">
          <p class="help-text">Provide a clear title that summarizes the session focus.</p>
        </div>

        <div class="form-group">
          <label for="summary">Summary</label>
          <textarea id="summary" name="summary" required placeholder="Enter a brief summary of the session (2-3 sentences)"></textarea>
          <p class="help-text">Write a concise overview of the session outcomes and main points discussed.</p>
        </div>

        <div class="form-group">
          <label for="content">Detailed Content</label>
          <textarea id="content" name="content" class="large" required placeholder="Enter detailed session notes, observations, interventions, and recommendations..."></textarea>
          <p class="help-text">Include detailed notes, observations, interventions used, student responses, and follow-up recommendations.</p>
        </div>

        <button type="submit" class="btn-primary">🔒 Submit & Lock Report</button>
      </form>
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
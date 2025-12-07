<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = intval($_SESSION['user_id']);
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Validate session_id
if ($session_id <= 0) {
    $_SESSION['error'] = "Invalid session ID.";
    header("Location: sessions.php");
    exit;
}

// Get counselor info with prepared statement
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

// Get session info with prepared statement
$stmt = $conn->prepare("
    SELECT s.start_time, s.end_time, stu.first_name, stu.last_name
    FROM sessions s
    JOIN students stu ON s.student_id = stu.id
    WHERE s.id = ? AND s.counselor_id = ?
");
$stmt->bind_param("ii", $session_id, $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$session_info = $result->fetch_assoc();
$stmt->close();

// Check if session exists and belongs to this counselor
if (!$session_info) {
    $_SESSION['error'] = "Session not found or you don't have access.";
    header("Location: sessions.php");
    exit;
}

// Check if report already exists for this session
$check_stmt = $conn->prepare("SELECT id FROM reports WHERE session_id = ?");
$check_stmt->bind_param("i", $session_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
if ($check_result->num_rows > 0) {
    $_SESSION['error'] = "A report already exists for this session.";
    header("Location: sessions.php");
    exit;
}
$check_stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $summary = trim($_POST['summary']);
    $content = trim($_POST['content']);
    
    // Basic validation
    if (empty($title) || empty($summary) || empty($content)) {
        $error = "All fields are required.";
    } else {
        // Insert report with prepared statement
        $stmt = $conn->prepare("
            INSERT INTO reports (session_id, counselor_id, title, summary, content, submission_date) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("iisss", $session_id, $counselor_id, $title, $summary, $content);

        if ($stmt->execute()) {
            $report_id = $stmt->insert_id;
            
            // Update session status to 'reported'
            $update_stmt = $conn->prepare("
                UPDATE sessions SET status = 'reported' WHERE id = ?
            ");
            $update_stmt->bind_param("i", $session_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Log action
            logAction($counselor_id, 'Create Report', 'reports', $report_id, "Report created for session #$session_id");

            // Fetch guardian's phone (linked to the student in this session)
            $guardian_stmt = $conn->prepare("
                SELECT g.phone 
                FROM student_guardians sg 
                JOIN guardians g ON sg.guardian_id = g.id
                JOIN sessions s ON s.student_id = sg.student_id
                WHERE s.id = ?
                LIMIT 1
            ");
            $guardian_stmt->bind_param("i", $session_id);
            $guardian_stmt->execute();
            $guardian_result = $guardian_stmt->get_result();
            $guardian = $guardian_result->fetch_assoc();
            $guardian_stmt->close();

            // Send SMS notification
            if ($guardian && !empty($guardian['phone'])) {
                $student_name = $session_info['first_name'] . ' ' . $session_info['last_name'];
                $msg = "Guidance Update: A counseling report has been completed for $student_name. Report ID: #$report_id";
                sendSMS($counselor_id, $guardian['phone'], $msg);
            }

            // Also send notification to adviser
            $adviser_stmt = $conn->prepare("
                SELECT u.phone 
                FROM adviser_sections adv_sec
                JOIN users u ON adv_sec.adviser_user_id = u.id
                JOIN sections sec ON adv_sec.section_id = sec.id
                JOIN students stu ON stu.section_id = sec.id
                JOIN sessions s ON s.student_id = stu.id
                WHERE s.id = ?
                LIMIT 1
            ");
            $adviser_stmt->bind_param("i", $session_id);
            $adviser_stmt->execute();
            $adviser_result = $adviser_stmt->get_result();
            $adviser = $adviser_result->fetch_assoc();
            $adviser_stmt->close();
            
            if ($adviser && !empty($adviser['phone'])) {
                $msg = "Counseling Report: A session report has been completed for a student in your section.";
                sendSMS($counselor_id, $adviser['phone'], $msg);
            }

            $_SESSION['success'] = "Report submitted successfully!";
            header("Location: reports.php");
            exit;
        } else {
            $error = "Error creating report: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Report - GOMS Counselor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--clr-bg);
      color: var(--clr-text);
      min-height: 100vh;
    }

    .page-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 40px 20px;
      transition: padding-left var(--time-transition);
    }

    @media (max-width: 900px) {
      .page-container {
        padding-left: calc(var(--layout-sidebar-collapsed-width) + 20px);
      }
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

    /* Alert Messages */
    .alert {
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      margin-bottom: 20px;
      font-size: 0.95rem;
      border: 1px solid;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert-error {
      background: color-mix(in srgb, var(--clr-error) 10%, var(--clr-bg));
      color: var(--clr-error);
      border-color: var(--clr-error);
    }

    .alert-success {
      background: color-mix(in srgb, var(--clr-success) 10%, var(--clr-bg));
      color: var(--clr-success);
      border-color: var(--clr-success);
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

    .character-count {
      text-align: right;
      font-size: 0.8rem;
      color: var(--clr-muted);
      margin-top: 5px;
    }

    .character-count.warning {
      color: var(--clr-warning);
    }

    .character-count.error {
      color: var(--clr-error);
    }

    .requirements {
      background: var(--clr-surface);
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-sm);
      padding: 15px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .requirements h4 {
      margin-top: 0;
      color: var(--clr-primary);
      font-size: 0.95rem;
    }

    .requirements ul {
      margin: 10px 0 0 0;
      padding-left: 20px;
    }

    .requirements li {
      margin-bottom: 5px;
      color: var(--clr-muted);
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
    <a href="dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="referrals.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
    </a>
    <a href="appointments.php" class="nav-link">
      <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
    </a>
    <a href="sessions.php" class="nav-link active">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
    </a>
    <a href="create_report.php" class="nav-link">
      <span class="icon"><i class="fas fa-file-alt"></i></span><span class="label">Generate Report</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <div class="page-container">
    <a href="sessions.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sessions</a>
    
    <h2 class="page-title">Create Counseling Report</h2>
    <p class="page-subtitle">
      Creating report for <span class="session-badge">Session #<?= htmlspecialchars($session_id); ?></span>
    </p>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['error']); ?>
        <?php unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

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

    <div class="requirements">
      <h4><i class="fas fa-info-circle"></i> Report Requirements</h4>
      <ul>
        <li>Title should clearly identify the session focus</li>
        <li>Summary should be 2-3 sentences highlighting key outcomes</li>
        <li>Detailed content should include observations, interventions, and recommendations</li>
        <li>Reports cannot be edited once submitted</li>
        <li>Notifications will be sent to guardian and adviser</li>
      </ul>
    </div>

    <div class="card">
      <form method="POST" action="" id="reportForm">
        <div class="form-group">
          <label for="title">Report Title <span style="color: var(--clr-error);">*</span></label>
          <input type="text" id="title" name="title" required 
                 placeholder="Enter report title (e.g., 'Progress Review', 'Initial Assessment', 'Follow-up Session')"
                 maxlength="200">
          <div class="character-count" id="titleCount">0/200</div>
        </div>

        <div class="form-group">
          <label for="summary">Summary <span style="color: var(--clr-error);">*</span></label>
          <textarea id="summary" name="summary" required 
                    placeholder="Enter a brief summary of the session (2-3 sentences)"
                    maxlength="500"></textarea>
          <div class="character-count" id="summaryCount">0/500</div>
          <p class="help-text">Write a concise overview of the session outcomes and main points discussed.</p>
        </div>

        <div class="form-group">
          <label for="content">Detailed Content <span style="color: var(--clr-error);">*</span></label>
          <textarea id="content" name="content" class="large" required 
                    placeholder="Enter detailed session notes, observations, interventions, and recommendations..."
                    maxlength="5000"></textarea>
          <div class="character-count" id="contentCount">0/5000</div>
          <p class="help-text">Include detailed notes, observations, interventions used, student responses, and follow-up recommendations.</p>
        </div>

        <button type="submit" class="btn-primary">
          <i class="fas fa-lock"></i> Submit & Lock Report
        </button>
      </form>
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
      
      // Initial padding
      updateContentPadding();
      
      // Listen for sidebar toggle
      document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        setTimeout(updateContentPadding, 300);
      });
      
      // Character counter functionality
      function setupCharacterCounter(textareaId, counterId, maxLength) {
        const textarea = document.getElementById(textareaId);
        const counter = document.getElementById(counterId);
        
        function updateCounter() {
          const length = textarea.value.length;
          counter.textContent = `${length}/${maxLength}`;
          
          // Add warning/error classes
          counter.classList.remove('warning', 'error');
          if (length > maxLength * 0.9) {
            counter.classList.add('warning');
          }
          if (length > maxLength) {
            counter.classList.add('error');
          }
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter(); // Initial count
      }
      
      // Setup counters for all fields
      setupCharacterCounter('title', 'titleCount', 200);
      setupCharacterCounter('summary', 'summaryCount', 500);
      setupCharacterCounter('content', 'contentCount', 5000);
      
      // Form validation
      document.getElementById('reportForm').addEventListener('submit', function(e) {
        const title = document.getElementById('title').value.trim();
        const summary = document.getElementById('summary').value.trim();
        const content = document.getElementById('content').value.trim();
        
        if (!title || !summary || !content) {
          e.preventDefault();
          alert('Please fill in all required fields.');
          return false;
        }
        
        if (title.length > 200) {
          e.preventDefault();
          alert('Title must be 200 characters or less.');
          return false;
        }
        
        if (summary.length > 500) {
          e.preventDefault();
          alert('Summary must be 500 characters or less.');
          return false;
        }
        
        if (content.length > 5000) {
          e.preventDefault();
          alert('Detailed content must be 5000 characters or less.');
          return false;
        }
        
        // Confirm submission
        if (!confirm('Are you sure you want to submit this report? Once submitted, it cannot be edited.')) {
          e.preventDefault();
          return false;
        }
        
        return true;
      });
    });
  </script>
</body>
</html>
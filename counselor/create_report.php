<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = intval($_SESSION['user_id']);
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

// Validate session_id
if ($session_id <= 0) {
    $_SESSION['error'] = "Invalid session ID.";
    header("Location: sessions.php");
    exit;
}

// Get counselor info with prepared statement - get the counselor's database ID
$stmt = $conn->prepare("
    SELECT c.id as counselor_db_id, u.username 
    FROM counselors c 
    JOIN users u ON c.user_id = u.id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

if (!$counselor) {
    die("Counselor information not found.");
}

$counselor_db_id = $counselor['counselor_db_id'];
$counselor_username = $counselor['username'];

// Get session info with prepared statement
$stmt = $conn->prepare("
    SELECT s.*, stu.first_name, stu.last_name, stu.grade_level, stu.gender, stu.id as student_id
    FROM sessions s
    JOIN students stu ON s.student_id = stu.id
    WHERE s.id = ? AND s.counselor_id = ?
");
$stmt->bind_param("ii", $session_id, $counselor_db_id);
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

// Check if session is completed (can only create reports for completed sessions)
if ($session_info['status'] !== 'completed') {
    $_SESSION['error'] = "Only completed sessions can have reports. Current status: " . $session_info['status'];
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $summary = trim($_POST['summary']);
    $content = trim($_POST['content']);

    // Basic validation
    if (empty($title) || empty($content)) {
        $error = "Title and content are required fields. Summary is optional.";
    } else {
        // Debug log
        error_log("DEBUG: Starting report creation for session $session_id");

        // Insert report
        $sql = "INSERT INTO reports (session_id, counselor_id, title, summary, content, submission_date, locked) 
                VALUES (?, ?, ?, ?, ?, NOW(), 1)";

        // Check connection first
        if (!$conn || $conn->connect_error) {
            $error = "Database connection error: " . ($conn->connect_error ?? 'Unknown');
            error_log("Database connection error");
        } else {
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                $error = "Prepare failed: " . htmlspecialchars($conn->error);
                error_log("SQL Prepare Error: " . $conn->error);
            } else {
                // Bind parameters
                $bind_result = $stmt->bind_param("iisss", $session_id, $counselor_db_id, $title, $summary, $content);

                if ($bind_result === false) {
                    $error = "Bind failed: " . $stmt->error;
                    error_log("Bind Error: " . $stmt->error);
                    $stmt->close();
                } else {
                    // Execute insert
                    if ($stmt->execute()) {
                        $report_id = $stmt->insert_id;
                        $stmt->close();

                        error_log("DEBUG: Report inserted successfully. ID: $report_id");

                        // Log action
                        logAction($user_id, 'Create Report', 'reports', $report_id, "Report created for session #$session_id");

                        // Get guardian phone for SMS notification
                        $guardian_phone = null;
                        $student_id = $session_info['student_id'];

                        // CORRECTED QUERY: Get guardian phone from users table
                        $guardian_sql = "
                            SELECT u.phone 
                            FROM student_guardians sg 
                            JOIN guardians g ON sg.guardian_id = g.id 
                            JOIN users u ON g.user_id = u.id
                            WHERE sg.student_id = ?
                            ORDER BY sg.primary_guardian DESC, sg.id ASC 
                            LIMIT 1
                        ";

                        error_log("DEBUG: Preparing guardian query for student ID: $student_id");
                        $guardian_stmt = $conn->prepare($guardian_sql);

                        if ($guardian_stmt === false) {
                            // Log error but don't stop the process
                            error_log("Guardian query prepare failed: " . $conn->error);
                            error_log("SQL: " . $guardian_sql);
                        } else {
                            $guardian_stmt->bind_param("i", $student_id);
                            
                            if ($guardian_stmt->execute()) {
                                $guardian_result = $guardian_stmt->get_result();
                                if ($guardian_row = $guardian_result->fetch_assoc()) {
                                    $guardian_phone = $guardian_row['phone'];
                                    error_log("DEBUG: Found guardian phone: $guardian_phone");
                                } else {
                                    error_log("DEBUG: No guardian found for student ID: $student_id");
                                }
                            } else {
                                error_log("DEBUG: Guardian query execute failed: " . $guardian_stmt->error);
                            }
                            
                            $guardian_stmt->close();
                        }

                        // Send SMS notification if phone found
                        if (!empty($guardian_phone) && function_exists('sendSMS')) {
                            try {
                                $student_name = $session_info['first_name'] . ' ' . $session_info['last_name'];
                                $msg = "Guidance Update: A counseling report has been completed for $student_name. Report ID: #$report_id";

                                error_log("DEBUG: Attempting to send SMS to: $guardian_phone");
                                $sms_result = sendSMS($user_id, $guardian_phone, $msg);

                                if ($sms_result) {
                                    error_log("DEBUG: SMS sent successfully");
                                } else {
                                    error_log("DEBUG: SMS sending failed");
                                }
                            } catch (Exception $e) {
                                error_log("SMS Exception: " . $e->getMessage());
                            }
                        }

                        $_SESSION['success'] = "Report submitted successfully!" .
                            (empty($guardian_phone) ? " (No SMS sent - guardian phone not found)" : "");

                        header("Location: sessions.php");
                        exit;

                    } else {
                        $error = "Execute failed: " . $stmt->error;
                        error_log("Execute Error: " . $stmt->error);
                        $stmt->close();
                    }
                }
            }
        }
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
  <link rel="stylesheet" href="../utils/css/create_report.css">
  <style>
    .session-info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin: 20px 0;
    }

    .info-item {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 8px;
    }

    .info-item .label {
      font-size: 0.85rem;
      color: #6c757d;
      margin-bottom: 5px;
    }

    .info-item .value {
      font-weight: 500;
    }

    .info-item.full-width {
      grid-column: 1 / -1;
    }

    .requirements {
      background: #e3f2fd;
      padding: 15px;
      border-radius: 8px;
      margin: 20px 0;
    }

    .requirements ul {
      margin: 10px 0 0 20px;
    }

    .requirements li {
      margin-bottom: 5px;
    }

    .character-count {
      text-align: right;
      font-size: 0.85rem;
      color: #6c757d;
      margin-top: 5px;
    }

    .character-count.warning {
      color: #ff9800;
    }

    .character-count.error {
      color: #f44336;
    }

    .session-badge {
      background: #007bff;
      color: white;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 0.9rem;
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($counselor_username ?? ''); ?>
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
            <div class="value"><?= htmlspecialchars($session_info['first_name'] . ' ' . $session_info['last_name']); ?>
            </div>
          </div>
          <div class="info-item">
            <div class="label">Grade Level</div>
            <div class="value">Grade <?= htmlspecialchars($session_info['grade_level']); ?></div>
          </div>
          <div class="info-item">
            <div class="label">Gender</div>
            <div class="value"><?= htmlspecialchars($session_info['gender'] ?? 'Not specified'); ?></div>
          </div>
          <div class="info-item">
            <div class="label">Session Date</div>
            <div class="value"><?= date('M d, Y', strtotime($session_info['start_time'])); ?></div>
          </div>
          <div class="info-item">
            <div class="label">Session Time</div>
            <div class="value">
              <?= date('h:i A', strtotime($session_info['start_time'])) . ' - ' . date('h:i A', strtotime($session_info['end_time'])); ?>
            </div>
          </div>
          <div class="info-item">
            <div class="label">Session Type</div>
            <div class="value"><?= ucfirst($session_info['session_type'] ?: 'Regular'); ?></div>
          </div>
          <div class="info-item">
            <div class="label">Location</div>
            <div class="value"><?= htmlspecialchars($session_info['location'] ?: 'Not specified'); ?></div>
          </div>
          <?php if (!empty($session_info['issues_discussed'])): ?>
            <div class="info-item full-width">
              <div class="label">Issues Discussed</div>
              <div class="value">
                <?= htmlspecialchars(substr($session_info['issues_discussed'], 0, 100)); ?>
                <?= strlen($session_info['issues_discussed']) > 100 ? '...' : ''; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($session_info['follow_up_plan'])): ?>
            <div class="info-item full-width">
              <div class="label">Follow-up Plan</div>
              <div class="value">
                <?= htmlspecialchars(substr($session_info['follow_up_plan'], 0, 100)); ?>
                <?= strlen($session_info['follow_up_plan']) > 100 ? '...' : ''; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="requirements">
      <h4><i class="fas fa-info-circle"></i> Report Requirements</h4>
      <ul>
        <li>Title should clearly identify the session focus</li>
        <li>Summary should be 2-3 sentences highlighting key outcomes</li>
        <li>Detailed content should include observations, interventions, and recommendations</li>
        <li>Reports are locked by default after submission</li>
        <li>Notifications will be sent to guardian and adviser</li>
      </ul>
    </div>

    <div class="card">
      <form method="POST" action="" id="reportForm">
        <div class="form-group">
          <label for="title">Report Title <span style="color: #f44336;">*</span></label>
          <input type="text" id="title" name="title" required
            placeholder="Enter report title (e.g., 'Progress Review', 'Initial Assessment', 'Follow-up Session')"
            maxlength="200">
          <div class="character-count" id="titleCount">0/200</div>
        </div>

        <div class="form-group">
          <label for="summary">Summary <span style="color: #f44336;">*</span></label>
          <textarea id="summary" name="summary" required
            placeholder="Enter a brief summary of the session (2-3 sentences)" maxlength="500"></textarea>
          <div class="character-count" id="summaryCount">0/500</div>
          <p class="help-text">Write a concise overview of the session outcomes and main points discussed.</p>
        </div>

        <div class="form-group">
          <label for="content">Detailed Content <span style="color: #f44336;">*</span></label>
          <textarea id="content" name="content" rows="10" required
            placeholder="Enter detailed session notes, observations, interventions, and recommendations..."
            maxlength="5000"></textarea>
          <div class="character-count" id="contentCount">0/5000</div>
          <p class="help-text">Include detailed notes, observations, interventions used, student responses, and
            follow-up recommendations.</p>
        </div>

        <button type="submit" class="btn-primary">
          <i class="fas fa-lock"></i> Submit & Lock Report
        </button>
      </form>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Handle sidebar toggle
      const sidebar = document.getElementById('sidebar');
      const pageContainer = document.querySelector('.page-container');

      function updateContentPadding() {
        if (sidebar.classList.contains('collapsed')) {
          pageContainer.style.paddingLeft = '60px';
        } else {
          pageContainer.style.paddingLeft = '220px';
        }
      }

      updateContentPadding();
      document.getElementById('sidebarToggle')?.addEventListener('click', updateContentPadding);

      // Character counters
      function setupCounter(inputId, counterId, max) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);

        function update() {
          const length = input.value.length;
          counter.textContent = length + '/' + max;
          counter.className = 'character-count';
          if (length > max * 0.9) counter.classList.add('warning');
          if (length > max) counter.classList.add('error');
        }

        input.addEventListener('input', update);
        update();
      }

      setupCounter('title', 'titleCount', 200);
      setupCounter('summary', 'summaryCount', 500);
      setupCounter('content', 'contentCount', 5000);

      // Form validation
      document.getElementById('reportForm').addEventListener('submit', function (e) {
        const title = document.getElementById('title').value.trim();
        const summary = document.getElementById('summary').value.trim();
        const content = document.getElementById('content').value.trim();

        if (!title || !summary || !content) {
          e.preventDefault();
          alert('Please fill in all required fields.');
          return false;
        }

        return confirm('Are you sure you want to submit this report? Once submitted, it will be locked.');
      });
    });
  </script>
</body>

</html>
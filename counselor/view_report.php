<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$user_id = intval($_SESSION['user_id']);

// Get counselor info
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

// Get report details
$stmt = $conn->prepare("
    SELECT r.*, s.first_name, s.last_name, s.grade_level, se.start_time, se.end_time
    FROM reports r
    JOIN sessions se ON r.session_id = se.id
    JOIN students s ON se.student_id = s.id
    WHERE r.session_id = ? AND r.counselor_id = ?
");
$stmt->bind_param("ii", $session_id, $counselor_db_id);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) {
    $_SESSION['error'] = "Report not found or you don't have access.";
    header("Location: sessions.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Report - GOMS Counselor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/sessions.css">
  <style>
    .report-header { margin-bottom: 30px; }
    .report-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
    .report-section { margin-bottom: 30px; }
    .report-section h3 { border-bottom: 2px solid #007bff; padding-bottom: 5px; margin-bottom: 15px; }
    .report-content { background: white; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; }
    .locked-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <div class="page-container">
    <a href="sessions.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sessions</a>
    
    <div class="report-header">
      <h2 class="page-title">Counseling Report</h2>
      <p class="page-subtitle">Report for Session #<?= htmlspecialchars($session_id); ?></p>
      <span class="locked-badge">
        <i class="fas fa-lock"></i> Locked Report
      </span>
    </div>

    <div class="report-info">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
          <div style="color: #6c757d; font-size: 0.85rem;">Student</div>
          <div style="font-weight: 500;"><?= htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></div>
        </div>
        <div>
          <div style="color: #6c757d; font-size: 0.85rem;">Grade Level</div>
          <div style="font-weight: 500;">Grade <?= htmlspecialchars($report['grade_level']); ?></div>
        </div>
        <div>
          <div style="color: #6c757d; font-size: 0.85rem;">Session Date</div>
          <div style="font-weight: 500;"><?= date('M d, Y', strtotime($report['start_time'])); ?></div>
        </div>
        <div>
          <div style="color: #6c757d; font-size: 0.85rem;">Report Submitted</div>
          <div style="font-weight: 500;"><?= date('M d, Y', strtotime($report['submission_date'])); ?></div>
        </div>
      </div>
    </div>

    <div class="report-section">
      <h3>Report Title</h3>
      <div class="report-content">
        <h4 style="color: #007bff;"><?= htmlspecialchars($report['title']); ?></h4>
      </div>
    </div>

    <div class="report-section">
      <h3>Summary</h3>
      <div class="report-content">
        <p style="white-space: pre-line;"><?= htmlspecialchars($report['summary']); ?></p>
      </div>
    </div>

    <div class="report-section">
      <h3>Detailed Report</h3>
      <div class="report-content">
        <div style="white-space: pre-line;"><?= htmlspecialchars($report['content']); ?></div>
      </div>
    </div>

    <!-- <?php if ($report['locked'] == 1): ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle"></i>
      This report is locked and cannot be edited. To request edits, contact the system administrator.
    </div>
    <?php endif; ?> -->
  </div>

  <script src="../utils/js/sidebar.js"></script>
</body>
</html>
<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$session_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$counselor_id = intval($_SESSION['user_id']);

// Get counselor's database ID
$stmt = $conn->prepare("SELECT c.id as counselor_db_id FROM counselors c WHERE c.user_id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

if (!$counselor) {
    die("Counselor information not found.");
}

$counselor_db_id = $counselor['counselor_db_id'];

// Get session details
$stmt = $conn->prepare("
    SELECT 
        s.*,
        st.first_name, 
        st.last_name,
        st.grade_level,
        sec.section_name,
        a.id as appointment_id,
        r.id as referral_id,
        r.category as referral_category
    FROM sessions s
    JOIN students st ON s.student_id = st.id
    LEFT JOIN sections sec ON st.section_id = sec.id
    LEFT JOIN appointments a ON s.appointment_id = a.id
    LEFT JOIN referrals r ON a.referral_id = r.id
    WHERE s.id = ? AND s.counselor_id = ?
");
$stmt->bind_param("ii", $session_id, $counselor_db_id);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    die("Session not found or access denied.");
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'start' && $session['status'] == 'scheduled') {
        // Start the session
        $update = $conn->prepare("UPDATE sessions SET status = 'in_progress', updated_at = NOW() WHERE id = ?");
        $update->bind_param("i", $session_id);
        $update->execute();
        $update->close();
        
        // Update appointment if exists
        if ($session['appointment_id']) {
            $conn->query("UPDATE appointments SET status = 'in_session' WHERE id = {$session['appointment_id']}");
        }
        
        // Update referral if exists
        if ($session['referral_id']) {
            $conn->query("UPDATE referrals SET status = 'in_session' WHERE id = {$session['referral_id']}");
        }
        
        $_SESSION['success'] = "Session started successfully!";
        header("Location: session_details.php?id=$session_id");
        exit;
        
    } elseif ($action == 'complete' && $session['status'] == 'in_progress') {
        // Complete the session
        $update = $conn->prepare("UPDATE sessions SET status = 'completed', updated_at = NOW() WHERE id = ?");
        $update->bind_param("i", $session_id);
        $update->execute();
        $update->close();
        
        // Update appointment if exists
        if ($session['appointment_id']) {
            $conn->query("UPDATE appointments SET status = 'completed' WHERE id = {$session['appointment_id']}");
        }
        
        // Update referral if exists
        if ($session['referral_id']) {
            $conn->query("UPDATE referrals SET status = 'completed' WHERE id = {$session['referral_id']}");
        }
        
        $_SESSION['success'] = "Session completed successfully! Redirecting to report...";
        header("Location: create_report.php?session_id=$session_id");
        exit;
        
    } elseif ($action == 'cancel' && in_array($session['status'], ['scheduled', 'in_progress'])) {
        // Cancel the session
        $reason = trim($_POST['cancel_reason'] ?? '');
        $update = $conn->prepare("UPDATE sessions SET status = 'cancelled', notes_draft = CONCAT(IFNULL(notes_draft, ''), '\n\nCancelled: ', ?), updated_at = NOW() WHERE id = ?");
        $update->bind_param("si", $reason, $session_id);
        $update->execute();
        $update->close();
        
        $_SESSION['success'] = "Session cancelled.";
        header("Location: sessions.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Session Details - GOMS Counselor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/sessions.css">
  <style>
    .session-header { margin-bottom: 30px; }
    .session-actions { display: flex; gap: 10px; margin-bottom: 20px; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .info-card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .info-label { color: var(--clr-muted); font-size: 0.9rem; margin-bottom: 5px; }
    .info-value { font-weight: 500; }
    .notes-section { background: white; border-radius: 8px; padding: 20px; margin-top: 20px; }
    .status-badge { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 500; }
    .status-scheduled { background: #e3f2fd; color: #1976d2; }
    .status-in_progress { background: #fff3e0; color: #f57c00; }
    .status-completed { background: #e8f5e9; color: #388e3c; }
    .status-cancelled { background: #ffebee; color: #d32f2f; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Sidebar (same as sessions.php) -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>
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
    <a href="guardian_requests.php" class="nav-link">
      <span class="icon"><i class="fas fa-paper-plane"></i></span><span class="label">Appointment Requests</span>
    </a>
    <a href="sessions.php" class="nav-link">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <div class="page-container">
    <a href="sessions.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sessions</a>
    
    <div class="session-header">
      <h2 class="page-title">Session Details</h2>
      <p class="page-subtitle">Session with <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?></p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <div class="session-actions">
      <?php if ($session['status'] == 'scheduled'): ?>
        <form method="POST" style="display: inline;">
          <input type="hidden" name="action" value="start">
          <button type="submit" class="btn-primary">
            <i class="fas fa-play-circle"></i> Start Session
          </button>
        </form>
      <?php elseif ($session['status'] == 'in_progress'): ?>
        <form method="POST" style="display: inline;">
          <input type="hidden" name="action" value="complete">
          <button type="submit" class="btn-primary">
            <i class="fas fa-check-circle"></i> Complete Session
          </button>
        </form>
        
        <button type="button" class="btn-secondary" onclick="document.getElementById('cancelForm').style.display='block'">
          <i class="fas fa-times-circle"></i> Cancel Session
        </button>
        
        <div id="cancelForm" style="display: none; margin-top: 10px;">
          <form method="POST" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="cancel">
            <input type="text" name="cancel_reason" placeholder="Cancellation reason" style="flex: 1;">
            <button type="submit" class="btn-danger">Confirm Cancel</button>
            <button type="button" class="btn-secondary" onclick="document.getElementById('cancelForm').style.display='none'">Cancel</button>
          </form>
        </div>
      <?php endif; ?>
      
      <a href="create_report.php?session_id=<?= $session_id; ?>" class="btn-primary">
        <i class="fas fa-file-medical"></i> Create Report
      </a>
    </div>

    <div class="info-grid">
      <div class="info-card">
        <div class="info-label">Status</div>
        <div class="info-value">
          <span class="status-badge status-<?= $session['status']; ?>">
            <?= ucfirst(str_replace('_', ' ', $session['status'])); ?>
          </span>
        </div>
      </div>
      
      <div class="info-card">
        <div class="info-label">Date & Time</div>
        <div class="info-value">
          <?= date('F j, Y', strtotime($session['start_time'])); ?><br>
          <?= date('g:i A', strtotime($session['start_time'])); ?> - 
          <?= date('g:i A', strtotime($session['end_time'])); ?>
        </div>
      </div>
      
      <div class="info-card">
        <div class="info-label">Duration</div>
        <div class="info-value">
          <?php 
            $duration = strtotime($session['end_time']) - strtotime($session['start_time']);
            $hours = floor($duration / 3600);
            $minutes = floor(($duration % 3600) / 60);
            echo ($hours > 0 ? "$hours hr " : "") . "$minutes min";
          ?>
        </div>
      </div>
      
      <div class="info-card">
        <div class="info-label">Location & Mode</div>
        <div class="info-value">
          <?= htmlspecialchars($session['location'] ?: 'Not specified'); ?><br>
          <small><?= ucfirst($session['mode']); ?></small>
        </div>
      </div>
      
      <div class="info-card">
        <div class="info-label">Student</div>
        <div class="info-value">
          <?= htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?><br>
          <small>Grade <?= $session['grade_level']; ?></small>
        </div>
      </div>
      
      <div class="info-card">
        <div class="info-label">Session Type</div>
        <div class="info-value">
          <?= ucfirst($session['session_type'] ?: 'Regular'); ?>
        </div>
      </div>
      
      <?php if ($session['follow_up_needed']): ?>
      <div class="info-card">
        <div class="info-label">Follow-up</div>
        <div class="info-value">
          <?= $session['follow_up_date'] ? date('M d, Y', strtotime($session['follow_up_date'])) : 'Date not set'; ?>
        </div>
      </div>
      <?php endif; ?>
      
      <?php if ($session['referral_id']): ?>
      <div class="info-card">
        <div class="info-label">Referral</div>
        <div class="info-value">
          <?= ucfirst($session['referral_category']); ?><br>
          <small>ID: <?= $session['referral_id']; ?></small>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($session['notes_draft'] || $session['issues_discussed'] || $session['follow_up_plan']): ?>
    <div class="notes-section">
      <h3 style="margin-bottom: 15px;">Session Notes</h3>
      
      <?php if ($session['notes_draft']): ?>
        <div style="margin-bottom: 20px;">
          <div class="info-label">Session Notes</div>
          <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-line;">
            <?= htmlspecialchars($session['notes_draft']); ?>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if ($session['issues_discussed']): ?>
        <div style="margin-bottom: 20px;">
          <div class="info-label">Issues Discussed</div>
          <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-line;">
            <?= htmlspecialchars($session['issues_discussed']); ?>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if ($session['follow_up_plan']): ?>
        <div>
          <div class="info-label">Follow-up Plan</div>
          <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; white-space: pre-line;">
            <?= htmlspecialchars($session['follow_up_plan']); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <script src="../utils/js/sidebar.js"></script>
</body>
</html>
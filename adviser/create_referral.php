<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');
include('../includes/functions.php');

$user_id = intval($_SESSION['user_id']);
$complaint_id = isset($_GET['complaint_id']) ? intval($_GET['complaint_id']) : null;

// Get user info for sidebar
$user_info = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc();

// Get complaint info for context
$complaint_info = $conn->query("
    SELECT c.*, s.first_name, s.last_name, s.section
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    WHERE c.id = $complaint_id
")->fetch_assoc();

if (!$complaint_id) {
    header("Location: complaints.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason = $_POST['referral_reason'];
    $priority = $_POST['priority'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO referrals (complaint_id, adviser_id, referral_reason, priority, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $complaint_id, $user_id, $reason, $priority, $notes);

    if ($stmt->execute()) {
        $referral_id = $stmt->insert_id;
        logAction($user_id, 'Create Referral', 'referrals', $referral_id, "Referral created from complaint #$complaint_id");
        
        // Update complaint status with prepared statement
        $update_stmt = $conn->prepare("UPDATE complaints SET status='referred' WHERE id=?");
        $update_stmt->bind_param("i", $complaint_id);
        $update_stmt->execute();
        
        echo "<script>alert('Referral created successfully!'); window.location='referrals.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Referral - GOMS Adviser</title>
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

    .complaint-info {
      background: rgba(16, 185, 129, 0.05);
      border-left: 4px solid var(--clr-primary);
      padding: 16px 20px;
      border-radius: var(--radius-sm);
      margin-bottom: 28px;
    }

    .complaint-info-grid {
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

    .complaint-content {
      background: var(--clr-accent);
      padding: 16px;
      border-radius: var(--radius-sm);
      margin: 16px 0;
      border: 1px solid var(--clr-border);
    }

    .complaint-content .label {
      font-size: 0.75rem;
      color: var(--clr-muted);
      text-transform: uppercase;
      font-weight: 600;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }

    .complaint-content .value {
      font-size: 0.95rem;
      color: var(--clr-text);
      line-height: 1.5;
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

    textarea,
    select {
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

    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--clr-primary);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 140px;
      line-height: 1.5;
    }

    textarea.large {
      min-height: 180px;
    }

    select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 40px;
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

    .complaint-badge {
      display: inline-block;
      background: rgba(16, 185, 129, 0.15);
      color: var(--clr-primary);
      padding: 6px 14px;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .priority-info {
      display: flex;
      gap: 16px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .priority-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: var(--clr-muted);
    }

    .priority-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .priority-dot.high {
      background: var(--clr-error);
    }

    .priority-dot.medium {
      background: var(--clr-warning);
    }

    .priority-dot.low {
      background: var(--clr-success);
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 20px 16px;
      }
      
      .card {
        padding: 20px;
      }
      
      .complaint-info-grid {
        grid-template-columns: 1fr;
      }
      
      .priority-info {
        flex-direction: column;
        gap: 8px;
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
    <a href="complaints.php" class="back-link">← Back to Complaints</a>
    
    <h2 class="page-title">Create Counseling Referral</h2>
    <p class="page-subtitle">
      Referral for Complaint <span class="complaint-badge">#<?= htmlspecialchars($complaint_id); ?></span>
    </p>

    <?php if ($complaint_info): ?>
    <div class="complaint-info">
      <div class="complaint-info-grid">
        <div class="info-item">
          <div class="label">Student</div>
          <div class="value"><?= htmlspecialchars($complaint_info['first_name'] . ' ' . $complaint_info['last_name']); ?></div>
        </div>
        <div class="info-item">
          <div class="label">Section</div>
          <div class="value"><?= htmlspecialchars($complaint_info['section']); ?></div>
        </div>
        <div class="info-item">
          <div class="label">Complaint Date</div>
          <div class="value"><?= date('M d, Y', strtotime($complaint_info['created_at'])); ?></div>
        </div>
      </div>
      
      <div class="complaint-content">
        <div class="label">Complaint Content</div>
        <div class="value"><?= htmlspecialchars($complaint_info['content']); ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" action="">
        <div class="form-group">
          <label for="referral_reason">Reason for Referral</label>
          <textarea id="referral_reason" name="referral_reason" class="large" required placeholder="Describe why this case requires counselor intervention. Include specific concerns, behaviors observed, and any previous interventions attempted."></textarea>
          <p class="help-text">Explain the situation and why professional counseling is needed. Be specific about the concerns.</p>
        </div>

        <div class="form-group">
          <label for="priority">Priority Level</label>
          <select id="priority" name="priority">
            <option value="low">Low - Routine follow-up, can be scheduled flexibly</option>
            <option value="medium" selected>Medium - Standard case, should be addressed soon</option>
            <option value="high">High - Requires immediate attention, urgent case</option>
            <option value="urgent">Urgent - Critical situation needing immediate intervention</option>
          </select>
          <div class="priority-info">
            <div class="priority-item">
              <span class="priority-dot high"></span>
              <span>High: Urgent cases needing prompt attention</span>
            </div>
            <div class="priority-item">
              <span class="priority-dot medium"></span>
              <span>Medium: Standard cases for routine counseling</span>
            </div>
            <div class="priority-item">
              <span class="priority-dot low"></span>
              <span>Low: Follow-up or preventive counseling</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="notes">Additional Notes (Optional)</label>
          <textarea id="notes" name="notes" placeholder="Any additional context, observations, or relevant background information..."></textarea>
          <p class="help-text">Include any relevant background information, previous interventions, or specific observations.</p>
        </div>

        <button type="submit" class="btn-primary">📨 Submit Referral</button>
      </form>
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
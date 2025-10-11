<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');
include('../includes/functions.php');

$user_id = intval($_SESSION['user_id']);
$complaint_id = isset($_GET['complaint_id']) ? intval($_GET['complaint_id']) : null;

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
  <title>Create Referral</title>
  <link rel="stylesheet" href="../utils/css/root.css"> <!-- Global root vars -->
  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--color-bg);
      color: var(--color-text);
      min-height: 100vh;
      padding: 40px;
      box-sizing: border-box;
    }

    .page-container {
      max-width: 800px;
      margin: 0 auto;
    }

    h2.page-title {
      font-size: 1.6rem;
      color: var(--color-primary);
      font-weight: 700;
      margin-bottom: 4px;
    }

    p.page-subtitle {
      color: var(--color-muted);
      font-size: 0.95rem;
      margin-bottom: 20px;
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 30px;
    }

    .form-group {
      margin-bottom: 24px;
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--color-text);
      font-size: 0.95rem;
    }

    textarea,
    select {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--color-border);
      border-radius: 8px;
      background: var(--color-bg);
      color: var(--color-text);
      font-family: var(--font-family);
      font-size: 0.95rem;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }

    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 120px;
    }

    textarea.large {
      min-height: 180px;
    }

    select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 40px;
    }

    .btn-primary {
      display: inline-block;
      background: var(--color-primary);
      color: #fff;
      padding: 12px 28px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
      background: var(--color-secondary);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .back-link {
      display: inline-block;
      margin-bottom: 20px;
      color: var(--color-secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s ease;
    }

    .back-link:hover {
      color: var(--color-primary);
    }

    .help-text {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-top: 4px;
    }

    .complaint-badge {
      display: inline-block;
      background: rgba(99, 102, 241, 0.15);
      color: var(--color-primary);
      padding: 4px 12px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .priority-info {
      display: flex;
      gap: 12px;
      margin-top: 8px;
    }

    .priority-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.85rem;
      color: var(--color-muted);
    }

    .priority-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
    }

    .priority-dot.high {
      background: #ef4444;
    }

    .priority-dot.medium {
      background: #f59e0b;
    }

    .priority-dot.low {
      background: #22c55e;
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .card {
        padding: 20px;
      }

      .priority-info {
        flex-direction: column;
        gap: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <a href="complaints.php" class="back-link">← Back to Complaints</a>
    
    <h2 class="page-title">Create Counseling Referral</h2>
    <p class="page-subtitle">
      Complaint <span class="complaint-badge">#<?= htmlspecialchars($complaint_id); ?></span>
    </p>

    <div class="card">
      <form method="POST" action="">
        <div class="form-group">
          <label for="referral_reason">Reason for Referral</label>
          <textarea id="referral_reason" name="referral_reason" class="large" required placeholder="Describe why this case requires counselor intervention"></textarea>
          <p class="help-text">Explain the situation and why professional counseling is needed.</p>
        </div>

        <div class="form-group">
          <label for="priority">Priority Level</label>
          <select id="priority" name="priority">
            <option value="low">Low - Can be scheduled flexibly</option>
            <option value="medium" selected>Medium - Should be addressed soon</option>
            <option value="high">High - Requires immediate attention</option>
          </select>
          <div class="priority-info">
            <div class="priority-item">
              <span class="priority-dot high"></span>
              <span>High: Urgent cases</span>
            </div>
            <div class="priority-item">
              <span class="priority-dot medium"></span>
              <span>Medium: Standard cases</span>
            </div>
            <div class="priority-item">
              <span class="priority-dot low"></span>
              <span>Low: Routine follow-up</span>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label for="notes">Additional Notes (Optional)</label>
          <textarea id="notes" name="notes" placeholder="Any additional context or observations"></textarea>
          <p class="help-text">Include any relevant background information or observations.</p>
        </div>

        <button type="submit" class="btn-primary">📨 Submit Referral</button>
      </form>
    </div>
  </div>
</body>
</html>
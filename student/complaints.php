<?php
include('../includes/auth_check.php');
checkRole(['student']);
include('../config/db.php');
include('../includes/functions.php');

$user_id = intval($_SESSION['user_id']);

// Fetch student's ID safely
$student_sql = "SELECT id FROM students WHERE user_id = ?";
$student_stmt = $conn->prepare($student_sql);
$student_stmt->bind_param("i", $user_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_id = $student ? $student['id'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];
    $attachments = $_POST['attachments'] ?? '';
    $code = "CMP-" . date("Y") . "-" . rand(1000,9999);

    $stmt = $conn->prepare("INSERT INTO complaints (complaint_code, student_id, created_by_user_id, content, attachments) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("siiss", $code, $student_id, $user_id, $content, $attachments);

    if ($stmt->execute()) {
        $complaint_id = $stmt->insert_id;
        logAction($user_id, 'Submit Complaint', 'complaints', $complaint_id, 'Student filed a complaint.');
        echo "<script>alert('Complaint submitted successfully!'); window.location='complaints.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// Fetch complaints with prepared statement
$complaints_sql = "SELECT * FROM complaints WHERE created_by_user_id = ? ORDER BY created_at DESC";
$complaints_stmt = $conn->prepare($complaints_sql);
$complaints_stmt->bind_param("i", $user_id);
$complaints_stmt->execute();
$complaints_result = $complaints_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit Complaint</title>
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
      max-width: 1200px;
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
      margin-bottom: 30px;
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 30px;
      margin-bottom: 30px;
    }

    .card h3 {
      margin-top: 0;
      color: var(--color-primary);
      font-size: 1.2rem;
      margin-bottom: 20px;
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

    input[type="text"],
    textarea {
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

    input[type="text"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    textarea {
      resize: vertical;
      min-height: 150px;
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

    .help-text {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-top: 4px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
    }

    th, td {
      padding: 12px 14px;
      border-bottom: 1px solid var(--color-border);
      text-align: left;
    }

    th {
      background: rgba(255, 255, 255, 0.05);
      color: var(--color-secondary);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.85rem;
      white-space: nowrap;
    }

    tr:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .badge {
      font-size: 0.8rem;
      font-weight: 600;
      padding: 4px 8px;
      border-radius: 10px;
      display: inline-block;
    }

    .status-pending {
      background: rgba(245, 158, 11, 0.15);
      color: #f59e0b;
    }

    .status-open {
      background: rgba(59, 130, 246, 0.15);
      color: #3b82f6;
    }

    .status-referred {
      background: rgba(168, 85, 247, 0.15);
      color: #a855f7;
    }

    .status-resolved {
      background: rgba(34, 197, 94, 0.15);
      color: #22c55e;
    }

    .status-closed {
      background: rgba(107, 114, 128, 0.15);
      color: #6b7280;
    }

    .datetime {
      color: var(--color-muted);
      font-size: 0.9rem;
      white-space: nowrap;
    }

    .complaint-content {
      max-width: 400px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }

    .info-box {
      background: rgba(99, 102, 241, 0.1);
      border: 1px solid rgba(99, 102, 241, 0.2);
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 24px;
    }

    .info-box p {
      margin: 0;
      font-size: 0.9rem;
      color: var(--color-text);
      line-height: 1.6;
    }

    .info-box strong {
      color: var(--color-primary);
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .card {
        padding: 20px;
      }

      table {
        font-size: 0.85rem;
      }

      th, td {
        padding: 10px 8px;
      }

      .complaint-content {
        max-width: 200px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Submit a Complaint</h2>
    <p class="page-subtitle">Report any concerns, issues, or matters that need attention from the guidance office.</p>

    <div class="card">
      <h3>📝 New Complaint</h3>
      
      <div class="info-box">
        <p><strong>Confidentiality Notice:</strong> Your complaint will be handled with care and confidentiality. A guidance counselor will review your submission and reach out to you if needed.</p>
      </div>

      <form method="POST" action="">
        <div class="form-group">
          <label for="content">Describe Your Concern or Issue</label>
          <textarea id="content" name="content" required placeholder="Please provide as much detail as possible about your concern..."></textarea>
          <p class="help-text">Be specific about what happened, when it occurred, and who was involved.</p>
        </div>

        <div class="form-group">
          <label for="attachments">Attachment Link (Optional)</label>
          <input type="text" id="attachments" name="attachments" placeholder="Paste a link to supporting documents or images">
          <p class="help-text">You can provide a link to Google Drive, Dropbox, or any other file-sharing service.</p>
        </div>

        <button type="submit" class="btn-primary">📨 Submit Complaint</button>
      </form>
    </div>

    <div class="card">
      <h3>📋 Your Submitted Complaints</h3>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Content</th>
              <th>Status</th>
              <th>Date Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($complaints_result->num_rows > 0): ?>
              <?php while($row = $complaints_result->fetch_assoc()): 
                $status_class = 'status-' . strtolower($row['status']);
              ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['complaint_code']); ?></strong></td>
                  <td>
                    <div class="complaint-content" title="<?= htmlspecialchars($row['content']); ?>">
                      <?= htmlspecialchars($row['content']); ?>
                    </div>
                  </td>
                  <td><span class="badge <?= $status_class; ?>"><?= ucfirst($row['status']); ?></span></td>
                  <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4" class="empty">No complaints submitted yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
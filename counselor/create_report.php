<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = intval($_SESSION['user_id']);
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : null;

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
  <title>Create Report</title>
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
      min-height: 100px;
    }

    textarea.large {
      min-height: 200px;
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

    .session-badge {
      display: inline-block;
      background: rgba(99, 102, 241, 0.15);
      color: var(--color-primary);
      padding: 4px 12px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    @media (max-width: 768px) {
      body {
        padding: 20px;
      }

      .card {
        padding: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="page-container">
    <a href="sessions.php" class="back-link">← Back to Sessions</a>
    
    <h2 class="page-title">Create Counseling Report</h2>
    <p class="page-subtitle">
      Session <span class="session-badge">#<?= htmlspecialchars($session_id); ?></span>
    </p>

    <div class="card">
      <form method="POST" action="">
        <div class="form-group">
          <label for="title">Report Title</label>
          <input type="text" id="title" name="title" required placeholder="Enter report title">
          <p class="help-text">Provide a brief title that summarizes the session focus.</p>
        </div>

        <div class="form-group">
          <label for="summary">Summary</label>
          <textarea id="summary" name="summary" required placeholder="Enter a brief summary of the session"></textarea>
          <p class="help-text">Write a concise overview of the session outcomes.</p>
        </div>

        <div class="form-group">
          <label for="content">Detailed Content</label>
          <textarea id="content" name="content" class="large" required placeholder="Enter detailed session notes and observations"></textarea>
          <p class="help-text">Include detailed notes, observations, interventions, and recommendations.</p>
        </div>

        <button type="submit" class="btn-primary">🔒 Submit & Lock Report</button>
      </form>
    </div>
  </div>
</body>
</html>
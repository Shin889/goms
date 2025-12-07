<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$counselor_id = $_SESSION['user_id'];

// Get counselor info for sidebar
$counselor = $conn->query("
    SELECT username FROM users WHERE id = $counselor_id
")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $location = $_POST['location'];
    $notes = $_POST['notes_draft'];

    $stmt = $conn->prepare("INSERT INTO sessions (counselor_id, student_id, start_time, end_time, location, notes_draft, status) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')");
    $stmt->bind_param("iissss", $counselor_id, $student_id, $start, $end, $location, $notes);

    if ($stmt->execute()) {
        logAction($counselor_id, 'Create Session', 'sessions', $stmt->insert_id, "New session created.");
        echo "<script>alert('Session recorded successfully!'); window.location='sessions.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// Get student list
$students = $conn->query("SELECT id, first_name, last_name FROM students ORDER BY last_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Session - GOMS Counselor</title>
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

    .form-body {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    @media (min-width: 550px) {
      .form-row.two-col {
        grid-template-columns: 1fr 1fr;
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
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

    input[type="datetime-local"],
    select,
    input[type="text"],
    textarea {
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--clr-border);
      border-radius: var(--radius-sm);
      background: var(--clr-bg);
      color: var(--clr-text);
      font-size: 0.95rem;
      font-family: var(--font-family);
      transition: all var(--time-transition);
      box-sizing: border-box;
    }

    input[type="datetime-local"]:focus,
    select:focus,
    input[type="text"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--clr-primary);
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }

    select {
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23718096' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 40px;
    }

    textarea {
      resize: vertical;
      min-height: 140px;
      line-height: 1.5;
    }

    .btn-submit {
      width: 100%;
      padding: 14px 20px;
      background: var(--clr-primary);
      color: #fff;
      border: none;
      border-radius: var(--radius-md);
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all var(--time-transition);
      margin-top: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-submit:hover {
      background: var(--clr-secondary);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-submit:active {
      transform: translateY(0);
    }

    @media (max-width: 768px) {
      .page-container {
        padding: 20px 16px;
      }
    }

    /* Helper text */
    .helper-text {
      color: var(--clr-muted);
      font-size: 0.85rem;
      margin-top: 4px;
      font-style: italic;
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
    
    <h2 class="page-title">Create New Counseling Session</h2>
    <p class="page-subtitle">Record details of a counseling session with a student.</p>

    <div class="card">
      <form method="POST" action="">
        <div class="form-body">
          <div class="form-row">
            <div class="form-group">
              <label>Student</label>
              <select name="student_id" required>
                <option value="">Select a student...</option>
                <?php while($s = $students->fetch_assoc()): ?>
                  <option value="<?= $s['id']; ?>"><?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name']); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="form-row two-col">
            <div class="form-group">
              <label>Start Time</label>
              <input type="datetime-local" name="start_time" required>
              <div class="helper-text">When the session begins</div>
            </div>

            <div class="form-group">
              <label>End Time</label>
              <input type="datetime-local" name="end_time" required>
              <div class="helper-text">When the session ends</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Location</label>
              <input type="text" name="location" placeholder="e.g., Room 101, Online, Phone" required>
              <div class="helper-text">Where the session takes place</div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Notes Draft</label>
              <textarea name="notes_draft" placeholder="Enter session notes, observations, discussion points, follow-up actions..."></textarea>
              <div class="helper-text">These are draft notes that can be finalized later in a report</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">💾 Save Session</button>
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
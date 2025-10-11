<?php
include('../includes/auth_check.php');
checkRole(['student']);
include('../config/db.php');
include('../includes/functions.php');

// Make sure user_id is set in session
if (!isset($_SESSION['user_id'])) {
    // If not set, force logout
    header('Location: ../auth/logout.php');
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Fetch student profile safely with prepared statement
$sql = "SELECT * FROM students WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$student = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - GOMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Student</h2>
    <div class="sidebar-user">
      Student · <?= htmlspecialchars($_SESSION['username'] ?? ''); ?>
    </div>

    <a href="#" class="nav-link active" data-page="profile.php">
      <span class="icon">👤</span><span class="label">My Profile</span>
    </a>
    <a href="#" class="nav-link" data-page="complaints.php">
      <span class="icon">📝</span><span class="label">Submit Complaint</span>
    </a>
    <a href="#" class="nav-link" data-page="appointments.php">
      <span class="icon">📅</span><span class="label">Request Appointment</span>
    </a>
    <a href="#" class="nav-link" data-page="my_complaints.php">
      <span class="icon">📋</span><span class="label">My Complaints</span>
    </a>
    <a href="#" class="nav-link" data-page="my_appointments.php">
      <span class="icon">🗓️</span><span class="label">My Appointments</span>
    </a>

    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <main class="content" id="mainContent">
    <div class="loading">Loading content...</div>
  </main>

  <script src="../utils/js/sidebar.js"></script>
  <script src="../utils/js/dashboard.js"></script>
  <script>
    // Load profile content by default
    document.addEventListener('DOMContentLoaded', function() {
      const mainContent = document.getElementById('mainContent');
      
      // Create profile content
      mainContent.innerHTML = `
        <div style="max-width: 900px; margin: 0 auto;">
          <h2 class="page-title">Welcome Back!</h2>
          <p class="page-subtitle">Your personal dashboard for guidance services.</p>

          <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 14px; box-shadow: var(--shadow-sm); padding: 30px; margin-bottom: 24px;">
            <h3 style="margin-top: 0; color: var(--color-primary); font-size: 1.3rem; margin-bottom: 20px;">
              👤 Your Profile
            </h3>
            
            <?php if ($student): ?>
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div>
                  <p style="color: var(--color-muted); font-size: 0.85rem; margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Full Name</p>
                  <p style="font-size: 1.1rem; font-weight: 600; margin: 0;"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                </div>
                <div>
                  <p style="color: var(--color-muted); font-size: 0.85rem; margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Grade Level</p>
                  <p style="font-size: 1.1rem; font-weight: 600; margin: 0;"><?= htmlspecialchars($student['grade_level']); ?></p>
                </div>
                <div>
                  <p style="color: var(--color-muted); font-size: 0.85rem; margin-bottom: 4px; font-weight: 600; text-transform: uppercase;">Section</p>
                  <p style="font-size: 1.1rem; font-weight: 600; margin: 0;"><?= htmlspecialchars($student['section']); ?></p>
                </div>
              </div>
            <?php else: ?>
              <p style="color: var(--color-muted); text-align: center; padding: 20px 0;">
                No student profile found yet. Please contact your administrator.
              </p>
            <?php endif; ?>
          </div>

          <div class="card" style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 14px; box-shadow: var(--shadow-sm); padding: 30px;">
            <h3 style="margin-top: 0; color: var(--color-primary); font-size: 1.3rem; margin-bottom: 20px;">
              🚀 Quick Actions
            </h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
              <a href="#" class="nav-link" data-page="complaints.php" style="display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(99, 102, 241, 0.1); border: 1px solid var(--color-border); border-radius: 10px; text-decoration: none; color: var(--color-text); transition: all 0.2s ease;">
                <span style="font-size: 2rem;">📝</span>
                <div>
                  <p style="margin: 0; font-weight: 600; font-size: 1rem;">Submit Complaint</p>
                  <p style="margin: 0; font-size: 0.85rem; color: var(--color-muted);">Report an issue or concern</p>
                </div>
              </a>
              
              <a href="#" class="nav-link" data-page="appointments.php" style="display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(99, 102, 241, 0.1); border: 1px solid var(--color-border); border-radius: 10px; text-decoration: none; color: var(--color-text); transition: all 0.2s ease;">
                <span style="font-size: 2rem;">📅</span>
                <div>
                  <p style="margin: 0; font-weight: 600; font-size: 1rem;">Request Appointment</p>
                  <p style="margin: 0; font-size: 0.85rem; color: var(--color-muted);">Schedule a counseling session</p>
                </div>
              </a>
            </div>
          </div>
        </div>
      `;

      // Re-attach click handlers for the quick action links
      document.querySelectorAll('.nav-link[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const page = this.getAttribute('data-page');
          if (page) {
            loadPage(page);
            document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
            document.querySelector(`.nav-link[data-page="${page}"]`).classList.add('active');
          }
        });
      });
    });
  </script>
</body>
</html>
<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

// Make sure user_id is set in session
if (!isset($_SESSION['user_id'])) {
    // If not set, force logout
    header('Location: ../auth/logout.php');
    exit;
}

// Fetch admin info safely
$user_id = intval($_SESSION['user_id']);
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$admin = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - GOMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Admin</h2>
    <div class="sidebar-user">
      Admin · <?= htmlspecialchars($admin['username'] ?? ''); ?>
    </div>

    <a href="#" class="nav-link active" data-page="manage_users.php">
      <span class="icon">👥</span><span class="label">Manage Users</span>
    </a>
    <a href="#" class="nav-link" data-page="../auth/approve_user.php">
      <span class="icon">✅</span><span class="label">Approve Accounts</span>
    </a>
    <a href="#" class="nav-link" data-page="manage_adviser_sections.php">
      <span class="icon">📋</span><span class="label">Manage Sections</span>
    </a>
    <a href="#" class="nav-link" data-page="audit_logs.php">
      <span class="icon">📜</span><span class="label">View Audit Logs</span>
    </a>
    <a href="#" class="nav-link" data-page="reports.php">
      <span class="icon">📊</span><span class="label">Generate Reports</span>
    </a>

    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <main class="content" id="mainContent">
    <div class="loading">Loading content...</div>
  </main>

  <script src="../utils/js/sidebar.js"></script>
  <script src="../utils/js/dashboard.js"></script>
</body>
</html>

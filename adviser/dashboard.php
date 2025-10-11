<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

// Ensure user session
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/logout.php');
    exit;
}

// Fetch adviser info
$user_id = intval($_SESSION['user_id']);
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$adviser = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Adviser Dashboard - GOMS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
</head>
<body>
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>

    <h2 class="logo">GOMS Adviser</h2>
    <div class="sidebar-user">
      Adviser · <?= htmlspecialchars($adviser['username'] ?? ''); ?>
    </div>

    <a href="#" class="nav-link active" data-page="complaints.php">
      <span class="icon">📋</span><span class="label">Student Complaints</span>
    </a>

    <a href="#" class="nav-link" data-page="referrals.php">
      <span class="icon">📨</span><span class="label">Referrals</span>
    </a>

   <!--  <a href="#" class="nav-link" data-page="reports.php">
      <span class="icon">📊</span><span class="label">Generate Reports</span>
    </a> -->

    <a href="../auth/logout.php" class="logout-link">Logout</a>
  </nav>

  <main class="content" id="mainContent">
    <div class="loading">Loading content...</div>
  </main>

  <script src="../utils/js/sidebar.js"></script>
  <script src="../utils/js/dashboard.js"></script>
</body>
</html>

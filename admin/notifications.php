<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');
$result = $conn->query("SELECT * FROM notifications_log ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Notifications Log - GOMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <h2>SMS Notifications Log</h2>
  <a href="dashboard.php">← Back to Dashboard</a>
  <hr>

  <table border="1" cellpadding="6">
    <tr><th>Date</th><th>Recipient</th><th>Message</th><th>Status</th></tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
      <td><?= $row['created_at']; ?></td>
      <td><?= htmlspecialchars($row['recipient_number']); ?></td>
      <td><?= htmlspecialchars($row['message']); ?></td>
      <td><?= ucfirst($row['status']); ?></td>
    </tr>
    <?php endwhile; ?>
  </table>
</body>
</html>

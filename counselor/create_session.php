<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$counselor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $location = $_POST['location'];
    $notes = $_POST['notes_draft'];

    $stmt = $conn->prepare("INSERT INTO sessions (counselor_id, student_id, start_time, end_time, location, notes_draft) VALUES (?, ?, ?, ?, ?, ?)");
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
  <title>Create Session - GOMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <h2>Create New Counseling Session</h2>
  <a href="sessions.php">← Back to Sessions</a>
  <hr>

  <form method="POST" action="">
      <label>Student:</label><br>
      <select name="student_id" required>
          <?php while($s = $students->fetch_assoc()): ?>
              <option value="<?= $s['id']; ?>"><?= $s['last_name']; ?>, <?= $s['first_name']; ?></option>
          <?php endwhile; ?>
      </select><br><br>

      <label>Start Time:</label><br>
      <input type="datetime-local" name="start_time" required><br><br>

      <label>End Time:</label><br>
      <input type="datetime-local" name="end_time" required><br><br>

      <label>Location:</label><br>
      <input type="text" name="location" required><br><br>

      <label>Notes Draft:</label><br>
      <textarea name="notes_draft" rows="5" cols="60"></textarea><br><br>

      <button type="submit">Save Session</button>
  </form>
</body>
</html>

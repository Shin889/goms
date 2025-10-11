<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');
include_once('../includes/sms_helper.php');

$counselor_id = $_SESSION['user_id'];
$referral_id = $_GET['referral_id'] ?? null;

if (!$referral_id) {
    header("Location: referrals.php");
    exit;
}

// Fetch student linked to referral
$ref = $conn->query("
  SELECT c.student_id, s.first_name, s.last_name
  FROM referrals r
  JOIN complaints c ON r.complaint_id = c.id
  JOIN students s ON c.student_id = s.id
  WHERE r.id = $referral_id
")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];
    $student_id = $ref['student_id'];
    $appointment_code = "APT-" . date("Y") . "-" . rand(1000,9999);

    $stmt = $conn->prepare("
      INSERT INTO appointments (appointment_code, requested_by_user_id, student_id, counselor_id, referral_id, start_time, end_time, mode, notes, status)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')
    ");
    $stmt->bind_param("siiiissss", $appointment_code, $counselor_id, $student_id, $counselor_id, $referral_id, $start, $end, $mode, $notes);

    if ($stmt->execute()) {
        logAction($counselor_id, 'Create Appointment', 'appointments', $stmt->insert_id, "From referral #$referral_id");

        // ✅ Send SMS here after appointment creation
        $guardian = $conn->query("
          SELECT g.phone 
          FROM student_guardians sg 
          JOIN guardians g ON sg.guardian_id = g.id 
          WHERE sg.student_id = $student_id
        ")->fetch_assoc();

        if ($guardian && !empty($guardian['phone'])) {
            $msg = "Your child has a counseling appointment set on $start. Please confirm or be available.";
            sendSMS($counselor_id, $guardian['phone'], $msg);
        }

        echo "<script>alert('Appointment created successfully!'); window.location='appointments.php';</script>";
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
  <title>Book Appointment - GOMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <h2>Book Appointment for <?= htmlspecialchars($ref['first_name'].' '.$ref['last_name']); ?></h2>
  <a href="referrals.php">← Back to Referrals</a>
  <hr>

  <form method="POST" action="">
      <label>Start Time:</label><br>
      <input type="datetime-local" name="start_time" required><br><br>

      <label>End Time:</label><br>
      <input type="datetime-local" name="end_time" required><br><br>

      <label>Mode:</label><br>
      <select name="mode">
          <option value="in-person">In-person</option>
          <option value="online">Online</option>
          <option value="phone">Phone</option>
      </select><br><br>

      <label>Notes:</label><br>
      <textarea name="notes" rows="3" cols="50"></textarea><br><br>

      <button type="submit">Confirm Appointment</button>
  </form>
</body>
</html>

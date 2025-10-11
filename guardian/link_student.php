<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');
include('../includes/functions.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info safely
$guardian_sql = "SELECT * FROM guardians WHERE user_id = ?";
$guardian_stmt = $conn->prepare($guardian_sql);
$guardian_stmt->bind_param("i", $user_id);
$guardian_stmt->execute();
$guardian_result = $guardian_stmt->get_result();
$guardian = $guardian_result->fetch_assoc();
$guardian_id = $guardian ? $guardian['id'] : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);

    // Check if already linked with prepared statement
    $check_sql = "SELECT * FROM student_guardians WHERE student_id = ? AND guardian_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $student_id, $guardian_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        echo "<script>alert('Already linked to this student.');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_guardians (student_id, guardian_id, linked_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $student_id, $guardian_id, $user_id);
        if ($stmt->execute()) {
            $link_id = $stmt->insert_id;
            logAction($user_id, 'Link Guardian to Student', 'student_guardians', $link_id, "Guardian linked to student #$student_id");
            echo "<script>alert('Linked successfully! Awaiting admin confirmation.'); window.location='link_student.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }
    }
}

// Fetch all students for selection with prepared statement
$students = $conn->query("SELECT id, first_name, last_name, section FROM students ORDER BY last_name ASC");

// Fetch linked students with prepared statement
$linked_sql = "
    SELECT sg.*, s.first_name, s.last_name, s.section
    FROM student_guardians sg
    JOIN students s ON sg.student_id = s.id
    WHERE sg.guardian_id = ?
";
$linked_stmt = $conn->prepare($linked_sql);
$linked_stmt->bind_param("i", $guardian_id);
$linked_stmt->execute();
$linked_result = $linked_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Link to Student</title>
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
      max-width: 1000px;
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
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 14px center;
      padding-right: 40px;
    }

    select:focus {
      outline: none;
      border-color: var(--color-primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
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

    .datetime {
      color: var(--color-muted);
      font-size: 0.9rem;
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
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Link to Student</h2>
    <p class="page-subtitle">Connect your guardian account to your child's student profile.</p>

    <div class="card">
      <h3>🔗 Link Your Account</h3>
      
      <div class="info-box">
        <p><strong>Important:</strong> After linking, an administrator will need to verify and approve the connection before you can access your child's information.</p>
      </div>

      <form method="POST" action="">
        <div class="form-group">
          <label for="student_id">Select Student</label>
          <select id="student_id" name="student_id" required>
            <option value="">-- Choose Your Child --</option>
            <?php while($s = $students->fetch_assoc()): ?>
              <option value="<?= $s['id']; ?>">
                <?= htmlspecialchars($s['last_name'].', '.$s['first_name'].' ('.$s['section'].')'); ?>
              </option>
            <?php endwhile; ?>
          </select>
          <p class="help-text">Select the student you are a guardian of. You can link multiple students if needed.</p>
        </div>

        <button type="submit" class="btn-primary">🔗 Link Student</button>
      </form>
    </div>

    <div class="card">
      <h3>👨‍👩‍👧‍👦 Your Linked Students</h3>
      <div style="overflow-x: auto;">
        <table>
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Section</th>
              <th>Linked Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($linked_result->num_rows > 0): ?>
              <?php while($row = $linked_result->fetch_assoc()): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></strong></td>
                  <td><?= htmlspecialchars($row['section']); ?></td>
                  <td class="datetime"><?= date('M d, Y h:i A', strtotime($row['linked_at'])); ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="3" class="empty">No linked students yet. Link a student using the form above.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
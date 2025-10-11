<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where = '';
if ($start_date && $end_date) {
  $where = "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
}

// Fetch summary counts
$complaints = $conn->query("SELECT COUNT(*) AS total FROM complaints $where")->fetch_assoc()['total'];
$appointments = $conn->query("SELECT COUNT(*) AS total FROM appointments $where")->fetch_assoc()['total'];
$sessions = $conn->query("SELECT COUNT(*) AS total FROM sessions $where")->fetch_assoc()['total'];
$reports = $conn->query("SELECT COUNT(*) AS total FROM reports $where")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports & Exports - GOMS</title>
  <link rel="stylesheet" href="../utils/css/root.css"> <!-- Apply global variables -->
  <style>
    body {
      margin: 0;
      font-family: var(--font-family);
      background: var(--color-bg);
      color: var(--color-text);
      padding: 40px;
      min-height: 100vh;
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
      margin-bottom: 20px;
    }

    .card {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 14px;
      box-shadow: var(--shadow-sm);
      padding: 20px;
      margin-top: 20px;
      overflow-x: auto;
    }

    a.back-link {
      display: inline-block;
      margin-bottom: 14px;
      color: var(--color-secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s ease;
    }

    a.back-link:hover {
      color: var(--color-primary);
    }

    form.filter-form {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      margin-bottom: 20px;
    }

    form.filter-form label {
      font-weight: 500;
      color: var(--color-text);
    }

    form.filter-form input[type="date"] {
      background: var(--color-surface);
      border: 1px solid var(--color-border);
      border-radius: 8px;
      color: var(--color-text);
      padding: 8px 10px;
      font-size: 0.95rem;
    }

    form.filter-form button {
      background: var(--color-primary);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 8px 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    form.filter-form button:hover {
      background: var(--color-secondary);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
      margin-top: 10px;
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
    }

    tr:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .export-buttons {
      display: flex;
      gap: 10px;
      margin-top: 20px;
    }

    .export-buttons button {
      background: var(--color-accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 10px 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
    }

    .export-buttons button:hover {
      background: var(--color-primary);
    }

    .empty {
      text-align: center;
      color: var(--color-muted);
      padding: 20px 0;
    }
  </style>
</head>
<body>
  <div class="page-container">
    <h2 class="page-title">Reports & Exports</h2>
    <p class="page-subtitle">Filter records by date and export summary reports for complaints, appointments, and sessions.</p>

    <form method="GET" class="filter-form">
      <label>From:</label>
      <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
      <label>To:</label>
      <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
      <button type="submit">Filter</button>
    </form>

    <div class="card">
      <h3>Summary</h3>
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Complaints Filed</td><td><?= $complaints ?></td></tr>
          <tr><td>Appointments Booked</td><td><?= $appointments ?></td></tr>
          <tr><td>Sessions Conducted</td><td><?= $sessions ?></td></tr>
          <tr><td>Reports Filed</td><td><?= $reports ?></td></tr>
        </tbody>
      </table>

      <form method="POST" action="export_report.php" class="export-buttons">
        <input type="hidden" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        <input type="hidden" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        <button type="submit" name="export_type" value="csv">Export to CSV</button>
        <button type="submit" name="export_type" value="pdf">Export to PDF</button>
      </form>
    </div>
  </div>
</body>
</html>

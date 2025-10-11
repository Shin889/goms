<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$export_type = $_POST['export_type'] ?? 'csv';

$where = '';
if ($start_date && $end_date) {
    $where = "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
}

// Fetch detailed report data
$data = $conn->query("
  SELECT 'Complaint' AS type, complaint_code AS code, student_id, created_at AS date
  FROM complaints $where
  UNION ALL
  SELECT 'Appointment', appointment_code, student_id, created_at
  FROM appointments $where
  UNION ALL
  SELECT 'Session', id, student_id, created_at
  FROM sessions $where
  UNION ALL
  SELECT 'Report', id, counselor_id, created_at
  FROM reports $where
  ORDER BY date DESC
");

if ($export_type == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="goms_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Type', 'Code/ID', 'User/Student ID', 'Date Created']);
    while($row = $data->fetch_assoc()) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
} else {
    // PDF export
    require('../lib/fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Guidance Office Summary Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "From $start_date to $end_date", 0, 1, 'C');
    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 8, 'Type', 1);
    $pdf->Cell(50, 8, 'Code/ID', 1);
    $pdf->Cell(40, 8, 'User/Student ID', 1);
    $pdf->Cell(50, 8, 'Date Created', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 10);
    while ($row = $data->fetch_assoc()) {
        $pdf->Cell(40, 8, $row['type'], 1);
        $pdf->Cell(50, 8, $row['code'], 1);
        $pdf->Cell(40, 8, $row['student_id'], 1);
        $pdf->Cell(50, 8, $row['date'], 1);
        $pdf->Ln();
    }

    $pdf->Output('D', 'GOMS_Report.pdf');
    exit;
}
?>

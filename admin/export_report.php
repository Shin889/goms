<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

// Get export parameters
$report_type = $_GET['type'] ?? $_POST['type'] ?? 'summary';
$format = $_GET['format'] ?? $_POST['format'] ?? 'pdf';
$start_date = $_GET['start_date'] ?? $_POST['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? $_POST['end_date'] ?? date('Y-m-t');
$period = $_GET['period'] ?? $_POST['period'] ?? 'monthly';
$year = $_GET['year'] ?? $_POST['year'] ?? date('Y');

// Validate dates
if (!empty($start_date) && !empty($end_date)) {
    $start_date = date('Y-m-d', strtotime($start_date));
    $end_date = date('Y-m-d', strtotime($end_date));
} else {
    // Default to current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
}

// Log the export action
logAction($_SESSION['user_id'], 'EXPORT_REPORT', "Exported $report_type report ($format)", 'reports', null, null, [
    'format' => $format,
    'period' => $period,
    'start_date' => $start_date,
    'end_date' => $end_date
]);

// Function to generate Guidance Office format summary
function generateGuidanceOfficeSummary($conn, $start_date, $end_date, $period, $year) {
    $summary = [];
    
    // 1. CASE STATISTICS
    // Total new complaints (created within period)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM complaints 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['new_cases'] = $result->fetch_assoc()['count'];
    
    // Total referrals
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM referrals 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['referrals'] = $result->fetch_assoc()['count'];
    
    // Appointments scheduled
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status != 'cancelled'
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['appointments'] = $result->fetch_assoc()['count'];
    
    // Sessions conducted
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM sessions 
        WHERE DATE(start_time) BETWEEN ? AND ?
        AND status = 'completed'
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['sessions'] = $result->fetch_assoc()['count'];
    
    // Reports submitted
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM reports 
        WHERE DATE(submission_date) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['reports'] = $result->fetch_assoc()['count'];
    
    // Total active cases (complaints not closed)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM complaints 
        WHERE status != 'closed'
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['ongoing_cases'] = $result->fetch_assoc()['count'];
    
    // Resolved cases (complaints closed within period)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM complaints 
        WHERE status = 'closed'
        AND DATE(updated_at) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['resolved_cases'] = $result->fetch_assoc()['count'];
    
    // 2. CASELOAD DISTRIBUTION
    // By grade level
    $stmt = $conn->prepare("
        SELECT s.grade_level, COUNT(c.id) as count
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        WHERE DATE(c.created_at) BETWEEN ? AND ?
        GROUP BY s.grade_level
        ORDER BY s.grade_level
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['by_grade'] = [];
    while ($row = $result->fetch_assoc()) {
        $summary['by_grade'][] = $row;
    }
    
    // By concern category
    $stmt = $conn->prepare("
        SELECT category, COUNT(*) as count
        FROM complaints
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY category
        ORDER BY count DESC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['by_category'] = [];
    while ($row = $result->fetch_assoc()) {
        $summary['by_category'][] = $row;
    }
    
    // 3. COUNSELOR ACTIVITY
    $stmt = $conn->prepare("
        SELECT 
            co.name as counselor_name,
            COUNT(DISTINCT s.id) as sessions_count,
            COUNT(DISTINCT r.id) as reports_count
        FROM counselors co
        LEFT JOIN sessions s ON co.id = s.counselor_id AND DATE(s.start_time) BETWEEN ? AND ?
        LEFT JOIN reports r ON s.id = r.session_id
        GROUP BY co.id, co.name
        ORDER BY sessions_count DESC
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['counselor_activity'] = [];
    while ($row = $result->fetch_assoc()) {
        $summary['counselor_activity'][] = $row;
    }
    
    // 4. INTERVENTIONS PROVIDED
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN interventions_used IS NOT NULL THEN s.id END) as with_interventions,
            COUNT(DISTINCT s.id) as total_sessions
        FROM sessions s
        WHERE DATE(start_time) BETWEEN ? AND ?
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['interventions'] = $result->fetch_assoc();
    
    // 5. STUDENT DEMOGRAPHICS
    $stmt = $conn->prepare("
        SELECT 
            gender,
            COUNT(DISTINCT c.student_id) as student_count
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        WHERE DATE(c.created_at) BETWEEN ? AND ?
        GROUP BY gender
    ");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary['demographics'] = [];
    while ($row = $result->fetch_assoc()) {
        $summary['demographics'][] = $row;
    }
    
    return $summary;
}

// Handle different report types
switch ($report_type) {
    case 'summary':
        $data = generateGuidanceOfficeSummary($conn, $start_date, $end_date, $period, $year);
        $filename = "GOMS_Summary_" . date('Y-m-d') . ".$format";
        break;
        
    case 'audit_logs':
        // Get audit logs for export
        $stmt = $conn->prepare("
            SELECT 
                a.id,
                a.action,
                a.action_summary,
                a.target_table,
                u.full_name as user_name,
                u.username,
                a.created_at
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE DATE(a.created_at) BETWEEN ? AND ?
            ORDER BY a.created_at DESC
        ");
        $stmt->bind_param("ss", $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $filename = "GOMS_Audit_Logs_" . date('Y-m-d') . ".$format";
        break;
        
    case 'user_activity':
        // User activity report
        $stmt = $conn->prepare("
            SELECT 
                u.full_name,
                u.username,
                u.role,
                u.email,
                u.phone,
                u.is_active,
                u.is_approved,
                COUNT(DISTINCT a.id) as login_count,
                MAX(a.created_at) as last_login
            FROM users u
            LEFT JOIN audit_logs a ON u.id = a.user_id AND a.action = 'LOGIN'
            WHERE u.role != 'admin'
            GROUP BY u.id, u.full_name, u.username, u.role, u.email, u.phone, u.is_active, u.is_approved
            ORDER BY u.role, u.full_name
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $filename = "GOMS_User_Activity_" . date('Y-m-d') . ".$format";
        break;
        
    default:
        die("Invalid report type");
}

// Generate export based on format
if ($format == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($report_type == 'summary') {
        // Summary report in CSV format
        fputcsv($output, ['GUIDANCE OFFICE SUMMARY REPORT']);
        fputcsv($output, ['Reporting Period:', date('F Y', strtotime($start_date))]);
        fputcsv($output, []);
        
        fputcsv($output, ['I. CASE STATISTICS']);
        fputcsv($output, ['A. Total New Cases', $data['new_cases']]);
        fputcsv($output, ['B. Total Referrals', $data['referrals']]);
        fputcsv($output, ['C. Appointments Scheduled', $data['appointments']]);
        fputcsv($output, ['D. Sessions Conducted', $data['sessions']]);
        fputcsv($output, ['E. Reports Submitted', $data['reports']]);
        fputcsv($output, ['F. Ongoing Cases', $data['ongoing_cases']]);
        fputcsv($output, ['G. Resolved Cases', $data['resolved_cases']]);
        fputcsv($output, []);
        
        fputcsv($output, ['II. CASELOAD DISTRIBUTION']);
        fputcsv($output, ['Grade Level', 'Number of Cases']);
        foreach ($data['by_grade'] as $grade) {
            fputcsv($output, [$grade['grade_level'], $grade['count']]);
        }
        fputcsv($output, []);
        
        fputcsv($output, ['III. COUNSELOR ACTIVITY']);
        fputcsv($output, ['Counselor', 'Sessions', 'Reports']);
        foreach ($data['counselor_activity'] as $counselor) {
            fputcsv($output, [
                $counselor['counselor_name'],
                $counselor['sessions_count'],
                $counselor['reports_count']
            ]);
        }
        
    } else {
        // Other reports
        if (!empty($data)) {
            // Get headers from first row
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
    }
    
    fclose($output);
    
} elseif ($format == 'pdf') {
    require('../lib/fpdf/fpdf.php');
    
    class PDF extends FPDF {
        // Page header
        function Header() {
            // Logo
            $this->Image('../utils/images/cnhslogo.png', 10, 8, 25);
            // Arial bold 15
            $this->SetFont('Arial', 'B', 16);
            // Move to the right
            $this->Cell(30);
            // Title
            $this->Cell(130, 10, 'GUIDANCE OFFICE MANAGEMENT SYSTEM', 0, 0, 'C');
            // Date
            $this->SetFont('Arial', '', 10);
            $this->Cell(30, 10, date('F d, Y'), 0, 0, 'R');
            // Line break
            $this->Ln(20);
        }
        
        // Page footer
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
        
        // Function to create Guidance Office format table
        function GuidanceOfficeSummary($data, $start_date, $end_date) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'GUIDANCE OFFICE SUMMARY REPORT', 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, 'Reporting Period: ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'C');
            $this->Ln(10);
            
            // I. CASE STATISTICS
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'I. CASE STATISTICS', 0, 1);
            $this->SetFont('Arial', '', 11);
            
            $this->Cell(100, 8, 'A. New Cases Reported:', 0, 0);
            $this->Cell(30, 8, $data['new_cases'], 0, 1);
            
            $this->Cell(100, 8, 'B. Referrals Created:', 0, 0);
            $this->Cell(30, 8, $data['referrals'], 0, 1);
            
            $this->Cell(100, 8, 'C. Appointments Scheduled:', 0, 0);
            $this->Cell(30, 8, $data['appointments'], 0, 1);
            
            $this->Cell(100, 8, 'D. Sessions Conducted:', 0, 0);
            $this->Cell(30, 8, $data['sessions'], 0, 1);
            
            $this->Cell(100, 8, 'E. Reports Submitted:', 0, 0);
            $this->Cell(30, 8, $data['reports'], 0, 1);
            
            $this->Cell(100, 8, 'F. Ongoing Cases:', 0, 0);
            $this->Cell(30, 8, $data['ongoing_cases'], 0, 1);
            
            $this->Cell(100, 8, 'G. Resolved Cases:', 0, 0);
            $this->Cell(30, 8, $data['resolved_cases'], 0, 1);
            
            $this->Ln(10);
            
            // II. CASELOAD DISTRIBUTION
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'II. CASELOAD DISTRIBUTION', 0, 1);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(60, 8, 'Grade Level', 1, 0, 'C');
            $this->Cell(40, 8, 'Number of Cases', 1, 1, 'C');
            $this->SetFont('Arial', '', 11);
            
            if (!empty($data['by_grade'])) {
                foreach ($data['by_grade'] as $grade) {
                    $this->Cell(60, 8, $grade['grade_level'], 1);
                    $this->Cell(40, 8, $grade['count'], 1, 1, 'C');
                }
            } else {
                $this->Cell(100, 8, 'No data available', 1, 1, 'C');
            }
            
            $this->Ln(10);
            
            // III. COUNSELOR ACTIVITY
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'III. COUNSELOR ACTIVITY', 0, 1);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(80, 8, 'Counselor', 1, 0, 'C');
            $this->Cell(40, 8, 'Sessions', 1, 0, 'C');
            $this->Cell(40, 8, 'Reports', 1, 1, 'C');
            $this->SetFont('Arial', '', 11);
            
            if (!empty($data['counselor_activity'])) {
                foreach ($data['counselor_activity'] as $counselor) {
                    $this->Cell(80, 8, $counselor['counselor_name'], 1);
                    $this->Cell(40, 8, $counselor['sessions_count'], 1, 0, 'C');
                    $this->Cell(40, 8, $counselor['reports_count'], 1, 1, 'C');
                }
            } else {
                $this->Cell(160, 8, 'No data available', 1, 1, 'C');
            }
            
            $this->Ln(15);
            
            // Recommendations section
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'IV. RECOMMENDATIONS', 0, 1);
            $this->SetFont('Arial', '', 11);
            $this->MultiCell(0, 8, '________________________________________________________________________________');
            $this->MultiCell(0, 8, '________________________________________________________________________________');
            $this->MultiCell(0, 8, '________________________________________________________________________________');
            $this->Ln(10);
            
            // Prepared by section
            $this->Cell(0, 8, 'Prepared by:', 0, 1);
            $this->Ln(15);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(60, 8, '________________________', 0, 0);
            $this->Cell(60, 8, '________________________', 0, 1);
            $this->SetFont('Arial', '', 10);
            $this->Cell(60, 6, 'Signature', 0, 0);
            $this->Cell(60, 6, 'Date', 0, 1);
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 11);
            $this->Cell(120, 8, 'Name: ________________________', 0, 1);
            $this->Cell(120, 8, 'Position: ________________________', 0, 1);
        }
    }
    
    // Create PDF
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    if ($report_type == 'summary') {
        // Guidance Office format summary
        $pdf->GuidanceOfficeSummary($data, $start_date, $end_date);
    } elseif ($report_type == 'audit_logs') {
        // Audit logs table
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'AUDIT LOGS REPORT', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 6, date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date)), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Table header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 8, 'ID', 1);
        $pdf->Cell(40, 8, 'User', 1);
        $pdf->Cell(30, 8, 'Action', 1);
        $pdf->Cell(60, 8, 'Summary', 1);
        $pdf->Cell(25, 8, 'Table', 1);
        $pdf->Cell(25, 8, 'Date', 1);
        $pdf->Ln();
        
        // Table data
        $pdf->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            $pdf->Cell(20, 8, $row['id'], 1);
            $pdf->Cell(40, 8, substr($row['user_name'] ?: $row['username'], 0, 20), 1);
            $pdf->Cell(30, 8, substr($row['action'], 0, 15), 1);
            $pdf->Cell(60, 8, substr($row['action_summary'], 0, 40), 1);
            $pdf->Cell(25, 8, substr($row['target_table'], 0, 10), 1);
            $pdf->Cell(25, 8, date('m/d/y', strtotime($row['created_at'])), 1);
            $pdf->Ln();
        }
    }
    
    $pdf->Output('D', $filename);
    
} elseif ($format == 'excel') {
    // Excel export using HTML table (can be opened in Excel)
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    
    if ($report_type == 'summary') {
        echo '<table border="1">';
        echo '<tr><th colspan="2" style="font-size:16px;text-align:center;">GUIDANCE OFFICE SUMMARY REPORT</th></tr>';
        echo '<tr><th colspan="2">Reporting Period: ' . date('F Y', strtotime($start_date)) . '</th></tr>';
        echo '<tr><td colspan="2"></td></tr>';
        
        echo '<tr><th colspan="2">I. CASE STATISTICS</th></tr>';
        echo '<tr><td>A. New Cases Reported:</td><td>' . $data['new_cases'] . '</td></tr>';
        echo '<tr><td>B. Referrals Created:</td><td>' . $data['referrals'] . '</td></tr>';
        echo '<tr><td>C. Appointments Scheduled:</td><td>' . $data['appointments'] . '</td></tr>';
        echo '<tr><td>D. Sessions Conducted:</td><td>' . $data['sessions'] . '</td></tr>';
        echo '<tr><td>E. Reports Submitted:</td><td>' . $data['reports'] . '</td></tr>';
        echo '<tr><td>F. Ongoing Cases:</td><td>' . $data['ongoing_cases'] . '</td></tr>';
        echo '<tr><td>G. Resolved Cases:</td><td>' . $data['resolved_cases'] . '</td></tr>';
        
        echo '<tr><td colspan="2"></td></tr>';
        echo '<tr><th colspan="2">II. CASELOAD DISTRIBUTION</th></tr>';
        echo '<tr><th>Grade Level</th><th>Number of Cases</th></tr>';
        
        foreach ($data['by_grade'] as $grade) {
            echo '<tr><td>' . $grade['grade_level'] . '</td><td>' . $grade['count'] . '</td></tr>';
        }
        
        echo '</table>';
    } else {
        // Other reports as tables
        if (!empty($data)) {
            echo '<table border="1">';
            // Headers
            echo '<tr>';
            foreach (array_keys($data[0]) as $header) {
                echo '<th>' . htmlspecialchars($header) . '</th>';
            }
            echo '</tr>';
            
            // Data rows
            foreach ($data as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars($cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    
    echo '</body></html>';
    
} else {
    die("Invalid format specified");
}

exit;
?>
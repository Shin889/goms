<?php
include('../config/db.php');
include('../includes/auth_check.php');
checkRole(['counselor']);

$user_id = $_SESSION['user_id'];

// Fetch counselor info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$counselor = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle appointment status updates
if (isset($_POST['update_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("si", $status, $appointment_id);

    if ($stmt->execute()) {
        $_SESSION['msg'] = "✅ Appointment status updated successfully!";
    } else {
        $_SESSION['msg'] = "❌ Failed to update status: " . $stmt->error;
    }

    $stmt->close();
    header("Location: appointments.php");
    exit();
}

// Handle new appointment creation
if (isset($_POST['create_appointment'])) {
    $student_id = intval($_POST['student_id']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $mode = $_POST['mode'];
    $notes = $_POST['notes'];

    $appointment_code = "APT-" . date("YmdHis") . "-" . strtoupper(substr(md5(uniqid()), 0, 4));

    $stmt = $conn->prepare("INSERT INTO appointments (
        appointment_code, requested_by_user_id, student_id, counselor_id,
        start_time, end_time, mode, status, notes, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "siiissss",
        $appointment_code,
        $user_id,
        $student_id,
        $user_id,
        $start_time,
        $end_time,
        $mode,
        $notes
    );

    if ($stmt->execute()) {
        $_SESSION['msg'] = "✅ Appointment created successfully!";
    } else {
        $_SESSION['msg'] = "❌ Error creating appointment: " . $stmt->error;
    }

    $stmt->close();
    header("Location: appointments.php");
    exit();
}

// Fetch all appointments assigned to this counselor
$sql = "
SELECT 
    a.id, 
    a.appointment_code, 
    a.start_time, 
    a.end_time, 
    a.mode, 
    a.status, 
    a.notes,
    s.first_name, 
    s.last_name, 
    s.grade_level,
    sec.section_name as section,
    TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) as duration_minutes
FROM appointments a
JOIN students s ON a.student_id = s.id
LEFT JOIN sections sec ON s.section_id = sec.id
WHERE a.counselor_id = ?
ORDER BY a.start_time DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Error: " . $conn->error . "<br>Query: " . htmlspecialchars($sql));
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments_result = $stmt->get_result();

// Store appointments for later use
$appointments_data = [];
while ($row = $appointments_result->fetch_assoc()) {
    $appointments_data[] = $row;
}

// Fetch student list for dropdown
$students_result = $conn->query("
    SELECT s.id, s.first_name, s.last_name, s.grade_level, sec.section_name
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    ORDER BY s.last_name ASC
");
$students = [];
if ($students_result) {
    while ($stu = $students_result->fetch_assoc()) {
        $students[] = $stu;
    }
}

// AJAX endpoint for calendar data
if (isset($_GET['get_calendar_events'])) {
    $events = [];
    foreach ($appointments_data as $row) {
        $color_map = [
            'confirmed' => 'var(--clr-info)',
            'completed' => 'var(--clr-success)',
            'cancelled' => 'var(--clr-error)',
            'pending' => 'var(--clr-warning)',
            'scheduled' => '#8b5cf6'
        ];

        $events[] = [
            'id' => $row['id'],
            'title' => $row['first_name'] . ' ' . $row['last_name'],
            'start' => $row['start_time'],
            'end' => $row['end_time'],
            'color' => $color_map[$row['status']] ?? 'var(--clr-muted)',
            'extendedProps' => [
                'code' => $row['appointment_code'],
                'mode' => $row['mode'],
                'status' => $row['status'],
                'notes' => $row['notes'],
                'student' => $row['first_name'] . ' ' . $row['last_name'],
                'section' => $row['section']
            ]
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($events);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Appointments - GOMS</title>
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- FullCalendar CSS -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        /* Use CSS Variables from root.css */
        :root {
            --clr-bg: #ffffff;
            --clr-surface: #f7f7f7;
            --clr-text: #2d3748;
            --clr-muted: #718096;
            --clr-border: #e2e8f0;
            --clr-primary: #10b981;
            --clr-secondary: #059669;
            --clr-accent: #edf2f7;
            --clr-success: #059669;
            --clr-error: #f87171;
            --clr-warning: #fbbf24;
            --clr-info: #3b82f6;
            --font-family: "Inter", "Poppins", "Segoe UI", sans-serif;
            --fs-base: 16px;
            --fs-heading: 1.75rem;
            --fs-subheading: 1.25rem;
            --fs-small: 0.875rem;
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1), 0 1px 2px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
            --time-transition: 0.3s ease;
            --layout-sidebar-width: 260px;
            --layout-sidebar-collapsed-width: 72px;
        }

        /* Base Layout */
        body {
            font-family: var(--font-family);
            background: var(--clr-bg);
            color: var(--clr-text);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Main Content Area - Updated for proper sidebar integration */
        .content-wrap {
            display: flex;
            min-height: 100vh;
        }

        .content-area {
            flex: 1;
            padding: 50px;
            margin-left: var(--layout-sidebar-width);
            transition: margin-left var(--time-transition);
            overflow-y: auto;
        }

        /* When sidebar is collapsed */
        .sidebar.collapsed ~ .content-wrap .content-area {
            margin-left: var(--layout-sidebar-collapsed-width);
        }

        @media (max-width: 900px) {
            .content-area {
                margin-left: var(--layout-sidebar-collapsed-width);
            }
            
            .sidebar:not(.collapsed) ~ .content-wrap .content-area {
                margin-left: var(--layout-sidebar-width);
            }
        }

        /* Page Header */
        .page-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
        }

        @media (min-width: 768px) {
            .page-header {
                flex-direction: row;
                align-items: center;
            }
        }

        .page-title {
            font-size: var(--fs-heading);
            color: var(--clr-primary);
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            margin: 4px 0 0 0;
        }

        /* Alert Styling */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            font-weight: 500;
            border: 1px solid;
        }

        .alert-success {
            background: color-mix(in srgb, var(--clr-success) 10%, var(--clr-bg));
            color: var(--clr-success);
            border-color: var(--clr-success);
        }

        .alert-error {
            background: color-mix(in srgb, var(--clr-error) 10%, var(--clr-bg));
            color: var(--clr-error);
            border-color: var(--clr-error);
        }

        /* View Toggle */
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: var(--clr-surface);
            border-radius: var(--radius-md);
            padding: 4px;
            width: fit-content;
            border: 1px solid var(--clr-border);
        }

        .view-btn {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            background: transparent;
            border: none;
            color: var(--clr-muted);
            cursor: pointer;
            font-weight: 500;
            transition: all var(--time-transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-btn.active {
            background: var(--clr-primary);
            color: white;
        }

        .view-btn:hover:not(.active) {
            background: var(--clr-accent);
        }

        /* Table Container */
        .table-container {
            background: var(--clr-surface);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--clr-border);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--fs-base);
            min-width: 1200px;
        }

        th, td {
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid var(--clr-border);
            vertical-align: middle;
        }

        th {
            background: var(--clr-accent);
            color: var(--clr-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: var(--clr-accent);
        }

        /* Appointment Code */
        .appointment-code {
            font-weight: 600;
            color: var(--clr-primary);
            font-size: 0.95rem;
        }

        /* Student Info */
        .student-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .student-name {
            font-weight: 600;
            color: var(--clr-text);
        }

        .student-details {
            font-size: 0.85rem;
            color: var(--clr-muted);
        }

        /* Time Cell */
        .time-cell {
            min-width: 200px;
        }

        .time-date {
            font-weight: 500;
            color: var(--clr-text);
            margin-bottom: 4px;
        }

        .time-range {
            color: var(--clr-secondary);
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .duration {
            font-size: 0.85rem;
            color: var(--clr-muted);
        }

        /* Mode Badge */
        .mode-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
            background: var(--clr-accent);
            color: var(--clr-secondary);
        }

        .mode-in-person {
            background: color-mix(in srgb, var(--clr-info) 15%, var(--clr-bg));
            color: var(--clr-info);
        }

        .mode-online {
            background: color-mix(in srgb, #0ea5e9 15%, var(--clr-bg));
            color: #0ea5e9;
        }

        .mode-phone {
            background: var(--clr-accent);
            color: var(--clr-muted);
        }

        /* Status Badge */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-scheduled {
            background: color-mix(in srgb, #7c3aed 15%, var(--clr-bg));
            color: #7c3aed;
        }

        .badge-confirmed {
            background: color-mix(in srgb, var(--clr-info) 15%, var(--clr-bg));
            color: var(--clr-info);
        }

        .badge-completed {
            background: color-mix(in srgb, var(--clr-success) 15%, var(--clr-bg));
            color: var(--clr-success);
        }

        .badge-cancelled {
            background: color-mix(in srgb, var(--clr-error) 15%, var(--clr-bg));
            color: var(--clr-error);
        }

        .badge-pending {
            background: color-mix(in srgb, var(--clr-warning) 15%, var(--clr-bg));
            color: var(--clr-warning);
        }

        /* Notes */
        .notes-cell {
            max-width: 300px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .notes-preview {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 120px;
        }

        @media (min-width: 768px) {
            .action-buttons {
                flex-direction: row;
                min-width: auto;
            }
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all var(--time-transition);
            white-space: nowrap;
        }

        .btn-complete {
            background: var(--clr-success);
            color: white;
        }

        .btn-complete:hover {
            background: color-mix(in srgb, var(--clr-success) 80%, black);
        }

        .btn-cancel {
            background: var(--clr-error);
            color: white;
        }

        .btn-cancel:hover {
            background: color-mix(in srgb, var(--clr-error) 80%, black);
        }

        /* Empty State */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--clr-muted);
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .empty-state-text {
            color: var(--clr-muted);
            font-size: 1.1rem;
        }

        /* Calendar Container */
        .calendar-container {
            display: none;
            background: var(--clr-surface);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--clr-border);
            margin-top: 20px;
        }

        #calendar {
            height: 600px;
        }

        /* Calendar Styling */
        .fc-toolbar-title {
            color: var(--clr-primary);
            font-weight: 700;
        }

        .fc-button {
            background: var(--clr-surface) !important;
            border: 1px solid var(--clr-border) !important;
            color: var(--clr-text) !important;
        }

        .fc-button-primary:not(:disabled).fc-button-active {
            background: var(--clr-primary) !important;
            color: white !important;
        }

        .fc-button:hover {
            background: var(--clr-accent) !important;
        }

        .fc-event {
            border-radius: var(--radius-sm);
            border: none;
            padding: 2px 5px;
            font-size: 0.85rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(45, 55, 72, 0.8);
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--clr-surface);
            max-width: 600px;
            width: 95%;
            margin: auto;
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--clr-border);
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--clr-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }

        .btn-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--clr-muted);
            font-size: 1.2rem;
            padding: 5px;
        }

        .btn-close:hover {
            color: var(--clr-error);
        }

        /* Form Styles */
        .form-body {
            padding: 20px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--clr-muted);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-sm);
            background: var(--clr-bg);
            color: var(--clr-text);
            font-size: 1rem;
            transition: border-color var(--time-transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--clr-primary) 30%, transparent);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 24px;
        }

        @media (min-width: 480px) {
            .modal-footer {
                flex-direction: row-reverse;
            }
        }

        .btn-primary {
            flex: 1;
            padding: 12px 20px;
            background: var(--clr-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            cursor: pointer;
            font-weight: 600;
            transition: background var(--time-transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: var(--clr-secondary);
        }

        .btn-secondary {
            flex: 1;
            padding: 12px 20px;
            background: transparent;
            color: var(--clr-text);
            border: 2px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            cursor: pointer;
            font-weight: 600;
            transition: all var(--time-transition);
        }

        .btn-secondary:hover {
            background: var(--clr-accent);
            border-color: var(--clr-primary);
            color: var(--clr-primary);
        }

        /* Calendar Action Buttons */
        .calendar-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-start-session {
            background: var(--clr-success);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-start-session:hover {
            background: color-mix(in srgb, var(--clr-success) 80%, black);
        }

        .btn-view-details {
            background: var(--clr-info);
            color: white;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view-details:hover {
            background: color-mix(in srgb, var(--clr-info) 80%, black);
        }

        /* Event Details */
        .event-details {
            padding: 15px 0;
        }

        .event-detail-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .event-label {
            font-weight: 600;
            color: var(--clr-primary);
            min-width: 100px;
            flex-shrink: 0;
        }

        .event-value {
            color: var(--clr-text);
            flex: 1;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .content-area {
                padding: 50px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .view-toggle {
                width: 100%;
            }
            
            .view-btn {
                flex: 1;
                justify-content: center;
            }
            
            .fc-toolbar {
                flex-direction: column;
                gap: 10px;
            }
            
            .fc-toolbar-chunk {
                width: 100%;
                display: flex;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }

        /* Focus animation */
        @keyframes highlight {
            0% { background-color: color-mix(in srgb, var(--clr-primary) 10%, transparent); }
            100% { background-color: transparent; }
        }

        .highlight {
            animation: highlight 2s ease;
        }
    </style>
</head>

<body>
    <!-- Sidebar is included from root.css -->
    <nav class="sidebar" id="sidebar">
        <button class="toggle-btn" id="sidebarToggle">☰</button>
        
        <h2 class="logo">GOMS Counselor</h2>
        <div class="sidebar-user">
            <i class="fas fa-user-md"></i> Counselor · <?= htmlspecialchars($counselor['full_name'] ?? $counselor['username']); ?>
        </div>
        
        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        <a href="referrals.php" class="nav-link">
            <span class="icon"><i class="fas fa-clipboard-list"></i></span>
            <span class="label">Referrals</span>
        </a>
        <a href="appointments.php" class="nav-link active">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>
        <a href="sessions.php" class="nav-link">
            <span class="icon"><i class="fas fa-comments"></i></span>
            <span class="label">Sessions</span>
        </a>
        <!-- <a href="create_report.php" class="nav-link">
            <span class="icon"><i class="fas fa-file-alt"></i></span>
            <span class="label">Generate Report</span>
        </a> -->
        
        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <div class="content-wrap">
        <div class="content-area">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Appointments</h1>
                    <p class="page-subtitle">Manage and track counseling appointments assigned to you.</p>
                </div>
                <button class="btn" onclick="document.getElementById('createModal').style.display='flex'">
                    <i class="fas fa-plus-circle"></i> New Appointment
                </button>
            </div>

            <?php if (isset($_SESSION['msg'])): 
                $alert_class = strpos($_SESSION['msg'], '❌') !== false || strpos($_SESSION['msg'], 'Failed') !== false ? 'alert-error' : 'alert-success';
            ?>
                <div class="alert <?= $alert_class ?>">
                    <i class="fas <?= $alert_class === 'alert-error' ? 'fa-exclamation-triangle' : 'fa-check-circle' ?>"></i>
                    <?= $_SESSION['msg'] ?>
                </div>
                <?php unset($_SESSION['msg']); ?>
            <?php endif; ?>

            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-btn active" data-view="table">
                    <i class="fas fa-table"></i> Table View
                </button>
                <button class="view-btn" data-view="calendar">
                    <i class="fas fa-calendar-alt"></i> Calendar View
                </button>
            </div>

            <!-- Calendar View -->
            <div class="calendar-container" id="calendarContainer">
                <div id="calendar"></div>
            </div>

            <!-- Table View -->
            <div class="table-container" id="tableView">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Appointment Code</th>
                                <th>Student</th>
                                <th>Time & Date</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($appointments_data)): ?>
                                <?php foreach ($appointments_data as $row): 
                                    $status_class = 'badge-' . strtolower($row['status']);
                                    $mode_class = 'mode-' . str_replace(' ', '-', strtolower($row['mode']));
                                    $duration_hours = floor($row['duration_minutes'] / 60);
                                    $duration_minutes = $row['duration_minutes'] % 60;
                                ?>
                                    <tr id="appointment-<?= $row['id'] ?>">
                                        <td>
                                            <div class="appointment-code">
                                                <?= htmlspecialchars($row['appointment_code']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-name">
                                                    <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                                </div>
                                                <div class="student-details">
                                                    Grade <?= htmlspecialchars($row['grade_level']) ?> · 
                                                    <?= htmlspecialchars($row['section'] ?? 'No Section') ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="time-cell">
                                            <div class="time-date">
                                                <?= date("M d, Y", strtotime($row['start_time'])) ?>
                                            </div>
                                            <div class="time-range">
                                                <?= date("h:i A", strtotime($row['start_time'])) ?> - 
                                                <?= date("h:i A", strtotime($row['end_time'])) ?>
                                            </div>
                                            <div class="duration">
                                                (<?= $duration_hours ?>h <?= $duration_minutes ?>m)
                                            </div>
                                        </td>
                                        <td>
                                            <span class="mode-badge <?= $mode_class ?>">
                                                <?= ucfirst($row['mode']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="notes-cell">
                                            <div class="notes-preview" title="<?= htmlspecialchars($row['notes']) ?>">
                                                <?= htmlspecialchars($row['notes'] ?: 'No notes') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['status'] !== 'completed' && $row['status'] !== 'cancelled'): ?>
                                                <div class="action-buttons">
                                                    <form method="POST" class="inline-form">
                                                        <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_status" class="btn-action btn-complete"
                                                                onclick="return confirm('Mark this appointment as completed?')">
                                                            <i class="fas fa-check"></i>
                                                            Complete
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="inline-form">
                                                        <input type="hidden" name="appointment_id" value="<?= $row['id'] ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" name="update_status" class="btn-action btn-cancel"
                                                                onclick="return confirm('Cancel this appointment?')">
                                                            <i class="fas fa-times"></i>
                                                            Cancel
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--clr-muted); font-size: 0.9rem;">
                                                    No actions available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">📅</div>
                                            <p class="empty-state-text">
                                                No appointments found. Create your first appointment using the "New Appointment" button.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Appointment Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-calendar-plus"></i>
                    Schedule New Appointment
                </h2>
                <button class="btn-close" onclick="document.getElementById('createModal').style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="createAppointmentForm">
                <div class="form-body">
                    <div class="form-group">
                        <label class="form-label" for="student_id">Student</label>
                        <select class="form-control" name="student_id" id="student_id" required>
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $stu): ?>
                                <option value="<?= $stu['id'] ?>">
                                    <?= htmlspecialchars($stu['last_name'] . ', ' . $stu['first_name'] . ' (Grade ' . $stu['grade_level'] . ' - ' . ($stu['section_name'] ?? 'No Section') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="start_time">Start Time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="end_time">End Time</label>
                            <input type="datetime-local" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="mode">Mode</label>
                        <select class="form-control" name="mode" id="mode" required>
                            <option value="in-person">In-Person</option>
                            <option value="online">Online</option>
                            <option value="phone">Phone</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="notes">Notes/Purpose</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3" 
                                  placeholder="Add any additional notes or purpose for the appointment..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="document.getElementById('createModal').style.display='none'">
                        Cancel
                    </button>
                    <button type="submit" name="create_appointment" class="btn-primary">
                        <i class="fas fa-save"></i>
                        Create Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">
                    <i class="fas fa-calendar-check"></i>
                    Appointment Details
                </h2>
                <button class="btn-close" onclick="closeEventModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="eventDetails" class="event-details"></div>
            <div class="modal-footer">
                <div id="eventActions" class="calendar-actions"></div>
                <button type="button" class="btn-secondary" onclick="closeEventModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- FullCalendar JS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });

        // View Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const viewBtns = document.querySelectorAll('.view-btn');
            const tableView = document.getElementById('tableView');
            const calendarContainer = document.getElementById('calendarContainer');

            viewBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.dataset.view;

                    // Update active button
                    viewBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Show selected view
                    if (view === 'table') {
                        tableView.style.display = 'block';
                        calendarContainer.style.display = 'none';
                    } else {
                        tableView.style.display = 'none';
                        calendarContainer.style.display = 'block';
                        // Initialize calendar if not already initialized
                        if (!window.calendarInitialized) {
                            initCalendar();
                        }
                    }
                });
            });

            // Initialize calendar
            function initCalendar() {
                var calendarEl = document.getElementById('calendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    events: 'appointments.php?get_calendar_events=true',
                    eventClick: function(info) {
                        showEventDetails(info.event);
                    },
                    eventContent: function(arg) {
                        return {
                            html: `
                                <div style="padding: 3px;">
                                    <div style="font-weight: bold; font-size: 0.85rem;">${arg.event.title}</div>
                                    <div style="font-size: 0.75rem; opacity: 0.9;">${arg.timeText}</div>
                                    <div style="font-size: 0.7rem; opacity: 0.8; text-transform: capitalize;">${arg.event.extendedProps.mode}</div>
                                </div>
                            `
                        };
                    },
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    },
                    slotMinTime: '07:00:00',
                    slotMaxTime: '20:00:00',
                    height: 600,
                    nowIndicator: true,
                    editable: false,
                    selectable: false,
                    businessHours: {
                        daysOfWeek: [1, 2, 3, 4, 5],
                        startTime: '07:00',
                        endTime: '17:00'
                    }
                });

                calendar.render();
                window.calendarInstance = calendar;
                window.calendarInitialized = true;
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            }

            // Set default time for new appointments
            const now = new Date();
            const startTime = new Date(now.getTime() + 60 * 60 * 1000); // 1 hour from now
            const endTime = new Date(startTime.getTime() + 60 * 60 * 1000); // 1 hour duration

            // Format for datetime-local input
            function formatDateTime(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            document.getElementById('start_time').value = formatDateTime(startTime);
            document.getElementById('end_time').value = formatDateTime(endTime);

            // Form validation
            document.getElementById('createAppointmentForm').addEventListener('submit', function(e) {
                const start = new Date(document.getElementById('start_time').value);
                const end = new Date(document.getElementById('end_time').value);
                
                if (start >= end) {
                    e.preventDefault();
                    alert('End time must be after start time!');
                    return false;
                }
                
                if (start < new Date()) {
                    if (!confirm('Start time is in the past. Continue anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                return true;
            });

            // Highlight appointment if URL has focus parameter
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('focus')) {
                const appointmentId = urlParams.get('focus');
                const row = document.getElementById('appointment-' + appointmentId);
                if (row) {
                    row.classList.add('highlight');
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Remove highlight after animation
                    setTimeout(() => {
                        row.classList.remove('highlight');
                    }, 2000);
                }
            }
        });

        // Show event details modal
        function showEventDetails(event) {
            const props = event.extendedProps;
            const start = event.start;
            const end = event.end || new Date(start.getTime() + 60 * 60 * 1000);

            const detailsHTML = `
                <div class="event-detail-row">
                    <span class="event-label">Student:</span>
                    <span class="event-value">${props.student}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Code:</span>
                    <span class="event-value">${props.code}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Date:</span>
                    <span class="event-value">${start.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Time:</span>
                    <span class="event-value">${start.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })} - ${end.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Mode:</span>
                    <span class="event-value">${props.mode}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Status:</span>
                    <span class="event-value">
                        <span class="status-indicator" style="background: ${event.backgroundColor}"></span>
                        ${props.status}
                    </span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Section:</span>
                    <span class="event-value">${props.section || 'N/A'}</span>
                </div>
                <div class="event-detail-row">
                    <span class="event-label">Notes:</span>
                    <span class="event-value">${props.notes || 'No notes'}</span>
                </div>
            `;

            document.getElementById('eventDetails').innerHTML = detailsHTML;

            // Action buttons
            let actionsHTML = '';
            if (props.status === 'confirmed' || props.status === 'scheduled') {
                const now = new Date();
                const canStart = start <= new Date(now.getTime() + 60 * 60 * 1000); // Can start 1 hour before

                if (canStart) {
                    actionsHTML += `
                        <button class="btn-start-session" onclick="startSession(${event.id})">
                            <i class="fas fa-play"></i> Start Session
                        </button>
                    `;
                }

                actionsHTML += `
                    <button class="btn-view-details" onclick="location.href='appointments.php?focus=${event.id}'">
                        <i class="fas fa-external-link-alt"></i> View in Table
                    </button>
                `;
            }

            document.getElementById('eventActions').innerHTML = actionsHTML;
            document.getElementById('eventModal').style.display = 'flex';
        }

        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }

        function startSession(appointmentId) {
            if (confirm('Start counseling session for this appointment?')) {
                window.location.href = `create_session.php?appointment_id=${appointmentId}`;
            }
        }
    </script>
</body>
</html>
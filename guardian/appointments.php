<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info
$guardian = null;
$guardian_id = 0;
$stmt = $conn->prepare("SELECT * FROM guardians WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardian = $result->fetch_assoc();
    $guardian_id = $guardian ? $guardian['id'] : 0;
}

// Get filter parameters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get linked students
$linked_students = [];
if ($guardian_id > 0) {
    $linked_sql = "
        SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level
        FROM student_guardians sg
        JOIN students s ON sg.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE sg.guardian_id = ?
        ORDER BY s.last_name, s.first_name
    ";
    
    $linked_stmt = $conn->prepare($linked_sql);
    if ($linked_stmt) {
        $linked_stmt->bind_param("i", $guardian_id);
        $linked_stmt->execute();
        $result = $linked_stmt->get_result();
        $linked_students = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get appointments for guardian's linked students
$appointments = [];
$appointment_stats = [
    'total' => 0,
    'scheduled' => 0,
    'completed' => 0,
    'cancelled' => 0
];

if (!empty($linked_students)) {
    $student_ids = array_column($linked_students, 'id');
    $student_ids_str = implode(',', $student_ids);
    
    // Build query with filters
    $query = "
        SELECT 
            a.*,
            s.first_name,
            s.last_name,
            s.student_id as student_code,
            sec.section_name,
            sec.grade_level,
            u.full_name as counselor_name,
            cr.specialty as counselor_specialty,
            u.email as counselor_email,
            u.phone as counselor_phone
        FROM appointments a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        JOIN counselors cr ON a.counselor_id = cr.id
        JOIN users u ON cr.user_id = u.id
        WHERE a.student_id IN ($student_ids_str)
    ";
    
    // Add status filter
    if ($status_filter !== 'all') {
        $query .= " AND a.status = '" . $conn->real_escape_string($status_filter) . "'";
    }
    
    // Add student filter
    if ($student_id > 0) {
        $query .= " AND a.student_id = " . intval($student_id);
    }
    
    $query .= " ORDER BY a.start_time DESC";
    
    $result = $conn->query($query);
    if ($result) {
        $appointments = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate statistics
        foreach ($appointments as $appt) {
            $appointment_stats['total']++;
            if ($appt['status'] == 'scheduled') $appointment_stats['scheduled']++;
            elseif ($appt['status'] == 'completed') $appointment_stats['completed']++;
            elseif ($appt['status'] == 'cancelled') $appointment_stats['cancelled']++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Child's Appointments - GOMS Guardian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .appointments-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: var(--clr-primary);
            font-size: var(--fs-heading);
            margin-bottom: 8px;
        }
        
        .page-header .subtitle {
            color: var(--clr-muted);
            font-size: var(--fs-normal);
        }
        
        /* Stats Cards */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            transition: all var(--time-transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-card.total {
            border-top: 4px solid var(--clr-primary);
        }
        
        .stat-card.scheduled {
            border-top: 4px solid var(--clr-info);
        }
        
        .stat-card.completed {
            border-top: 4px solid var(--clr-success);
        }
        
        .stat-card.cancelled {
            border-top: 4px solid var(--clr-info);
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            font-weight: 500;
        }
        
        /* Filters */
        .filters-container {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            margin-bottom: 0;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--clr-secondary);
            font-size: var(--fs-normal);
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: var(--fs-normal);
            font-family: 'Inter', sans-serif;
            background: var(--clr-surface);
            color: var(--clr-text);
            transition: all var(--time-transition);
            cursor: pointer;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px var(--clr-primary-light);
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--time-transition);
            font-size: var(--fs-normal);
        }
        
        .btn-primary {
            background: var(--clr-primary);
            color: white;
        }
        
        .btn-primary:hover {
            /* background: var(--clr-primary-dark); */
            transform: translateY(-1px);
            /* box-shadow: var(--shadow-md); */
        }
        
        .btn-secondary {
            background: var(--clr-surface);
            color: var(--clr-text);
            border: 1px solid var(--clr-border);
        }
        
        .btn-secondary:hover {
            background: var(--clr-bg-light);
            border-color: var(--clr-muted);
            transform: translateY(-1px);
        }
        
        /* Appointments List */
        .appointments-grid {
            display: grid;
            gap: 20px;
        }
        
        .appointment-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: all var(--time-transition);
        }
        
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--clr-primary);
        }
        
        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--clr-border-light);
        }
        
        .student-info {
            flex: 1;
        }
        
        .student-name {
            font-weight: 700;
            color: var(--clr-text);
            font-size: var(--fs-normal);
            margin-bottom: 4px;
        }
        
        .student-details {
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: var(--fs-xsmall);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-scheduled {
            background: var(--clr-info-light);
            color: var(--clr-info);
        }
        
        .status-completed {
            background: var(--clr-success-light);
            color: var(--clr-success);
        }
        
        .status-cancelled {
            background: var(--clr-info-light);
            color: var(--clr-info);
        }
        
        .status-rescheduled {
            background: var(--clr-warning-light);
            color: var(--clr-warning);
        }
        
        .appointment-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            background: var(--clr-bg-light);
            padding: 15px;
            border-radius: var(--radius-md);
            border-left: 3px solid var(--clr-primary);
        }
        
        .detail-label {
            color: var(--clr-muted);
            display: block;
            margin-bottom: 8px;
            font-size: var(--fs-small);
            font-weight: 600;
        }
        
        .detail-value {
            color: var(--clr-text);
            font-weight: 500;
            line-height: 1.4;
        }
        
        .mode-badge {
            display: inline-block;
            padding: 4px 12px;
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: 20px;
            font-size: var(--fs-xsmall);
            font-weight: 600;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--clr-border-light);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: var(--fs-small);
            border-radius: var(--radius-sm);
        }
        
        .notes-box {
            margin-top: 15px;
            padding: 15px;
            background: var(--clr-bg-light);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--clr-info);
        }
        
        .notes-title {
            font-weight: 600;
            color: var(--clr-secondary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notes-content {
            color: var(--clr-text);
            font-size: var(--fs-small);
            line-height: 1.5;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--clr-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
            margin-top: 30px;
        }
        
        .empty-state h3 {
            color: var(--clr-secondary);
            margin-bottom: 10px;
            font-size: var(--fs-subheading);
        }
        
        .empty-state p {
            color: var(--clr-muted);
            margin-bottom: 20px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .no-students {
            text-align: center;
            padding: 80px 20px;
            background: var(--clr-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
        }
        
        .no-students h3 {
            color: var(--clr-secondary);
            margin-bottom: 10px;
            font-size: var(--fs-subheading);
        }
        
        .no-students p {
            color: var(--clr-muted);
            margin-bottom: 20px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .meeting-link {
            display: inline-block;
            margin-top: 5px;
            padding: 4px 12px;
            background: var(--clr-success);
            color: white;
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-size: var(--fs-xsmall);
            font-weight: 600;
        }
        
        .meeting-link:hover {
            background: var(--clr-success-dark);
        }
        
        @media (max-width: 768px) {
            .appointments-content {
                padding: 20px;
            }
            
            .appointment-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .appointment-details {
                grid-template-columns: 1fr;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn-small {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>

        <h2 class="logo">GOMS Guardian</h2>
        <div class="sidebar-user">
            <i class="fas fa-user-shield"></i> Guardian · <?= htmlspecialchars($guardian['full_name'] ?? 'Guardian'); ?>
            <?php if (!empty($linked_students)): ?>
                <br><small>
                    <?= count($linked_students) ?> student<?= count($linked_students) > 1 ? 's' : '' ?> linked
                </small>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
        <a href="link_student.php" class="nav-link">
            <span class="icon"><i class="fas fa-link"></i></span>
            <span class="label">Link Student</span>
        </a>
        
        <a href="appointments.php" class="nav-link active">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>
        
        <a href="request_appointment.php" class="nav-link">
            <span class="icon"><i class="fas fa-plus-circle"></i></span>
            <span class="label">Request Appointment</span>
        </a>
        
        <a href="submit_concern.php" class="nav-link">
            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="label">Submit Concern</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <main class="content" id="mainContent">
        <div class="appointments-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Your Child's Appointments</h1>
                <p class="subtitle">View and manage counseling appointments for your linked students</p>
            </div>

            <?php if (empty($linked_students)): ?>
                <div class="no-students">
                    <div style="font-size: 48px; margin-bottom: 16px; color: var(--clr-muted);">
                        <i class="fas fa-link"></i>
                    </div>
                    <h3>No Linked Students</h3>
                    <p>You haven't linked any students to your account yet. Link your account to your child's student profile to view their appointments.</p>
                    <a href="link_student.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-link"></i> Link a Student
                    </a>
                </div>
            <?php else: ?>
                <!-- Statistics Summary -->
                <div class="stats-summary">
                    <div class="stat-card total">
                        <div class="stat-number"><?= $appointment_stats['total'] ?></div>
                        <div class="stat-label">Total Appointments</div>
                    </div>
                    <div class="stat-card scheduled">
                        <div class="stat-number"><?= $appointment_stats['scheduled'] ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                    <div class="stat-card completed">
                        <div class="stat-number"><?= $appointment_stats['completed'] ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card cancelled">
                        <div class="stat-number"><?= $appointment_stats['cancelled'] ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-container">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label" for="studentFilter">
                                <i class="fas fa-user-graduate"></i> Filter by Student
                            </label>
                            <select id="studentFilter" class="filter-select" onchange="filterAppointments()">
                                <option value="0">All Students</option>
                                <?php foreach ($linked_students as $student): ?>
                                    <option value="<?= $student['id'] ?>" <?= $student_id == $student['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label" for="statusFilter">
                                <i class="fas fa-filter"></i> Filter by Status
                            </label>
                            <select id="statusFilter" class="filter-select" onchange="filterAppointments()">
                                <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="scheduled" <?= $status_filter == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="rescheduled" <?= $status_filter == 'rescheduled' ? 'selected' : '' ?>>Rescheduled</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <a href="request_appointment.php" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Request New Appointment
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if (empty($appointments)): ?>
                    <div class="empty-state">
                        <div style="font-size: 48px; margin-bottom: 16px; color: var(--clr-muted);">
                            <i class="far fa-calendar"></i>
                        </div>
                        <h3>No Appointments Found</h3>
                        <p>No appointments match your current filters. Try changing your filters or request a new appointment.</p>
                        <a href="request_appointment.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-plus-circle"></i> Request Appointment
                        </a>
                    </div>
                <?php else: ?>
                    <div class="appointments-grid">
                        <?php foreach ($appointments as $appt): ?>
                            <div class="appointment-card">
                                <div class="appointment-header">
                                    <div class="student-info">
                                        <div class="student-name">
                                            <?= htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']) ?>
                                            <small>(<?= htmlspecialchars($appt['student_code']) ?>)</small>
                                        </div>
                                        <div class="student-details">
                                            Grade <?= htmlspecialchars($appt['grade_level']) ?> - 
                                            <?= htmlspecialchars($appt['section_name']) ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-<?= $appt['status'] ?>">
                                        <?= ucfirst($appt['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="appointment-details">
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="far fa-calendar"></i> Date & Time
                                        </span>
                                        <span class="detail-value">
                                            <?= date('F j, Y', strtotime($appt['start_time'])) ?><br>
                                            <strong>
                                                <?= date('h:i A', strtotime($appt['start_time'])) ?> - 
                                                <?= date('h:i A', strtotime($appt['end_time'])) ?>
                                            </strong>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-user-md"></i> Counselor
                                        </span>
                                        <span class="detail-value">
                                            <?= htmlspecialchars($appt['counselor_name']) ?><br>
                                            <small style="color: var(--clr-muted);">
                                                <?= htmlspecialchars($appt['counselor_specialty']) ?>
                                            </small>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-laptop-house"></i> Mode
                                        </span>
                                        <span class="detail-value">
                                            <span class="mode-badge"><?= ucfirst($appt['mode']) ?></span>
                                            <?php if ($appt['mode'] == 'online' && !empty($appt['meeting_link'])): ?>
                                                <br>
                                                <a href="<?= htmlspecialchars($appt['meeting_link']) ?>" 
                                                   class="meeting-link" 
                                                   target="_blank">
                                                    <i class="fas fa-video"></i> Join Meeting
                                                </a>
                                            <?php elseif ($appt['mode'] == 'in-person' && !empty($appt['location'])): ?>
                                                <br>
                                                <small style="color: var(--clr-muted);">
                                                    <i class="fas fa-map-marker-alt"></i> 
                                                    <?= htmlspecialchars($appt['location']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">
                                            <i class="fas fa-info-circle"></i> Details
                                        </span>
                                        <span class="detail-value">
                                            <?php if (!empty($appt['appointment_code'])): ?>
                                                Code: <?= htmlspecialchars($appt['appointment_code']) ?><br>
                                            <?php endif; ?>
                                            Duration: <?= ceil((strtotime($appt['end_time']) - strtotime($appt['start_time'])) / 60) ?> mins
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($appt['notes'])): ?>
                                    <div class="notes-box">
                                        <div class="notes-title">
                                            <i class="fas fa-sticky-note"></i> Notes
                                        </div>
                                        <div class="notes-content">
                                            <?= nl2br(htmlspecialchars(substr($appt['notes'], 0, 200))) ?>
                                            <?= strlen($appt['notes']) > 200 ? '...' : '' ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="actions">
                                    <button class="btn-small btn-secondary" 
                                            onclick="showAppointmentDetails(<?= $appt['id'] ?>, 
                                            '<?= addslashes($appt['first_name'] . ' ' . $appt['last_name']) ?>',
                                            '<?= date('M d, Y', strtotime($appt['start_time'])) ?>',
                                            '<?= date('h:i A', strtotime($appt['start_time'])) ?>',
                                            '<?= date('h:i A', strtotime($appt['end_time'])) ?>',
                                            '<?= $appt['status'] ?>',
                                            '<?= addslashes($appt['location'] ?? 'Not specified') ?>')">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <button class="btn-small btn-primary" 
                                            onclick="showCounselorContact('<?= addslashes($appt['counselor_name']) ?>',
                                            '<?= htmlspecialchars($appt['counselor_email'] ?? 'Not available') ?>',
                                            '<?= htmlspecialchars($appt['counselor_phone'] ?? 'Not available') ?>')">
                                        <i class="fas fa-phone"></i> Contact Counselor
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        function filterAppointments() {
            const studentId = document.getElementById('studentFilter').value;
            const status = document.getElementById('statusFilter').value;
            
            let url = 'appointments.php?';
            if (studentId > 0) url += `student_id=${studentId}&`;
            if (status !== 'all') url += `status=${status}`;
            
            window.location.href = url;
        }
        
        function showAppointmentDetails(id, studentName, date, startTime, endTime, status, location) {
            const modalHtml = `
                <div style="padding: 20px; max-width: 500px;">
                    <h3 style="color: var(--clr-primary); margin-bottom: 15px;">Appointment Details</h3>
                    <div style="background: var(--clr-bg-light); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p><strong>Student:</strong> ${studentName}</p>
                        <p><strong>Date:</strong> ${date}</p>
                        <p><strong>Time:</strong> ${startTime} - ${endTime}</p>
                        <p><strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)}</p>
                        <p><strong>Location:</strong> ${location}</p>
                    </div>
                    <p style="color: var(--clr-muted); font-size: 14px;">For more detailed information, please contact the guidance office.</p>
                </div>
            `;
            
            showModal(modalHtml);
        }
        
        function showCounselorContact(name, email, phone) {
            const modalHtml = `
                <div style="padding: 20px; max-width: 500px;">
                    <h3 style="color: var(--clr-primary); margin-bottom: 15px;">Counselor Contact Information</h3>
                    <div style="background: var(--clr-bg-light); padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <p><strong>Name:</strong> ${name}</p>
                        <p><strong>Email:</strong> <a href="mailto:${email}" style="color: var(--clr-primary);">${email}</a></p>
                        <p><strong>Phone:</strong> <a href="tel:${phone}" style="color: var(--clr-primary);">${phone}</a></p>
                    </div>
                    <p style="color: var(--clr-muted); font-size: 14px;">Please be respectful of the counselor's time when contacting them.</p>
                </div>
            `;
            
            showModal(modalHtml);
        }
        
        function showModal(content) {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                padding: 20px;
                box-sizing: border-box;
            `;
            
            // Create modal content
            const modal = document.createElement('div');
            modal.style.cssText = `
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                max-width: 600px;
                width: 100%;
                overflow: hidden;
                animation: slideDown 0.3s ease-out;
            `;
            
            modal.innerHTML = content;
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = 'Close';
            closeBtn.style.cssText = `
                display: block;
                margin: 20px auto 0;
                padding: 10px 24px;
                background: var(--clr-primary);
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
            `;
            closeBtn.onclick = () => {
                document.body.removeChild(overlay);
            };
            
            modal.querySelector('div').appendChild(closeBtn);
            overlay.appendChild(modal);
            document.body.appendChild(overlay);
            
            // Close on overlay click
            overlay.onclick = (e) => {
                if (e.target === overlay) {
                    document.body.removeChild(overlay);
                }
            };
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize active nav link
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
            
            // Auto-refresh every 2 minutes
            setInterval(() => {
                location.reload();
            }, 120000);
        });
    </script>
</body>
</html>
<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info
$guardian = null;
$stmt = $conn->prepare("
    SELECT g.*, u.full_name, u.email, u.phone 
    FROM guardians g 
    JOIN users u ON g.user_id = u.id 
    WHERE g.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$guardian = $stmt->get_result()->fetch_assoc();

// Get linked students
$linked_students = [];
if ($guardian) {
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level, s.contact_number
        FROM student_guardians sg
        JOIN students s ON sg.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE sg.guardian_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    if ($stmt) {
        $stmt->bind_param("i", $guardian['id']);
        $stmt->execute();
        $linked_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get dashboard stats
$stats = [
    'total_students' => count($linked_students),
    'total_appointments' => 0,
    'upcoming_appointments' => 0,
    'pending_appointments' => 0,
    'completed_sessions' => 0,
    'concerns_submitted' => 0
];

$student_ids = [];
$upcoming_appointments = [];
$recent_activity = [];

if ($guardian && !empty($linked_students)) {
    $student_ids = array_column($linked_students, 'id');
    
    // Total appointments - using simpler query without placeholders
    if (!empty($student_ids)) {
        $student_ids_str = implode(',', $student_ids);
        
        // Total appointments
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE student_id IN ($student_ids_str)";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_appointments'] = $row['count'] ?? 0;
        }
        
        // Upcoming appointments (scheduled status, future date)
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE student_id IN ($student_ids_str) 
                AND status = 'scheduled' AND start_time > NOW()";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['upcoming_appointments'] = $row['count'] ?? 0;
        }
        
        // Pending appointments (requested status)
        $sql = "SELECT COUNT(*) as count FROM appointments WHERE student_id IN ($student_ids_str) 
                AND status = 'requested'";
        $result = $conn->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['pending_appointments'] = $row['count'] ?? 0;
        }
        
        // Get upcoming appointments for display
        if ($stats['upcoming_appointments'] > 0) {
            $sql = "SELECT a.*, s.first_name, s.last_name, u.full_name as counselor_name
                    FROM appointments a
                    JOIN students s ON a.student_id = s.id
                    JOIN counselors cr ON a.counselor_id = cr.id
                    JOIN users u ON cr.user_id = u.id
                    WHERE a.student_id IN ($student_ids_str) 
                    AND a.status = 'scheduled' 
                    AND a.start_time > NOW()
                    ORDER BY a.start_time ASC
                    LIMIT 5";
            $result = $conn->query($sql);
            if ($result) {
                $upcoming_appointments = $result->fetch_all(MYSQLI_ASSOC);
            }
        }
        
        // Get recent activity
        $sql = "SELECT a.*, s.first_name, s.last_name, u.full_name as counselor_name
                FROM appointments a
                JOIN students s ON a.student_id = s.id
                JOIN counselors cr ON a.counselor_id = cr.id
                JOIN users u ON cr.user_id = u.id
                WHERE a.student_id IN ($student_ids_str)
                ORDER BY a.created_at DESC
                LIMIT 5";
        $result = $conn->query($sql);
        if ($result) {
            $recent_activity = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        // Check if sessions table exists before querying
        $result = $conn->query("SHOW TABLES LIKE 'sessions'");
        if ($result && $result->num_rows > 0) {
            // Completed sessions
            $sql = "SELECT COUNT(DISTINCT s.id) as count
                    FROM sessions s
                    JOIN appointments a ON s.appointment_id = a.id
                    WHERE a.student_id IN ($student_ids_str)
                    AND s.status = 'completed'";
            $result = $conn->query($sql);
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['completed_sessions'] = $row['count'] ?? 0;
            }
        }
    }
    
    // Concerns submitted
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM guardian_concerns WHERE guardian_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $guardian['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['concerns_submitted'] = $row['count'] ?? 0;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guardian Dashboard - GOMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/guardian_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        <a href="dashboard.php" class="nav-link active">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
       <!--  <a href="link_student.php" class="nav-link">
            <span class="icon"><i class="fas fa-link"></i></span>
            <span class="label">Link Student</span>
        </a> -->
        
        <a href="appointments.php" class="nav-link">
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
        <div class="dashboard-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Welcome Back, <?= htmlspecialchars($guardian['full_name'] ?? 'Guardian') ?>!</h1>
                <p class="subtitle">Monitor your child's guidance services and appointments</p>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Linked Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['upcoming_appointments'] ?></div>
                    <div class="stat-label">Upcoming Appointments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['completed_sessions'] ?></div>
                    <div class="stat-label">Sessions Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['concerns_submitted'] ?></div>
                    <div class="stat-label">Concerns Submitted</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
               <!--  <a href="link_student.php" class="action-btn">
                    <span class="action-icon"><i class="fas fa-link"></i></span>
                    <div class="action-title">Link to Student</div>
                    <div class="action-desc">Connect your account to your child's record</div>
                </a> -->
                <a href="appointments.php" class="action-btn">
                    <span class="action-icon"><i class="fas fa-calendar-alt"></i></span>
                    <div class="action-title">View Appointments</div>
                    <div class="action-desc">Check your child's counseling schedule</div>
                </a>
                <a href="request_appointment.php" class="action-btn">
                    <span class="action-icon"><i class="fas fa-plus-circle"></i></span>
                    <div class="action-title">Request Appointment</div>
                    <div class="action-desc">Schedule a counseling session</div>
                </a>
                <a href="submit_concern.php" class="action-btn">
                    <span class="action-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="action-title">Submit Concern</div>
                    <div class="action-desc">Report issues or concerns</div>
                </a>
            </div>

            <!-- Linked Students Section -->
            <div class="section">
                <h3 class="section-title">My Linked Students</h3>
                <?php if (empty($linked_students)): ?>
                    <div class="empty-state">
                        <h4>No students linked yet</h4>
                        <p>Connect to your child's account to monitor their guidance services and appointments.</p>
                        <a href="link_student.php" class="btn-primary">
                            <i class="fas fa-link"></i> Link a Student
                        </a>
                    </div>
                <?php else: ?>
                    <div class="students-list">
                        <?php foreach ($linked_students as $student): ?>
                            <div class="student-item">
                                <div class="student-info">
                                    <h4>
                                        <i class="fas fa-user-graduate"></i>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    </h4>
                                    <div class="student-details">
                                        ID: <?= htmlspecialchars($student['student_code']) ?> • 
                                        Grade <?= htmlspecialchars($student['grade_level']) ?> - 
                                        <?= htmlspecialchars($student['section_name']) ?>
                                        <?php if ($student['contact_number']): ?>
                                            • <i class="fas fa-phone"></i> <?= htmlspecialchars($student['contact_number']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <a href="appointments.php?student_id=<?= $student['id'] ?>" class="btn-small">
                                        <i class="fas fa-calendar-check"></i> Appointments
                                    </a>
                                    <a href="request_appointment.php?student_id=<?= $student['id'] ?>" class="btn-small">
                                        <i class="fas fa-plus"></i> Request
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments Section -->
            <?php if ($stats['upcoming_appointments'] > 0 && !empty($upcoming_appointments)): ?>
                <div class="section">
                    <h3 class="section-title">Upcoming Appointments</h3>
                    <div class="upcoming-appointments">
                        <?php foreach ($upcoming_appointments as $appt): ?>
                            <div class="appointment-item">
                                <div>
                                    <div class="appointment-time">
                                        <i class="far fa-calendar"></i>
                                        <?= date('M d, Y h:i A', strtotime($appt['start_time'])) ?>
                                    </div>
                                    <div class="appointment-details">
                                        <strong><?= htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']) ?></strong>
                                        • With <?= htmlspecialchars($appt['counselor_name']) ?>
                                        • <?= ucfirst($appt['mode']) ?> session
                                    </div>
                                </div>
                                <span class="badge badge-scheduled">
                                    <?= ucfirst($appt['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="view-all">
                            <a href="appointments.php" class="btn-primary">
                                <i class="fas fa-calendar-alt"></i> View All Appointments
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Recent Activity Section -->
            <?php if (!empty($recent_activity)): ?>
                <div class="section">
                    <h3 class="section-title">Recent Activity</h3>
                    <div class="upcoming-appointments">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="appointment-item">
                                <div>
                                    <div class="appointment-time">
                                        <i class="fas fa-calendar-check"></i>
                                        <?= date('M d, Y', strtotime($activity['created_at'])) ?>
                                    </div>
                                    <div class="appointment-details">
                                        <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                        had <?= $activity['status'] ?> appointment with 
                                        <?= htmlspecialchars($activity['counselor_name']) ?>
                                    </div>
                                </div>
                                <?php 
                                $badge_class = 'badge-' . $activity['status'];
                                if (!in_array($activity['status'], ['scheduled', 'requested', 'completed', 'cancelled'])) {
                                    $badge_class = 'badge-scheduled';
                                }
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= ucfirst($activity['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif (!empty($student_ids)): ?>
                <div class="section">
                    <h3 class="section-title">Recent Activity</h3>
                    <div class="upcoming-appointments">
                        <div class="empty-state" style="padding: 20px;">
                            <p>No recent activity to display.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize active nav link
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage || (currentPage === '' && href === 'dashboard.php')) {
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
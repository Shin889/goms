<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Get adviser info
$adviser_info = null;
$stmt = $conn->prepare("
    SELECT a.*, u.full_name, sec.section_name, sec.grade_level 
    FROM advisers a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN sections sec ON sec.adviser_id = a.id
    WHERE a.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adviser_info = $stmt->get_result()->fetch_assoc();

// Get appointments for adviser's section students
$appointments = [];
$stats = [
    'total' => 0,
    'scheduled' => 0,
    'in_session' => 0,
    'completed' => 0,
    'cancelled' => 0
];

if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            s.first_name,
            s.last_name,
            s.student_id as student_code,
            sec.section_name,
            sec.grade_level,
            u.full_name as counselor_name,
            cr.specialty as counselor_specialty,
            r.referral_reason
        FROM appointments a
        JOIN students s ON a.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        JOIN counselors cr ON a.counselor_id = cr.id
        JOIN users u ON cr.user_id = u.id
        LEFT JOIN referrals r ON a.referral_id = r.id
        WHERE sec.adviser_id = ?
        ORDER BY a.start_time DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    foreach ($appointments as $appointment) {
        $stats['total']++;
        if ($appointment['status'] == 'scheduled') $stats['scheduled']++;
        elseif ($appointment['status'] == 'in_session') $stats['in_session']++;
        elseif ($appointment['status'] == 'completed') $stats['completed']++;
        elseif ($appointment['status'] == 'cancelled') $stats['cancelled']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Appointments - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/adviser_appointments.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>

        <h2 class="logo">GOMS Adviser</h2>
        <div class="sidebar-user">
            <i class="fas fa-chalkboard-teacher"></i> Adviser · <?= htmlspecialchars($adviser_info['full_name'] ?? 'User'); ?>
            <?php if ($adviser_info['section_name']): ?>
                <br><small>Grade <?= htmlspecialchars($adviser_info['grade_level']) ?> - 
                <?= htmlspecialchars($adviser_info['section_name']) ?></small>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
        <a href="students.php" class="nav-link">
            <span class="icon"><i class="fas fa-users"></i></span>
            <span class="label">My Students</span>
        </a>
        
        <a href="create_complaint.php" class="nav-link">
            <span class="icon"><i class="fas fa-plus-circle"></i></span>
            <span class="label">Create Complaint</span>
        </a>
        
        <a href="complaints.php" class="nav-link">
            <span class="icon"><i class="fas fa-clipboard-list"></i></span>
            <span class="label">View Complaints</span>
        </a>
        
        <a href="create_referral.php" class="nav-link">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Create Referral</span>
        </a>
        
        <a href="referrals.php" class="nav-link">
            <span class="icon"><i class="fas fa-exchange-alt"></i></span>
            <span class="label">My Referrals</span>
        </a>
        
        <a href="appointments.php" class="nav-link active">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
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
                <h1>Student Appointments</h1>
                <p class="subtitle">Counseling appointments for your students</p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card scheduled">
                    <div class="stat-number"><?= $stats['scheduled'] ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
                <div class="stat-card in_session">
                    <div class="stat-number"><?= $stats['in_session'] ?></div>
                    <div class="stat-label">In Session</div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-number"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card cancelled">
                    <div class="stat-number"><?= $stats['cancelled'] ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Filter Buttons -->
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all">All Appointments</button>
                <button class="filter-btn" data-filter="scheduled">Scheduled</button>
                <button class="filter-btn" data-filter="in_session">In Session</button>
                <button class="filter-btn" data-filter="completed">Completed</button>
                <button class="filter-btn" data-filter="cancelled">Cancelled</button>
                <button class="filter-btn" data-filter="today">Today's</button>
                <button class="filter-btn" data-filter="upcoming">Upcoming</button>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <h3>No appointments found</h3>
                    <p>Appointments will appear here when scheduled by counselors for your students.</p>
                    <p>Check your referrals to see which students have been scheduled for counseling sessions.</p>
                    <a href="referrals.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-exchange-alt"></i> View My Referrals
                    </a>
                </div>
            <?php else: ?>
                <div class="appointments-list" id="appointmentsList">
                    <?php foreach ($appointments as $appointment): 
                        $isToday = date('Y-m-d') == date('Y-m-d', strtotime($appointment['start_time']));
                        $isUpcoming = strtotime($appointment['start_time']) > time();
                    ?>
                        <div class="appointment-card" 
                             data-status="<?= htmlspecialchars($appointment['status']) ?>"
                             data-date="<?= date('Y-m-d', strtotime($appointment['start_time'])) ?>"
                             data-today="<?= $isToday ? 'true' : 'false' ?>"
                             data-upcoming="<?= $isUpcoming ? 'true' : 'false' ?>">
                            <div class="appointment-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']) ?>
                                        <small>(<?= htmlspecialchars($appointment['student_code']) ?>)</small>
                                    </div>
                                    <div class="student-details">
                                        Grade <?= htmlspecialchars($appointment['grade_level']) ?> - 
                                        <?= htmlspecialchars($appointment['section_name']) ?>
                                    </div>
                                </div>
                                <div class="appointment-status status-<?= htmlspecialchars($appointment['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($appointment['status'])) ?>
                                </div>
                            </div>
                            
                            <div class="appointment-details">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="fas fa-user-md"></i> Counselor
                                    </span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($appointment['counselor_name']) ?>
                                        <br>
                                        <small style="color: var(--clr-muted);">
                                            <?= htmlspecialchars($appointment['counselor_specialty']) ?>
                                        </small>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="far fa-calendar"></i> Date & Time
                                    </span>
                                    <span class="detail-value">
                                        <div class="time-info">
                                            <?= date('F j, Y', strtotime($appointment['start_time'])) ?>
                                            <br>
                                            <?= date('h:i A', strtotime($appointment['start_time'])) ?> - 
                                            <?= date('h:i A', strtotime($appointment['end_time'])) ?>
                                        </div>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="fas fa-laptop-house"></i> Mode
                                    </span>
                                    <span class="detail-value">
                                        <?= ucfirst(htmlspecialchars($appointment['mode'])) ?>
                                        <?php if ($appointment['mode'] == 'online' && $appointment['meeting_link']): ?>
                                            <br>
                                            <a href="<?= htmlspecialchars($appointment['meeting_link']) ?>" 
                                               class="meeting-link" 
                                               target="_blank">
                                                <i class="fas fa-video"></i> Join Meeting
                                            </a>
                                        <?php elseif ($appointment['mode'] == 'in-person' && $appointment['location']): ?>
                                            <br>
                                            <small style="color: var(--clr-muted);">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($appointment['location']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="far fa-clock"></i> Created
                                    </span>
                                    <span class="detail-value">
                                        <?= date('M d, Y h:i A', strtotime($appointment['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($appointment['referral_reason']): ?>
                                <div class="referral-info">
                                    <div class="referral-title">
                                        <i class="fas fa-paper-plane"></i> Referral Reason
                                    </div>
                                    <div class="referral-content">
                                        <?= nl2br(htmlspecialchars(substr($appointment['referral_reason'], 0, 250))) ?>
                                        <?= strlen($appointment['referral_reason']) > 250 ? '...' : '' ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($appointment['notes']): ?>
                                <div class="notes-info">
                                    <strong><i class="fas fa-sticky-note"></i> Notes:</strong> 
                                    <?= htmlspecialchars(substr($appointment['notes'], 0, 150)) ?>
                                    <?= strlen($appointment['notes']) > 150 ? '...' : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const appointmentCards = document.querySelectorAll('.appointment-card');
            const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
            
            filterButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Update active button
                    filterButtons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    
                    appointmentCards.forEach(card => {
                        const status = card.getAttribute('data-status');
                        const date = card.getAttribute('data-date');
                        const isToday = card.getAttribute('data-today') === 'true';
                        const isUpcoming = card.getAttribute('data-upcoming') === 'true';
                        
                        let show = false;
                        
                        switch(filter) {
                            case 'all':
                                show = true;
                                break;
                            case 'scheduled':
                            case 'in_session':
                            case 'completed':
                            case 'cancelled':
                                show = status === filter;
                                break;
                            case 'today':
                                show = isToday;
                                break;
                            case 'upcoming':
                                show = isUpcoming;
                                break;
                            default:
                                show = true;
                        }
                        
                        card.style.display = show ? 'block' : 'none';
                    });
                });
            });
            
            // Auto-refresh every 60 seconds
            setInterval(() => {
                if (appointmentCards.length > 0) {
                    location.reload();
                }
            }, 60000);
            
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
        });
    </script>
</body>
</html>
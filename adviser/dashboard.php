<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Get adviser info
$adviser_info = null;
$stmt = $conn->prepare("
    SELECT a.*, u.full_name, u.email, u.phone, sec.section_name, sec.grade_level
    FROM advisers a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN sections sec ON sec.adviser_id = a.id
    WHERE a.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adviser_info = $stmt->get_result()->fetch_assoc();

// Get dashboard stats
$stats = [
    'total_students' => 0,
    'total_complaints' => 0,
    'open_complaints' => 0,
    'referred_complaints' => 0,
    'pending_referrals' => 0,
    'total_referrals' => 0
];

if ($adviser_info) {
    // Count students in section
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.adviser_id = ?
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_students'] = $result['count'] ?? 0;

    // Count complaints
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN c.status = 'new' THEN 1 ELSE 0 END) as new,
            SUM(CASE WHEN c.status = 'referred' THEN 1 ELSE 0 END) as referred
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.adviser_id = ?
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_complaints'] = $result['total'] ?? 0;
    $stats['open_complaints'] = $result['new'] ?? 0;
    $stats['referred_complaints'] = $result['referred'] ?? 0;

    // Count referrals
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN r.status = 'open' THEN 1 ELSE 0 END) as pending
        FROM referrals r
        WHERE r.adviser_id = ?
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stats['total_referrals'] = $result['total'] ?? 0;
    $stats['pending_referrals'] = $result['pending'] ?? 0;
}

// Get recent complaints
$recent_complaints = [];
if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.first_name,
            s.last_name,
            s.student_id as student_code
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.adviser_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $recent_complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adviser Dashboard - GOMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .dashboard-content {
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            margin-bottom: 30px;
        }
        
        .welcome-section h1 {
            color: var(--clr-primary);
            font-size: var(--fs-heading);
            margin-bottom: 8px;
        }
        
        .welcome-section p {
            color: var(--clr-muted);
            font-size: var(--fs-normal);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all var(--time-transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--clr-primary);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: var(--clr-primary);
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: var(--fs-subheading);
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--clr-secondary);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--clr-text);
            transition: all var(--time-transition);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .action-btn:hover {
            background: var(--clr-primary);
            color: white;
            border-color: var(--clr-primary);
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }
        
        .action-label {
            font-weight: 500;
            font-size: var(--fs-normal);
        }
        
        .complaints-list {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }
        
        .complaint-item {
            padding: 16px;
            border-bottom: 1px solid var(--clr-border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color var(--time-transition);
        }
        
        .complaint-item:hover {
            background-color: var(--clr-bg-light);
        }
        
        .complaint-item:last-child {
            border-bottom: none;
        }
        
        .complaint-student {
            font-weight: 600;
            color: var(--clr-text);
        }
        
        .complaint-date {
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }
        
        .complaint-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: var(--fs-xsmall);
            font-weight: 600;
        }
        
        .status-badge-new {
            background: var(--clr-info-light);
            color: var(--clr-info);
        }
        
        .status-badge-referred {
            background: var(--clr-warning-light);
            color: var(--clr-warning);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--clr-muted);
            background: var(--clr-surface);
            border-radius: var(--radius-lg);
            border: 1px solid var(--clr-border);
        }
        
        @media (max-width: 768px) {
            .dashboard-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .complaint-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
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

        <a href="dashboard.php" class="nav-link active">
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
        
        <!-- <a href="create_referral.php" class="nav-link">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Create Referral</span>
        </a> -->
        
        <a href="referrals.php" class="nav-link">
            <span class="icon"><i class="fas fa-exchange-alt"></i></span>
            <span class="label">My Referrals</span>
        </a>
        
        <a href="appointments.php" class="nav-link">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <main class="content" id="mainContent">
        <div class="dashboard-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <h1>Welcome, <?= htmlspecialchars($adviser_info['full_name'] ?? 'Adviser'); ?>!</h1>
                <p>Adviser Dashboard - Guidance Office Management System</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_students'] ?></div>
                    <div class="stat-label">Students in Section</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_complaints'] ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['open_complaints'] ?></div>
                    <div class="stat-label">Open Complaints</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_referrals'] ?></div>
                    <div class="stat-label">Referrals Created</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <h3 class="section-title">Quick Actions</h3>
                <div class="quick-actions">
                    <a href="create_complaint.php" class="action-btn">
                        <span class="action-icon">📝</span>
                        <div class="action-label">Create Complaint</div>
                    </a>
                    <a href="complaints.php" class="action-btn">
                        <span class="action-icon">📋</span>
                        <div class="action-label">View Complaints</div>
                    </a>
                    <a href="referrals.php" class="action-btn">
                        <span class="action-icon">📨</span>
                        <div class="action-label">My Referrals</div>
                    </a>
                    <a href="students.php" class="action-btn">
                        <span class="action-icon">👥</span>
                        <div class="action-label">View Students</div>
                    </a>
                </div>
            </div>

            <!-- Recent Complaints -->
            <div class="section">
                <h3 class="section-title">Recent Complaints</h3>
                <?php if (empty($recent_complaints)): ?>
                    <div class="empty-state">
                        <p>No complaints yet. Create your first complaint.</p>
                        <a href="create_complaint.php" class="btn btn-primary" style="margin-top: 16px;">
                            Create Complaint
                        </a>
                    </div>
                <?php else: ?>
                    <div class="complaints-list">
                        <?php foreach ($recent_complaints as $complaint): ?>
                            <div class="complaint-item">
                                <div>
                                    <div class="complaint-student">
                                        <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?>
                                        <small>(<?= htmlspecialchars($complaint['student_code']) ?>)</small>
                                    </div>
                                    <div class="complaint-date">
                                        <?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?>
                                        • <?= ucfirst(htmlspecialchars($complaint['category'])) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="complaint-status status-badge-<?= htmlspecialchars($complaint['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($complaint['status'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 16px; text-align: center;">
                        <a href="complaints.php" class="btn" style="padding: 8px 16px; background: var(--clr-bg-light); color: var(--clr-text);">
                            View All Complaints
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Additional Info Section -->
            <div class="section">
                <h3 class="section-title">Quick Stats</h3>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['pending_referrals'] ?></div>
                        <div class="stat-label">Pending Referrals</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['referred_complaints'] ?></div>
                        <div class="stat-label">Referred Complaints</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Initialize dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Handle sidebar navigation
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Remove active class from all links
                    navLinks.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    this.classList.add('active');
                });
            });
            
            // Auto-refresh dashboard every 60 seconds
            setInterval(() => {
                // You can implement AJAX refresh here if needed
                // location.reload(); // Simple refresh
            }, 60000);
        });
    </script>
</body>
</html>
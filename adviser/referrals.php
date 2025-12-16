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

// Get referrals created by this adviser
$referrals = [];
$stats = [
    'total' => 0,
    'open' => 0,
    'scheduled' => 0,
    'in_session' => 0,
    'completed' => 0
];

if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            r.*,
            c.content as complaint_content,
            c.category,
            c.urgency_level,
            s.first_name,
            s.last_name,
            s.student_id as student_code,
            sec.section_name,
            sec.grade_level,
            co.full_name as counselor_name,
            cr.specialty as counselor_specialty,
            (SELECT COUNT(*) FROM appointments a WHERE a.referral_id = r.id) as appointment_count
        FROM referrals r
        JOIN complaints c ON r.complaint_id = c.id
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN counselors cr ON r.counselor_id = cr.id
        LEFT JOIN users co ON cr.user_id = co.id
        WHERE r.adviser_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    foreach ($referrals as $referral) {
        $stats['total']++;
        if ($referral['status'] == 'open') $stats['open']++;
        elseif ($referral['status'] == 'scheduled') $stats['scheduled']++;
        elseif ($referral['status'] == 'in_session') $stats['in_session']++;
        elseif ($referral['status'] == 'completed') $stats['completed']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Referrals - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/adviser_referrals.css">
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
        
        <a href="referrals.php" class="nav-link active">
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
        <div class="referrals-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Referrals</h1>
                <p class="subtitle">Track counseling referrals you have created</p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Referrals</div>
                </div>
                <div class="stat-card open">
                    <div class="stat-number"><?= $stats['open'] ?></div>
                    <div class="stat-label">Open</div>
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
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="open">Open</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_session">In Session</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="priorityFilter">Priority:</label>
                    <select id="priorityFilter" class="filter-select">
                        <option value="all">All Priority</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="counselorFilter">Counselor:</label>
                    <select id="counselorFilter" class="filter-select">
                        <option value="all">All Counselors</option>
                        <option value="assigned">Assigned Only</option>
                        <option value="unassigned">Unassigned Only</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="complaints.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create New Referral
                </a>
                <button class="btn btn-warning" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <?php if (empty($referrals)): ?>
                <div class="empty-state">
                    <h3>No referrals yet</h3>
                    <p>Create your first referral from the Complaints page by clicking "Create Referral" on any open complaint.</p>
                    <a href="complaints.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-clipboard-list"></i> Go to Complaints
                    </a>
                </div>
            <?php else: ?>
                <div class="referrals-grid" id="referralsGrid">
                    <?php foreach ($referrals as $referral): ?>
                        <div class="referral-card" 
                             data-status="<?= htmlspecialchars($referral['status']) ?>"
                             data-priority="<?= htmlspecialchars($referral['priority']) ?>"
                             data-counselor="<?= $referral['counselor_name'] ? 'assigned' : 'unassigned' ?>">
                            <div class="referral-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($referral['first_name'] . ' ' . $referral['last_name']) ?>
                                        <small>(<?= htmlspecialchars($referral['student_code']) ?>)</small>
                                    </div>
                                    <div class="student-details">
                                        Grade <?= htmlspecialchars($referral['grade_level']) ?> - 
                                        <?= htmlspecialchars($referral['section_name']) ?>
                                    </div>
                                </div>
                                <div class="referral-status status-<?= htmlspecialchars($referral['status']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', htmlspecialchars($referral['status']))) ?>
                                </div>
                            </div>
                            
                            <div class="referral-content">
                                <div style="margin-bottom: 10px;">
                                    <strong style="color: var(--clr-secondary);">
                                        <i class="fas fa-tag"></i> 
                                        <?= ucfirst(htmlspecialchars($referral['category'])) ?>
                                    </strong>
                                    <span class="priority priority-<?= htmlspecialchars($referral['priority']) ?>" style="margin-left: 15px;">
                                        <?= ucfirst(htmlspecialchars($referral['priority'])) ?> Priority
                                    </span>
                                </div>
                                <div style="color: var(--clr-text); line-height: 1.6; margin-bottom: 10px;">
                                    <strong>Referral Reason:</strong><br>
                                    <?= nl2br(htmlspecialchars(substr($referral['referral_reason'], 0, 250))) ?>
                                    <?= strlen($referral['referral_reason']) > 250 ? '...' : '' ?>
                                </div>
                            </div>
                            
                            <?php if ($referral['counselor_name']): ?>
                                <div class="counselor-info">
                                    <div class="counselor-name">
                                        <i class="fas fa-user-md"></i> 
                                        <?= htmlspecialchars($referral['counselor_name']) ?>
                                    </div>
                                    <div class="counselor-specialty">
                                        <i class="fas fa-star"></i> 
                                        <?= htmlspecialchars($referral['counselor_specialty']) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="counselor-info" style="border-left-color: var(--clr-warning);">
                                    <div class="counselor-name">
                                        <i class="fas fa-clock"></i> Awaiting Counselor Assignment
                                    </div>
                                    <div class="counselor-specialty">
                                        No counselor assigned yet. The guidance office will assign one soon.
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="referral-meta">
                                <div class="meta-left">
                                    <span style="color: var(--clr-muted);">
                                        <i class="far fa-calendar"></i>
                                        <?= date('M d, Y h:i A', strtotime($referral['created_at'])) ?>
                                    </span>
                                    <?php if ($referral['appointment_count'] > 0): ?>
                                        <span style="color: var(--clr-info);">
                                            <i class="fas fa-calendar-check"></i>
                                            <?= $referral['appointment_count'] ?> appointment(s)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="meta-right">
                                    <a href="#" 
                                       class="btn-sm" 
                                       style="background: var(--clr-bg-light); color: var(--clr-text); text-decoration: none;"
                                       onclick="viewReferralDetails(<?= $referral['id'] ?>)">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if ($referral['status'] == 'open' || $referral['status'] == 'scheduled'): ?>
                                        <a href="appointments.php?referral_id=<?= $referral['id'] ?>" 
                                           class="btn-sm btn-primary" style="text-decoration: none;">
                                            <i class="fas fa-calendar-alt"></i> Appointments
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
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
            const statusFilter = document.getElementById('statusFilter');
            const priorityFilter = document.getElementById('priorityFilter');
            const counselorFilter = document.getElementById('counselorFilter');
            const referralCards = document.querySelectorAll('.referral-card');
            
            function filterReferrals() {
                const status = statusFilter.value;
                const priority = priorityFilter.value;
                const counselor = counselorFilter.value;
                
                referralCards.forEach(card => {
                    const cardStatus = card.dataset.status;
                    const cardPriority = card.dataset.priority;
                    const cardCounselor = card.dataset.counselor;
                    
                    const statusMatch = status === 'all' || cardStatus === status;
                    const priorityMatch = priority === 'all' || cardPriority === priority;
                    const counselorMatch = counselor === 'all' || cardCounselor === counselor;
                    
                    if (statusMatch && priorityMatch && counselorMatch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', filterReferrals);
            priorityFilter.addEventListener('change', filterReferrals);
            counselorFilter.addEventListener('change', filterReferrals);
            
            // Auto-refresh every 60 seconds
            setInterval(() => {
                if (referralCards.length > 0) {
                    location.reload();
                }
            }, 60000);
            
            // View referral details function
            window.viewReferralDetails = function(referralId) {
                // You can implement a modal or redirect to referral details page
                alert(`Viewing details for referral ID: ${referralId}\nThis would open a detailed view or modal.`);
            };
            
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
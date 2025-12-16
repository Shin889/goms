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

// Get adviser's section students
$students = [];
if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT s.id, s.student_id, s.first_name, s.last_name, s.grade_level, sec.section_name
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.adviser_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get complaints for this adviser's section
$complaints = [];
$stats = [
    'total' => 0,
    'new' => 0,
    'referred' => 0,
    'closed' => 0
];

if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            c.*,
            s.first_name,
            s.last_name,
            s.student_id as student_code,
            sec.section_name,
            sec.grade_level,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM referrals r WHERE r.complaint_id = c.id) as referral_count
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        JOIN users u ON c.created_by_user_id = u.id
        WHERE sec.adviser_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    foreach ($complaints as $complaint) {
        $stats['total']++;
        if ($complaint['status'] == 'new') $stats['new']++;
        elseif ($complaint['status'] == 'referred') $stats['referred']++;
        elseif ($complaint['status'] == 'closed') $stats['closed']++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Complaints - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/adviser_complaints.css">
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
        
        <a href="complaints.php" class="nav-link active">
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
        <div class="complaints-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Student Complaints</h1>
                <p class="subtitle">Manage complaints for your section students</p>
            </div>

            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-card total">
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
                <div class="stat-card new">
                    <div class="stat-number"><?= $stats['new'] ?></div>
                    <div class="stat-label">New Complaints</div>
                </div>
                <div class="stat-card referred">
                    <div class="stat-number"><?= $stats['referred'] ?></div>
                    <div class="stat-label">Referred</div>
                </div>
                <div class="stat-card closed">
                    <div class="stat-number"><?= $stats['closed'] ?></div>
                    <div class="stat-label">Closed</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label for="statusFilter">Status:</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="all">All Status</option>
                        <option value="new">New</option>
                        <option value="referred">Referred</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="urgencyFilter">Urgency:</label>
                    <select id="urgencyFilter" class="filter-select">
                        <option value="all">All Urgency</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="categoryFilter">Category:</label>
                    <select id="categoryFilter" class="filter-select">
                        <option value="all">All Categories</option>
                        <option value="academic">Academic</option>
                        <option value="behavioral">Behavioral</option>
                        <option value="emotional">Emotional</option>
                        <option value="social">Social</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="create_complaint.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create New Complaint
                </a>
                <button class="btn btn-warning" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <h3>No complaints found</h3>
                    <p>Start by creating a complaint for one of your students.</p>
                    <a href="create_complaint.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus-circle"></i> Create Your First Complaint
                    </a>
                </div>
            <?php else: ?>
                <div class="complaints-grid" id="complaintsGrid">
                    <?php foreach ($complaints as $complaint): ?>
                        <div class="complaint-card" 
                             data-status="<?= htmlspecialchars($complaint['status']) ?>"
                             data-urgency="<?= htmlspecialchars($complaint['urgency_level']) ?>"
                             data-category="<?= htmlspecialchars($complaint['category']) ?>">
                            <div class="complaint-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?>
                                        <small>(<?= htmlspecialchars($complaint['student_code']) ?>)</small>
                                    </div>
                                    <div class="student-details">
                                        Grade <?= htmlspecialchars($complaint['grade_level']) ?> - 
                                        <?= htmlspecialchars($complaint['section_name']) ?>
                                    </div>
                                </div>
                                <div class="complaint-status status-<?= htmlspecialchars($complaint['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($complaint['status'])) ?>
                                </div>
                            </div>
                            
                            <div class="complaint-content">
                                <div style="margin-bottom: 10px;">
                                    <strong style="color: var(--clr-secondary);">
                                        <i class="fas fa-tag"></i> 
                                        <?= ucfirst(htmlspecialchars($complaint['category'])) ?>
                                    </strong>
                                </div>
                                <div style="color: var(--clr-text); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars(substr($complaint['content'], 0, 250))) ?>
                                    <?= strlen($complaint['content']) > 250 ? '...' : '' ?>
                                </div>
                            </div>
                            
                            <div class="complaint-meta">
                                <div class="meta-left">
                                    <span class="urgency urgency-<?= htmlspecialchars($complaint['urgency_level']) ?>">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?= ucfirst(htmlspecialchars($complaint['urgency_level'])) ?> Priority
                                    </span>
                                    <span style="color: var(--clr-muted);">
                                        <i class="far fa-calendar"></i>
                                        <?= date('M d, Y h:i A', strtotime($complaint['created_at'])) ?>
                                    </span>
                                    <?php if ($complaint['referral_count'] > 0): ?>
                                        <span style="color: var(--clr-info);">
                                            <i class="fas fa-paper-plane"></i>
                                            <?= $complaint['referral_count'] ?> referral(s)
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="meta-right">
                                    <a href="view_complaint.php?id=<?= $complaint['id'] ?>" 
                                       class="btn-sm" 
                                       style="background: var(--clr-bg-light); color: var(--clr-text); text-decoration: none;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($complaint['status'] == 'new'): ?>
                                        <a href="create_referral.php?complaint_id=<?= $complaint['id'] ?>" 
                                           class="btn-sm btn-success" style="text-decoration: none;">
                                            <i class="fas fa-paper-plane"></i> Refer
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($complaint['status'] == 'referred'): ?>
                                        <a href="referrals.php" 
                                           class="btn-sm" 
                                           style="background: var(--clr-warning-light); color: var(--clr-warning); text-decoration: none;">
                                            <i class="fas fa-exchange-alt"></i> View Referrals
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
            const urgencyFilter = document.getElementById('urgencyFilter');
            const categoryFilter = document.getElementById('categoryFilter');
            const complaintCards = document.querySelectorAll('.complaint-card');
            
            function filterComplaints() {
                const status = statusFilter.value;
                const urgency = urgencyFilter.value;
                const category = categoryFilter.value;
                
                complaintCards.forEach(card => {
                    const cardStatus = card.dataset.status;
                    const cardUrgency = card.dataset.urgency;
                    const cardCategory = card.dataset.category;
                    
                    const statusMatch = status === 'all' || cardStatus === status;
                    const urgencyMatch = urgency === 'all' || cardUrgency === urgency;
                    const categoryMatch = category === 'all' || cardCategory === category;
                    
                    if (statusMatch && urgencyMatch && categoryMatch) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            statusFilter.addEventListener('change', filterComplaints);
            urgencyFilter.addEventListener('change', filterComplaints);
            categoryFilter.addEventListener('change', filterComplaints);
            
            // Auto-refresh every 60 seconds
            setInterval(() => {
                if (complaintCards.length > 0) {
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
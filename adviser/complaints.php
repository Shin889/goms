<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Get adviser info
$adviser_info = null;
$stmt = $conn->prepare("
    SELECT a.id as adviser_id, u.full_name, sec.section_name, sec.grade_level 
    FROM advisers a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN sections sec ON sec.adviser_id = a.id
    WHERE a.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$adviser_info = $result ? $result->fetch_assoc() : null;

// Initialize variables
$referrals = [];
$stats = [
    'total' => 0,
    'open' => 0,
    'scheduled' => 0,
    'in_session' => 0,
    'completed' => 0,
    'cancelled' => 0
];

// Get referrals for the adviser
if ($adviser_info && isset($adviser_info['adviser_id'])) {
    // Simple query that should work with your current referrals table
    $sql = "
        SELECT 
            r.*,
            s.first_name,
            s.last_name,
            s.student_id as student_code,
            s.grade_level,
            sec.section_name,
            u.full_name as counselor_name
        FROM referrals r
        JOIN complaints c ON r.complaint_id = c.id
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN counselors cr ON r.counselor_id = cr.id
        LEFT JOIN users u ON cr.user_id = u.id
        WHERE r.adviser_id = ?
        ORDER BY r.created_at DESC
        LIMIT 50
    ";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $adviser_info['adviser_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $referrals = $result->fetch_all(MYSQLI_ASSOC);
            
            // Calculate statistics
            foreach ($referrals as $referral) {
                $stats['total']++;
                $status = $referral['status'];
                if (isset($stats[$status])) {
                    $stats[$status]++;
                }
            }
        }
    } else {
        // If the query fails, show error and try a simpler query
        error_log("SQL Error: " . $conn->error);
        
        // Try a simpler query
        $sql = "SELECT * FROM referrals WHERE adviser_id = ? ORDER BY created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $adviser_info['adviser_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $referrals = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Referrals - GOMS Adviser</title>
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
            <?php if (isset($adviser_info['section_name'])): ?>
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
                <div class="stat-card completed">
                    <div class="stat-number"><?= $stats['completed'] ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="create_referral.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Create New Referral
                </a>
                <button class="btn btn-warning" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <?php if (empty($referrals)): ?>
                <div class="empty-state">
                    <h3>No referrals yet</h3>
                    <p>Create your first referral to a counselor.</p>
                    <a href="create_referral.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus-circle"></i> Create Referral
                    </a>
                </div>
            <?php else: ?>
                <div class="referrals-grid" id="referralsGrid">
                    <?php foreach ($referrals as $referral): ?>
                        <div class="referral-card">
                            <div class="referral-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($referral['first_name'] ?? 'Unknown') . ' ' . htmlspecialchars($referral['last_name'] ?? 'Student') ?>
                                        <?php if (isset($referral['student_code'])): ?>
                                            <small>(<?= htmlspecialchars($referral['student_code']) ?>)</small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($referral['grade_level']) && isset($referral['section_name'])): ?>
                                        <div class="student-details">
                                            Grade <?= htmlspecialchars($referral['grade_level']) ?> - 
                                            <?= htmlspecialchars($referral['section_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="referral-status status-<?= htmlspecialchars($referral['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($referral['status'])) ?>
                                </div>
                            </div>
                            
                            <div class="referral-content">
                                <?php if (isset($referral['referral_reason'])): ?>
                                    <div style="color: var(--clr-text); line-height: 1.6; margin-bottom: 10px;">
                                        <strong>Referral Reason:</strong><br>
                                        <?= nl2br(htmlspecialchars(substr($referral['referral_reason'], 0, 250))) ?>
                                        <?= strlen($referral['referral_reason']) > 250 ? '...' : '' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($referral['counselor_name']) && $referral['counselor_name']): ?>
                                <div class="counselor-info">
                                    <div class="counselor-name">
                                        <i class="fas fa-user-md"></i> 
                                        <?= htmlspecialchars($referral['counselor_name']) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="counselor-info" style="border-left-color: var(--clr-warning);">
                                    <div class="counselor-name">
                                        <i class="fas fa-clock"></i> Awaiting Counselor Assignment
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="referral-meta">
                                <div class="meta-left">
                                    <span style="color: var(--clr-muted);">
                                        <i class="far fa-calendar"></i>
                                        <?= date('M d, Y h:i A', strtotime($referral['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="meta-right">
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
</body>
</html>
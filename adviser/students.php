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

// Get students in adviser's section
$students = [];
if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            sec.section_name,
            sec.grade_level,
            COUNT(c.id) as complaint_count,
            MAX(c.created_at) as last_complaint_date
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN complaints c ON s.id = c.student_id
        WHERE sec.adviser_id = ?
        GROUP BY s.id
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Students - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .students-content {
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
        
        .search-box {
            margin-bottom: 30px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: var(--fs-normal);
            transition: all var(--time-transition);
            background: var(--clr-surface);
            color: var(--clr-text);
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px var(--clr-primary-light);
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }
        
        .student-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            padding: 25px;
            box-shadow: var(--shadow-sm);
            transition: all var(--time-transition);
        }
        
        .student-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--clr-primary);
        }
        
        .student-header {
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
            font-size: var(--fs-subheading);
            font-weight: 700;
            color: var(--clr-text);
            margin-bottom: 4px;
        }
        
        .student-id {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            margin-bottom: 8px;
        }
        
        .student-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: var(--fs-xsmall);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: var(--clr-success-light);
            color: var(--clr-success);
        }
        
        .status-inactive {
            background: var(--clr-danger-light);
            color: var(--clr-danger);
        }
        
        .student-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--clr-bg-light);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--clr-text);
            font-size: var(--fs-small);
            font-weight: 600;
            text-align: right;
        }
        
        .complaint-info {
            background: var(--clr-bg-light);
            padding: 15px;
            border-radius: var(--radius-md);
            margin: 20px 0;
            border-left: 4px solid var(--clr-info);
        }
        
        .complaint-count {
            color: var(--clr-info);
            font-weight: 700;
            font-size: var(--fs-normal);
            margin-bottom: 5px;
        }
        
        .last-complaint {
            color: var(--clr-muted);
            font-size: var(--fs-xsmall);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: var(--fs-small);
            border-radius: var(--radius-sm);
            text-decoration: none;
            font-weight: 600;
            transition: all var(--time-transition);
            flex: 1;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--clr-primary);
            color: white;
            border: 1px solid var(--clr-primary);
        }
        
        .btn-primary:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--clr-surface);
            color: var(--clr-text);
            border: 1px solid var(--clr-border);
        }
        
        .btn-secondary:hover {
            background: var(--clr-bg-light);
            border-color: var(--clr-muted);
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
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            background: var(--clr-bg-light);
            padding: 20px;
            border-radius: var(--radius-lg);
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: var(--clr-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--clr-border-light);
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--clr-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }
        
        @media (max-width: 768px) {
            .students-content {
                padding: 20px;
            }
            
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
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

        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
        <a href="students.php" class="nav-link active">
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
        <div class="students-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Students</h1>
                <p class="subtitle">Students in your advisory section</p>
            </div>

            <!-- Summary Stats -->
            <?php if (!empty($students)): ?>
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= count($students) ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-item">
                        <?php
                        $total_complaints = array_sum(array_column($students, 'complaint_count'));
                        ?>
                        <div class="stat-number"><?= $total_complaints ?></div>
                        <div class="stat-label">Total Complaints</div>
                    </div>
                    <div class="stat-item">
                        <?php
                        $active_students = count(array_filter($students, function($s) {
                            return $s['status'] === 'active';
                        }));
                        ?>
                        <div class="stat-number"><?= $active_students ?></div>
                        <div class="stat-label">Active Students</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search Box -->
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Search students by name, ID, or section...">
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h3>No students assigned to your section</h3>
                    <p>Contact the administrator to assign students to your section or wait for section assignments to be completed.</p>
                    <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="students-grid" id="studentsGrid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card" data-search="<?= strtolower(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['student_id'] . ' ' . $student['section_name'])) ?>">
                            <div class="student-header">
                                <div class="student-info">
                                    <div class="student-name">
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    </div>
                                    <div class="student-id">
                                        ID: <?= htmlspecialchars($student['student_id']) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="student-status status-<?= $student['status'] ?>">
                                        <?= ucfirst(htmlspecialchars($student['status'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="student-details">
                                <div class="detail-row">
                                    <span class="detail-label">Section</span>
                                    <span class="detail-value">
                                        G<?= htmlspecialchars($student['grade_level']) ?> - 
                                        <?= htmlspecialchars($student['section_name']) ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value">
                                        <?= ucfirst(htmlspecialchars($student['gender'] ?? 'Not specified')) ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date of Birth</span>
                                    <span class="detail-value">
                                        <?= $student['dob'] ? date('M d, Y', strtotime($student['dob'])) : 'Not specified' ?>
                                    </span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Contact Number</span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($student['contact_number'] ?? 'N/A') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($student['complaint_count'] > 0): ?>
                                <div class="complaint-info">
                                    <div class="complaint-count">
                                        <i class="fas fa-exclamation-circle"></i> 
                                        <?= $student['complaint_count'] ?> complaint(s)
                                    </div>
                                    <?php if ($student['last_complaint_date']): ?>
                                        <div class="last-complaint">
                                            Last complaint: <?= date('M d, Y', strtotime($student['last_complaint_date'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="complaint-info" style="border-left-color: var(--clr-success); background: var(--clr-success-light);">
                                    <div class="complaint-count" style="color: var(--clr-success);">
                                        <i class="fas fa-check-circle"></i> No complaints
                                    </div>
                                    <div class="last-complaint">
                                        Clean record
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <a href="create_complaint.php?student_id=<?= $student['id'] ?>" class="btn-small btn-primary">
                                    <i class="fas fa-plus"></i> New Complaint
                                </a>
                                <a href="#" class="btn-small btn-secondary" onclick="viewStudentDetails(<?= $student['id'] ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.student-card');
            
            cards.forEach(card => {
                const searchText = card.getAttribute('data-search');
                if (searchText.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // View student details function
        function viewStudentDetails(studentId) {
            // You can implement a modal or redirect to student details page
            // For now, redirect to create complaint with the student pre-selected
            window.location.href = `create_complaint.php?student_id=${studentId}`;
        }
        
        // Initialize active nav link
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>
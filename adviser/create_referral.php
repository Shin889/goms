<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$complaint_id = isset($_GET['complaint_id']) ? intval($_GET['complaint_id']) : 0;
$error = '';
$success = '';

// Get complaint details
$complaint = null;
if ($complaint_id) {
    $stmt = $conn->prepare("
        SELECT c.*, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level
        FROM complaints c
        JOIN students s ON c.student_id = s.id
        JOIN sections sec ON s.section_id = sec.id
        WHERE c.id = ? AND c.created_by_user_id = ?
    ");
    $stmt->bind_param("ii", $complaint_id, $user_id);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
}

if (!$complaint) {
    header('Location: complaints.php');
    exit;
}

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

// Get available counselors
$counselors = [];
$stmt = $conn->prepare("
    SELECT c.id, u.full_name, c.specialty 
    FROM counselors c
    JOIN users u ON c.user_id = u.id
    WHERE u.is_active = 1 AND u.is_approved = 1
    ORDER BY u.full_name
");
$stmt->execute();
$counselors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $referral_reason = trim($_POST['referral_reason']);
    $priority = $_POST['priority'];
    $recommended_counselor_id = intval($_POST['recommended_counselor_id']);
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($referral_reason)) {
        $error = 'Please provide a referral reason.';
    } elseif (strlen($referral_reason) < 10) {
        $error = 'Referral reason must be at least 10 characters.';
    } else {
        // Create referral
       $stmt = $conn->prepare("
    INSERT INTO referrals 
    (complaint_id, adviser_id, referral_reason, priority, counselor_id, notes, status, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())
");
$stmt->bind_param("iisssi", $complaint_id, $adviser_info['id'], $referral_reason, $priority, $recommended_counselor_id, $notes);
        
        if ($stmt->execute()) {
            $referral_id = $conn->insert_id;
            
            // Update complaint status
            $update_stmt = $conn->prepare("
                UPDATE complaints 
                SET status = 'referred', updated_at = NOW() 
                WHERE id = ?
            ");
            $update_stmt->bind_param("i", $complaint_id);
            $update_stmt->execute();
            
            // Log audit
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, action_summary, target_table, target_id, created_at)
                VALUES (?, 'CREATE', 'Created referral from complaint', 'referrals', ?, NOW())
            ");
            $audit_stmt->bind_param("ii", $user_id, $referral_id);
            $audit_stmt->execute();
            
            $success = 'Referral created successfully! The complaint status has been updated to "referred". Redirecting to referrals page...';
            
            // Redirect after 3 seconds
            header("refresh:3;url=referrals.php");
        } else {
            $error = 'Failed to create referral: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Referral - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/create_referral.css">
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
        
        <a href="create_referral.php" class="nav-link active">
            <span class="icon"><i class="fas fa-paper-plane"></i></span>
            <span class="label">Create Referral</span>
        </a>
        
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
        <div class="referral-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Create Referral</h1>
                <p class="subtitle">Refer a student for counseling based on complaint</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Complaint Information -->
            <div class="info-box">
                <h3><i class="fas fa-exclamation-circle"></i> Complaint Details</h3>
                <div class="student-details">
                    <div class="detail-item">
                        <span class="detail-label">Student</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?>
                            (<?= htmlspecialchars($complaint['student_code']) ?>)
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Section</span>
                        <span class="detail-value">
                            Grade <?= htmlspecialchars($complaint['grade_level']) ?> - 
                            <?= htmlspecialchars($complaint['section_name']) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">
                            <?= ucfirst(htmlspecialchars($complaint['category'])) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Urgency</span>
                        <span class="detail-value">
                            <?= ucfirst(htmlspecialchars($complaint['urgency_level'])) ?>
                        </span>
                    </div>
                </div>
                
                <div class="complaint-content-box">
                    <div class="complaint-content-title">
                        <i class="fas fa-comment-alt"></i> Original Complaint
                    </div>
                    <div class="complaint-content-text">
                        <?= nl2br(htmlspecialchars($complaint['content'])) ?>
                    </div>
                </div>
            </div>

            <!-- Referral Form -->
            <div class="form-container">
                <form method="POST" action="" id="referralForm">
                    <!-- Counselor Selection -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user-md"></i> Recommended Counselor
                        </label>
                        <select name="recommended_counselor_id" class="form-control" id="counselorSelect">
                            <option value="0">No preference (assign any available counselor)</option>
                            <?php foreach ($counselors as $counselor): ?>
                                <option value="<?= $counselor['id'] ?>">
                                    <?= htmlspecialchars($counselor['full_name']) ?> 
                                    - <?= htmlspecialchars($counselor['specialty']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">
                            This is a recommendation. The guidance office will make the final assignment.
                        </div>
                        
                        <!-- Counselor Cards (Optional Visual Selection) -->
                        <?php if (!empty($counselors)): ?>
                            <div class="counselor-list">
                                <div class="counselor-option" data-value="0">
                                    <div class="counselor-name">No Preference</div>
                                    <div class="counselor-specialty">Assign any available counselor</div>
                                </div>
                                <?php foreach ($counselors as $counselor): ?>
                                    <div class="counselor-option" data-value="<?= $counselor['id'] ?>">
                                        <div class="counselor-name"><?= htmlspecialchars($counselor['full_name']) ?></div>
                                        <div class="counselor-specialty"><?= htmlspecialchars($counselor['specialty']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Priority Level -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-flag"></i> Priority Level
                        </label>
                        <select name="priority" class="form-control" required id="prioritySelect">
                            <option value="low">Low Priority - Follow-up within 2 weeks</option>
                            <option value="medium" selected>Medium Priority - Follow-up within 1 week</option>
                            <option value="high">High Priority - Follow-up within 48 hours</option>
                            <option value="critical">Critical - Immediate attention required</option>
                        </select>
                        <div class="priority-indicators">
                            <div class="priority-indicator priority-low" data-value="low">Low</div>
                            <div class="priority-indicator priority-medium" data-value="medium">Medium</div>
                            <div class="priority-indicator priority-high" data-value="high">High</div>
                            <div class="priority-indicator priority-critical" data-value="critical">Critical</div>
                        </div>
                    </div>

                    <!-- Referral Reason -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-clipboard"></i> Referral Reason
                        </label>
                        <textarea name="referral_reason" class="form-control" required 
                                placeholder="Explain why this student needs counseling. Include:
                                
• Specific concerns and observed behaviors
• Frequency and duration of issues
• Impact on academic performance
• Impact on peer relationships
• Any parent communication regarding the issue
• Previous interventions attempted
• Your recommendations for the counseling approach"></textarea>
                        <div class="form-help">
                            Be specific and objective. This information helps the counselor prepare effectively.
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i> Additional Notes (Optional)
                        </label>
                        <textarea name="notes" class="form-control" 
                                placeholder="Any additional information:
                                
• Parent contact details and conversations
• Medical or health considerations
• Cultural or family background factors
• Previous counseling history
• Special accommodations needed"></textarea>
                        <div class="form-help">
                            Include any information that may help the counselor understand the full context.
                        </div>
                    </div>

                    <!-- Form Footer -->
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Create Referral
                        </button>
                        <a href="complaints.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Complaints
                        </a>
                        <a href="referrals.php" class="btn btn-secondary">
                            <i class="fas fa-exchange-alt"></i> View My Referrals
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Initialize form interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Counselor card selection
            const counselorOptions = document.querySelectorAll('.counselor-option');
            const counselorSelect = document.getElementById('counselorSelect');
            
            counselorOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    counselorOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Update select element
                    counselorSelect.value = this.dataset.value;
                });
            });
            
            // Priority indicator selection
            const priorityIndicators = document.querySelectorAll('.priority-indicator');
            const prioritySelect = document.getElementById('prioritySelect');
            
            priorityIndicators.forEach(indicator => {
                indicator.addEventListener('click', function() {
                    // Remove selected style from all indicators
                    priorityIndicators.forEach(ind => {
                        ind.style.boxShadow = 'none';
                        ind.style.transform = 'scale(1)';
                    });
                    
                    // Add selected style to clicked indicator
                    this.style.boxShadow = '0 0 0 2px ' + getComputedStyle(document.documentElement).getPropertyValue('--clr-primary');
                    this.style.transform = 'scale(1.1)';
                    
                    // Update select element
                    prioritySelect.value = this.dataset.value;
                });
            });
            
            // Form validation
            document.getElementById('referralForm').addEventListener('submit', function(e) {
                const reason = document.querySelector('textarea[name="referral_reason"]').value.trim();
                if (reason.length < 10) {
                    e.preventDefault();
                    alert('Please provide a more detailed referral reason (at least 10 characters).');
                    return false;
                }
            });
            
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
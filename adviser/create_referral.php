<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$error = '';
$success = '';

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

// Get students from adviser's section
$students = [];
if ($adviser_info && isset($adviser_info['id'])) {
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
               s.grade_level, s.section_id, sec.section_name
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        WHERE sec.adviser_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

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
    $student_id = intval($_POST['student_id']);
    $category = $_POST['category'];
    $issue_description = trim($_POST['issue_description']);
    $priority = $_POST['priority'];
    $referral_reason = trim($_POST['referral_reason']);
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if (!$student_id) {
        $error = 'Please select a student.';
    } elseif (empty($issue_description)) {
        $error = 'Please provide an issue description.';
    } elseif (strlen($issue_description) < 10) {
        $error = 'Issue description must be at least 10 characters.';
    } elseif (empty($referral_reason)) {
        $error = 'Please provide a referral reason.';
    } elseif (strlen($referral_reason) < 10) {
        $error = 'Referral reason must be at least 10 characters.';
    } else {
        // Create direct referral
        $stmt = $conn->prepare("
            INSERT INTO referrals 
            (student_id, category, issue_description, adviser_id, 
             referral_reason, priority, notes, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())
        ");
        $stmt->bind_param("ississs", 
            $student_id, 
            $category, 
            $issue_description,
            $adviser_info['id'],
            $referral_reason,
            $priority,
            $notes
        );
        
        if ($stmt->execute()) {
            $referral_id = $conn->insert_id;
            
            // Log audit
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, action_summary, target_table, target_id, created_at)
                VALUES (?, 'CREATE', 'Created direct referral', 'referrals', ?, NOW())
            ");
            $audit_stmt->bind_param("ii", $user_id, $referral_id);
            $audit_stmt->execute();
            
            $success = 'Referral created successfully! Redirecting to referrals page...';
            
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
    <title>Create Direct Referral - GOMS Adviser</title>
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
                <h1>Create Direct Referral</h1>
                <p class="subtitle">Refer a student directly to counseling</p>
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

            <!-- Referral Form -->
            <div class="form-container">
                <form method="POST" action="" id="referralForm">
                    
                    <!-- Student Selection -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-user-graduate"></i> Select Student
                        </label>
                        <select name="student_id" class="form-control" required id="studentSelect">
                            <option value="">-- Select a student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['last_name']) ?>, 
                                    <?= htmlspecialchars($student['first_name']) ?>
                                    (<?= htmlspecialchars($student['student_code']) ?>)
                                    - Grade <?= htmlspecialchars($student['grade_level']) ?> 
                                    <?= htmlspecialchars($student['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">
                            Only students from your assigned section are listed.
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-tag"></i> Category
                        </label>
                        <select name="category" class="form-control" required>
                            <option value="academic">Academic Concerns</option>
                            <option value="behavioral">Behavioral Issues</option>
                            <option value="emotional">Emotional Support</option>
                            <option value="social">Social Skills</option>
                            <option value="attendance">Attendance Issues</option>
                            <option value="discipline">Disciplinary Concerns</option>
                            <option value="family">Family Issues</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Issue Description -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-clipboard"></i> Issue Description
                        </label>
                        <textarea name="issue_description" class="form-control" required 
                                placeholder="Describe the specific issue or concern. Include:
                                
• What behaviors/patterns have you observed?
• When did this start?
• Frequency and duration
• Specific incidents or examples
• Impact on the student and others"></textarea>
                        <div class="form-help">
                            Be objective and factual. Describe what you've observed.
                        </div>
                    </div>

                    <!-- Referral Reason -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-comment-medical"></i> Referral Reason
                        </label>
                        <textarea name="referral_reason" class="form-control" required 
                                placeholder="Explain why you're referring the student for counseling:
                                
• What interventions have you already tried?
• Why do you believe counseling is needed?
• What are your expectations from counseling?
• Any specific goals for the student?"></textarea>
                        <div class="form-help">
                            Explain why counseling is the appropriate next step.
                        </div>
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
                        <a href="referrals.php" class="btn btn-secondary">
                            <i class="fas fa-exchange-alt"></i> View My Referrals
                        </a>
                        <a href="students.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Students
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                const studentSelect = document.getElementById('studentSelect');
                const issueDesc = document.querySelector('textarea[name="issue_description"]').value.trim();
                const referralReason = document.querySelector('textarea[name="referral_reason"]').value.trim();
                
                if (!studentSelect.value) {
                    e.preventDefault();
                    alert('Please select a student.');
                    return false;
                }
                
                if (issueDesc.length < 10) {
                    e.preventDefault();
                    alert('Issue description must be at least 10 characters.');
                    return false;
                }
                
                if (referralReason.length < 10) {
                    e.preventDefault();
                    alert('Referral reason must be at least 10 characters.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
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

// Get students in adviser's section
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $category = $_POST['category'];
    $content = trim($_POST['content']);
    $referral_reason = trim($_POST['referral_reason']);
    $priority = $_POST['priority'] ?? 'medium';
    
    // Get student grade level
    $student_grade = 0;
    $student_name = '';
    foreach ($students as $student) {
        if ($student['id'] == $student_id) {
            $student_grade = intval($student['grade_level']);
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            break;
        }
    }
    
    // Determine level (Junior: 7-10, Senior: 11-12)
    $student_level = ($student_grade >= 7 && $student_grade <= 10) ? 'Junior' : 'Senior';
    
    // Get appropriate counselor based on level
    $stmt = $conn->prepare("
        SELECT c.id, u.full_name as counselor_name
        FROM counselors c
        JOIN users u ON c.user_id = u.id
        WHERE u.is_active = 1 
        AND (c.handles_level = ? OR c.handles_level = 'Both')
        ORDER BY RAND() 
        LIMIT 1
    ");
    $stmt->bind_param("s", $student_level);
    $stmt->execute();
    $counselor_result = $stmt->get_result()->fetch_assoc();
    
    if (!$counselor_result) {
        // Fallback: get any active counselor
        $stmt = $conn->prepare("
            SELECT c.id, u.full_name as counselor_name
            FROM counselors c
            JOIN users u ON c.user_id = u.id
            WHERE u.is_active = 1
            ORDER BY RAND() 
            LIMIT 1
        ");
        $stmt->execute();
        $counselor_result = $stmt->get_result()->fetch_assoc();
    }
    
    $counselor_id = $counselor_result['id'] ?? null;
    $counselor_name = $counselor_result['counselor_name'] ?? 'Unknown Counselor';
    
    // Validation
    if (empty($student_id) || empty($content) || empty($category) || empty($referral_reason)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($content) < 10) {
        $error = 'Issue description must be at least 10 characters.';
    } elseif (strlen($referral_reason) < 10) {
        $error = 'Referral reason must be at least 10 characters.';
    } elseif (!$counselor_id) {
        $error = 'No available counselor found for ' . $student_level . ' level students.';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get adviser_id
            $adviser_id = $adviser_info['id'] ?? 0;
            
            // Create referral directly
            $stmt = $conn->prepare("
                INSERT INTO referrals 
                (student_id, adviser_id, counselor_id, category, issue_description, referral_reason, priority, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            if (!$stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $stmt->bind_param("iiissss", $student_id, $adviser_id, $counselor_id, $category, $content, $referral_reason, $priority);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create referral: ' . $stmt->error);
            }
            
            $referral_id = $conn->insert_id;
            
            // Log audit - FIXED: removed string concatenation in SQL
            $action_summary = "Created referral for $student_name (Auto-assigned to $student_level counselor: $counselor_name)";
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, action_summary, target_table, target_id, created_at)
                VALUES (?, 'CREATE', ?, 'referrals', ?, NOW())
            ");
            $audit_stmt->bind_param("isi", $user_id, $action_summary, $referral_id);
            $audit_stmt->execute();
            
            $conn->commit();
            
            $success = "Referral created successfully! Auto-assigned to $student_level level counselor ($counselor_name). Redirecting to referrals page...";
            
            // Redirect after 2 seconds
            header("refresh:2;url=referrals.php");
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
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
    <link rel="stylesheet" href="../utils/css/create_complaint.css">
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
        <div class="form-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Create New Referral</h1>
                <p class="subtitle">Refer a student issue to appropriate counselor based on grade level</p>
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

            <div class="form-container">
                <form method="POST" action="" id="referralForm">
                    <!-- Student Selection -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-user-graduate"></i> Select Student
                        </label>
                        <select name="student_id" class="form-control" id="studentSelect" required onchange="updateStudentInfo()">
                            <option value="">Choose a student from your section...</option>
                            <?php foreach ($students as $student): 
                                $grade = intval($student['grade_level']);
                                $level = ($grade >= 7 && $grade <= 10) ? 'Junior' : 'Senior';
                            ?>
                                <option value="<?= $student['id'] ?>" 
                                        data-student-id="<?= $student['student_id'] ?>"
                                        data-first-name="<?= htmlspecialchars($student['first_name']) ?>"
                                        data-last-name="<?= htmlspecialchars($student['last_name']) ?>"
                                        data-grade="<?= $student['grade_level'] ?>"
                                        data-section="<?= htmlspecialchars($student['section_name']) ?>"
                                        data-level="<?= $level ?>">
                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                    (Grade <?= $student['grade_level'] ?> - <?= $level ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">
                            Only students from your advisory section are listed. Counselor will be auto-assigned.
                        </div>
                    </div>

                    <!-- Student Info Display -->
                    <div class="student-info-box" id="studentInfoBox">
                        <div class="student-info-title">
                            <i class="fas fa-info-circle"></i> Selected Student Information
                        </div>
                        <div class="student-details">
                            <div class="student-detail">
                                <strong>Full Name</strong>
                                <span id="studentFullName">-</span>
                            </div>
                            <div class="student-detail">
                                <strong>Student ID</strong>
                                <span id="studentID">-</span>
                            </div>
                            <div class="student-detail">
                                <strong>Grade & Section</strong>
                                <span id="studentGradeSection">-</span>
                            </div>
                            <div class="student-detail">
                                <strong>Level</strong>
                                <span id="studentLevel">-</span>
                            </div>
                            <div class="student-detail">
                                <strong>Counselor Assignment</strong>
                                <span id="counselorAssignment" style="color: #4CAF50; font-weight: bold;">Auto-assigned based on level</span>
                            </div>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-tag"></i> Issue Category
                        </label>
                        <select name="category" class="form-control" required>
                            <option value="">Select the issue category...</option>
                            <option value="academic">Academic Issues</option>
                            <option value="behavioral">Behavioral Concerns</option>
                            <option value="emotional">Emotional Distress</option>
                            <option value="social">Social Difficulties</option>
                            <option value="attendance">Attendance Problems</option>
                            <option value="discipline">Disciplinary Matters</option>
                            <option value="family">Family-related Issues</option>
                            <option value="other">Other Concerns</option>
                        </select>
                    </div>

                    <!-- Priority Level -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-exclamation-triangle"></i> Priority Level
                        </label>
                        <select name="priority" class="form-control" required>
                            <option value="low">Low Priority - Routine concern</option>
                            <option value="medium" selected>Medium Priority - Needs attention</option>
                            <option value="high">High Priority - Requires prompt action</option>
                            <option value="critical">Critical - Immediate intervention needed</option>
                        </select>
                        <div class="priority-indicators">
                            <span class="priority-indicator priority-low">Low</span>
                            <span class="priority-indicator priority-medium">Medium</span>
                            <span class="priority-indicator priority-high">High</span>
                            <span class="priority-indicator priority-critical">Critical</span>
                        </div>
                    </div>

                    <!-- Issue Description -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-edit"></i> Issue Description
                        </label>
                        <textarea name="content" class="form-control" required 
                                placeholder="Please provide detailed information about the student's issue. Include:
                                
• Specific behaviors or incidents observed
• Dates and times when issues occurred
• Context or background information
• Any previous interventions attempted
• Impact on the student's academic performance or wellbeing"></textarea>
                        <div class="form-help">
                            Be objective, specific, and factual. Include relevant dates, behaviors, and context.
                        </div>
                    </div>

                    <!-- Referral Reason -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-comment-medical"></i> Referral Reason
                        </label>
                        <textarea name="referral_reason" class="form-control" required 
                                placeholder="Explain why this case needs to be referred to a counselor. Include:
                                
• Why counselor intervention is needed
• Specific areas where counseling support is required
• Your expectations from the counseling session
• Any specific concerns to address"></textarea>
                        <div class="form-help">
                            Clearly state why this case requires professional counseling support
                        </div>
                    </div>

                    <!-- Form Footer -->
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Create Referral
                        </button>
                        <a href="referrals.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Referrals
                        </a>
                        <a href="students.php" class="btn btn-secondary">
                            <i class="fas fa-users"></i> View Students
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Update student info when selection changes
        function updateStudentInfo() {
            const select = document.getElementById('studentSelect');
            const selectedOption = select.options[select.selectedIndex];
            const infoBox = document.getElementById('studentInfoBox');
            
            if (selectedOption.value) {
                document.getElementById('studentFullName').textContent = 
                    selectedOption.dataset.firstName + ' ' + selectedOption.dataset.lastName;
                document.getElementById('studentID').textContent = selectedOption.dataset.studentId;
                document.getElementById('studentGradeSection').textContent = 
                    'Grade ' + selectedOption.dataset.grade + ' - ' + selectedOption.dataset.section;
                document.getElementById('studentLevel').textContent = selectedOption.dataset.level;
                infoBox.classList.add('active');
            } else {
                infoBox.classList.remove('active');
            }
        }
        
        // Form validation
        document.getElementById('referralForm').addEventListener('submit', function(e) {
            const content = document.querySelector('textarea[name="content"]').value.trim();
            const referralReason = document.querySelector('textarea[name="referral_reason"]').value.trim();
            
            if (content.length < 10) {
                e.preventDefault();
                alert('Please provide more detailed information in the issue description (at least 10 characters).');
                return false;
            }
            
            if (referralReason.length < 10) {
                e.preventDefault();
                alert('Please provide a detailed referral reason (at least 10 characters).');
                return false;
            }
        });
        
        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            // Check for URL parameters (if coming from student page)
            const urlParams = new URLSearchParams(window.location.search);
            const studentId = urlParams.get('student_id');
            
            if (studentId) {
                const select = document.getElementById('studentSelect');
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === studentId) {
                        select.selectedIndex = i;
                        updateStudentInfo();
                        break;
                    }
                }
            }
            
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
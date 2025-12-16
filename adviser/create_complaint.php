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
    $urgency_level = $_POST['urgency_level'];
    
    // Validation
    if (empty($student_id) || empty($content) || empty($category)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($content) < 10) {
        $error = 'Complaint content must be at least 10 characters.';
    } else {
        // Create complaint
        $stmt = $conn->prepare("
            INSERT INTO complaints 
            (student_id, created_by_user_id, category, content, urgency_level, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'new', NOW(), NOW())
        ");
        $stmt->bind_param("iisss", $student_id, $user_id, $category, $content, $urgency_level);
        
        if ($stmt->execute()) {
            $complaint_id = $conn->insert_id;
            
            // Log audit
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, action_summary, target_table, target_id, created_at)
                VALUES (?, 'CREATE', 'Created complaint for student', 'complaints', ?, NOW())
            ");
            $audit_stmt->bind_param("ii", $user_id, $complaint_id);
            $audit_stmt->execute();
            
            $success = 'Complaint created successfully! Redirecting to complaints page...';
            
            // Redirect after 2 seconds
            header("refresh:2;url=complaints.php");
        } else {
            $error = 'Failed to create complaint: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Complaint - GOMS Adviser</title>
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
        
        <a href="create_complaint.php" class="nav-link active">
            <span class="icon"><i class="fas fa-plus-circle"></i></span>
            <span class="label">Create Complaint</span>
        </a>
        
        <a href="complaints.php" class="nav-link">
            <span class="icon"><i class="fas fa-clipboard-list"></i></span>
            <span class="label">View Complaints</span>
        </a>
<!--         
        <a href="create_referral.php" class="nav-link">
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
        <div class="form-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Create New Complaint</h1>
                <p class="subtitle">Report an issue for one of your students</p>
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
                <form method="POST" action="" id="complaintForm">
                    <!-- Student Selection -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-user-graduate"></i> Select Student
                        </label>
                        <select name="student_id" class="form-control" id="studentSelect" required onchange="updateStudentInfo()">
                            <option value="">Choose a student from your section...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" 
                                        data-student-id="<?= $student['student_id'] ?>"
                                        data-first-name="<?= htmlspecialchars($student['first_name']) ?>"
                                        data-last-name="<?= htmlspecialchars($student['last_name']) ?>"
                                        data-grade="<?= $student['grade_level'] ?>"
                                        data-section="<?= htmlspecialchars($student['section_name']) ?>">
                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                    (<?= htmlspecialchars($student['student_id']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">
                            Only students from your advisory section are listed
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
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-tag"></i> Category
                        </label>
                        <select name="category" class="form-control" required>
                            <option value="">Select the complaint category...</option>
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

                    <!-- Urgency Level -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-exclamation-triangle"></i> Urgency Level
                        </label>
                        <select name="urgency_level" class="form-control" required>
                            <option value="low">Low Priority - Routine concern</option>
                            <option value="medium" selected>Medium Priority - Needs attention</option>
                            <option value="high">High Priority - Requires prompt action</option>
                            <option value="critical">Critical - Immediate intervention needed</option>
                        </select>
                        <div class="urgency-indicators">
                            <span class="urgency-indicator urgency-low">Low</span>
                            <span class="urgency-indicator urgency-medium">Medium</span>
                            <span class="urgency-indicator urgency-high">High</span>
                            <span class="urgency-indicator urgency-critical">Critical</span>
                        </div>
                    </div>

                    <!-- Complaint Details -->
                    <div class="form-group">
                        <label class="form-label required">
                            <i class="fas fa-edit"></i> Complaint Details
                        </label>
                        <textarea name="content" class="form-control" required 
                                placeholder="Please provide detailed information about the complaint. Include:
                                
• Specific behaviors or incidents observed
• Dates and times when issues occurred
• Context or background information
• Any previous interventions attempted
• Impact on the student's academic performance or wellbeing
• Your recommendations for support"></textarea>
                        <div class="form-help">
                            Be objective, specific, and factual. Include relevant dates, behaviors, and context.
                        </div>
                    </div>

                    <!-- Form Footer -->
                    <div class="form-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Create Complaint
                        </button>
                        <a href="complaints.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Complaints
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
                infoBox.classList.add('active');
            } else {
                infoBox.classList.remove('active');
            }
        }
        
        // Form validation
        document.getElementById('complaintForm').addEventListener('submit', function(e) {
            const content = document.querySelector('textarea[name="content"]').value.trim();
            if (content.length < 10) {
                e.preventDefault();
                alert('Please provide more detailed information in the complaint content (at least 10 characters).');
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
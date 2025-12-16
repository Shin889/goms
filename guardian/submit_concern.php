<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$error = '';
$success = '';

// Fetch guardian info
$guardian = null;
$guardian_id = 0;
$stmt = $conn->prepare("SELECT g.*, u.full_name FROM guardians g JOIN users u ON g.user_id = u.id WHERE g.user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardian = $result->fetch_assoc();
    $guardian_id = $guardian ? $guardian['id'] : 0;
}

// Get linked students
$linked_students = [];
if ($guardian_id > 0) {
    $linked_sql = "
        SELECT s.id, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level, sec.adviser_id,
               u.full_name as adviser_name
        FROM student_guardians sg
        JOIN students s ON sg.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN advisers a ON sec.adviser_id = a.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE sg.guardian_id = ?
        ORDER BY s.last_name, s.first_name
    ";
    
    $linked_stmt = $conn->prepare($linked_sql);
    if ($linked_stmt) {
        $linked_stmt->bind_param("i", $guardian_id);
        $linked_stmt->execute();
        $result = $linked_stmt->get_result();
        $linked_students = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $concern_type = $_POST['concern_type'];
    $description = trim($_POST['description']);
    $urgency = $_POST['urgency'];
    $contact_preference = $_POST['contact_preference'];
    $contact_info = trim($_POST['contact_info']);
    
    // Validation
    if (empty($student_id) || empty($description) || empty($concern_type)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($description) < 10) {
        $error = 'Description must be at least 10 characters.';
    } else {
        // Check if guardian_concerns table exists, if not create it
        $table_check = $conn->query("SHOW TABLES LIKE 'guardian_concerns'");
        if (!$table_check || $table_check->num_rows == 0) {
            // Create guardian_concerns table
            $create_table = "
                CREATE TABLE IF NOT EXISTS guardian_concerns (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    guardian_id INT NOT NULL,
                    student_id INT NOT NULL,
                    concern_type VARCHAR(50) NOT NULL,
                    description TEXT NOT NULL,
                    urgency VARCHAR(20) NOT NULL,
                    contact_preference VARCHAR(20) NOT NULL,
                    contact_info VARCHAR(255),
                    status VARCHAR(20) DEFAULT 'submitted',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (guardian_id) REFERENCES guardians(id),
                    FOREIGN KEY (student_id) REFERENCES students(id)
                )
            ";
            $conn->query($create_table);
        }
        
        // Insert concern into database
        $insert_sql = "
            INSERT INTO guardian_concerns 
            (guardian_id, student_id, concern_type, description, urgency, contact_preference, contact_info, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted')
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        if ($insert_stmt) {
            $insert_stmt->bind_param("iisssss", $guardian_id, $student_id, $concern_type, $description, $urgency, $contact_preference, $contact_info);
            
            if ($insert_stmt->execute()) {
                $concern_id = $insert_stmt->insert_id;
                
                // Log the action
                $log_sql = "INSERT INTO audit_logs (user_id, action, action_summary, target_table, target_id, created_at) VALUES (?, 'CREATE', 'Submitted guardian concern', 'guardian_concerns', ?, NOW())";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $log_stmt->bind_param("ii", $user_id, $concern_id);
                    $log_stmt->execute();
                }
                
                $success = 'Concern submitted successfully! The adviser will review your concern and contact you if needed.';
                
                // Clear form on success
                echo "<script>document.addEventListener('DOMContentLoaded', function() { document.querySelector('form').reset(); });</script>";
            } else {
                $error = 'Failed to submit concern: ' . $insert_stmt->error;
            }
        } else {
            $error = 'Error preparing statement: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Concern - GOMS Guardian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="../utils/css/submit_concern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <button id="sidebarToggle" class="toggle-btn">☰</button>

        <h2 class="logo">GOMS Guardian</h2>
        <div class="sidebar-user">
            <i class="fas fa-user-shield"></i> Guardian · <?= htmlspecialchars($guardian['full_name'] ?? 'Guardian'); ?>
            <?php if (!empty($linked_students)): ?>
                <br><small>
                    <?= count($linked_students) ?> student<?= count($linked_students) > 1 ? 's' : '' ?> linked
                </small>
            <?php endif; ?>
        </div>

        <a href="dashboard.php" class="nav-link">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span class="label">Dashboard</span>
        </a>
        
        <a href="link_student.php" class="nav-link">
            <span class="icon"><i class="fas fa-link"></i></span>
            <span class="label">Link Student</span>
        </a>
        
        <a href="appointments.php" class="nav-link">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            <span class="label">Appointments</span>
        </a>
        
        <a href="request_appointment.php" class="nav-link">
            <span class="icon"><i class="fas fa-plus-circle"></i></span>
            <span class="label">Request Appointment</span>
        </a>
        
        <a href="submit_concern.php" class="nav-link active">
            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="label">Submit Concern</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <main class="content" id="mainContent">
        <div class="submit-concern-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Submit Concern to Adviser</h1>
                <p class="subtitle">Report a concern about your child to their class adviser</p>
            </div>

            <!-- Message Alerts -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($linked_students)): ?>
                <div class="no-students">
                    <div class="no-students-icon">
                        <i class="fas fa-link"></i>
                    </div>
                    <h3>No Linked Students</h3>
                    <p>You need to link your account to your child's student profile before you can submit concerns.</p>
                    <a href="link_student.php" class="btn btn-primary">
                        <i class="fas fa-link"></i> Link a Student
                    </a>
                </div>
            <?php else: ?>
                <!-- Concern Form -->
                <div class="form-card">
                    <h3><i class="fas fa-exclamation-triangle"></i> Submit New Concern</h3>
                    
                    <form method="POST" action="" id="concernForm">
                        <!-- Student Selection -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-user-graduate"></i> Select Child
                            </label>
                            <select id="student_id" name="student_id" class="form-control" required>
                                <option value="">-- Choose your child --</option>
                                <?php foreach ($linked_students as $student): ?>
                                    <option value="<?= $student['id'] ?>" <?= isset($_POST['student_id']) && $_POST['student_id'] == $student['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        (ID: <?= htmlspecialchars($student['student_code']) ?>)
                                        <?php if ($student['section_name']): ?>
                                            - Grade <?= htmlspecialchars($student['grade_level']) ?> - <?= htmlspecialchars($student['section_name']) ?>
                                        <?php endif; ?>
                                        <?php if ($student['adviser_name']): ?>
                                            • Adviser: <?= htmlspecialchars($student['adviser_name']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help">
                                Select which child this concern is about.
                            </div>
                        </div>

                        <!-- Concern Type -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-tag"></i> Type of Concern
                            </label>
                            <select id="concern_type" name="concern_type" class="form-control" required>
                                <option value="">-- Select concern type --</option>
                                <option value="academic" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'academic') ? 'selected' : '' ?>>Academic Performance</option>
                                <option value="behavior" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'behavior') ? 'selected' : '' ?>>Behavioral Issues</option>
                                <option value="emotional" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'emotional') ? 'selected' : '' ?>>Emotional Well-being</option>
                                <option value="social" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'social') ? 'selected' : '' ?>>Social Interactions</option>
                                <option value="attendance" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'attendance') ? 'selected' : '' ?>>Attendance/Punctuality</option>
                                <option value="health" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'health') ? 'selected' : '' ?>>Health/Medical</option>
                                <option value="family" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'family') ? 'selected' : '' ?>>Family-related Issues</option>
                                <option value="other" <?= (isset($_POST['concern_type']) && $_POST['concern_type'] == 'other') ? 'selected' : '' ?>>Other</option>
                            </select>
                            <div class="form-help">
                                Select the category that best describes your concern.
                            </div>
                        </div>

                        <!-- Urgency Level -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-flag"></i> Urgency Level
                            </label>
                            <select id="urgency" name="urgency" class="form-control" required>
                                <option value="low" <?= (isset($_POST['urgency']) && $_POST['urgency'] == 'low') ? 'selected' : '' ?>>Low Priority - Can wait for response</option>
                                <option value="medium" <?= (!isset($_POST['urgency']) || (isset($_POST['urgency']) && $_POST['urgency'] == 'medium')) ? 'selected' : '' ?>>Medium Priority - Should address soon</option>
                                <option value="high" <?= (isset($_POST['urgency']) && $_POST['urgency'] == 'high') ? 'selected' : '' ?>>High Priority - Needs attention</option>
                                <option value="urgent" <?= (isset($_POST['urgency']) && $_POST['urgency'] == 'urgent') ? 'selected' : '' ?>>Urgent - Immediate attention needed</option>
                            </select>
                            <div class="urgency-options">
                                <div class="urgency-option urgency-low" data-value="low">Low</div>
                                <div class="urgency-option urgency-medium" data-value="medium">Medium</div>
                                <div class="urgency-option urgency-high" data-value="high">High</div>
                                <div class="urgency-option urgency-urgent" data-value="urgent">Urgent</div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-edit"></i> Description of Concern
                            </label>
                            <textarea id="description" name="description" class="form-control" required 
                                      placeholder="Please provide detailed information about your concern. Include:
                                      
• Specific behaviors or issues observed
• When the concern started
• Frequency and duration
• Impact on your child's academic performance
• Impact on social relationships
• Any patterns you've noticed
• Your child's response to the situation"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                            <div class="form-help">
                                Be specific and provide as much detail as possible to help the adviser understand the situation.
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-phone"></i> Preferred Contact Method
                            </label>
                            <select id="contact_preference" name="contact_preference" class="form-control" required>
                                <option value="phone" <?= (!isset($_POST['contact_preference']) || (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'phone')) ? 'selected' : '' ?>>Phone Call</option>
                                <option value="email" <?= (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'email') ? 'selected' : '' ?>>Email</option>
                                <option value="sms" <?= (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'sms') ? 'selected' : '' ?>>SMS/Text Message</option>
                                <option value="meeting" <?= (isset($_POST['contact_preference']) && $_POST['contact_preference'] == 'meeting') ? 'selected' : '' ?>>In-person Meeting</option>
                            </select>
                            <div class="form-help">
                                How would you prefer the adviser to contact you about this concern?
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">
                                <i class="fas fa-info-circle"></i> Contact Information
                            </label>
                            <input type="text" id="contact_info" name="contact_info" class="form-control" 
                                   placeholder="e.g., Your phone number, email address, or best time to call"
                                   value="<?= isset($_POST['contact_info']) ? htmlspecialchars($_POST['contact_info']) : '' ?>" required>
                            <div class="form-help">
                                Provide specific contact details for the selected method.
                            </div>
                        </div>

                        <!-- Form Footer -->
                        <div class="form-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Concern to Adviser
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                            <a href="appointments.php" class="btn btn-secondary">
                                <i class="fas fa-calendar-alt"></i> View Appointments
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- Tips Card -->
                <div class="form-card" style="margin-top: 30px;">
                    <h3><i class="fas fa-lightbulb"></i> Tips for Effective Concern Reporting</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-bullseye"></i> Be Specific
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                Include dates, times, and specific behaviors. The more detailed your description, the better the adviser can help.
                            </p>
                        </div>
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-clock"></i> Choose Appropriate Urgency
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                Reserve "Urgent" for immediate safety concerns. Most issues can be addressed within a few days.
                            </p>
                        </div>
                        <div>
                            <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                                <i class="fas fa-handshake"></i> Follow Up
                            </h4>
                            <p style="color: var(--clr-text); font-size: var(--fs-small);">
                                The adviser will contact you using your preferred method. If you don't hear back in 2-3 days, follow up.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Urgency option selection
            const urgencyOptions = document.querySelectorAll('.urgency-option');
            const urgencySelect = document.getElementById('urgency');
            
            urgencyOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    urgencyOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Update select element
                    urgencySelect.value = this.dataset.value;
                });
            });
            
            // Set initial selected urgency option
            const initialUrgency = urgencySelect.value;
            urgencyOptions.forEach(option => {
                if (option.dataset.value === initialUrgency) {
                    option.classList.add('selected');
                }
            });
            
            // Form validation
            const form = document.getElementById('concernForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const description = document.getElementById('description').value.trim();
                    if (description.length < 10) {
                        e.preventDefault();
                        alert('Please provide a more detailed description of your concern (at least 10 characters).');
                        document.getElementById('description').focus();
                        return false;
                    }
                    
                    const contactInfo = document.getElementById('contact_info').value.trim();
                    if (contactInfo.length < 3) {
                        e.preventDefault();
                        alert('Please provide valid contact information.');
                        document.getElementById('contact_info').focus();
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Auto-clear success message after 5 seconds
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>
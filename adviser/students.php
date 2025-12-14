<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Get adviser info
$adviser_info = null;
$stmt = $conn->prepare("
    SELECT a.*, u.full_name, sec.section_name, sec.grade_level, sec.id as section_id
    FROM advisers a 
    JOIN users u ON a.user_id = u.id 
    LEFT JOIN sections sec ON sec.adviser_id = a.id
    WHERE a.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$adviser_info = $stmt->get_result()->fetch_assoc();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_student'])) {
        // Add new student
        $student_id = $_POST['student_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $middle_name = $_POST['middle_name'] ?? '';
        $gender = $_POST['gender'];
        $dob = $_POST['dob'] ? date('Y-m-d', strtotime($_POST['dob'])) : null;
        $contact_number = $_POST['contact_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        // Generate student ID if not provided
        if (empty($student_id)) {
            $year = date('Y');
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(student_id, 6) AS UNSIGNED)) as max_num FROM students WHERE student_id LIKE ?");
            $like_pattern = $year . '-%';
            $stmt->bind_param("s", $like_pattern);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_num = ($row['max_num'] ?? 0) + 1;
            $student_id = $year . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
        }
        
        $stmt = $conn->prepare("
            INSERT INTO students (
                student_id, first_name, middle_name, last_name, 
                dob, gender, grade_level, section_id,
                contact_number, notes, created_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param(
            "ssssssssssi", 
            $student_id, $first_name, $middle_name, $last_name,
            $dob, $gender, $adviser_info['grade_level'], $adviser_info['section_id'],
            $contact_number, $notes, $user_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Student added successfully!";
            header('Location: students.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Error adding student: " . $conn->error;
        }
    }
    
    if (isset($_POST['update_student'])) {
        // Update existing student
        $student_id = intval($_POST['student_id']);
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $middle_name = $_POST['middle_name'] ?? '';
        $gender = $_POST['gender'];
        $dob = $_POST['dob'] ? date('Y-m-d', strtotime($_POST['dob'])) : null;
        $contact_number = $_POST['contact_number'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        $stmt = $conn->prepare("
            UPDATE students SET
                first_name = ?, middle_name = ?, last_name = ?,
                dob = ?, gender = ?, contact_number = ?,
                notes = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND section_id = ?
        ");
        $stmt->bind_param(
            "ssssssssii", 
            $first_name, $middle_name, $last_name,
            $dob, $gender, $contact_number,
            $notes, $status, $student_id, $adviser_info['section_id']
        );
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Student updated successfully!";
            header('Location: students.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Error updating student: " . $conn->error;
        }
    }
    
    if (isset($_POST['link_guardian'])) {
        // Link guardian to student
        $student_id = intval($_POST['student_id']);
        $guardian_email = $_POST['guardian_email'];
        $relationship = $_POST['relationship'];
        $primary_guardian = isset($_POST['primary_guardian']) ? 1 : 0;
        
        // Check if student belongs to adviser's section
        $check = $conn->prepare("SELECT id FROM students WHERE id = ? AND section_id = ?");
        $check->bind_param("ii", $student_id, $adviser_info['section_id']);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            // Find guardian by email
            $stmt = $conn->prepare("
                SELECT g.id 
                FROM guardians g
                JOIN users u ON g.user_id = u.id
                WHERE u.email = ? AND u.role = 'guardian' AND u.is_active = 1
            ");
            $stmt->bind_param("s", $guardian_email);
            $stmt->execute();
            $guardian_result = $stmt->get_result();
            
            if ($guardian_result->num_rows > 0) {
                $guardian = $guardian_result->fetch_assoc();
                
                // Check if already linked
                $check_link = $conn->prepare("
                    SELECT id FROM student_guardians 
                    WHERE student_id = ? AND guardian_id = ?
                ");
                $check_link->bind_param("ii", $student_id, $guardian['id']);
                $check_link->execute();
                
                if ($check_link->get_result()->num_rows == 0) {
                    // Insert new link
                    $stmt = $conn->prepare("
                        INSERT INTO student_guardians (student_id, guardian_id, relationship, primary_guardian, linked_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iissi", $student_id, $guardian['id'], $relationship, $primary_guardian, $user_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = "Guardian linked successfully!";
                    } else {
                        $_SESSION['error_message'] = "Error linking guardian: " . $conn->error;
                    }
                } else {
                    $_SESSION['error_message'] = "Guardian is already linked to this student.";
                }
            } else {
                $_SESSION['error_message'] = "No active guardian found with that email.";
            }
        } else {
            $_SESSION['error_message'] = "Student not found in your section.";
        }
        header('Location: students.php');
        exit;
    }
    
    if (isset($_POST['unlink_guardian'])) {
        // Unlink guardian via POST
        $student_id = intval($_POST['student_id']);
        $guardian_email = $_POST['guardian_email'];
        
        $stmt = $conn->prepare("
            DELETE sg FROM student_guardians sg
            JOIN guardians g ON sg.guardian_id = g.id
            JOIN users u ON g.user_id = u.id
            JOIN students s ON sg.student_id = s.id
            WHERE s.id = ? AND u.email = ? AND s.section_id = ?
        ");
        $stmt->bind_param("isi", $student_id, $guardian_email, $adviser_info['section_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Guardian unlinked successfully!";
        } else {
            $_SESSION['error_message'] = "Error unlinking guardian.";
        }
        header('Location: students.php');
        exit;
    }
}

// Process GET requests
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['delete_student'])) {
        // Delete student (soft delete by setting status to inactive)
        $student_id = intval($_GET['delete_student']);
        
        $stmt = $conn->prepare("
            UPDATE students 
            SET status = 'inactive', updated_at = NOW() 
            WHERE id = ? AND section_id = ?
        ");
        $stmt->bind_param("ii", $student_id, $adviser_info['section_id']);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Student marked as inactive!";
        } else {
            $_SESSION['error_message'] = "Error deleting student.";
        }
        header('Location: students.php');
        exit;
    }
}

// Get students in adviser's section
$students = [];
if ($adviser_info) {
    $stmt = $conn->prepare("
        SELECT 
            s.*,
            sec.section_name,
            sec.grade_level,
            COUNT(c.id) as complaint_count,
            MAX(c.created_at) as last_complaint_date,
            GROUP_CONCAT(
                CONCAT(g.relationship, ': ', u.full_name, ' (', u.email, ')') 
                SEPARATOR ';;'
            ) as guardians_info
        FROM students s
        JOIN sections sec ON s.section_id = sec.id
        LEFT JOIN complaints c ON s.id = c.student_id
        LEFT JOIN student_guardians sg ON s.id = sg.student_id
        LEFT JOIN guardians g ON sg.guardian_id = g.id
        LEFT JOIN users u ON g.user_id = u.id
        WHERE sec.adviser_id = ?
        GROUP BY s.id
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->bind_param("i", $adviser_info['id']);
    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Export functionality - MUST be before any HTML output
if (isset($_GET['export']) && $_GET['export'] == 'csv' && !empty($students)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Student ID', 'First Name', 'Last Name', 'Middle Name',
        'Gender', 'Date of Birth', 'Contact Number', 'Status',
        'Guardians', 'Complaint Count', 'Last Complaint'
    ]);
    
    // Add data
    foreach ($students as $student) {
        $guardians_list = !empty($student['guardians_info']) ? 
            str_replace(';;', ', ', $student['guardians_info']) : 'None';
        
        fputcsv($output, [
            $student['student_id'],
            $student['first_name'],
            $student['last_name'],
            $student['middle_name'] ?? '',
            $student['gender'] ?? '',
            $student['dob'] ? date('m/d/Y', strtotime($student['dob'])) : '',
            $student['contact_number'] ?? '',
            $student['status'],
            $guardians_list,
            $student['complaint_count'],
            $student['last_complaint_date'] ? date('m/d/Y', strtotime($student['last_complaint_date'])) : ''
        ]);
    }
    
    fclose($output);
    exit;
}

// Get available guardians for linking
$guardians = [];
$stmt = $conn->prepare("
    SELECT g.id, u.full_name, u.email, u.phone
    FROM guardians g
    JOIN users u ON g.user_id = u.id
    WHERE u.is_active = 1 AND u.is_approved = 1
    ORDER BY u.full_name
");
$stmt->execute();
$guardians = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <link rel="stylesheet" href="../utils/css/students.css">
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
            <!-- Status Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($_SESSION['success_message']); ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Management Header -->
            <div class="management-header">
                <div>
                    <h1>My Students</h1>
                    <p class="subtitle">Manage students in Grade <?= htmlspecialchars($adviser_info['grade_level'] ?? '') ?> - <?= htmlspecialchars($adviser_info['section_name'] ?? '') ?></p>
                </div>
                <div class="action-buttons-top">
                    <button class="btn btn-primary" onclick="openAddStudentModal()">
                        <i class="fas fa-user-plus"></i> Add Student
                    </button>
                    <a href="?export=csv" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
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
                        $active_students = count(array_filter($students, function($s) {
                            return $s['status'] === 'active';
                        }));
                        ?>
                        <div class="stat-number"><?= $active_students ?></div>
                        <div class="stat-label">Active Students</div>
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
                        $students_with_guardians = count(array_filter($students, function($s) {
                            return !empty($s['guardians_info']);
                        }));
                        ?>
                        <div class="stat-number"><?= $students_with_guardians ?></div>
                        <div class="stat-label">With Guardians</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search Box -->
            <div class="search-box">
                <input type="text" id="searchInput" class="search-input" placeholder="Search students by name, ID, or guardian...">
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h3>No students in your section yet</h3>
                    <p>Add students to your section using the "Add Student" button above.</p>
                    <button class="btn btn-primary" onclick="openAddStudentModal()" style="margin-top: 20px;">
                        <i class="fas fa-user-plus"></i> Add Your First Student
                    </button>
                </div>
            <?php else: ?>
                <div class="students-grid" id="studentsGrid">
                    <?php foreach ($students as $student): 
                        $guardians_list = !empty($student['guardians_info']) ? explode(';;', $student['guardians_info']) : [];
                    ?>
                        <div class="student-card" data-search="<?= strtolower(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['student_id'])) ?>">
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
                            
                            <!-- Guardians List -->
                            <?php if (!empty($guardians_list)): ?>
                                <div class="guardians-list">
                                    <div style="font-size: var(--fs-xsmall); color: var(--clr-muted); margin-bottom: 8px; font-weight: 600;">
                                        <i class="fas fa-user-shield"></i> Linked Guardians:
                                    </div>
                                    <?php foreach ($guardians_list as $guardian_info): 
                                        list($relationship, $guardian_full) = explode(': ', $guardian_info, 2);
                                        list($guardian_name, $guardian_email) = explode(' (', $guardian_full);
                                        $guardian_email = rtrim($guardian_email, ')');
                                    ?>
                                        <div class="guardian-item">
                                            <div class="guardian-info">
                                                <span class="guardian-relationship"><?= htmlspecialchars($relationship) ?>:</span>
                                                <span class="guardian-name"> <?= htmlspecialchars($guardian_name) ?></span><br>
                                                <span class="guardian-email"><?= htmlspecialchars($guardian_email) ?></span>
                                            </div>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                                                <input type="hidden" name="guardian_email" value="<?= htmlspecialchars($guardian_email) ?>">
                                                <button type="submit" name="unlink_guardian" class="unlink-btn" onclick="return confirm('Are you sure you want to unlink <?= htmlspecialchars($guardian_name) ?> from this student?')">
                                                    <i class="fas fa-unlink"></i> Unlink
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="guardians-list" style="text-align: center; padding: 10px;">
                                    <small style="color: var(--clr-muted);">
                                        <i class="fas fa-exclamation-circle"></i> No guardians linked
                                    </small>
                                </div>
                            <?php endif; ?>
                            
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
                                <button class="btn-small btn-primary" onclick="openLinkGuardianModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>')">
                                    <i class="fas fa-link"></i> Link Guardian
                                </button>
                                <button class="btn-small btn-secondary" onclick="openEditStudentModal(<?= htmlspecialchars(json_encode($student)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Student</h3>
                <span class="close-modal" onclick="closeModal('addStudentModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Student ID (Optional)</label>
                        <input type="text" name="student_id" class="form-control" placeholder="Auto-generate if empty">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="contact_number" class="form-control" placeholder="09XXXXXXXXX">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes about the student..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Student</h3>
                <span class="close-modal" onclick="closeModal('editStudentModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="student_id" id="edit_student_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middle_name" id="edit_middle_name" class="form-control">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gender *</label>
                        <select name="gender" id="edit_gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="edit_dob" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Student ID</label>
                        <input type="text" id="edit_student_id_display" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="tel" name="contact_number" id="edit_contact_number" class="form-control" placeholder="09XXXXXXXXX">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="graduated">Graduated</option>
                            <option value="transferred">Transferred</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editStudentModal')">Cancel</button>
                    <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                    <button type="button" class="btn btn-danger" onclick="deleteStudent()">Delete Student</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Link Guardian Modal -->
    <div id="linkGuardianModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Link Guardian</h3>
                <span class="close-modal" onclick="closeModal('linkGuardianModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="student_id" id="link_student_id">
                
                <div class="form-group">
                    <label class="form-label">Student</label>
                    <input type="text" id="link_student_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Guardian Email *</label>
                    <input type="email" name="guardian_email" class="form-control" required 
                           placeholder="Enter guardian's registered email">
                    <small style="color: var(--clr-muted); font-size: 0.85em;">
                        Guardian must be registered and approved in the system.
                    </small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Relationship *</label>
                        <select name="relationship" class="form-control" required>
                            <option value="">Select Relationship</option>
                            <option value="Father">Father</option>
                            <option value="Mother">Mother</option>
                            <option value="Guardian">Guardian</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Relative">Relative</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" name="primary_guardian" value="1">
                            Primary Guardian
                        </label>
                        <small style="color: var(--clr-muted); font-size: 0.85em;">
                            Primary guardians receive notifications
                        </small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('linkGuardianModal')">Cancel</button>
                    <button type="submit" name="link_guardian" class="btn btn-primary">Link Guardian</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../utils/js/sidebar.js"></script>
    <script>
        // Modal Functions
        function openAddStudentModal() {
            document.getElementById('addStudentModal').style.display = 'block';
        }
        
        function openEditStudentModal(student) {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_last_name').value = student.last_name;
            document.getElementById('edit_middle_name').value = student.middle_name || '';
            document.getElementById('edit_gender').value = student.gender || '';
            document.getElementById('edit_dob').value = student.dob || '';
            document.getElementById('edit_student_id_display').value = student.student_id;
            document.getElementById('edit_contact_number').value = student.contact_number || '';
            document.getElementById('edit_status').value = student.status || 'active';
            document.getElementById('edit_notes').value = student.notes || '';
            
            document.getElementById('editStudentModal').style.display = 'block';
        }
        
        function openLinkGuardianModal(studentId, studentName) {
            document.getElementById('link_student_id').value = studentId;
            document.getElementById('link_student_name').value = studentName;
            document.getElementById('linkGuardianModal').style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Delete student function
        function deleteStudent() {
            const studentId = document.getElementById('edit_student_id').value;
            if (confirm('Are you sure you want to delete this student? This will mark them as inactive.')) {
                window.location.href = `?delete_student=${studentId}`;
            }
        }
        
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        };
        
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
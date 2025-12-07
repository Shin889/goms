<?php
include('../includes/auth_check.php');
checkRole(['guardian']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);

// Fetch guardian info
$guardian = null;
$stmt = $conn->prepare("SELECT * FROM guardians WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guardian = $result->fetch_assoc();
    $guardian_id = $guardian ? $guardian['id'] : 0;
} else {
    // Handle prepare error
    die("Error preparing statement: " . $conn->error);
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    
    if ($student_id > 0 && $guardian_id > 0) {
        // Check if already linked
        $check_sql = "SELECT * FROM student_guardians WHERE student_id = ? AND guardian_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        
        if ($check_stmt) {
            $check_stmt->bind_param("ii", $student_id, $guardian_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'You are already linked to this student.';
                $message_type = 'warning';
            } else {
                // Link the student
                $link_sql = "INSERT INTO student_guardians (student_id, guardian_id, linked_at) VALUES (?, ?, NOW())";
                $link_stmt = $conn->prepare($link_sql);
                
                if ($link_stmt) {
                    $link_stmt->bind_param("ii", $student_id, $guardian_id);
                    
                    if ($link_stmt->execute()) {
                        $message = 'Successfully linked to student! The connection is pending admin approval.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error linking to student: ' . $link_stmt->error;
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Error preparing link statement: ' . $conn->error;
                    $message_type = 'error';
                }
            }
        } else {
            $message = 'Error preparing check statement: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid student or guardian information.';
        $message_type = 'error';
    }
}

// Fetch all students for selection
$students = [];
$students_result = $conn->query("
    SELECT s.id, s.first_name, s.last_name, s.student_id as student_code, 
           sec.section_name, sec.grade_level
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.status = 'active'
    ORDER BY s.last_name, s.first_name
");

if ($students_result) {
    $students = $students_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch linked students
$linked_students = [];
if ($guardian_id > 0) {
    $linked_sql = "
        SELECT sg.*, s.first_name, s.last_name, s.student_id as student_code,
               sec.section_name, sec.grade_level
        FROM student_guardians sg
        JOIN students s ON sg.student_id = s.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE sg.guardian_id = ?
        ORDER BY sg.linked_at DESC
    ";
    
    $linked_stmt = $conn->prepare($linked_sql);
    if ($linked_stmt) {
        $linked_stmt->bind_param("i", $guardian_id);
        $linked_stmt->execute();
        $linked_result = $linked_stmt->get_result();
        
        if ($linked_result) {
            $linked_students = $linked_result->fetch_all(MYSQLI_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link to Student - GOMS Guardian</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .link-student-content {
            padding: 30px;
            max-width: 1000px;
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
        
        /* Message Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            border: 1px solid transparent;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: var(--clr-success-light);
            color: var(--clr-success);
            border-color: var(--clr-success);
        }
        
        .alert-warning {
            background: var(--clr-warning-light);
            color: var(--clr-warning);
            border-color: var(--clr-warning);
        }
        
        .alert-error {
            background: var(--clr-danger-light);
            color: var(--clr-danger);
            border-color: var(--clr-danger);
        }
        
        /* Cards */
        .card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }
        
        .card h3 {
            margin-top: 0;
            color: var(--clr-secondary);
            font-size: var(--fs-subheading);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--clr-border-light);
        }
        
        /* Info Box */
        .info-box {
            background: var(--clr-primary-light);
            border: 1px solid var(--clr-primary);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box h4 {
            color: var(--clr-primary);
            margin-top: 0;
            margin-bottom: 10px;
            font-size: var(--fs-normal);
        }
        
        .info-box p {
            color: var(--clr-text);
            margin: 0;
            font-size: var(--fs-small);
            line-height: 1.5;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--clr-secondary);
            font-size: var(--fs-normal);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: var(--fs-normal);
            font-family: 'Inter', sans-serif;
            background: var(--clr-surface);
            color: var(--clr-text);
            transition: all var(--time-transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 3px var(--clr-primary-light);
        }
        
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .form-help {
            margin-top: 6px;
            color: var(--clr-muted);
            font-size: var(--fs-small);
            line-height: 1.4;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            font-size: var(--fs-normal);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all var(--time-transition);
        }
        
        .btn-primary {
            background: var(--clr-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--clr-primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        /* Table Styles */
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .students-table th {
            background: var(--clr-bg-light);
            color: var(--clr-secondary);
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid var(--clr-border-light);
            font-size: var(--fs-small);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .students-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--clr-border-light);
            color: var(--clr-text);
            font-size: var(--fs-small);
        }
        
        .students-table tr:hover {
            background: var(--clr-bg-light);
        }
        
        .students-table tr:last-child td {
            border-bottom: none;
        }
        
        .student-name {
            font-weight: 600;
            color: var(--clr-text);
        }
        
        .student-details {
            color: var(--clr-muted);
            font-size: var(--fs-xsmall);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--clr-muted);
        }
        
        .empty-state h4 {
            color: var(--clr-secondary);
            margin-bottom: 10px;
            font-size: var(--fs-subheading);
        }
        
        .empty-state p {
            margin-bottom: 20px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        @media (max-width: 768px) {
            .link-student-content {
                padding: 20px;
            }
            
            .card {
                padding: 20px;
            }
            
            .students-table {
                display: block;
                overflow-x: auto;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
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
        
        <a href="link_student.php" class="nav-link active">
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
        
        <a href="submit_concern.php" class="nav-link">
            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
            <span class="label">Submit Concern</span>
        </a>

        <a href="../auth/logout.php" class="logout-link">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>

    <!-- Main Content -->
    <main class="content" id="mainContent">
        <div class="link-student-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Link to Student</h1>
                <p class="subtitle">Connect your guardian account to your child's student profile</p>
            </div>

            <!-- Message Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : ($message_type == 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Link Student Form -->
            <div class="card">
                <h3><i class="fas fa-link"></i> Link Your Account</h3>
                
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Important Information</h4>
                    <p>
                        After linking your account to a student, the connection will need to be verified 
                        and approved by an administrator. Once approved, you'll be able to view your 
                        child's appointments, request counseling sessions, and submit concerns.
                    </p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="student_id">
                            <i class="fas fa-user-graduate"></i> Select Student
                        </label>
                        <select id="student_id" name="student_id" class="form-control" required>
                            <option value="">-- Choose your child from the list --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                    (ID: <?= htmlspecialchars($student['student_code']) ?>)
                                    <?php if ($student['section_name']): ?>
                                        - Grade <?= htmlspecialchars($student['grade_level']) ?> - <?= htmlspecialchars($student['section_name']) ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">
                            Select the student you are a guardian of. You can link to multiple students if needed.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-link"></i> Link Student
                    </button>
                </form>
            </div>

            <!-- Linked Students List -->
            <div class="card">
                <h3><i class="fas fa-users"></i> Your Linked Students</h3>
                
                <?php if (empty($linked_students)): ?>
                    <div class="empty-state">
                        <h4>No students linked yet</h4>
                        <p>Use the form above to link your account to your child's student profile.</p>
                    </div>
                <?php else: ?>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Grade & Section</th>
                                <th>Linked Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linked_students as $linked): ?>
                                <tr>
                                    <td>
                                        <div class="student-name">
                                            <?= htmlspecialchars($linked['first_name'] . ' ' . $linked['last_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($linked['student_code']) ?>
                                    </td>
                                    <td>
                                        <?php if ($linked['section_name']): ?>
                                            Grade <?= htmlspecialchars($linked['grade_level']) ?> - 
                                            <?= htmlspecialchars($linked['section_name']) ?>
                                        <?php else: ?>
                                            <span style="color: var(--clr-muted);">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($linked['linked_at'])): ?>
                                            <?= date('M d, Y', strtotime($linked['linked_at'])) ?>
                                            <br>
                                            <small style="color: var(--clr-muted);">
                                                <?= date('h:i A', strtotime($linked['linked_at'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <span style="color: var(--clr-muted);">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Check if there's an approval status field
                                        $status = isset($linked['status']) ? $linked['status'] : 'pending';
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        switch($status) {
                                            case 'approved':
                                                $status_class = 'success';
                                                $status_text = 'Approved';
                                                break;
                                            case 'rejected':
                                                $status_class = 'danger';
                                                $status_text = 'Rejected';
                                                break;
                                            default:
                                                $status_class = 'warning';
                                                $status_text = 'Pending';
                                        }
                                        ?>
                                        <span style="
                                            padding: 4px 12px;
                                            border-radius: 20px;
                                            font-size: var(--fs-xsmall);
                                            font-weight: 600;
                                            background: var(--clr-<?= $status_class ?>-light);
                                            color: var(--clr-<?= $status_class ?>);
                                        ">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Additional Information Card -->
            <div class="card">
                <h3><i class="fas fa-question-circle"></i> How It Works</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                            <i class="fas fa-search"></i> Step 1: Select Student
                        </h4>
                        <p style="color: var(--clr-text); font-size: var(--fs-small);">
                            Choose your child from the list of active students. Make sure you select the correct student.
                        </p>
                    </div>
                    <div>
                        <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                            <i class="fas fa-link"></i> Step 2: Link Account
                        </h4>
                        <p style="color: var(--clr-text); font-size: var(--fs-small);">
                            Submit the link request. The system will create a connection between your guardian account and the student.
                        </p>
                    </div>
                    <div>
                        <h4 style="color: var(--clr-primary); margin-top: 0; font-size: var(--fs-normal);">
                            <i class="fas fa-user-check"></i> Step 3: Await Approval
                        </h4>
                        <p style="color: var(--clr-text); font-size: var(--fs-small);">
                            An administrator will verify and approve the connection. You'll receive notification when approved.
                        </p>
                    </div>
                </div>
            </div>
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
            
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const studentSelect = document.getElementById('student_id');
                    if (!studentSelect.value) {
                        e.preventDefault();
                        alert('Please select a student to link.');
                        studentSelect.focus();
                    }
                });
            }
        });
    </script>
</body>
</html>
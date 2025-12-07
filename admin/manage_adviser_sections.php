<?php
include('../includes/auth_check.php');
checkRole(['admin']);
include('../config/db.php');

// Handle form submission for updating adviser section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_adviser_section'])) {
    $adviser_id = intval($_POST['adviser_id']);
    $section_id = intval($_POST['section_id']);
    
    // Update adviser's section in sections table
    $stmt = $conn->prepare("UPDATE sections SET adviser_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $adviser_id, $section_id);
    
    if ($stmt->execute()) {
        // Clear previous adviser assignment from other sections
        $stmt2 = $conn->prepare("UPDATE sections SET adviser_id = NULL WHERE adviser_id = ? AND id != ?");
        $stmt2->bind_param("ii", $adviser_id, $section_id);
        $stmt2->execute();
        
        // Log the action
        logAction($_SESSION['user_id'], 'UPDATE', "Updated adviser section assignment", 'sections', $section_id);
        
        $_SESSION['success_message'] = "Section assignment updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating section: " . $stmt->error;
    }
    
    header('Location: manage_adviser_sections.php');
    exit;
}

// Get all advisers with their current sections
$advisers_result = $conn->query("
    SELECT 
        a.id as adviser_id,
        a.user_id,
        a.subject_specialization,
        a.years_of_service,
        u.full_name,
        u.username,
        u.email,
        u.phone,
        s.id as section_id,
        s.section_code,
        s.section_name,
        s.level,
        s.grade_level
    FROM advisers a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN sections s ON s.adviser_id = a.id
    WHERE u.is_active = 1 AND u.is_approved = 1
    ORDER BY u.full_name ASC
");

// Get all available sections (for dropdown)
$sections_result = $conn->query("
    SELECT id, section_code, section_name, level, grade_level, adviser_id
    FROM sections 
    WHERE academic_year = '2024-2025'
    ORDER BY level, grade_level, section_name
");

// Get sections without advisers
$available_sections_result = $conn->query("
    SELECT id, section_code, section_name, level, grade_level
    FROM sections 
    WHERE adviser_id IS NULL 
    AND academic_year = '2024-2025'
    ORDER BY level, grade_level, section_name
");

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Adviser Sections</title>
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard_layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        h2.page-title {
            font-size: var(--fs-heading);
            color: var(--clr-primary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        p.page-subtitle {
            color: var(--clr-muted);
            font-size: var(--fs-small);
            margin-bottom: 25px;
        }

        /* Message Styles */
        .message {
            padding: 12px 20px;
            margin-bottom: 25px;
            border-radius: var(--radius-md);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            background-color: var(--clr-success-light);
            color: var(--clr-success);
            border: 1px solid var(--clr-success);
        }
        
        .error-message {
            background-color: var(--clr-error-light);
            color: var(--clr-error);
            border: 1px solid var(--clr-error);
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 25px;
        }
        
        .card h3 {
            font-size: var(--fs-subheading);
            color: var(--clr-secondary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h3 i {
            color: var(--clr-primary);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--clr-primary);
            margin-bottom: 10px;
        }
        
        .stat-description {
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }

        /* Advisers Table */
        .table-container {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--clr-border-radius-lg);
            padding: 25px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--fs-normal);
        }
        
        th, td {
            padding: 15px 14px;
            border-bottom: 1px solid var(--clr-border-light);
            text-align: left;
            vertical-align: middle;
        }
        
        th {
            background: var(--clr-bg-light);
            color: var(--clr-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: var(--fs-xsmall);
            white-space: nowrap;
        }
        
        tr:hover {
            background: var(--clr-hover);
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            font-size: var(--fs-small);
            color: var(--clr-muted);
            margin-bottom: 6px;
            font-weight: 500;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: var(--fs-normal);
            background: white;
            color: var(--clr-text);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--clr-primary);
            box-shadow: 0 0 0 2px var(--clr-accent);
        }
        
        .btn-primary {
            background: var(--clr-primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--time-transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: var(--clr-secondary);
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: var(--fs-xsmall);
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .badge-section {
            background: var(--clr-primary-light);
            color: var(--clr-primary);
        }
        
        .badge-level {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: var(--fs-xxsmall);
            font-weight: 500;
        }
        
        .badge-junior {
            background: #dbeafe;
            color: #1d4ed8;
        }
        
        .badge-senior {
            background: #f0f9ff;
            color: #0369a1;
        }
        
        .badge-none {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        /* Adviser Info */
        .adviser-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .adviser-name {
            font-weight: 600;
            color: var(--clr-text);
        }
        
        .adviser-details {
            font-size: var(--fs-xsmall);
            color: var(--clr-muted);
        }
        
        /* Update Form */
        .update-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .form-select-small {
            width: 200px;
            padding: 8px 12px;
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            font-size: var(--fs-normal);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--clr-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Available Sections Grid */
        .sections-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .section-card {
            background: var(--clr-surface);
            border: 1px solid var(--clr-border);
            border-radius: var(--radius-md);
            padding: 15px;
            transition: all var(--time-transition);
        }
        
        .section-card:hover {
            border-color: var(--clr-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .section-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .section-code {
            font-weight: 600;
            color: var(--clr-primary);
            font-size: var(--fs-normal);
        }
        
        .section-name {
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--clr-text);
        }
        
        .section-details {
            font-size: var(--fs-xsmall);
            color: var(--clr-muted);
            display: flex;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: var(--fs-xsmall);
            }
            
            .update-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-select-small {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h2 class="page-title">Manage Adviser Sections</h2>
        <p class="page-subtitle">Assign and update section assignments for advisers. Sections are organized by Junior/Senior levels.</p>
        
        <?php if ($success_message): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Dashboard Stats -->
        <div class="dashboard-cards">
            <div class="card">
                <h3><i class="fas fa-chalkboard-teacher"></i> Assigned Advisers</h3>
                <div class="stat-number">
                    <?= mysqli_num_rows($advisers_result); ?>
                </div>
                <div class="stat-description">
                    Total advisers with active accounts
                </div>
            </div>
            
            <?php 
            $available_sections = $available_sections_result->fetch_all(MYSQLI_ASSOC);
            $available_count = count($available_sections);
            ?>
            <div class="card">
                <h3><i class="fas fa-door-open"></i> Available Sections</h3>
                <div class="stat-number"><?= $available_count; ?></div>
                <div class="stat-description">
                    Sections without assigned advisers
                </div>
            </div>
            
            <?php 
            $assigned_sections = $conn->query("SELECT COUNT(*) as count FROM sections WHERE adviser_id IS NOT NULL");
            $assigned_count = $assigned_sections->fetch_assoc()['count'];
            ?>
            <div class="card">
                <h3><i class="fas fa-link"></i> Assigned Sections</h3>
                <div class="stat-number"><?= $assigned_count; ?></div>
                <div class="stat-description">
                    Sections with assigned advisers
                </div>
            </div>
        </div>
        
        <!-- Advisers Table -->
        <div class="table-container">
            <h3 style="font-size: var(--fs-subheading); color: var(--clr-secondary); margin-bottom: 20px;">
                <i class="fas fa-users"></i> Adviser Section Assignments
            </h3>
            
            <?php if ($advisers_result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Adviser</th>
                            <th>Contact Information</th>
                            <th>Current Section</th>
                            <th>Update Section</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Reset pointer and fetch all advisers
                        $advisers_result->data_seek(0);
                        while ($adviser = $advisers_result->fetch_assoc()): 
                            $current_section_id = $adviser['section_id'];
                        ?>
                            <tr>
                                <td>
                                    <div class="adviser-info">
                                        <div class="adviser-name"><?= htmlspecialchars($adviser['full_name']); ?></div>
                                        <div class="adviser-details">
                                            @<?= htmlspecialchars($adviser['username']); ?>
                                            <?php if ($adviser['subject_specialization']): ?>
                                                · <?= htmlspecialchars($adviser['subject_specialization']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="adviser-details">
                                        <?php if ($adviser['email']): ?>
                                            <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($adviser['email']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($adviser['phone']): ?>
                                            <div><i class="fas fa-phone"></i> <?= htmlspecialchars($adviser['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($adviser['section_id']): ?>
                                        <div>
                                            <span class="badge badge-section"><?= htmlspecialchars($adviser['section_name']); ?></span>
                                            <div style="margin-top: 5px;">
                                                <span class="badge badge-level badge-<?= strtolower($adviser['level']); ?>">
                                                    <?= htmlspecialchars($adviser['level']); ?> (Grade <?= $adviser['grade_level']; ?>)
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-none">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="" class="update-form" onsubmit="return confirm('Update section assignment for <?= htmlspecialchars(addslashes($adviser['full_name'])); ?>?');">
                                        <input type="hidden" name="adviser_id" value="<?= $adviser['adviser_id']; ?>">
                                        <input type="hidden" name="update_adviser_section" value="1">
                                        
                                        <div class="form-group" style="flex: 1;">
                                            <select name="section_id" class="form-select-small" required>
                                                <option value="">-- Select Section --</option>
                                                <optgroup label="Junior High School">
                                                    <?php 
                                                    // Reset sections pointer
                                                    $sections_result->data_seek(0);
                                                    while ($section = $sections_result->fetch_assoc()):
                                                        if ($section['level'] == 'Junior'): ?>
                                                        <option value="<?= $section['id']; ?>" 
                                                                data-level="<?= $section['level']; ?>"
                                                                <?= ($current_section_id == $section['id']) ? 'selected' : ''; ?>>
                                                            <?= htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                                        </option>
                                                    <?php endif; endwhile; ?>
                                                </optgroup>
                                                
                                                <optgroup label="Senior High School">
                                                    <?php 
                                                    $sections_result->data_seek(0);
                                                    while ($section = $sections_result->fetch_assoc()):
                                                        if ($section['level'] == 'Senior'): ?>
                                                        <option value="<?= $section['id']; ?>" 
                                                                data-level="<?= $section['level']; ?>"
                                                                <?= ($current_section_id == $section['id']) ? 'selected' : ''; ?>>
                                                            <?= htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                                        </option>
                                                    <?php endif; endwhile; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" class="btn-primary">
                                            <i class="fas fa-save"></i> Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Advisers Found</h3>
                    <p>There are no active advisers in the system. Advisers need to register and be approved first.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Available Sections -->
        <div class="table-container">
            <h3 style="font-size: var(--fs-subheading); color: var(--clr-secondary); margin-bottom: 20px;">
                <i class="fas fa-door-open"></i> Available Sections (No Adviser Assigned)
            </h3>
            
            <?php if ($available_count > 0): ?>
                <div class="sections-grid">
                    <?php foreach ($available_sections as $section): ?>
                        <div class="section-card">
                            <div class="section-card-header">
                                <span class="section-code"><?= htmlspecialchars($section['section_code']); ?></span>
                                <span class="badge badge-level badge-<?= strtolower($section['level']); ?>">
                                    <?= htmlspecialchars($section['level']); ?>
                                </span>
                            </div>
                            <div class="section-name"><?= htmlspecialchars($section['section_name']); ?></div>
                            <div class="section-details">
                                <span>Grade <?= htmlspecialchars($section['grade_level']); ?></span>
                                <span><?= htmlspecialchars($section['level']); ?> High School</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>All Sections Assigned</h3>
                    <p>Great! All sections currently have advisers assigned to them.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter sections by level when selecting from dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const levelFilters = document.querySelectorAll('.level-filter');
            const sectionCards = document.querySelectorAll('.section-card');
            
            levelFilters.forEach(filter => {
                filter.addEventListener('change', function() {
                    const selectedLevel = this.value;
                    
                    sectionCards.forEach(card => {
                        const cardLevel = card.querySelector('.badge-level').textContent.toLowerCase();
                        
                        if (selectedLevel === 'all' || cardLevel.includes(selectedLevel)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            });
            
            // Add confirmation for form submission
            const updateForms = document.querySelectorAll('.update-form');
            updateForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const sectionSelect = this.querySelector('select[name="section_id"]');
                    if (sectionSelect.value === '') {
                        e.preventDefault();
                        alert('Please select a section.');
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>
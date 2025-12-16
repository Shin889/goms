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
    <link rel="stylesheet" href="../utils/css/manage_adviser_sections.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
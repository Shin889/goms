<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// Get complaint details
$stmt = $conn->prepare("
    SELECT c.*, s.first_name, s.last_name 
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    WHERE c.id = ? AND c.created_by_user_id = ? AND c.status = 'new'
");
$stmt->bind_param("ii", $complaint_id, $user_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header('Location: complaints.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'];
    $content = trim($_POST['content']);
    $urgency_level = $_POST['urgency_level'];
    
    // Validation
    if (empty($content) || empty($category)) {
        $error = 'Please fill all required fields.';
    } elseif (strlen($content) < 10) {
        $error = 'Complaint content must be at least 10 characters.';
    } else {
        // Update complaint
        $stmt = $conn->prepare("
            UPDATE complaints 
            SET category = ?, content = ?, urgency_level = ?, updated_at = NOW()
            WHERE id = ? AND created_by_user_id = ?
        ");
        $stmt->bind_param("sssii", $category, $content, $urgency_level, $complaint_id, $user_id);
        
        if ($stmt->execute()) {
            // Log audit
            $audit_stmt = $conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action, action_summary, target_table, target_id, created_at)
                VALUES (?, 'UPDATE', 'Updated complaint details', 'complaints', ?, NOW())
            ");
            $audit_stmt->bind_param("ii", $user_id, $complaint_id);
            $audit_stmt->execute();
            
            $success = 'Complaint updated successfully!';
        } else {
            $error = 'Failed to update complaint: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Complaint - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
        }
        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .student-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    
    <div class="container">
        <div class="page-header">
            <h1>Edit Complaint</h1>
            <p class="subtitle">Update complaint details for <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?></p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="student-info">
            <strong>Student:</strong> <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?>
            <br>
            <strong>Created:</strong> <?= date('F j, Y h:i A', strtotime($complaint['created_at'])) ?>
        </div>

        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control" required>
                        <option value="academic" <?= $complaint['category'] == 'academic' ? 'selected' : '' ?>>Academic</option>
                        <option value="behavioral" <?= $complaint['category'] == 'behavioral' ? 'selected' : '' ?>>Behavioral</option>
                        <option value="emotional" <?= $complaint['category'] == 'emotional' ? 'selected' : '' ?>>Emotional</option>
                        <option value="social" <?= $complaint['category'] == 'social' ? 'selected' : '' ?>>Social</option>
                        <option value="career" <?= $complaint['category'] == 'career' ? 'selected' : '' ?>>Career</option>
                        <option value="family" <?= $complaint['category'] == 'family' ? 'selected' : '' ?>>Family</option>
                        <option value="other" <?= $complaint['category'] == 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Urgency Level</label>
                    <select name="urgency_level" class="form-control" required>
                        <option value="low" <?= $complaint['urgency_level'] == 'low' ? 'selected' : '' ?>>Low Priority</option>
                        <option value="medium" <?= $complaint['urgency_level'] == 'medium' ? 'selected' : '' ?>>Medium Priority</option>
                        <option value="high" <?= $complaint['urgency_level'] == 'high' ? 'selected' : '' ?>>High Priority</option>
                        <option value="critical" <?= $complaint['urgency_level'] == 'critical' ? 'selected' : '' ?>>Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Complaint Details</label>
                    <textarea name="content" class="form-control" required><?= htmlspecialchars($complaint['content']) ?></textarea>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                    <button type="submit" class="btn btn-primary">
                        <span>✓</span> Update Complaint
                    </button>
                    <a href="view_complaint.php?id=<?= $complaint_id ?>" class="btn btn-secondary">
                        <span>←</span> Cancel
                    </a>
                    <a href="complaints.php" class="btn" style="background: #f3f4f6; color: #374151;">
                        Back to List
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="../utils/js/dashboard.js"></script>
</body>
</html>
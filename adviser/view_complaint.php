<?php
include('../includes/auth_check.php');
checkRole(['adviser']);
include('../config/db.php');

$user_id = intval($_SESSION['user_id']);
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get complaint details
$complaint = null;
$stmt = $conn->prepare("
    SELECT 
        c.*,
        s.first_name,
        s.last_name,
        s.student_id as student_code,
        s.gender,
        s.dob,
        sec.section_name,
        sec.grade_level,
        u.full_name as created_by_name,
        u2.full_name as closed_by_name
    FROM complaints c
    JOIN students s ON c.student_id = s.id
    JOIN sections sec ON s.section_id = sec.id
    JOIN users u ON c.created_by_user_id = u.id
    LEFT JOIN users u2 ON c.closed_by = u2.id
    WHERE c.id = ? AND c.created_by_user_id = ?
");
$stmt->bind_param("ii", $complaint_id, $user_id);
$stmt->execute();
$complaint = $stmt->get_result()->fetch_assoc();

if (!$complaint) {
    header('Location: complaints.php');
    exit;
}

// Get referrals for this complaint
$referrals = [];
$stmt = $conn->prepare("
    SELECT 
        r.*,
        cr.full_name as counselor_name,
        cr.specialty as counselor_specialty
    FROM referrals r
    LEFT JOIN counselors c ON r.counselor_id = c.id
    LEFT JOIN users cr ON c.user_id = cr.id
    WHERE r.complaint_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $complaint_id);
$stmt->execute();
$referrals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Complaint - GOMS Adviser</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/dashboard.css">
    <style>
        .complaint-details {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .detail-section {
            margin-bottom: 30px;
        }
        .detail-section h3 {
            color: #1f2937;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .detail-item {
            margin-bottom: 12px;
        }
        .detail-label {
            font-weight: 500;
            color: #6b7280;
            display: block;
            margin-bottom: 4px;
        }
        .detail-value {
            color: #1f2937;
            font-weight: 500;
        }
        .complaint-content-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin-top: 10px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-new {
            background: #dbeafe;
            color: #1e40af;
        }
        .status-referred {
            background: #fef3c7;
            color: #92400e;
        }
        .status-closed {
            background: #dcfce7;
            color: #166534;
        }
        .urgency-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .urgency-critical {
            background: #fee2e2;
            color: #991b1b;
        }
        .urgency-high {
            background: #fef3c7;
            color: #92400e;
        }
        .urgency-medium {
            background: #dbeafe;
            color: #1e40af;
        }
        .urgency-low {
            background: #dcfce7;
            color: #166534;
        }
        .referrals-list {
            margin-top: 20px;
        }
        .referral-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body>
    
    <div class="container">
        <div class="page-header">
            <h1>Complaint Details</h1>
            <div class="action-buttons">
                <a href="complaints.php" class="btn btn-secondary">
                    ← Back to Complaints
                </a>
                <?php if ($complaint['status'] == 'new'): ?>
                    <a href="create_referral.php?complaint_id=<?= $complaint_id ?>" class="btn btn-success">
                        📨 Create Referral
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="complaint-details">
            <div class="detail-section">
                <h3>Student Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Student Name</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($complaint['first_name'] . ' ' . $complaint['last_name']) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Student ID</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($complaint['student_code']) ?>
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
                        <span class="detail-label">Gender</span>
                        <span class="detail-value">
                            <?= ucfirst(htmlspecialchars($complaint['gender'] ?? 'Not specified')) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <h3>Complaint Information</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="status-badge status-<?= htmlspecialchars($complaint['status']) ?>">
                            <?= ucfirst(htmlspecialchars($complaint['status'])) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">
                            <?= ucfirst(htmlspecialchars($complaint['category'])) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Urgency Level</span>
                        <span class="urgency-badge urgency-<?= htmlspecialchars($complaint['urgency_level']) ?>">
                            <?= ucfirst(htmlspecialchars($complaint['urgency_level'])) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created By</span>
                        <span class="detail-value">
                            <?= htmlspecialchars($complaint['created_by_name']) ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created Date</span>
                        <span class="detail-value">
                            <?= date('F j, Y h:i A', strtotime($complaint['created_at'])) ?>
                        </span>
                    </div>
                    <?php if ($complaint['closed_at']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Closed Date</span>
                            <span class="detail-value">
                                <?= date('F j, Y h:i A', strtotime($complaint['closed_at'])) ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Closed By</span>
                            <span class="detail-value">
                                <?= htmlspecialchars($complaint['closed_by_name']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-section">
                <h3>Complaint Details</h3>
                <div class="complaint-content-box">
                    <?= nl2br(htmlspecialchars($complaint['content'])) ?>
                </div>
            </div>

            <?php if (!empty($referrals)): ?>
                <div class="detail-section">
                    <h3>Referrals (<?= count($referrals) ?>)</h3>
                    <div class="referrals-list">
                        <?php foreach ($referrals as $referral): ?>
                            <div class="referral-item">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div>
                                        <strong>Status:</strong> 
                                        <span class="status-badge">
                                            <?= ucfirst(str_replace('_', ' ', $referral['status'])) ?>
                                        </span>
                                        <br>
                                        <strong>Priority:</strong> 
                                        <span class="urgency-badge urgency-<?= $referral['priority'] ?>">
                                            <?= ucfirst($referral['priority']) ?>
                                        </span>
                                        <br>
                                        <?php if ($referral['counselor_name']): ?>
                                            <strong>Counselor:</strong> <?= htmlspecialchars($referral['counselor_name']) ?>
                                            <br>
                                            <small><?= htmlspecialchars($referral['counselor_specialty']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <small>Created: <?= date('M d, Y', strtotime($referral['created_at'])) ?></small>
                                    </div>
                                </div>
                                <div style="margin-top: 10px;">
                                    <strong>Reason:</strong><br>
                                    <?= nl2br(htmlspecialchars($referral['referral_reason'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../utils/js/dashboard.js"></script>
</body>
</html>
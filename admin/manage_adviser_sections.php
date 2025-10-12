<?php
session_start();
include('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$advisers = $conn->query("SELECT a.id, a.name, a.subject, a.phone, a.section, u.username 
                          FROM advisers a
                          JOIN users u ON a.user_id = u.id
                          ORDER BY a.name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Adviser Sections</title>
    <link rel="stylesheet" href="../utils/css/root.css"> <!-- Global root vars -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            margin: 0;
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            padding: 40px;
            box-sizing: border-box;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h2.page-title {
            font-size: 1.6rem;
            color: var(--color-primary);
            font-weight: 700;
            margin-bottom: 4px;
        }

        p.page-subtitle {
            color: var(--color-muted);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }

        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            box-shadow: var(--shadow-sm);
            padding: 20px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th, td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            color: var(--color-secondary);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .badge {
            background: var(--color-primary);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .empty {
            text-align: center;
            color: var(--color-muted);
            padding: 20px 0;
        }

        a.back-link {
            display: inline-block;
            margin-bottom: 14px;
            color: var(--color-secondary);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        a.back-link:hover {
            color: var(--color-primary);
        }

        .section-input {
            padding: 6px 10px;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            background: var(--color-bg);
            color: var(--color-text);
            font-size: 0.9rem;
            width: 120px;
            transition: border-color 0.2s ease;
        }

        .section-input:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        .update-btn {
            padding: 6px 14px;
            background: var(--color-primary);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 8px;
        }

        .update-btn:hover {
            background: var(--color-primary-dark, #0056b3);
            transform: translateY(-1px);
        }

        .update-btn:active {
            transform: translateY(0);
        }

        .section-display {
            color: var(--color-text);
            font-weight: 500;
        }

        .input-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <h2 class="page-title">Manage Adviser Sections</h2>
        <p class="page-subtitle">Assign and update section assignments for advisers.</p>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Adviser Name</th>
                        <th>Username</th>
                        <th>Subject</th>
                        <th>Phone</th>
                        <th>Current Section</th>
                        <th>Set New Section</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($advisers->num_rows > 0): ?>
                        <?php while ($row = $advisers->fetch_assoc()): ?>
                            <tr id="row-<?= $row['id']; ?>">
                                <td><?= htmlspecialchars($row['name']); ?></td>
                                <td><?= htmlspecialchars($row['username']); ?></td>
                                <td><?= htmlspecialchars($row['subject']); ?></td>
                                <td><?= htmlspecialchars($row['phone']); ?></td>
                                <td class="section-display">
                                    <span class="badge"><?= htmlspecialchars($row['section']); ?></span>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="section-input"
                                               id="section-<?= $row['id']; ?>" 
                                               value="<?= htmlspecialchars($row['section']); ?>">
                                        <button class="update-btn" data-id="<?= $row['id']; ?>">Update</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty">No advisers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

   <script>
$(document).on('click', '.update-btn', function() {
    const adviserId = $(this).data('id');
    const newSection = $('#section-' + adviserId).val().trim();

    if (!newSection) {
        alert('Section cannot be empty.');
        return;
    }

    console.log('📤 Sending update request:', { adviser_id: adviserId, section: newSection });

    $.ajax({
        // ✅ IMPORTANT: absolute path to your PHP backend
        url: 'http://localhost/guidance/admin/update_section.php',
        type: 'POST',
        data: { 
            adviser_id: adviserId, 
            section: newSection 
        },
        dataType: 'json',
        success: function(response) {
            console.log('✅ Raw response:', response);

            // Make sure it's an object
            if (typeof response !== 'object') {
                try {
                    response = JSON.parse(response);
                } catch (err) {
                    console.error('❌ JSON parse error:', err, response);
                    alert('Server returned invalid JSON.');
                    return;
                }
            }

            if (response.success) {
                alert(response.message || 'Section updated successfully.');
                // Update the table instantly
                $('#row-' + adviserId + ' .section-display .badge').text(newSection);
            } else {
                alert('Error: ' + (response.message || 'Unknown server error.'));
            }
        },
        error: function(xhr, status, error) {
            console.error('🚨 AJAX Error:', { status, error });
            console.error('Response text:', xhr.responseText);
            alert('Request failed: ' + error);
        }
    });
});
</script>
<script src="../utils/js/jquery-3.6.0.min.js"></script>
</body>
</html>
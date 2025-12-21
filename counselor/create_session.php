<?php
include('../includes/auth_check.php');
checkRole(['counselor']);
include('../config/db.php');
include('../includes/functions.php');

$counselor_id = intval($_SESSION['user_id']);
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Get counselor info
$stmt = $conn->prepare("SELECT c.id as counselor_db_id, u.full_name FROM counselors c JOIN users u ON c.user_id = u.id WHERE u.id = ?");
$stmt->bind_param("i", $counselor_id);
$stmt->execute();
$result = $stmt->get_result();
$counselor = $result->fetch_assoc();
$stmt->close();

if (!$counselor) {
    die("Counselor information not found.");
}

$counselor_db_id = $counselor['counselor_db_id'];
$student_id = 0;
$appointment_info = null;
$student_info = null;
$referral_id = null;

// If coming from appointment, pre-fill data
if ($appointment_id > 0) {
    $stmt = $conn->prepare("SELECT a.*, r.id as referral_id, s.first_name, s.last_name, s.grade_level, sec.section_name FROM appointments a JOIN referrals r ON a.referral_id = r.id JOIN students s ON a.student_id = s.id LEFT JOIN sections sec ON s.section_id = sec.id WHERE a.id = ? AND a.counselor_id = ? AND a.status IN ('scheduled', 'confirmed')");
    $stmt->bind_param("ii", $appointment_id, $counselor_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment_info = $result->fetch_assoc();
    $stmt->close();
    
    if ($appointment_info) {
        $student_id = $appointment_info['student_id'];
        $referral_id = $appointment_info['referral_id'];
        $student_info = [
            'name' => $appointment_info['first_name'] . ' ' . $appointment_info['last_name'],
            'grade' => $appointment_info['grade_level'],
            'section' => $appointment_info['section_name']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $location = trim($_POST['location']);
    $mode = $_POST['mode'] ?? 'in-person';
    $notes = trim($_POST['notes_draft'] ?? '');
    $session_type = $_POST['session_type'] ?? 'regular';
    $issues_discussed = trim($_POST['issues_discussed'] ?? '');
    $follow_up_plan = trim($_POST['follow_up_plan'] ?? '');
    $follow_up_needed = isset($_POST['follow_up_needed']) ? 1 : 0;
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $status = 'in_progress';
    
    // Validate times
    if (strtotime($end) <= strtotime($start)) {
        $error = "End time must be after start time.";
    } else {
        // Set defaults
        $notes = empty($notes) ? 'Session notes pending' : $notes;
        $issues_discussed = empty($issues_discussed) ? 'General counseling discussion' : $issues_discussed;
        $follow_up_plan = empty($follow_up_plan) ? 'No specific follow-up plan' : $follow_up_plan;
        
        // Handle NULL for follow_up_date
        if (empty($follow_up_date)) {
            $follow_up_date = null;
        }
        
        // Handle appointment_id
        $appointment_id_for_db = $appointment_id > 0 ? $appointment_id : null;
        
        // SIMPLIFIED SQL - Only required columns first
        $sql = "INSERT INTO sessions (
            appointment_id, 
            counselor_id, 
            student_id, 
            start_time, 
            end_time, 
            location, 
            mode, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            // Bind minimal parameters
            $stmt->bind_param("iiisssss", 
                $appointment_id_for_db,
                $counselor_db_id,
                $student_id,
                $start,
                $end,
                $location,
                $mode,
                $status
            );
            
            if ($stmt->execute()) {
                $session_id = $stmt->insert_id;
                $stmt->close();
                
                // Now update the session with additional fields
                $update_sql = "UPDATE sessions SET 
                    notes_draft = ?,
                    session_type = ?,
                    issues_discussed = ?,
                    follow_up_needed = ?,
                    follow_up_date = ?,
                    follow_up_plan = ?
                    WHERE id = ?";
                
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param("sssisss", 
                        $notes,
                        $session_type,
                        $issues_discussed,
                        $follow_up_needed,
                        $follow_up_date,
                        $follow_up_plan,
                        $session_id
                    );
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Update appointment if from appointment
                if ($appointment_id > 0) {
                    $conn->query("UPDATE appointments SET status = 'in_session', updated_at = NOW() WHERE id = $appointment_id");
                    
                    if ($referral_id) {
                        $conn->query("UPDATE referrals SET status = 'in_session', updated_at = NOW() WHERE id = $referral_id");
                    }
                }
                
                // Log action
                logAction($counselor_id, 'Create Session', 'sessions', $session_id, "New session created");
                
                $_SESSION['success'] = "Session recorded successfully!";
                header("Location: sessions.php");
                exit;
            } else {
                $error = "Failed to create session: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get student list
$stmt = $conn->prepare("SELECT s.id, s.first_name, s.last_name, s.grade_level, sec.section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id ORDER BY s.last_name ASC, s.first_name ASC");
$stmt->execute();
$students_result = $stmt->get_result();
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Session - GOMS Counselor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../utils/css/root.css">
  <link rel="stylesheet" href="../utils/css/dashboard.css">
  <link rel="stylesheet" href="../utils/css/create_session.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <button id="sidebarToggle" class="toggle-btn">☰</button>
    <h2 class="logo">GOMS Counselor</h2>
    <div class="sidebar-user">
      Counselor · <?= htmlspecialchars($counselor['full_name']); ?>
    </div>
    <a href="dashboard.php" class="nav-link">
      <span class="icon"><i class="fas fa-home"></i></span><span class="label">Dashboard</span>
    </a>
    <a href="referrals.php" class="nav-link">
      <span class="icon"><i class="fas fa-clipboard-list"></i></span><span class="label">Referrals</span>
    </a>
    <a href="appointments.php" class="nav-link">
      <span class="icon"><i class="fas fa-calendar-alt"></i></span><span class="label">Appointments</span>
    </a>
    <a href="sessions.php" class="nav-link active">
      <span class="icon"><i class="fas fa-comments"></i></span><span class="label">Sessions</span>
    </a>
    <a href="create_report.php" class="nav-link">
      <span class="icon"><i class="fas fa-file-alt"></i></span><span class="label">Generate Report</span>
    </a>
    <a href="../auth/logout.php" class="logout-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </nav>

  <div class="page-container">
    <a href="sessions.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Sessions</a>
    
    <h2 class="page-title">Create Counseling Session</h2>
    <p class="page-subtitle">Record details of a counseling session with a student.</p>

    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <?php if ($appointment_info): ?>
    <div class="appointment-context">
      <div class="label">Starting Session from Appointment</div>
      <div class="value">
        <i class="fas fa-calendar-check"></i> 
        <?= htmlspecialchars($student_info['name']); ?> 
        (Grade <?= htmlspecialchars($student_info['grade']); ?> - <?= htmlspecialchars($student_info['section']); ?>)
      </div>
      <div class="helper-text" style="margin-top: 8px;">
        Appointment will be updated to 'in_session' status. Referral will be marked as 'in_session'.
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <form method="POST" action="" id="sessionForm">
        <!-- Hidden field for appointment_id -->
        <?php if ($appointment_id > 0): ?>
          <input type="hidden" name="appointment_id" value="<?= $appointment_id ?>">
        <?php endif; ?>
        
        <div class="form-body">
          <div class="form-row">
            <div class="form-group">
              <label class="required">Student</label>
              <select name="student_id" required <?= $appointment_info ? 'disabled' : '' ?>>
                <option value="">Select a student...</option>
                <?php foreach ($students as $s): ?>
                  <option value="<?= $s['id']; ?>" 
                    <?= ($student_id == $s['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['last_name'] . ', ' . $s['first_name'] . ' (Grade ' . $s['grade_level'] . ' - ' . ($s['section_name'] ?? 'No Section') . ')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if ($appointment_info): ?>
                <input type="hidden" name="student_id" value="<?= $student_id ?>">
                <div class="helper-text">Student is locked because this session is from an appointment.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="form-row two-col">
            <div class="form-group">
              <label class="required">Start Time</label>
              <input type="datetime-local" name="start_time" required 
                     value="<?= $appointment_info ? date('Y-m-d\TH:i', strtotime($appointment_info['start_time'])) : date('Y-m-d\TH:i') ?>">
              <div class="helper-text">When the session begins</div>
            </div>

            <div class="form-group">
              <label class="required">End Time</label>
              <input type="datetime-local" name="end_time" required 
                     value="<?= $appointment_info ? date('Y-m-d\TH:i', strtotime($appointment_info['end_time'])) : date('Y-m-d\TH:i', strtotime('+1 hour')) ?>">
              <div class="helper-text">When the session ends</div>
            </div>
          </div>

          <div class="form-row three-col">
            <div class="form-group">
              <label class="required">Location</label>
              <input type="text" name="location" placeholder="e.g., Room 101, Online, Phone" required
                     value="<?= $appointment_info ? htmlspecialchars(ucfirst($appointment_info['mode']) . ' Session') : '' ?>">
              <div class="helper-text">Where the session takes place</div>
            </div>
            
            <div class="form-group">
              <label class="required">Session Mode</label>
              <select name="mode" required>
                <option value="in-person" <?= ($appointment_info && $appointment_info['mode'] === 'in-person') ? 'selected' : '' ?>>In-person</option>
                <option value="online" <?= ($appointment_info && $appointment_info['mode'] === 'online') ? 'selected' : '' ?>>Online</option>
                <option value="phone" <?= ($appointment_info && $appointment_info['mode'] === 'phone') ? 'selected' : '' ?>>Phone</option>
              </select>
            </div>
            
            <div class="form-group">
              <label class="required">Session Type</label>
              <select name="session_type" required>
                <option value="regular" selected>Regular Session</option>
                <option value="initial">Initial Assessment</option>
                <option value="followup">Follow-up</option>
                <!-- <option value="crisis">Crisis Intervention</option> -->
                <option value="group">Group Session</option>
              </select>
            </div>
          </div>
          
          <div class="form-row two-col">
            <div class="form-group">
              <label>Follow-up Needed?</label>
              <div style="display: flex; align-items: center; gap: 10px; margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 5px;">
                  <input type="checkbox" name="follow_up_needed" value="1" id="followUpCheckbox">
                  Yes, schedule a follow-up
                </label>
              </div>
            </div>
            
            <div class="form-group">
              <label>Follow-up Date</label>
              <input type="date" name="follow_up_date" id="followUpDate" 
                     min="<?= date('Y-m-d') ?>" 
                     <?= !isset($_POST['follow_up_needed']) ? 'disabled' : '' ?>>
            </div>
          </div>

          <h3 class="section-title"><i class="fas fa-clipboard"></i> Session Content</h3>

          <div class="form-group">
            <label class="required">Session Notes</label>
            <textarea name="notes_draft" placeholder="Enter session notes, observations, discussion points, follow-up actions..." 
                      maxlength="2000"><?= $appointment_info ? htmlspecialchars($appointment_info['notes']) : '' ?></textarea>
            <div class="character-count" id="notesCount">0/2000</div>
            <div class="helper-text">Main session notes (draft that can be finalized later in a report)</div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Issues Discussed</label>
              <textarea name="issues_discussed" class="small" placeholder="List the main issues or topics discussed during the session..." maxlength="1000"></textarea>
              <div class="character-count" id="issuesCount">0/1000</div>
            </div>
          </div>

          <!-- <div class="form-row">
            <div class="form-group">
              <label>Interventions Used</label>
              <textarea name="interventions_used" class="small" placeholder="Describe counseling techniques or interventions applied..." maxlength="1000"></textarea>
              <div class="character-count" id="interventionsCount">0/1000</div>
            </div>
          </div> -->

          <div class="form-row">
            <div class="form-group">
              <label>Follow-up Plan</label>
              <textarea name="follow_up_plan" class="small" placeholder="Outline next steps, homework, or follow-up actions..." maxlength="1000"></textarea>
              <div class="character-count" id="followupCount">0/1000</div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-submit">
          <i class="fas fa-play-circle"></i> Start Session
        </button>
      </form>
    </div>
  </div>

  <script src="../utils/js/sidebar.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Handle sidebar toggle for content padding
      const sidebar = document.getElementById('sidebar');
      const pageContainer = document.querySelector('.page-container');
      
      function updateContentPadding() {
        if (sidebar.classList.contains('collapsed')) {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-collapsed-width) + 20px)';
        } else {
          pageContainer.style.paddingLeft = 'calc(var(--layout-sidebar-width) + 20px)';
        }
      }
      
      updateContentPadding();
      
      document.getElementById('sidebarToggle')?.addEventListener('click', function() {
        setTimeout(updateContentPadding, 300);
      });
      
      // Character counter functionality
      function setupCharacterCounter(textareaId, counterId, maxLength) {
        const textarea = document.querySelector(`textarea[name="${textareaId}"]`);
        if (!textarea) return;
        
        const counter = document.getElementById(counterId);
        
        function updateCounter() {
          const length = textarea.value.length;
          counter.textContent = `${length}/${maxLength}`;
          
          counter.classList.remove('warning', 'error');
          if (length > maxLength * 0.9) {
            counter.classList.add('warning');
          }
          if (length > maxLength) {
            counter.classList.add('error');
          }
        }
        
        textarea.addEventListener('input', updateCounter);
        updateCounter();
      }
      
      // Setup counters
      setupCharacterCounter('notes_draft', 'notesCount', 2000);
      setupCharacterCounter('issues_discussed', 'issuesCount', 1000);
      // setupCharacterCounter('interventions_used', 'interventionsCount', 1000);
      setupCharacterCounter('follow_up_plan', 'followupCount', 1000);
      
      // Follow-up date toggle
      const followUpCheckbox = document.getElementById('followUpCheckbox');
      const followUpDate = document.getElementById('followUpDate');
      
      if (followUpCheckbox && followUpDate) {
        followUpCheckbox.addEventListener('change', function() {
          followUpDate.disabled = !this.checked;
          if (!this.checked) {
            followUpDate.value = '';
          }
        });
      }
      
      // Form validation
      const sessionForm = document.getElementById('sessionForm');
      if (sessionForm) {
        sessionForm.addEventListener('submit', function(e) {
          const startInput = document.querySelector('input[name="start_time"]');
          const endInput = document.querySelector('input[name="end_time"]');
          
          if (startInput && endInput) {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            
            if (start >= end) {
              e.preventDefault();
              alert('End time must be after start time.');
              startInput.focus();
              return false;
            }
          }
          
          return true;
        });
      }
    });
  </script>
</body>
</html>
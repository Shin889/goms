<?php
include('../config/db.php');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone'] ?? '');
    $phone = empty($phone) ? null : $phone;
    $level = isset($_POST['level']) ? trim($_POST['level']) : null;
    
    // Basic validation
    if (empty($username) || empty($password) || empty($role) || empty($full_name)) {
        $error_message = "Please fill in all required fields.";
    } elseif ($role === 'counselor' && empty($level)) {
        $error_message = "Please select counselor level (Junior or Senior).";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error_message = "Username already exists. Please choose a different username.";
        } else {
            // Check if email already exists (if provided)
            if (!empty($email)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND email != ''");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $error_message = "Email already registered. Please use a different email.";
                }
            }
            
            // Check if phone already exists (if provided)
            if (empty($error_message) && !empty($phone)) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ? AND phone != ''");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $error_message = "Phone number already registered. Please use a different phone number.";
                }
            }
            
            if (empty($error_message)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert user with default inactive/unapproved status
                    $stmt = $conn->prepare("
                        INSERT INTO users 
                        (username, email, password, role, full_name, phone, is_active, is_approved) 
                        VALUES (?, ?, ?, ?, ?, ?, 0, 0)
                    ");
                    $stmt->bind_param("ssssss", $username, $email, $hashed_password, $role, $full_name, $phone);
                    
                    if (!$stmt->execute()) {
                        throw new Exception("User registration error: " . $stmt->error);
                    }
                    
                    $user_id = $stmt->insert_id;
                    
                    // If user is counselor, insert into counselors table with level
                    if ($role === 'counselor') {
                        // Determine levels based on level
                        $handles_level = $level === 'Senior' ? 'Senior High Counseling' : 'Junior High Counseling';
                        
                        $stmt2 = $conn->prepare("
                            INSERT INTO counselors 
                            (user_id, handles_level, license_number, years_of_experience, max_caseload) 
                            VALUES (?, ?, NULL, NULL, 30)
                        ");
                        $stmt2->bind_param("is", $user_id, $handles_level);
                        
                        if (!$stmt2->execute()) {
                            throw new Exception("Counselor profile creation error: " . $stmt2->error);
                        }
                        $stmt2->close();
                    }
                    
                    // If user is guardian, insert into guardians table with default relationship
                    if ($role === 'guardian') {
                        $stmt3 = $conn->prepare("
                            INSERT INTO guardians 
                            (user_id, relationship) 
                            VALUES (?, 'Parent')  -- Default relationship
                        ");
                        $stmt3->bind_param("i", $user_id);
                        
                        if (!$stmt3->execute()) {
                            throw new Exception("Guardian profile creation error: " . $stmt3->error);
                        }
                        $stmt3->close();
                    }
                    
                    // Log registration action
                    include('../includes/functions.php');
                    $log_summary = $role === 'counselor' 
                        ? "User registered as {$role} ({$level})" 
                        : "User registered as {$role}";
                    logAction($user_id, 'REGISTER', $log_summary, 'users', $user_id);
                    
                    // Commit transaction
                    $conn->commit();
                    
                    $success_message = 'Registration successful! Please wait for admin approval. You will receive a notification once your account is approved.';
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error_message = $e->getMessage();
                }
            }
        }
        if (isset($stmt)) $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | GOMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../utils/css/root.css"> 
    <link rel="stylesheet" href="../utils/css/register.css"> 
    <style>
        .level-select-wrapper {
            display: none;
            margin-bottom: 15px;
        }
        
        .level-select-wrapper.visible {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <img src="../utils/images/cnhslogo.png" alt="CNHS Logo" class="logo-img" style="max-width: 80px; height: auto; margin-bottom: 15px;">

        <h2>Create an Account</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <input type="text" name="full_name" placeholder="Full Name *" required autocomplete="name">
            <input type="text" name="username" placeholder="Username *" required autocomplete="username">
            <input type="email" name="email" placeholder="Email (optional)" autocomplete="email">
            <input type="text" name="phone" placeholder="Phone Number (optional)" autocomplete="tel">
            <input type="password" name="password" placeholder="Password (min. 6 characters) *" required autocomplete="new-password" minlength="6">
            
            <div class="select-wrapper">
                <select name="role" id="roleSelect" required>
                    <option value="" disabled selected>Select Role *</option>
                    <option value="guardian">Guardian</option>
                    <option value="adviser">Adviser (Teacher)</option>
                    <option value="counselor">Counselor</option>
                </select>
            </div>

            <div class="select-wrapper level-select-wrapper" id="levelSelectWrapper">
                <select name="level" id="levelSelect">
                    <option value="" disabled selected>Select Counselor Level *</option>
                    <option value="Junior">Junior High Counselor</option>
                    <option value="Senior">Senior High Counselor</option>
                </select>
            </div>

            <button type="submit">
                <i class="fas fa-user-plus"></i> Register Account
            </button>
        </form>

        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Login
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('roleSelect');
            const levelSelectWrapper = document.getElementById('levelSelectWrapper');
            const levelSelect = document.getElementById('levelSelect');
            
            // Function to toggle level dropdown
            function toggleLevelDropdown() {
                if (roleSelect.value === 'counselor') {
                    levelSelectWrapper.classList.add('visible');
                    levelSelect.required = true;
                } else {
                    levelSelectWrapper.classList.remove('visible');
                    levelSelect.required = false;
                    levelSelect.value = '';
                }
            }
            
            // Initial check
            toggleLevelDropdown();
            
            // Add event listener for role change
            roleSelect.addEventListener('change', toggleLevelDropdown);
            
            // Form validation
            const form = document.getElementById('registerForm');
            form.addEventListener('submit', function(event) {
                if (roleSelect.value === 'counselor' && levelSelect.value === '') {
                    event.preventDefault();
                    alert('Please select counselor level (Junior or Senior).');
                    levelSelect.focus();
                }
            });
        });
    </script>
</body>
</html>
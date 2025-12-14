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
    $phone = trim($_POST['phone']);
    
    // Basic validation
    if (empty($username) || empty($password) || empty($role) || empty($full_name)) {
        $error_message = "Please fill in all required fields.";
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
                
                // Insert user with default inactive/unapproved status
                $stmt = $conn->prepare("
                    INSERT INTO users 
                    (username, email, password, role, full_name, phone, is_active, is_approved) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0)
                ");
                $stmt->bind_param("ssssss", $username, $email, $hashed_password, $role, $full_name, $phone);

                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    
                    // Log registration action
                    include('../includes/functions.php');
                    logAction($user_id, 'REGISTER', "User registered as {$role}", 'users', $user_id);
                    
                    $success_message = 'Registration successful! Please wait for admin approval. You will receive a notification once your account is approved.';
                } else {
                    $error_message = "Registration error: " . $stmt->error;
                }
            }
        }
        $stmt->close();
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
    <link rel="stylesheet" href="../utils/css/root.css"> 
    <link rel="stylesheet" href="../utils/css/register.css"> 
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

        <form method="POST" action="">
            <input type="text" name="full_name" placeholder="Full Name *" required autocomplete="name">
            <input type="text" name="username" placeholder="Username *" required autocomplete="username">
            <input type="email" name="email" placeholder="Email (optional)" autocomplete="email">
            <input type="text" name="phone" placeholder="Phone Number (optional)" autocomplete="tel">
            <input type="password" name="password" placeholder="Password (min. 6 characters) *" required autocomplete="new-password" minlength="6">
            
            <div class="select-wrapper">
                <select name="role" required>
                    <option value="" disabled selected>Select Role *</option>
                    <option value="guardian">Guardian</option>
                    <option value="adviser">Adviser (Teacher)</option>
                    <option value="counselor">Counselor</option>
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
</body>
</html>
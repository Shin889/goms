<?php
// auth/register.php
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

    <style>
        body {
            margin: 0;
            height: 100vh;
            background: var(--clr-bg); 
            color: var(--clr-text); 
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px; 
            font-family: var(--font-family);
        }

        .register-container {
            background: var(--clr-surface); 
            box-shadow: var(--shadow-md);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 480px; 
            padding: 40px 30px;
            text-align: center;
            border: 1px solid var(--clr-border);
            transition: border-color var(--time-transition);
        }
        
        .register-container:hover {
            border-color: var(--clr-primary);
        }

        h2 {
            color: var(--clr-primary); 
            font-size: var(--fs-heading); 
            margin-bottom: 25px;
            font-weight: 700;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 18px; 
        }

        input, select {
            width: 100%; 
            box-sizing: border-box; 
            padding: 12px 14px;
            border: 1px solid var(--clr-border); 
            border-radius: var(--radius-md);
            font-size: 1rem;
            outline: none;
            transition: all var(--time-transition); 
            background-color: #fff;
            color: var(--clr-text);
            -webkit-appearance: none; 
            -moz-appearance: none;
            appearance: none;
        }

        input:focus, select:focus {
            border-color: var(--clr-primary); 
            box-shadow: 0 0 0 2px var(--clr-accent);
        }

        input::placeholder {
            color: var(--clr-muted);
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '▼';
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            pointer-events: none; 
            color: var(--clr-muted);
            font-size: var(--fs-small);
        }
        
        select:required:invalid {
            color: var(--clr-muted); 
        }
        
        select option {
            color: var(--clr-text); 
        }

        .message {
            padding: 12px;
            border-radius: var(--radius-sm);
            margin-bottom: 20px;
            font-size: var(--fs-small);
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background-color: #d1fae5;
            color: var(--clr-success); 
            border: 1px solid var(--clr-success);
        }

        .error-message {
            background-color: #fee2e2; 
            color: var(--clr-error); 
            border: 1px solid var(--clr-error);
        }
        
        .message i {
            font-size: var(--fs-normal);
        }

        button {
            background: var(--clr-primary); 
            color: #fff;
            padding: 12px 30px;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--time-transition); 
            width: 100%; 
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        button:hover {
            background: var(--clr-secondary); 
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .back-link {
            margin-top: 20px;
            display: inline-block;
            color: var(--clr-secondary); 
            text-decoration: none;
            font-size: var(--fs-small); 
            transition: color var(--time-transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .back-link:hover {
            color: var(--clr-primary); 
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
            }
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
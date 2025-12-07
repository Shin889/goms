<?php
// Session is started in db.php
include('../config/db.php');

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role, is_active, is_approved FROM users WHERE username=?");
        
        if ($stmt === false) {
             $error_message = "Database error during preparation: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Check if account is approved
                if ($row['is_approved'] == 0) {
                    $error_message = 'Account pending admin approval. Please wait for approval.';
                } 
                // Check if account is active
                elseif ($row['is_active'] == 0) {
                    $error_message = 'Account has been deactivated. Please contact administrator.';
                }
                // Verify password
                elseif (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    
                    // Log login action
                    include('../includes/functions.php');
                    logAction($row['id'], 'LOGIN', 'User logged in', 'users', $row['id']);

                    switch ($row['role']) {
                        case 'admin':
                            header('Location: ../admin/dashboard.php'); break;
                        case 'counselor':
                            header('Location: ../counselor/dashboard.php'); break;
                        case 'adviser':
                            header('Location: ../adviser/dashboard.php'); break;
                        case 'guardian':
                            header('Location: ../guardian/dashboard.php'); break;
                        default:
                            $error_message = 'Invalid user role.';
                            session_destroy();
                    }
                    if (empty($error_message)) exit;
                } else {
                    $error_message = 'Invalid credentials.';
                }
            } else {
                $error_message = 'User not found.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | GOMS</title>
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

    .login-container {
        background: var(--clr-surface); 
        box-shadow: var(--shadow-md);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 420px;
        padding: 40px 30px;
        text-align: center;
        border: 1px solid var(--clr-border);
        transition: border-color var(--time-transition);
    }
    
    .login-container:hover {
        border-color: var(--clr-primary);
    }

    h2 {
        color: var(--clr-primary); 
        font-size: var(--fs-heading); 
        margin-bottom: 25px;
        font-weight: 700;
    }
    
    .logo-img {
        max-width: 80px; 
        height: auto;
        margin-bottom: 15px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    input {
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
    }

    input:focus {
        border-color: var(--clr-primary); 
        box-shadow: 0 0 0 2px var(--clr-accent);
    }
    
    /* Error Message Styling */
    .error-message {
        background-color: #fee2e2; 
        color: var(--clr-error); 
        border: 1px solid var(--clr-error);
        padding: 12px;
        border-radius: var(--radius-sm);
        margin-bottom: 20px;
        font-size: var(--fs-small);
        text-align: left;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .error-message i {
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
        .login-container {
            padding: 30px 20px;
        }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <img src="../utils/images/cnhslogo.png" alt="CNHS Logo" class="logo-img">

    <h2>Login to GOMS</h2>
    
    <?php if ($error_message): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required autocomplete="username">
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        <button type="submit">
            <i class="fas fa-sign-in-alt"></i> Log In
        </button>
    </form>

    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
  </div>
</body>
</html>
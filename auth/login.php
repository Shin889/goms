<?php
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
  <link rel="stylesheet" href="../utils/css/login.css">
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
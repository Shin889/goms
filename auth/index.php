<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect logged-in users directly to their dashboard
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/dashboard.php");
            exit;
        case 'counselor':
            header("Location: ../counselor/dashboard.php");
            exit;
        case 'adviser':
            header("Location: ../adviser/dashboard.php");
            exit;
        case 'guardian':
            header("Location: ../guardian/dashboard.php");
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Login or Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../utils/css/root.css">
    <link rel="stylesheet" href="../utils/css/auth_index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="logos-container">
            <img src="../utils/images/cnhslogo.png" alt="CNHS Logo" class="logo-img">
            <img src="../utils/images/goms_logo.png" alt="GOMS Logo" class="logo-img">
        </div>
        <?php if (isset($_GET['error']) && $_GET['error'] == 'account_not_approved'): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                Your account is pending admin approval or has been deactivated. Please contact the administrator.
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_role'): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                Invalid user role detected. Please contact the administrator.
            </div>
        <?php endif; ?>
        
        <h1>Welcome to GOMS</h1>
        <p>Guidance Office Management System<br>Access your account or create a new one to get started.</p>

        <div class="buttons">
            <a href="login.php" class="btn"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="register.php" class="btn btn-outline"><i class="fas fa-user-plus"></i> Register</a>
        </div>
    </div>
</body>
</html>
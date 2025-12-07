<?php
// Session will be started when db.php is included
// But this file doesn't include db.php, so we need to check
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            margin: 0;
            background: var(--clr-bg); 
            color: var(--clr-text); 
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: var(--font-family);
        }

        .container {
            background: var(--clr-surface); 
            color: var(--clr-text);
            text-align: center;
            border-radius: var(--radius-lg);
            padding: 40px 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--clr-border); 
            transition: all var(--time-transition);
        }

        .container:hover {
            border-color: var(--clr-primary);
        }
        
        .logo-img {
            max-width: 80px; 
            height: auto;
            margin-bottom: 15px;
        }

        h1 {
            margin-bottom: 10px;
            color: var(--clr-primary); 
            font-size: var(--fs-heading);
            font-weight: 700;
        }

        p {
            color: var(--clr-muted); 
            margin-bottom: 30px;
            font-size: var(--fs-small); 
        }

        .buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            background: var(--clr-primary); 
            color: #fff;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 500;
            transition: all var(--time-transition); 
            border: 2px solid transparent;
        }

        .btn:hover {
            background: var(--clr-secondary); 
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--clr-primary); 
            color: var(--clr-primary); 
        }

        .btn-outline:hover {
            background: var(--clr-primary); 
            color: #fff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); 
        }
        
        /* Error message styling */
        .error-message {
            background-color: #fee2e2;
            color: var(--clr-error);
            border: 1px solid var(--clr-error);
            padding: 10px;
            border-radius: var(--radius-md);
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

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .buttons {
                flex-direction: column;
                gap: 12px;
            }

            .btn, .btn-outline {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="../utils/images/cnhslogo.png" alt="CNHS Logo" class="logo-img">
        
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
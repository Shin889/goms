<?php
session_start();

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
        case 'student':
            header("Location: ../student/dashboard.php");
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
    <!-- Link to global root CSS -->
    <link rel="stylesheet" href="../utils/css/root.css">

    <style>
        body {
            margin: 0;
            background: var(--color-bg);
            color: var(--color-text);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            background: var(--color-surface);
            color: var(--color-text);
            text-align: center;
            border-radius: var(--radius-lg);
            padding: 40px 30px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-md);
        }

        h1 {
            margin-bottom: 10px;
            color: var(--color-primary);
            font-size: var(--font-size-heading);
            font-weight: 600;
        }

        p {
            color: var(--color-muted);
            margin-bottom: 30px;
            font-size: var(--font-size-small);
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
            background: var(--color-primary);
            color: #fff;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-weight: 500;
            transition: var(--transition);
        }

        .btn:hover {
            background: var(--color-secondary);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--color-primary);
            color: var(--color-primary);
        }

        .btn-outline:hover {
            background: var(--color-primary);
            color: #fff;
        }

        /* 📱 Responsive layout */
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .buttons {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome!</h1>
        <p>Access your account or create a new one to get started.</p>

        <div class="buttons">
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn btn-outline">Register</a>
        </div>
    </div>
</body>
</html>

<?php
session_start();
include('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['is_active'] == 0) {
            echo "<script>alert('Account pending admin approval.');</script>";
        } elseif (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on role
            switch ($row['role']) {
                case 'admin':
                    header('Location: ../admin/dashboard.php'); break;
                case 'counselor':
                    header('Location: ../counselor/dashboard.php'); break;
                case 'adviser':
                    header('Location: ../adviser/dashboard.php'); break;
                case 'student':
                    header('Location: ../student/dashboard.php'); break;
                case 'guardian':
                    header('Location: ../guardian/dashboard.php'); break;
            }
            exit;
        } else {
            echo "<script>alert('Invalid credentials');</script>";
        }
    } else {
        echo "<script>alert('User not found');</script>";
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
  <link rel="stylesheet" href="../utils/css/root.css"> <!-- 🌈 Global Theme -->

  <style>
    body {
        margin: 0;
        height: 100vh;
        background: var(--color-bg);
        color: var(--color-text);
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .login-container {
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 420px;
        padding: 40px 30px;
        text-align: center;
    }

    h2 {
        color: var(--color-primary);
        font-size: var(--font-size-heading);
        margin-bottom: 25px;
        font-weight: 600;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    input {
       /*  width: 100%; */
        padding: 12px 14px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 1rem;
        outline: none;
        transition: var(--transition);
        background-color: #fff;
    }

    input:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px var(--color-accent);
    }

    button {
        background: var(--color-primary);
        color: #fff;
        padding: 12px 30px;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        align-self: center; /* keeps button compact */
        width: 100%; /* not full width */
    }

    button:hover {
        background: var(--color-secondary);
    }

    .back-link {
        margin-top: 20px;
        display: inline-block;
        color: var(--color-secondary);
        text-decoration: none;
        font-size: var(--font-size-small);
    }

    .back-link:hover {
        color: var(--color-primary);
    }

    @media (max-width: 480px) {
        .login-container {
            padding: 30px 20px;
        }

        button {
            width: 100%;
        }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Login</h2>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>

    <a href="index.php" class="back-link">← Back to Home</a>
  </div>
</body>
</html>

<?php
include('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, full_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $email, $hashed_password, $role, $full_name, $phone);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Wait for admin approval.'); window.location='login.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register | GOMS</title>
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

    .register-container {
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 480px;
        padding: 40px 30px;
        text-align: center;
    }

    h2 {
        color: var(--color-primary);
        font-size: var(--font-size-heading);
        margin-bottom: 25px;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    input, select {
        padding: 12px;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 1rem;
        outline: none;
        transition: var(--transition);

    }

    input::placeholder {
        color: var(--color-muted);
    }

    input:focus, select:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px var(--color-accent);
    }
/* 
    select {
        background-color: #fff;
        color: var(--color-text);
    } */

    button {
        background: var(--color-primary);
        color: #fff;
        padding: 12px 30px;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        align-self: center;
        width: 100%; /* not full width */
    }

    button:hover {
        background: var(--color-secondary);
    }

    .back-link {
        margin-top: 18px;
        display: inline-block;
        color: var(--color-secondary);
        
        text-decoration: none;
        font-size: var(--font-size-small);
    }

    .back-link:hover {
        color: var(--color-primary);
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
    <h2>Create an Account</h2>

    <form method="POST" action="">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email (optional)">
        <input type="text" name="phone" placeholder="Phone Number">
        <input type="password" name="password" placeholder="Password" required>
        
        <select name="role" required>
            <option value="" disabled selected>Select Role</option>
            <option value="student">Student</option>
            <option value="guardian">Guardian</option>
            <option value="adviser">Adviser</option>
            <option value="counselor">Counselor</option>
        </select>

        <button type="submit">Register</button>
    </form>

    <a href="login.php" class="back-link">← Back to Login</a>
  </div>
</body>
</html>

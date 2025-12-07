<?php
// dashboard.php - Main dashboard router
require_once 'config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['role'])) {
    header("Location: auth/login.php");
    exit();
}

// Check if user is approved and active
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1 AND is_approved = 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        // User not approved or inactive
        session_destroy();
        header("Location: auth/login.php?error=account_not_approved");
        exit();
    }
}

// Route based on role
switch ($_SESSION['role']) {
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    case 'counselor':
        header('Location: counselor/dashboard.php');
        break;
    case 'adviser':
        header('Location: adviser/dashboard.php');
        break;
    case 'guardian':
        header('Location: guardian/dashboard.php');
        break;
    default:
        // Invalid role - destroy session and redirect to login
        session_destroy();
        header("Location: auth/login.php?error=invalid_role");
        break;
}
exit();
?>
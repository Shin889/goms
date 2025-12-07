<?php
session_start();
include('../config/db.php');

// Log logout action if user was logged in
if (isset($_SESSION['user_id'])) {
    include('../includes/functions.php');
    logAction($_SESSION['user_id'], 'LOGOUT', 'User logged out', 'users', $_SESSION['user_id']);
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Optional: Clear session cookie for security
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Redirect to login page
header("Location: index.php");
exit;
?>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Optional: restrict page access by role
function checkRole($roles) {
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: ../auth/login.php");
        exit;
    }
}
?>

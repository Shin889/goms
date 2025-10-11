<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: auth/login.php");
    exit;
}

switch ($_SESSION['role']) {
    case 'admin':
        header('Location: admin/dashboard.php'); break;
    case 'counselor':
        header('Location: counselor/dashboard.php'); break;
    case 'adviser':
        header('Location: adviser/dashboard.php'); break;
    case 'student':
        header('Location: student/dashboard.php'); break;
    case 'guardian':
        header('Location: guardian/dashboard.php'); break;
}
exit;
?>

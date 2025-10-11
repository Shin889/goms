<?php
session_start();

// If not logged in, go to the page with both login and register options
if (!isset($_SESSION['role'])) {
    header("Location: auth/index.php");
    exit;
}

// Redirect to correct dashboard based on role
switch ($_SESSION['role']) {
    case 'admin':
        header("Location: admin/dashboard.php");
        break;
    case 'counselor':
        header("Location: counselor/dashboard.php");
        break;
    case 'adviser':
        header("Location: adviser/dashboard.php");
        break;
    case 'student':
        header("Location: student/dashboard.php");
        break;
    case 'guardian':
        header("Location: guardian/dashboard.php");
        break;
    default:
        session_destroy();
        header("Location: auth/index.php");
        break;
}
exit;
?>

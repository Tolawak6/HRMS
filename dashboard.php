<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

switch ($user['role']) {
    case 'admin':
        header('Location: admin_dashboard.php');
        break;
    case 'hr_staff':
        header('Location: hr_staff_dashboard.php');
        break;
    case 'ess_staff':
        header('Location: ess_staff_dashboard.php');
        break;
    default:
        echo "Invalid user role.";
        exit;
}
?>

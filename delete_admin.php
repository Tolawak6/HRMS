<?php
session_start();
include('db.php');

// Ensure the user is an admin or HR staff
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'hr_staff')) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Sanitize the ID

    // Perform deletion for admin user
    $query = "DELETE FROM users WHERE id = $id AND role = 'admin'";
    if (mysqli_query($conn, $query)) {
        $message = urlencode("Admin deleted successfully.");
    } else {
        $message = urlencode("Error: " . mysqli_error($conn));
    }
} else {
    $message = urlencode("Invalid request.");
}

$section = isset($_GET['section']) ? $_GET['section'] : 'manage_admin';

// Redirect back to admin_dashboard.php with message and section
header("Location: admin_dashboard.php?section=$section&message=$message");
exit;
?>

<?php
session_start();
include('db.php');

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'hr_staff')) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Perform deletion
    $query = "DELETE FROM users WHERE id = $id AND role = 'hr_staff'";
    if (mysqli_query($conn, $query)) {
        $message = urlencode("HR staff deleted successfully.");
    } else {
        $message = urlencode("Error: " . mysqli_error($conn));
    }
} else {
    $message = urlencode("Invalid request.");
}

$section = isset($_GET['section']) ? $_GET['section'] : 'manage_hr';

// Redirect back to admin_dashboard.php with message and section
header("Location: admin_dashboard.php?section=$section&message=$message");
exit;
?>

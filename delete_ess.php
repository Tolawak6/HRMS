<?php
session_start();
include('db.php');

// Ensure the user is an admin or HR staff
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'hr_staff')) {
    header('Location: index.php');
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Sanitize the input ID

    // Delete related files in the file_attachments table first
    $delete_files_query = "DELETE FROM file_attachments WHERE ess_staff_id = $id";
    if (mysqli_query($conn, $delete_files_query)) {
        // Now delete the ESS staff user
        $delete_user_query = "DELETE FROM users WHERE id = $id AND role = 'ess_staff'";
        if (mysqli_query($conn, $delete_user_query)) {
            $message = urlencode("ESS staff deleted successfully.");
        } else {
            $message = urlencode("Error deleting ESS staff user: " . mysqli_error($conn));
        }
    } else {
        $message = urlencode("Error deleting related files: " . mysqli_error($conn));
    }
} else {
    $message = urlencode("Invalid request.");
}

$section = isset($_GET['section']) ? $_GET['section'] : 'manage_ess';

// Redirect back to admin_dashboard.php with message and section
header("Location: admin_dashboard.php?section=$section&message=$message");
exit;
?>

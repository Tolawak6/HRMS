<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'hr_staff') {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Staff Dashboard - HRMS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Welcome, <?php echo $_SESSION['user']['username']; ?> (HR Staff)</h1>
    <!-- HR Staff-specific content here -->
</body>
</html>

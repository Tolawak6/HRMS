<?php
// Start session and check if the user is an admin
session_start();
include('db.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header('Location: index.php');
    exit;
}

// Get the HR staff ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$message = '';
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Fetch HR staff details
$query = "SELECT * FROM users WHERE id = $id AND role = 'hr_staff'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if ($password) {
        $query = "UPDATE users SET username='$username', password='$password' WHERE id='$id'";
    } else {
        $query = "UPDATE users SET username='$username' WHERE id='$id'";
    }

    if (mysqli_query($conn, $query)) {
        $message = urlencode("HR staff updated successfully.");
        header("Location: edit_hr.php?id=$id&message=$message");
        exit;
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit HR Staff</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Edit HR Staff</h2>
        <?php if (!empty($message)) { echo "<p class='alert alert-success'>$message</p>"; } ?>

        <form action="edit_hr.php?id=<?php echo $id; ?>" method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password (leave blank to keep current password):</label>
                <input type="password" name="password" id="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</body>
</html>

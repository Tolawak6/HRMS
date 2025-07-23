<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('db.php');

// Check if the user is logged in as admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header('Location: index.php');
    exit;
}

// Check if an ID is passed for editing
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Sanitize the ID

    // Get the current admin details
    $query = "SELECT * FROM users WHERE id = ? AND role = 'admin'";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
        } else {
            echo "Admin not found.";
            exit;
        }
    } else {
        echo "Error preparing query: " . $conn->error;
        exit;
    }
} else {
    echo "Invalid request.";
    exit;
}

// Handle the form submission to update the admin details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_username = $_POST['username'];
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the new password for security

    // Update the admin details in the database
    $update_query = "UPDATE users SET username = ?, password = ? WHERE id = ? AND role = 'admin'";
    $update_stmt = $conn->prepare($update_query);

    if ($update_stmt) {
        $update_stmt->bind_param("ssi", $new_username, $new_password, $id);
        if ($update_stmt->execute()) {
            // Redirect back to the admin dashboard with a success message
            header("Location: admin_dashboard.php?section=manage_admins&message=" . urlencode("Admin details updated successfully."));
            exit;
        } else {
            echo "Error updating admin details: " . $conn->error;
        }
    } else {
        echo "Error preparing the update query: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <!-- Bootstrap CSS link -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <div class="container mt-5">
        <h2>Edit Admin Details</h2>

        <!-- Display success or error message -->
        <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Admin edit form -->
        <form method="POST" action="" class="mt-4">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Update Admin</button>
            <a href="admin_dashboard.php" class="btn btn-secondary ml-2">Cancel</a>
        </form>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php
// Start session and check if the user is an admin or HR staff
session_start();
include('db.php');

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['admin', 'hr_staff'])) {
    header('Location: index.php');
    exit;
}

// Get the ESS staff ID from the URL
if (isset($_GET['id'])) {
    $ess_id = $_GET['id'];

    // Fetch ESS staff details from the database
    $query = "SELECT first_name, middle_name, last_name, username FROM users WHERE id = '$ess_id' AND role = 'ess_staff'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $ess_user = mysqli_fetch_assoc($result);
    } else {
        echo "<p class='error'>ESS user not found.</p>";
        exit;
    }
} else {
    echo "<p class='error'>Invalid ESS user ID.</p>";
    exit;
}

// Initialize variables for error and success messages
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];

    // Update the ESS staff information in the database
    $update_query = "UPDATE users SET first_name = '$first_name', middle_name = '$middle_name', last_name = '$last_name', username = '$username' WHERE id = '$ess_id' AND role = 'ess_staff'";
    if (mysqli_query($conn, $update_query)) {
        $success_message = "ESS staff updated successfully.";
        // Refresh the page to show the updated information and success message
        $query = "SELECT first_name, middle_name, last_name, username FROM users WHERE id = '$ess_id' AND role = 'ess_staff'";
        $result = mysqli_query($conn, $query);
        $ess_user = mysqli_fetch_assoc($result);
    } else {
        $error_message = "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit ESS User - HRMS</title>
    <style>
        /* Add styling for the form */
        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }

        button:hover {
            background-color: #0056b3;
        }

        .error {
            color: red;
        }

        .success {
            color: green;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Edit ESS Staff Information</h2>

        <!-- Display error or success messages -->
        <?php if ($error_message) { echo "<p class='error'>$error_message</p>"; } ?>
        <?php if ($success_message) { echo "<p class='success'>$success_message</p>"; } ?>

        <form action="edit_ess.php?id=<?php echo $ess_id; ?>" method="post">
            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($ess_user['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name:</label>
                <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($ess_user['middle_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($ess_user['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($ess_user['username']); ?>" required>
            </div>
            <button type="submit">Update ESS Staff</button>
        </form>
    </div>
</body>
</html>

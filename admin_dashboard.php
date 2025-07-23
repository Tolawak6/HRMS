



<?php
// Start session and check if the user is an admin or HR staff
session_start();
include('db.php');

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] != 'admin' && $_SESSION['user']['role'] != 'hr_staff')) {
    header('Location: index.php');
    exit;
}

$message = '';
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

$active_section = isset($_GET['section']) ? $_GET['section'] : 'overview'; // Default active section

// Handle HR staff creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_hr'])) {
    $username = $_POST['username'];
    $password = $_POST['password']; // Encrypt password

    // Insert new HR staff into the database
    $query = "INSERT INTO users (username, password, role) VALUES ('$username', '$password', 'hr_staff')";
    if (mysqli_query($conn, $query)) {
        $message = "HR staff created successfully.";
        $active_section = $_POST['active_section']; // Set active section to manage_hr
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_file'])) {
    // Handle file upload
    $ess_staff_id = $_POST['ess_staff_id'];
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    $upload_dir = 'uploads/';
    $file_path = $upload_dir . basename($file_name);

    if (move_uploaded_file($file_tmp, $file_path)) {
        $query = "INSERT INTO file_attachments (ess_staff_id, file_name, file_path, uploaded_at) VALUES ('$ess_staff_id', '$file_name', '$file_path', NOW())";
        if (mysqli_query($conn, $query)) {
            $message = "File uploaded successfully.";
            $active_section = 'manage_files';
        } else {
            $message = "Error: " . mysqli_error($conn);
        }
    } else {
        $message = "File upload failed.";
    }
}

// Handle ESS staff search
$search_query = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_query = mysqli_real_escape_string($conn, $_POST['search_query']);
}

// Fetch all HR staff from the database
$hr_query = "SELECT * FROM users WHERE role = 'hr_staff'";
$hr_result = mysqli_query($conn, $hr_query);

// Fetch all ESS staff from the database
$ess_query = "SELECT * FROM users WHERE role = 'ess_staff'";
if ($search_query) {
    $ess_query .= " AND (username LIKE '%$search_query%' OR first_name LIKE '%$search_query%' OR last_name LIKE '%$search_query%')";
}
$ess_query .= " ORDER BY username";
$ess_result = mysqli_query($conn, $ess_query);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_hr'])) {
    $id = $_POST['id'];
    $username = $_POST['username'];
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if ($password) {
        $query = "UPDATE users SET username='$username', password='$password' WHERE id='$id'";
    } else {
        $query = "UPDATE users SET username='$username' WHERE id='$id'";
    }

    if (mysqli_query($conn, $query)) {
        $message = "HR staff updated successfully.";
        $active_section = 'manage_hr'; // Stay on the manage HR staff page
    } else {
        $message = "Error: " . mysqli_error($conn);
    }
}

// Handle admin creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($username) || empty($password)) {
        $message = "Both fields are required!";
        $message_type = "danger"; // Set message type for styling
    } else {
        // Check if the username already exists
        $query = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            $message = "Error preparing statement: " . $conn->error;
            $message_type = "danger";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $message = "Username already exists!";
                $message_type = "danger";
            } else {
                // Insert the new admin into the database without hashing the password
                $insert_query = "INSERT INTO users (username, password, role) VALUES (?, ?, 'admin')";
                $insert_stmt = $conn->prepare($insert_query);

                if (!$insert_stmt) {
                    $message = "Error preparing insert statement: " . $conn->error;
                    $message_type = "danger";
                } else {
                    $insert_stmt->bind_param("ss", $username, $password);

                    if ($insert_stmt->execute()) {
                        $message = "Admin user created successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error creating admin user: " . $conn->error;
                        $message_type = "danger";
                    }
                }
            }
        }
    }
}


// Fetch all registered admins, ordered by most recent first
$admins_query = "SELECT id, username FROM users WHERE role = 'admin' ORDER BY id DESC";
$admins_result = $conn->query($admins_query);


?>







<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - HRMS</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 250px;
            background-color: #007bff;
            color: white;
            height: 100%;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            left: 0;
            top: 0;
        }

        .sidebar a {
            padding: 15px 20px;
            text-decoration: none;
            font-size: 18px;
            color: white;
            display: block;
        }

        .sidebar a:hover {
            background-color: #0056b3;
        }

        .main-content {
            flex: 1;
            margin-left: 250px; /* Sidebar width */
            padding: 20px;
            overflow-y: auto;
        }

        .content-section {
            display: none;
            overflow-y: auto; /* Makes the section scrollable */
            margin-top: 20px; /* Increases the top margin */
            max-height: calc(100vh - 40px); /* Ensures the section does not exceed the viewport height */
            padding-right: 10px; /* Optional: Add padding to avoid content being too close to the scrollbar */
        }

        .content-section.active {
            display: block;
        }

        .message {
            color: green;
        }

        .table-container {
            max-width: 95%; /* Adjust this percentage as needed */
            margin: 0 auto; /* Center the table */
        }

        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .table thead th {
            background-color: #007bff;
            color: white;
            text-align: center;
            padding: 12px;
        }

        .table tbody td {
            text-align: center;
            padding: 12px;
        }

        .table-actions {
            display: flex;
            justify-content: flex-end;
            gap: 5px; /* Space between buttons */
        }

        .btn {
            border-radius: 4px;
        }

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .message {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 20px;
    text-align: center;
}

.message.success {
    color: green;
    font-weight: bold;
}

.message.error {
    color: red;
    font-weight: bold;
}



    </style>

    <script>
        function showSection(sectionId) {
            var sections = document.getElementsByClassName('content-section');
            for (var i = 0; i < sections.length; i++) {
                sections[i].classList.remove('active');
            }
            document.getElementById(sectionId).classList.add('active');
        }

        window.onload = function() {
            var activeSection = '<?php echo $active_section; ?>';
            showSection(activeSection);
        }
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <a href="admin_dashboard.php?section=overview">Overview</a>
        <a href="admin_dashboard.php?section=manage_hr">Manage HR Staff</a>
        <a href="admin_dashboard.php?section=manage_ess">Manage ESS Staff</a>
        <a href="admin_dashboard.php?section=manage_files">Attach Files</a>


        <a href="?section=manage_admins" class="nav-link <?php echo $active_section == 'manage_admins' ? 'active' : ''; ?>">Manage Admins</a>
        
    </div>



   













    <!-- Main Content -->
    <div class="main-content">
        <!-- Overview Section -->
        <div id="overview" class="content-section <?php echo $active_section == 'overview' ? 'active' : ''; ?>">
    <div class="d-flex justify-content-end">
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>

        </form>
    </div>
    <?php include 'overview.php'; ?>
</div>


       <!-- Manage HR Staff Section -->
<div id="manage_hr" class="content-section <?php echo $active_section == 'manage_hr' ? 'active' : ''; ?>">
<div class="d-flex justify-content-between align-items-center">
        <h2>Manage HR Staff</h2>
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>

        </form>
    </div>
    <?php if (!empty($message)) { echo "<p class='message'>$message</p>"; } ?>

    <!-- HR Staff Creation Form -->
    <form action="admin_dashboard.php" method="post" class="mb-4">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <input type="hidden" name="active_section" value="manage_hr">
        <button type="submit" name="create_hr" class="btn btn-primary">Create HR Staff</button>
    </form>

    <!-- Existing HR Staff Table -->
    <table class="table table-striped table-bordered mt-4">
        <thead>
            <tr>
                <th>Username</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = mysqli_fetch_assoc($hr_result)) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td>
                        <a href="edit_hr.php?id=<?php echo $row['id']; ?>&section=manage_hr" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_hr.php?id=<?php echo $row['id']; ?>&section=manage_hr" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this HR staff?');">Delete</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>



        <!-- Manage ESS Staff Section -->
        <div id="manage_ess" class="content-section <?php echo $active_section == 'manage_ess' ? 'active' : ''; ?>">
        <div class="d-flex justify-content-between align-items-center">    
        <h2>Manage ESS Staff</h2>
            <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>
        </form>
        </div>
            <?php if (!empty($message)) { echo "<p class='message'>$message</p>"; } ?>
            <table class="table table-striped table-bordered mt-4">
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($ess_result)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <a href="edit_ess.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="delete_ess.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ESS staff?');">Delete</a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- Manage Files Section -->
        <div id="manage_files" class="content-section <?php echo $active_section == 'manage_files' ? 'active' : ''; ?>">
        <div class="d-flex justify-content-between align-items-center"> 
        <h2>Attach Files</h2>
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>
        </form>
        </div>
            <?php if (!empty($message)) { echo "<p class='message'>$message</p>"; } ?>

            <form action="admin_dashboard.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="ess_staff_id">Select ESS Staff:</label>
                    <select name="ess_staff_id" id="ess_staff_id" required>
                        <?php
                        $ess_result = mysqli_query($conn, "SELECT * FROM users WHERE role = 'ess_staff'");
                        while ($staff = mysqli_fetch_assoc($ess_result)) {
                            echo "<option value='" . $staff['id'] . "'>" . htmlspecialchars($staff['username']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="file">Upload File:</label>
                    <input type="file" name="file" id="file" required>
                </div>
                <button type="submit" name="upload_file" class="btn btn-primary">Upload</button>
            </form>

            <!-- Display attached files -->
            <h3 class="mt-4">Attached Files</h3>
            <table class="table table-striped table-bordered mt-2">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $file_query = "SELECT * FROM file_attachments";
                    $file_result = mysqli_query($conn, $file_query);
                    while ($file = mysqli_fetch_assoc($file_result)) {
                        echo "<tr>
                                <td>" . htmlspecialchars($file['file_name']) . "</td>
                                <td>" . htmlspecialchars($file['uploaded_at']) . "</td>
                                <td><a href='" . htmlspecialchars($file['file_path']) . "' class='btn btn-success btn-sm' download>Download</a></td>
                            </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!--hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh -->

        <div id="manage_admins" class="content-section <?php echo $active_section == 'manage_admins' ? 'active' : ''; ?>">
    <h2>Create Admin User</h2>

    <!-- Display success/error message -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Form for creating admin -->
    <form action="" method="post">
        <div class="form-group">
            <label for="admin_username">Username:</label>
            <input type="text" name="username" id="admin_username" class="form-control" placeholder="Enter username" required>
        </div>
        <div class="form-group">
            <label for="admin_password">Password:</label>
            <input type="password" name="password" id="admin_password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" name="create_admin" class="btn btn-primary">Create Admin</button>
    </form>

    <!-- Table of registered admins -->
    <h3>Registered Admins</h3>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Username</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
if ($admins_result && $admins_result->num_rows > 0) {
    while ($admin = $admins_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($admin['username']) . "</td>";
        echo "<td>
            <!-- Edit Button -->
            <a href='edit_admin.php?id=" . $admin['id'] . "' class='btn btn-warning btn-sm'>
                Edit
            </a>
            <!-- Delete Button -->
            <a href='delete_admin.php?id=" . $admin['id'] . "' 
               class='btn btn-danger btn-sm' 
               onclick='return confirm(\"Are you sure you want to delete this admin?\")'>
                Delete
            </a>
        </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='2' class='text-center'>No admins found</td></tr>";
}
?>

        </tbody>
    </table>
</div>



<!-- --------------------------------------------------------- -->




    </div>
</body>
</html>

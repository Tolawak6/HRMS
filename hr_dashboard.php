<?php
session_start();
include('db.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'hr_staff') {
    header('Location: index.php');
    exit;
}

$message = ''; // Initialize the $message variable

// Handle form submission for creating ESS staff
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_ess'])) {
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'];
    $last_name = $_POST['last_name'];
    $sex = $_POST['sex'];
    $phone_number = $_POST['phone_number'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $room_number = $_POST['room_number'];
    $shelf_number = $_POST['shelf_number'];
    $username = $_POST['username'];
    $password = $_POST['password']; // Store the plain text password

    // Handle Image Upload
    $image = $_FILES['image']['name'];
    $image_tmp = $_FILES['image']['tmp_name'];
    $image_folder = 'uploads/images/' . basename($image);

    // Debugging information
    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload error: " . $_FILES['image']['error'];
    } else {
        $upload_dir = realpath('uploads/images/');
        if (move_uploaded_file($image_tmp, $image_folder)) {
            $message = "Image uploaded successfully.";
        } else {
            $message = "Image upload failed. Error: " . error_get_last()['message'] . ". Upload Directory: " . $upload_dir . ". Target File: " . $image_folder;
        }
    }

    // Insert new ESS staff into the database
    $query = "INSERT INTO users 
              (username, password, role, first_name, middle_name, last_name, sex, image, phone_number, email, address, room_number, shelf_number) 
              VALUES 
              ('$username', '$password', 'ess_staff', '$first_name', '$middle_name', '$last_name', '$sex', '$image', '$phone_number', '$email', '$address', '$room_number', '$shelf_number')";
    
    if (mysqli_query($conn, $query)) {
        $message .= " ESS staff created successfully.";
    } else {
        $message .= " Error: " . mysqli_error($conn);
    }
}

// Fetch all ESS staff from the database
$query = "SELECT * FROM users WHERE role = 'ess_staff'";
$result = mysqli_query($conn, $query);

// Determine which section to display
$section = isset($_GET['section']) ? $_GET['section'] : 'create';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Staff Dashboard - HRMS</title>
    <link rel="stylesheet" href="style.css">
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            min-height: 100vh;
            overflow: hidden; /* Prevent horizontal scroll */
        }

        .sidebar {
            width: 250px;
            background-color: #007bff;
            color: white;
            height: 100vh; /* Full viewport height */
            position: fixed;
            top: 0;
            left: 0;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto; /* Make sidebar scrollable if needed */
        }

        .sidebar a {
            display: block;
            color: white;
            padding: 15px 20px;
            text-decoration: none;
            font-size: 18px;
        }

        .sidebar a:hover {
            background-color: #0056b3;
        }

        .main-content {
            margin-left: 250px; /* Sidebar width */
            padding: 20px;
            width: calc(100% - 250px);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .section-content {
            flex: 1;
            overflow-y: auto; /* Make content scrollable */
            padding-right: 20px; /* Add padding to prevent content from being hidden by the scrollbar */
        }

        .form-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
        }

        .form-group label {
            flex: 0 0 150px;
            margin-right: 10px;
            text-align: right;
        }

        .form-group input, .form-group select {
            flex: 1;
            padding: 5px;
            font-size: 16px;
        }

        .form-group input[type="file"] {
            padding: 0;
        }

        button {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 5px;
        }

        button:hover {
            background-color: #0056b3;
        }

        h1, h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .message {
            color: green;
            font-weight: bold;
        }

        .profile-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-image-container {
            display: flex;
            align-items: center;
        }

        .profile-image-container img {
            margin-right: 10px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>HR Dashboard</h2>
        <a href="?section=create">Create ESS Staff</a>
        <a href="?section=manage">Manage ESS Staff</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['username']); ?> (HR Staff)</h1>


        <div class="section-content">
        <div class="d-flex justify-content-between align-items-center">
            <?php if ($section === 'create') { ?>
                <h2>Create ESS Staff</h2>


               
        <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>

        </form>
    </div>
                <form action="hr_dashboard.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name:</label>
                        <input type="text" id="middle_name" name="middle_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="sex">Sex:</label>
                        <select id="sex" name="sex" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number:</label>
                        <input type="text" id="phone_number" name="phone_number" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" required>
                    </div>
                    <div class="form-group">
                        <label for="room_number">Room Number:</label>
                        <input type="text" id="room_number" name="room_number" required>
                    </div>
                    <div class="form-group">
                        <label for="shelf_number">Shelf Number:</label>
                        <input type="text" id="shelf_number" name="shelf_number" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Image:</label>
                        <input type="file" id="image" name="image" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="create_ess">Create ESS Staff</button>
                </form>


                <div class="d-flex justify-content-between align-items-center">

                <?php if (!empty($message)) { echo "<p class='message'>$message</p>"; } ?>

            <?php } elseif ($section === 'manage') { ?>
                <h2>Manage ESS Staff</h2>

                <form action="logout.php" method="post">
            <button type="submit" class="btn btn-danger btn-sm">
    <i class="fas fa-sign-out-alt"></i> Logout
</button>

        </form>
    </div>
                
                <table>
                    <tr>
                        <th>Profile Image</th>
                        <th>First Name</th>
                        <th>Middle Name</th>
                        <th>Last Name</th>
                        <th>Sex</th>
                        <th>Phone Number</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Room Number</th>
                        <th>Shelf Number</th>
                        <th>Username</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <tr>
                            <td class="profile-image-container">
                                <?php 
                                $image_path = 'uploads/images/' . htmlspecialchars($row['image']);
                                if (!empty($row['image']) && file_exists($image_path)) { ?>
                                    <img src="<?php echo $image_path; ?>" class="profile-image" alt="Profile Image">
                                <?php } else { ?>
                                    <img src="uploads/images/default.png" class="profile-image" alt="Default Image">
                                <?php } ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['first_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['middle_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['sex']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                            <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['shelf_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <a href="edit_ess.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="delete_ess.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ESS staff?');">Delete</a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>


<?php
session_start();
include('db.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'ess_staff') {
    header('Location: index.php');
    exit;
}

// Fetch the logged-in ESS staff's profile details
$ess_id = $_SESSION['user']['id'];
$user_query = "SELECT first_name, middle_name, last_name, sex, image FROM users WHERE id = '$ess_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Fetch the logged-in ESS staff's files
$file_query = "SELECT * FROM file_attachments WHERE ess_staff_id = '$ess_id'";
$file_result = mysqli_query($conn, $file_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESS Dashboard - HRMS</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: #007bff;
            color: white;
            height: 100vh;
            padding-top: 20px;
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
            margin-left: 270px; /* Account for sidebar width and some margin */
            flex: 1;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .profile-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-image-container {
            width: 180px;
            height: 180px;
            overflow: hidden;
            border-radius: 50%;
            margin: 0 auto 20px auto;
            border: 5px solid #007bff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .profile-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-details {
            text-align: center;
        }

        .profile-details p {
            font-size: 18px;
            margin: 10px 0;
        }

        .profile-details p strong {
            color: #007bff;
        }

        .table th, .table td {
            vertical-align: middle;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004494;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>ESS Dashboard</h2>
        <a href="javascript:void(0)" onclick="showSection('profile')">View Profile</a>
        <a href="javascript:void(0)" onclick="showSection('files')">Attached Files</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Profile Section -->
        <div id="profile" class="content-section">
            <h1 class="text-center mb-4">Profile</h1>
            
            <div class="profile-container">
                <div class="profile-image-container">
                    <?php 
                    // Set the path to the profile image
                    $profile_image = !empty($user['image']) ? 'uploads/images/' . htmlspecialchars($user['image']) : 'uploads/images/default.png';
                    ?>
                    <img src="<?php echo $profile_image; ?>" alt="Profile Image">
                </div>
                <div class="profile-details">
                    <p><strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?></p>
                    <p><strong>Middle Name:</strong> <?php echo htmlspecialchars($user['middle_name']); ?></p>
                    <p><strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?></p>
                    <p><strong>Sex:</strong> <?php echo htmlspecialchars($user['sex']); ?></p>
                </div>
            </div>
        </div>

        <!-- Files Section -->
        <div id="files" class="content-section" style="display: none;">
            <h1 class="text-center mb-4">Attached Files</h1>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($file_result)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['file_name']); ?></td>
                            <td><a href="uploads/<?php echo htmlspecialchars($row['file_name']); ?>" class="btn btn-primary" download>Download</a></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        function showSection(sectionId) {
            var sections = document.getElementsByClassName('content-section');
            for (var i = 0; i < sections.length; i++) {
                sections[i].style.display = 'none';
            }
            document.getElementById(sectionId).style.display = 'block';
        }

        window.onload = function() {
            showSection('profile');
        }
    </script>
</body>
</html>

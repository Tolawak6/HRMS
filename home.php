<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - HRMS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .navbar {
            background-color: #007bff;
            padding: 15px;
            width: 100%;
            display: flex;
            justify-content: center;
            position: fixed;
            top: 0;
            z-index: 1000;
            align-items: center;
        }
        .navbar ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            flex-grow: 1;
        }
        .navbar ul li {
            margin: 0 15px;
        }
        .navbar ul li a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            padding: 8px 16px;
            display: block;
        }
        .navbar ul li a:hover {
            background-color: #0056b3;
            border-radius: 5px;
        }
        .navbar img {
            position: absolute;
            left: 20px;
            top: 10px;
            width: 120px;
            height: auto;
        }
        .motto {
            color: white;
            font-size: 20px;
            font-weight: bold;
            font-style: italic;
            margin-left: 200px; /* Adjust this value to fit the design */
        }
        .container {
            max-width: 800px;
            width: 100%;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
            text-align: center;
            margin-top: 120px; /* Adjusted to account for navbar */
            margin-bottom: 20px; /* Space at the bottom */
        }
        h1 {
            color: #333;
        }
        p {
            color: #666;
            line-height: 1.6;
            text-align: justify;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <img src="images/logo.png" alt="Company Logo"> <!-- Update the path to your logo image -->
        <div class="motto">Your reliable data source</div> <!-- Motto added here -->
        <ul>
            <li><a href="#">Home</a></li>
            <li><a href="about_us.php">About Us</a></li>
        </ul>
        <button onclick="location.href='index.php'" style="position: absolute; right: 20px; background-color: #fff; color: #007bff; border-radius: 5px; padding: 8px 16px; border: none; cursor: pointer;">Login</button>
    </div>

    <div class="container">
        <h1>Welcome to Ethiopian Statistical Service (ESS) HRMS</h1>
        <p>
            This Human Resource Management System (HRMS) is designed to streamline and manage the human resources of the Ethiopian Statistics Service (ESS). 
            Our system helps in managing staff profiles, HR operations, and more, ensuring an efficient and organized approach to human resources.
        </p>
        <p>The Human Resource Management System (HRMS) is a comprehensive web-based application designed to streamline HR operations efficiently. It allows administrators to manage users with distinct roles, including HR staff and ESS (Employee Self-Service) staff. The system features functionalities such as user profile management, file attachment handling, and role-specific dashboards, ensuring seamless interaction and data access. Administrators can create, edit, and delete user accounts, while ESS staff can view profiles and access files uploaded by the admin. Built with a focus on user-friendly design and functionality, the HRMS enhances productivity by centralizing HR operations in a secure and professional environment, with plans for further enhancements such as robust password encryption and extended integrations.</p>
    </div>
</body>
</html>

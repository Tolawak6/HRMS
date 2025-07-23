<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - HRMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> <!-- Font Awesome CDN -->
    <style>
        html, body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            height: 100%;
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensure body takes at least full viewport height */
            padding-bottom: 60px; /* Space for the footer */
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
            max-width: 80%; /* Set the container width to 60% */
            padding: 15px; /* Reduced padding */
            background: #fff;
            box-shadow: 0 0 8px rgba(0,0,0,0.1); /* Reduced shadow */
            border-radius: 8px;
            margin-top: 100px; /* Adjusted margin-top */
            margin-left: 10%; /* Position the container with some left margin */
            margin-right: 10%;
            text-align: center;
        }
        h1 {
            color: #1e78ba;
            font-size: 24px; /* Adjusted font size */
        }
        p {
            color: #666;
            line-height: 1.7;
            font-size: 17px; /* Reduced font size */
        }
        h3 {
            text-align: left;
            font-size: 18px; /* Adjusted font size */
        }
        .scroll-container {
            flex: 1;
            overflow-y: auto;
            width: 100%;
            padding-bottom: 20px;
        }
        .footer {
            background-color: #007bff;
            color: white;
            padding: 5px; /* Adjusted padding */
            text-align: center;
            position: relative;
            bottom: 0;
            width: 100%;
            height: 90px; /* Reduced height for the footer */
        }
        .footer h2 {
            margin-top: 0;
            font-size: 18px; /* Reduced font size */
            margin-bottom: 5px; /* Reduced bottom margin */
        }
        .footer p {
            font-size: 14px; /* Reduced font size */
            margin: 0; /* Removed margin */
        }
        .footer .info {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 10px; /* Reduced gap */
        }
        .footer .info .location-icon {
            font-size: 24px; /* Adjust the icon size */
        }
        .footer .info div {
            margin: 0; /* Removed margin */
            max-width: 200px; /* Reduced max-width */
        }
        .footer .info iframe {
            width: 100%;
            height: 150px; /* Reduced height */
            border: 0;
        }
        .footer hr {
            margin: 10px 0; /* Reduced margin-top */
            border-color: rgba(255, 255, 255, 0.2);
        }
        .footer p.copyright {
            margin-top: 5px; /* Adjust the margin to move it up */
            font-size: 14px; /* Reduced font size */
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-content">
            <img src="images/logo.png" alt="Company Logo"> <!-- Update the path to your logo image -->
            <div class="motto">Your reliable data source</div>
        </div>
        <ul>
            <li><a href="home.php">Home</a></li>
            <li><a href="about_us.php">About Us</a></li>
        </ul>
        <button onclick="location.href='index.php'" style="background-color: #fff; color: #007bff; border-radius: 5px; padding: 6px 12px; border: none; cursor: pointer;">Login</button>
    </div>

    <div class="scroll-container">
        <div class="container">
            <h1>About Us</h1>
            <h3>Introduction</h3>
            <p>
                Statistical data collected and compiled using scientific methodologies are crucial for accelerating the economic, social, and democratic activities of a country, and for enhancing people's lives. Moreover, these data enable the Government and other institutions to formulate policies for various sectors, to monitor and evaluate policy implementations, and to make informed decisions going forward.
            </p>
            <p>
            The Ethiopian Statistics Service (ESS) collects and compiles data from sample surveys, censuses, and administrative records, and also analyzes data and delivers reports for various sectors and users. Additionally, the ESS provides technical guidance and assistance to government agencies, institutions, and individuals to help them establish administrative recording, registration, and reporting systems and to build the capacity required for providing directives and consultations on the development of administrative records and registration systems. In general, the ESS leads the statistical system of the country.
            </p>
            <p>To maintain internationally accepted standards and to satisfy the excessive demands of statistical data, the ESS is diversifying its statistical methodologies and upgrading the use of technology in its data collection, compilation, classification, analysis, and dissemination methods.</p>
            <p>Besides narrating the vision, mission, objectives, duties, and responsibilities of ESS, this booklet aims to highlight the importance of data which is acquired in various ways.</p>
            <h3>Background</h3>
            <p>Though statistics work in Ethiopia started many years ago, an organized system was created in the late 1950s in part because statistical data was by then also in demand for the development of socioeconomic plans. In addition, in 1952 there had been a conference for Statisticians of African countries in the African Economic Commission. At this conference, there was an agreement among participants to submit a five-year statistical action plan, which also encouraged our country to create a more organized statistical system. Proclamations in 2006 and 2007, to renew the Central Statistics Authority and to establish the Population Census Commission, respectively, were fundamental in creating the statistical system ESS has today.</p>
            <p>The name Central Statistics Agency was given to the office in 2007 when the Council of Ministries was reestablished in proclamation 471/98. ESS was established in 1959 under the instruction of the Minister of Trade and Industry with 14 professional staff members. Now ESS has 2439 full-time professionals and 3000 temporary workers, who are involved in different activities due to the range of studies conducted. Moreover, ESS has 25 branch offices across the nation in order to accomplish its duties and responsibilities.</p>
            <h1>Vision</h1>
            <p>“To become globally competent and the center of excellence for reliable official statistical data.”</p>
            <h1>Mission</h1>
            <p>“Our mission is generating, analyzing, and disseminating reliable statistical data timely and appropriately to the users by establishing integrated systems & undertaking standardized scientific methods and modern technologies transparently and professionally.”</p>
        </div>
    </div>

    <div class="footer">
        <div class="info">
            <div>
                <h2>Contact Us</h2>
                <p>Email: info@ess.gov.et</p>
                <p>Phone: +251-11-1234567</p>
                
            </div>
            <div>
                <h2>Location</h2>
                <p>
                    <a href="https://www.google.com/maps/place/Ethiopian+Statistics+Service,+Addis+Ababa,+Ethiopia" target="_blank" style="text-decoration: none; color: white;">
                        <i class="fas fa-map-marker-alt location-icon"></i> Find us on the map below:
                    </a>
                </p>
            </div>
            <div>
                <h2>Other Information</h2>
                <p>For more details, visit our website or contact us directly.</p>
            </div>
        </div>
        <hr>
        <p class="copyright">&copy; 2024 Ethiopian Statistics Service. All Rights Reserved.</p>
    </div>

</body>
</html>

<?php
include('db.php');

// Fetch the number of workers
$query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$result = mysqli_query($conn, $query);
$roles = [];
$counts = [];

while ($row = mysqli_fetch_assoc($result)) {
    $roles[] = $row['role'];
    $counts[] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview - HRMS</title>
    <link rel="stylesheet" href="style.css">
    <script src="js/Chart.min.js"></script>
    <style>
        /* Adjust container size */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Adjust canvas size */
        #workersChart {
            width: 100% !important;
            height: 450px !important; /* Increase height */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Company Overview</h1>
        <canvas id="workersChart"></canvas>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('workersChart').getContext('2d');
            var workersChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($roles); ?>,
                    datasets: [{
                        label: '# of Workers',
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    maintainAspectRatio: false, /* Ensure the chart scales properly */
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>

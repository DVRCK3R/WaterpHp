<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tracker = new WaterTracker($_SESSION['user_id']);
$weeklyData = $tracker->getWeeklyReport();
$monthlyData = $tracker->getMonthlyReport();
$stats = $tracker->getStats();
$householdHistory = $tracker->getHouseholdHistory(10);

// Prepare data for charts
$weeklyLabels = [];
$weeklyValues = [];
foreach ($weeklyData as $data) {
    $weeklyLabels[] = date('M d', strtotime($data['date']));
    $weeklyValues[] = $data['total'];
}

$monthlyLabels = [];
$monthlyValues = [];
foreach ($monthlyData as $data) {
    $monthlyLabels[] = date('M d', strtotime($data['date']));
    $monthlyValues[] = $data['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .btn-analytics {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .btn-analytics:hover {
            transform: translateY(-2px);
            color: white;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-line"></i> Water Usage Reports</h2>
            <a href="analytics.php" class="btn btn-analytics">
                <i class="fas fa-chart-pie"></i> Go to Advanced Analytics
            </a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-calendar-month fa-3x text-primary"></i>
                    <h3><?php echo number_format($stats['monthly_total'] ?? 0); ?> ml</h3>
                    <p>Total This Month</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-trophy fa-3x text-warning"></i>
                    <h3><?php echo $stats['best_day']['total'] ?? 0; ?> ml</h3>
                    <p>Best Day: <?php echo $stats['best_day']['date'] ?? 'N/A'; ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <i class="fas fa-calendar-day fa-3x text-success"></i>
                    <h3><?php echo $stats['total_days'] ?? 0; ?></h3>
                    <p>Total Days Tracked</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" onclick="location.href='analytics.php'">
                    <i class="fas fa-chart-line fa-3x text-info"></i>
                    <h3>Deep Dive</h3>
                    <p>Click for Analytics →</p>
                </div>
            </div>
        </div>
        
        <!-- Weekly Chart -->
        <div class="chart-container">
            <h5><i class="fas fa-chart-bar"></i> Weekly Overview</h5>
            <canvas id="weeklyChart" height="100"></canvas>
            <div class="text-center mt-3">
                <a href="analytics.php?period=7days" class="btn btn-sm btn-outline-primary">
                    View Detailed Weekly Analytics
                </a>
            </div>
        </div>
        
        <!-- Monthly Chart -->
        <div class="chart-container">
            <h5><i class="fas fa-chart-line"></i> Monthly Trend</h5>
            <canvas id="monthlyChart" height="100"></canvas>
            <div class="text-center mt-3">
                <a href="analytics.php?period=30days" class="btn btn-sm btn-outline-primary">
                    View Detailed Monthly Analytics
                </a>
            </div>
        </div>
        
        <!-- Household History -->
        <?php if (!empty($householdHistory)): ?>
        <div class="chart-container">
            <h5><i class="fas fa-history"></i> Household Usage History</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day 1</th>
                            <th>Day 2</th>
                            <th>Day 3</th>
                            <th>Total</th>
                            <th>Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($householdHistory as $record): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($record['record_date']); ?></td>
                            <td><?php echo htmlspecialchars($record['day1_liters']); ?> L</td>
                            <td><?php echo htmlspecialchars($record['day2_liters']); ?> L</td>
                            <td><?php echo htmlspecialchars($record['day3_liters']); ?> L</td>
                            <td><strong><?php echo htmlspecialchars($record['total_liters']); ?> L</strong></td>
                            <td><?php echo htmlspecialchars($record['average_liters']); ?> L</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Call to Action -->
        <div class="stat-card text-center bg-primary text-white">
            <h4>Want Deeper Insights?</h4>
            <p>Check out our advanced analytics with AI-powered recommendations and patterns</p>
            <a href="analytics.php" class="btn btn-light">
                <i class="fas fa-chart-pie"></i> Explore Analytics Dashboard
            </a>
        </div>
    </div>
    
    <script>
        // Weekly Chart
        const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weeklyLabels); ?>,
                datasets: [{
                    label: 'Water Intake (ml)',
                    data: <?php echo json_encode($weeklyValues); ?>,
                    backgroundColor: 'rgba(102, 126, 234, 0.5)',
                    borderColor: '#667eea',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'top' }
                }
            }
        });
        
        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthlyLabels); ?>,
                datasets: [{
                    label: 'Daily Intake (ml)',
                    data: <?php echo json_encode($monthlyValues); ?>,
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    </script>
</body>
</html>
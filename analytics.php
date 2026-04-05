<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tracker = new WaterTracker($_SESSION['user_id']);
$database = new Database();
$conn = $database->getConnection();

// Get date range from URL or default to last 30 days
$end_date = date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$period = isset($_GET['period']) ? $_GET['period'] : '30days';

// Initialize variables to avoid undefined errors
$dailyData = [];
$hourlyData = [];
$weeklyPatterns = [];
$goalData = [];
$householdStats = null;
$topDays = [];
$recommendations = [];
$currentPeriodTotal = 0;
$currentPeriodAvg = 0;
$previousPeriodAvg = 0;
$improvement = 0;
$goalAchievementRate = 0;
$achievedDays = 0;
$totalDays = 0;
$current_streak = 0;
$longest_streak = 0;
$peakHour = null;

try {
    // 1. Daily intake for selected period
    $dailyQuery = "SELECT 
                    DATE(record_date) as date,
                    SUM(amount_ml) as total_intake,
                    COUNT(*) as entries_count,
                    AVG(amount_ml) as avg_per_entry
                   FROM water_intake 
                   WHERE user_id = :user_id 
                   AND record_date BETWEEN :start_date AND :end_date
                   GROUP BY DATE(record_date)
                   ORDER BY date ASC";
    $dailyStmt = $conn->prepare($dailyQuery);
    $dailyStmt->bindParam(":user_id", $_SESSION['user_id']);
    $dailyStmt->bindParam(":start_date", $start_date);
    $dailyStmt->bindParam(":end_date", $end_date);
    $dailyStmt->execute();
    $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate current period totals
    $currentPeriodTotal = array_sum(array_column($dailyData, 'total_intake'));
    $currentPeriodAvg = count($dailyData) > 0 ? $currentPeriodTotal / count($dailyData) : 0;
    
    // 2. Hourly patterns (when users drink most water)
    $hourlyQuery = "SELECT 
                    HOUR(record_time) as hour,
                    SUM(amount_ml) as total_intake,
                    COUNT(*) as frequency
                   FROM water_intake 
                   WHERE user_id = :user_id 
                   AND record_date BETWEEN :start_date AND :end_date
                   GROUP BY HOUR(record_time)
                   ORDER BY hour ASC";
    $hourlyStmt = $conn->prepare($hourlyQuery);
    $hourlyStmt->bindParam(":user_id", $_SESSION['user_id']);
    $hourlyStmt->bindParam(":start_date", $start_date);
    $hourlyStmt->bindParam(":end_date", $end_date);
    $hourlyStmt->execute();
    $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate peak hour
    if (!empty($hourlyData)) {
        $maxIntake = 0;
        foreach ($hourlyData as $data) {
            if ($data['total_intake'] > $maxIntake) {
                $maxIntake = $data['total_intake'];
                $peakHour = $data['hour'];
            }
        }
    }
    
    // 3. Weekly averages
    $weeklyQuery = "SELECT 
                    DAYOFWEEK(record_date) as day_of_week,
                    AVG(amount_ml) as avg_intake
                   FROM water_intake 
                   WHERE user_id = :user_id 
                   AND record_date BETWEEN :start_date AND :end_date
                   GROUP BY DAYOFWEEK(record_date)";
    $weeklyStmt = $conn->prepare($weeklyQuery);
    $weeklyStmt->bindParam(":user_id", $_SESSION['user_id']);
    $weeklyStmt->bindParam(":start_date", $start_date);
    $weeklyStmt->bindParam(":end_date", $end_date);
    $weeklyStmt->execute();
    $weeklyPatterns = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 4. Goal achievement rate
    if (!empty($dailyData)) {
        $goalQuery = "SELECT 
                      DATE(record_date) as date,
                      SUM(amount_ml) as total_intake,
                      :daily_goal as goal_amount,
                      CASE 
                          WHEN SUM(amount_ml) >= :daily_goal THEN 1 
                          ELSE 0 
                      END as goal_achieved
                     FROM water_intake 
                     WHERE user_id = :user_id 
                     AND record_date BETWEEN :start_date AND :end_date
                     GROUP BY DATE(record_date)";
        $goalStmt = $conn->prepare($goalQuery);
        $goalStmt->bindParam(":user_id", $_SESSION['user_id']);
        $goalStmt->bindParam(":start_date", $start_date);
        $goalStmt->bindParam(":end_date", $end_date);
        $goalStmt->bindParam(":daily_goal", $_SESSION['daily_goal']);
        $goalStmt->execute();
        $goalData = $goalStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalDays = count($goalData);
        $achievedDays = 0;
        foreach ($goalData as $day) {
            if ($day['goal_achieved'] == 1) $achievedDays++;
        }
        $goalAchievementRate = $totalDays > 0 ? round(($achievedDays / $totalDays) * 100, 2) : 0;
    }
    
    // 5. Streak calculation
    $streakQuery = "SELECT DISTINCT record_date 
                   FROM water_intake 
                   WHERE user_id = :user_id 
                   ORDER BY record_date DESC";
    $streakStmt = $conn->prepare($streakQuery);
    $streakStmt->bindParam(":user_id", $_SESSION['user_id']);
    $streakStmt->execute();
    $streakDates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($streakDates)) {
        $today = new DateTime();
        $check_date = clone $today;
        while (in_array($check_date->format('Y-m-d'), $streakDates)) {
            $current_streak++;
            $check_date->modify('-1 day');
        }
        
        // Calculate longest streak
        $temp_streak = 1;
        $longest_streak = 1;
        for ($i = 0; $i < count($streakDates) - 1; $i++) {
            $date1 = new DateTime($streakDates[$i]);
            $date2 = new DateTime($streakDates[$i + 1]);
            $diff = $date1->diff($date2)->days;
            
            if ($diff == 1) {
                $temp_streak++;
                if ($temp_streak > $longest_streak) {
                    $longest_streak = $temp_streak;
                }
            } else {
                $temp_streak = 1;
            }
        }
    }
    
    // 6. Comparison with previous period (only if we have data)
    if (count($dailyData) > 0) {
        $daysInPeriod = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
        $previous_start = date('Y-m-d', strtotime($start_date . ' - ' . $daysInPeriod . ' days'));
        $previous_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
        
        $compareQuery = "SELECT 
                         SUM(amount_ml) as total,
                         COUNT(DISTINCT record_date) as days_count
                        FROM water_intake 
                        WHERE user_id = :user_id 
                        AND record_date BETWEEN :prev_start AND :prev_end";
        $compareStmt = $conn->prepare($compareQuery);
        $compareStmt->bindParam(":user_id", $_SESSION['user_id']);
        $compareStmt->bindParam(":prev_start", $previous_start);
        $compareStmt->bindParam(":prev_end", $previous_end);
        $compareStmt->execute();
        $previousPeriod = $compareStmt->fetch(PDO::FETCH_ASSOC);
        
        $previousPeriodAvg = ($previousPeriod && $previousPeriod['days_count'] > 0) ? 
                             $previousPeriod['total'] / $previousPeriod['days_count'] : 0;
        
        $improvement = $previousPeriodAvg > 0 ? 
                       round((($currentPeriodAvg - $previousPeriodAvg) / $previousPeriodAvg) * 100, 1) : 0;
    }
    
    // 7. Household analytics
    $householdQuery = "SELECT 
                       AVG(day1_liters) as avg_day1,
                       AVG(day2_liters) as avg_day2,
                       AVG(day3_liters) as avg_day3,
                       AVG(total_liters) as avg_total,
                       MIN(total_liters) as min_total,
                       MAX(total_liters) as max_total,
                       COUNT(*) as record_count
                      FROM household_usage 
                      WHERE user_id = :user_id 
                      AND record_date BETWEEN :start_date AND :end_date";
    $householdStmt = $conn->prepare($householdQuery);
    $householdStmt->bindParam(":user_id", $_SESSION['user_id']);
    $householdStmt->bindParam(":start_date", $start_date);
    $householdStmt->bindParam(":end_date", $end_date);
    $householdStmt->execute();
    $householdStats = $householdStmt->fetch(PDO::FETCH_ASSOC);
    
    // 8. Top performing days
    if (!empty($dailyData)) {
        $topDays = array_slice($dailyData, 0, 5);
        usort($topDays, function($a, $b) {
            return $b['total_intake'] - $a['total_intake'];
        });
        $topDays = array_slice($topDays, 0, 5);
    }
    
    // 9. Recommendations based on data
    $recommendations = [];
    
    if ($currentPeriodAvg > 0 && $currentPeriodAvg < $_SESSION['daily_goal']) {
        $deficit = $_SESSION['daily_goal'] - $currentPeriodAvg;
        $recommendations[] = "You're averaging " . round($currentPeriodAvg) . "ml daily, which is " . round($deficit) . "ml below your goal. Try adding an extra glass of water in the afternoon.";
    }
    
    if ($goalAchievementRate > 0 && $goalAchievementRate < 50 && $totalDays > 5) {
        $recommendations[] = "Your goal achievement rate is only {$goalAchievementRate}%. Set reminders on your phone to drink water every 2 hours.";
    }
    
    if ($peakHour) {
        $recommendations[] = "You drink most water around " . date('g:i A', strtotime("{$peakHour}:00")) . ". Consider spreading your intake more evenly throughout the day.";
    }
    
    if ($improvement > 0) {
        $recommendations[] = "Great job! You've improved by {$improvement}% compared to the previous period. Keep it up! 🎉";
    } elseif ($improvement < 0 && $improvement != 0) {
        $recommendations[] = "Your water intake has decreased by " . abs($improvement) . "%. Try to be more consistent with your hydration habits.";
    }
    
    if ($current_streak > 3) {
        $recommendations[] = "You're on a {$current_streak}-day streak! Maintain this momentum to build a lasting habit.";
    }
    
    if (empty($recommendations) && $currentPeriodTotal > 0) {
        $recommendations[] = "You're doing great! Keep tracking your water intake daily to maintain these healthy habits.";
    } elseif ($currentPeriodTotal == 0) {
        $recommendations[] = "Start tracking your water intake to get personalized recommendations and insights!";
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log($error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analytics - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 30px;
        }
        .sidebar a.active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .trend-up {
            color: #10b981;
            font-weight: bold;
        }
        .trend-down {
            color: #ef4444;
            font-weight: bold;
        }
        .insight-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .recommendation-item {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
        }
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }
        .streak-fire {
            font-size: 24px;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .no-data-message {
            text-align: center;
            padding: 40px;
            color: #6c757d;
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
            <h2><i class="fas fa-chart-pie"></i> Advanced Analytics</h2>
            <div class="streak-fire">
                <?php if ($current_streak >= 7): ?>
                    <i class="fas fa-fire text-danger"></i> 🔥 <?php echo $current_streak; ?> Day Streak!
                <?php elseif ($current_streak >= 3): ?>
                    <i class="fas fa-fire text-warning"></i> <?php echo $current_streak; ?> Day Streak
                <?php elseif ($current_streak > 0): ?>
                    <i class="fas fa-fire text-info"></i> <?php echo $current_streak; ?> Day Streak
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Date Filter -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label>Quick Select</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        <option value="7days" <?php echo $period == '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="30days" <?php echo $period == '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90days" <?php echo $period == '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="custom" <?php echo $period == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                    <a href="analytics.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (empty($dailyData) && $currentPeriodTotal == 0): ?>
            <!-- No Data Message -->
            <div class="analytics-card no-data-message">
                <i class="fas fa-chart-line fa-4x mb-3 text-muted"></i>
                <h4>No Data Available</h4>
                <p>Start tracking your water intake to see analytics and insights!</p>
                <a href="track-water.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Track Your First Entry
                </a>
            </div>
        <?php else: ?>
        
        <!-- Key Metrics -->
        <div class="row animate-fade-in">
            <div class="col-md-3">
                <div class="analytics-card text-center">
                    <i class="fas fa-calendar-day fa-2x text-primary"></i>
                    <div class="stat-number"><?php echo count($dailyData); ?></div>
                    <p>Days Tracked</p>
                    <small class="text-muted">In selected period</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card text-center">
                    <i class="fas fa-tint fa-2x text-info"></i>
                    <div class="stat-number"><?php echo number_format($currentPeriodTotal); ?> ml</div>
                    <p>Total Water Intake</p>
                    <small><?php echo number_format($currentPeriodTotal / 1000, 1); ?> Liters</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card text-center">
                    <i class="fas fa-chart-line fa-2x text-success"></i>
                    <div class="stat-number"><?php echo round($currentPeriodAvg); ?> ml</div>
                    <p>Daily Average</p>
                    <?php if ($improvement != 0 && $previousPeriodAvg > 0): ?>
                        <small class="<?php echo $improvement > 0 ? 'trend-up' : 'trend-down'; ?>">
                            <i class="fas fa-arrow-<?php echo $improvement > 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($improvement); ?>% vs previous
                        </small>
                    <?php else: ?>
                        <small class="text-muted">Start tracking to see trends</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div class="analytics-card text-center">
                    <i class="fas fa-bullseye fa-2x text-warning"></i>
                    <div class="stat-number"><?php echo $goalAchievementRate; ?>%</div>
                    <p>Goal Achievement Rate</p>
                    <small><?php echo $achievedDays; ?>/<?php echo $totalDays; ?> days</small>
                </div>
            </div>
        </div>
        
        <!-- Daily Trend Chart -->
        <div class="analytics-card animate-fade-in">
            <h5><i class="fas fa-chart-line"></i> Daily Water Intake Trend</h5>
            <div class="chart-container">
                <canvas id="dailyTrendChart"></canvas>
            </div>
            <div class="text-muted mt-2">
                <small>Goal line: <?php echo $_SESSION['daily_goal']; ?> ml per day</small>
            </div>
        </div>
        
        <div class="row animate-fade-in">
            <!-- Hourly Pattern Chart -->
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-clock"></i> Hourly Drinking Pattern</h5>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="hourlyPatternChart"></canvas>
                    </div>
                    <p class="text-muted small mt-2">
                        <i class="fas fa-info-circle"></i> Shows when you typically drink water during the day
                    </p>
                </div>
            </div>
            
            <!-- Weekly Pattern Chart -->
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-calendar-week"></i> Weekly Pattern</h5>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="weeklyPatternChart"></canvas>
                    </div>
                    <p class="text-muted small mt-2">
                        <i class="fas fa-info-circle"></i> Average intake by day of week
                    </p>
                </div>
            </div>
        </div>
        
        <div class="row animate-fade-in">
            <!-- Goal Achievement Chart -->
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-check-circle"></i> Goal Achievement Overview</h5>
                    <div class="chart-container" style="height: 250px;">
                        <canvas id="goalAchievementChart"></canvas>
                    </div>
                    <div class="mt-2">
                        <span class="badge bg-success">Met Goal</span>
                        <span class="badge bg-danger">Below Goal</span>
                    </div>
                </div>
            </div>
            
            <!-- Top Performing Days -->
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-trophy"></i> Top Performing Days</h5>
                    <?php if (!empty($topDays)): ?>
                        <div class="list-group">
                            <?php foreach ($topDays as $index => $day): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>#<?php echo $index + 1; ?></strong>
                                        <span class="ms-2"><?php echo date('F j, Y', strtotime($day['date'])); ?></span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($day['total_intake']); ?> ml</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No data available for the selected period</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Household Analytics -->
        <?php if ($householdStats && $householdStats['record_count'] > 0 && $householdStats['avg_total'] > 0): ?>
        <div class="analytics-card animate-fade-in">
            <h5><i class="fas fa-home"></i> Household Water Usage Analytics</h5>
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="stat-number"><?php echo round($householdStats['avg_total'], 1); ?> L</div>
                    <p>Average Total (3 days)</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number"><?php echo round($householdStats['avg_day1'], 1); ?> L</div>
                    <p>Average Day 1</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number"><?php echo round($householdStats['avg_day2'], 1); ?> L</div>
                    <p>Average Day 2</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="stat-number"><?php echo round($householdStats['avg_day3'], 1); ?> L</div>
                    <p>Average Day 3</p>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="alert alert-info">
                        <i class="fas fa-chart-line"></i> <strong>Range:</strong>
                        <?php echo round($householdStats['min_total'], 1); ?> L - <?php echo round($householdStats['max_total'], 1); ?> L
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-success">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong>
                        <?php if ($householdStats['avg_total'] > 500): ?>
                            Consider water-saving fixtures to reduce household consumption
                        <?php else: ?>
                            Great job keeping household water usage low!
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Insights & Recommendations -->
        <div class="row animate-fade-in">
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-lightbulb"></i> Personalized Insights</h5>
                    <div class="insight-badge">
                        <i class="fas fa-chart-line"></i> Hydration Score: 
                        <?php 
                        $hydrationScore = $currentPeriodAvg > 0 ? min(100, round(($currentPeriodAvg / $_SESSION['daily_goal']) * 100)) : 0;
                        echo $hydrationScore; ?>%
                    </div>
                    <div class="progress mb-3">
                        <div class="progress-bar bg-success" style="width: <?php echo $hydrationScore; ?>%"></div>
                    </div>
                    
                    <div class="insight-badge">
                        <i class="fas fa-calendar"></i> Consistency Score: 
                        <?php 
                        $consistencyScore = $totalDays > 0 ? round(($achievedDays / $totalDays) * 100) : 0;
                        echo $consistencyScore; ?>%
                    </div>
                    
                    <div class="insight-badge">
                        <i class="fas fa-clock"></i> Best Time to Drink: 
                        <?php 
                        if ($peakHour) {
                            $bestTime = date('g:i A', strtotime("{$peakHour}:00"));
                            echo $bestTime;
                        } else {
                            echo "Morning (8-10 AM)";
                        }
                        ?>
                    </div>
                    
                    <div class="insight-badge">
                        <i class="fas fa-chart-line"></i> Weekly Trend: 
                        <?php 
                        if ($improvement > 5) {
                            $trendText = "Improving 📈";
                        } elseif ($improvement < -5) {
                            $trendText = "Declining 📉";
                        } else {
                            $trendText = "Stable →";
                        }
                        echo $trendText;
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="analytics-card">
                    <h5><i class="fas fa-clipboard-list"></i> Smart Recommendations</h5>
                    <?php if (!empty($recommendations)): ?>
                        <?php foreach ($recommendations as $recommendation): ?>
                            <div class="recommendation-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <?php echo htmlspecialchars($recommendation); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="recommendation-item">
                            <i class="fas fa-smile-wink text-success"></i>
                            Start tracking your water intake to get personalized recommendations!
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-primary mt-3">
                        <i class="fas fa-download"></i> <strong>Export Report:</strong>
                        <a href="#" class="alert-link" onclick="exportCSV()">Export as CSV</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        <?php if (!empty($dailyData)): ?>
        // Prepare data for charts
        const dailyLabels = <?php echo json_encode(array_column($dailyData, 'date')); ?>;
        const dailyValues = <?php echo json_encode(array_column($dailyData, 'total_intake')); ?>;
        const dailyGoal = <?php echo $_SESSION['daily_goal']; ?>;
        
        // Daily Trend Chart
        const dailyCtx = document.getElementById('dailyTrendChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Daily Water Intake (ml)',
                        data: dailyValues,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#667eea'
                    },
                    {
                        label: 'Daily Goal',
                        data: Array(dailyLabels.length).fill(dailyGoal),
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                let value = context.raw;
                                let percentage = (value / dailyGoal * 100).toFixed(1);
                                return `${label}: ${value.toLocaleString()} ml (${percentage}% of goal)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Water Intake (ml)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
        
        // Hourly Pattern Chart
        <?php 
        $hours = array_fill(0, 24, 0);
        foreach ($hourlyData as $data) {
            $hours[$data['hour']] = $data['total_intake'];
        }
        $hourLabels = array_map(function($h) { 
            return date('g A', mktime($h, 0, 0, 1, 1, 2024)); 
        }, array_keys($hours));
        ?>
        const hourlyLabels = <?php echo json_encode($hourLabels); ?>;
        const hourlyValues = <?php echo json_encode(array_values($hours)); ?>;
        
        const hourlyCtx = document.getElementById('hourlyPatternChart').getContext('2d');
        new Chart(hourlyCtx, {
            type: 'bar',
            data: {
                labels: hourlyLabels,
                datasets: [{
                    label: 'Water Intake (ml)',
                    data: hourlyValues,
                    backgroundColor: 'rgba(102, 126, 234, 0.6)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Total Intake (ml)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Time of Day'
                        }
                    }
                }
            }
        });
        
        // Weekly Pattern Chart
        const weekDays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const weeklyAvg = array_fill(0, 7, 0);
        <?php foreach ($weeklyPatterns as $pattern): ?>
            weeklyAvg[<?php echo $pattern['day_of_week'] - 1; ?>] = <?php echo round($pattern['avg_intake']); ?>;
        <?php endforeach; ?>
        
        const weeklyCtx = document.getElementById('weeklyPatternChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'radar',
            data: {
                labels: weekDays,
                datasets: [{
                    label: 'Average Intake (ml)',
                    data: weeklyAvg,
                    backgroundColor: 'rgba(102, 126, 234, 0.2)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    pointBackgroundColor: '#764ba2',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#764ba2'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Water Intake (ml)'
                        }
                    }
                }
            }
        });
        
        // Goal Achievement Chart
        <?php if (!empty($goalData)): ?>
        const goalLabels = <?php echo json_encode(array_column($goalData, 'date')); ?>;
        const goalValues = <?php echo json_encode(array_column($goalData, 'total_intake')); ?>;
        const goalStatus = <?php echo json_encode(array_column($goalData, 'goal_achieved')); ?>;
        
        const goalCtx = document.getElementById('goalAchievementChart').getContext('2d');
        new Chart(goalCtx, {
            type: 'bar',
            data: {
                labels: goalLabels,
                datasets: [{
                    label: 'Daily Intake (ml)',
                    data: goalValues,
                    backgroundColor: goalStatus.map(status => status == 1 ? '#10b981' : '#ef4444'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let value = context.raw;
                                let status = goalStatus[context.dataIndex] == 1 ? '✅ Goal Met!' : '❌ Goal Not Met';
                                return `${value.toLocaleString()} ml - ${status}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Water Intake (ml)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        <?php endif; ?>
        
        // Export CSV function
        function exportCSV() {
            <?php if (!empty($dailyData)): ?>
            let csv = "Date,Water Intake (ml),Goal Achieved\n";
            <?php foreach ($dailyData as $day): ?>
                csv += "<?php echo $day['date']; ?>,<?php echo $day['total_intake']; ?>,<?php echo $day['total_intake'] >= $_SESSION['daily_goal'] ? 'Yes' : 'No'; ?>\n";
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'water_analytics_<?php echo date('Y-m-d'); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            <?php else: ?>
            alert('No data available to export');
            <?php endif; ?>
        }
    </script>
</body>
</html>
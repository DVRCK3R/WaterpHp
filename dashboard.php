<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tracker = new WaterTracker($_SESSION['user_id']);
$todayIntake = $tracker->getTodayIntake();
$todayRecords = $tracker->getTodayIntakeRecords();
$weeklyReport = $tracker->getWeeklyReport();
$dailyGoal = $_SESSION['daily_goal'];
$progress = $todayIntake > 0 ? min(($todayIntake / $dailyGoal) * 100, 100) : 0;

// Get recent achievements
$stats = $tracker->getStats();
$currentStreak = 0;
$database = new Database();
$conn = $database->getConnection();

$streakQuery = "SELECT DISTINCT record_date FROM water_intake 
                WHERE user_id = :user_id ORDER BY record_date DESC";
$streakStmt = $conn->prepare($streakQuery);
$streakStmt->bindParam(":user_id", $_SESSION['user_id']);
$streakStmt->execute();
$streakDates = $streakStmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($streakDates)) {
    $today = new DateTime();
    $check_date = clone $today;
    while (in_array($check_date->format('Y-m-d'), $streakDates)) {
        $currentStreak++;
        $check_date->modify('-1 day');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .progress-circle {
            width: 120px;
            height: 120px;
            margin: 0 auto;
            position: relative;
        }
        
        .water-drop {
            color: #3498db;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            transition: transform 0.2s;
        }
        
        .btn-add:hover {
            transform: scale(1.05);
            color: white;
        }
        
        .navbar-custom {
            background: white;
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .achievement-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            margin: 5px;
        }
        
        .quick-add-btn {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .quick-add-btn:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="navbar-custom">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-link d-md-none" id="menuToggle">
                        <i class="fas fa-bars fa-lg"></i>
                    </button>
                    <h3 class="d-inline-block ms-2">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </h3>
                </div>
                <div>
                    <span class="badge bg-info me-2">
                        <i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
                    </span>
                    <?php if ($currentStreak > 0): ?>
                        <span class="badge bg-danger">
                            <i class="fas fa-fire"></i> <?php echo $currentStreak; ?> Day Streak!
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card text-center" onclick="location.href='track-water.php'">
                    <i class="fas fa-tint fa-3x water-drop"></i>
                    <h2 class="mt-2"><?php echo number_format($todayIntake); ?> ml</h2>
                    <p>Today's Water Intake</p>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <small>Goal: <?php echo number_format($dailyGoal); ?> ml (<?php echo round($progress); ?>%)</small>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card text-center" onclick="location.href='analytics.php'">
                    <i class="fas fa-chart-line fa-3x text-info"></i>
                    <h2 class="mt-2"><?php echo number_format($stats['monthly_total'] ?? 0); ?> ml</h2>
                    <p>Total This Month</p>
                    <i class="fas fa-arrow-right"></i> View Analytics
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card text-center" onclick="location.href='reports.php'">
                    <i class="fas fa-calendar-week fa-3x text-warning"></i>
                    <h2 class="mt-2"><?php echo count($weeklyReport); ?> days</h2>
                    <p>Tracked This Week</p>
                    <i class="fas fa-chart-bar"></i> View Reports
                </div>
            </div>
        </div>
        
        <!-- Quick Add Section -->
        <div class="stat-card">
            <h5><i class="fas fa-plus-circle"></i> Quick Add Water</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn quick-add-btn flex-grow-1" onclick="quickAdd(250)">
                            <i class="fas fa-glass-water"></i> 250ml
                        </button>
                        <button class="btn quick-add-btn flex-grow-1" onclick="quickAdd(500)">
                            <i class="fas fa-bottle-water"></i> 500ml
                        </button>
                        <button class="btn quick-add-btn flex-grow-1" onclick="quickAdd(1000)">
                            <i class="fas fa-fill-drip"></i> 1000ml
                        </button>
                    </div>
                </div>
                <div class="col-md-6">
                    <form id="customAddForm" class="d-flex gap-2">
                        <input type="number" id="customAmount" class="form-control" placeholder="Custom ml" required>
                        <button type="submit" class="btn btn-add">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Today's Records -->
            <?php if (!empty($todayRecords)): ?>
                <div class="mt-3">
                    <small class="text-muted">Today's entries:</small>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <?php foreach ($todayRecords as $record): ?>
                            <span class="badge bg-primary">
                                <?php echo date('g:i A', strtotime($record['record_time'])); ?>: 
                                <?php echo $record['amount_ml']; ?>ml
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- Weekly Chart -->
            <div class="col-md-8">
                <div class="stat-card">
                    <h5><i class="fas fa-chart-line"></i> Weekly Overview</h5>
                    <canvas id="weeklyChart" height="200"></canvas>
                    <div class="text-center mt-2">
                        <a href="analytics.php" class="btn btn-sm btn-outline-primary">
                            View Detailed Analytics <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Achievements & Tips -->
            <div class="col-md-4">
                <div class="stat-card">
                    <h5><i class="fas fa-trophy"></i> Achievements</h5>
                    <?php if ($currentStreak >= 7): ?>
                        <div class="achievement-badge">
                            <i class="fas fa-fire"></i> 7-Day Warrior
                        </div>
                    <?php endif; ?>
                    <?php if ($progress >= 100): ?>
                        <div class="achievement-badge">
                            <i class="fas fa-check-circle"></i> Daily Goal Master
                        </div>
                    <?php endif; ?>
                    <?php if ($stats['monthly_total'] > 50000): ?>
                        <div class="achievement-badge">
                            <i class="fas fa-tint"></i> 50K Club
                        </div>
                    <?php endif; ?>
                    <?php if (empty($todayRecords)): ?>
                        <p class="text-muted mt-2">No achievements yet. Start tracking to earn badges!</p>
                    <?php endif; ?>
                    
                    <hr>
                    <h6><i class="fas fa-lightbulb text-warning"></i> Tip of the Day</h6>
                    <p class="small">
                        <?php
                        $tips = [
                            "Drink a glass of water when you wake up to kickstart your metabolism.",
                            "Keep a water bottle on your desk to remind yourself to drink.",
                            "Add fruit slices to your water for natural flavor.",
                            "Set a reminder on your phone every 2 hours to drink water.",
                            "Drink water before meals to help with portion control."
                        ];
                        echo $tips[array_rand($tips)];
                        ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Household Usage Summary -->
        <div class="stat-card" onclick="location.href='track-household.php'">
            <h5><i class="fas fa-home"></i> Household Usage Summary</h5>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="text-muted">Last Recording</div>
                    <?php
                    $lastRecord = $tracker->getHouseholdHistory(1);
                    if (!empty($lastRecord)):
                    ?>
                        <strong><?php echo $lastRecord[0]['total_liters']; ?> L</strong>
                        <small>(3-day total)</small>
                    <?php else: ?>
                        <p class="text-muted">No records yet</p>
                        <a href="track-household.php" class="btn btn-sm btn-primary">Add First Record</a>
                    <?php endif; ?>
                </div>
                <div class="col-md-8">
                    <div class="text-muted">Quick Stats</div>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-info" style="width: 65%"></div>
                    </div>
                    <small>Average household: 350-450L per 3 days</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div class="toast-notification" id="toast"></div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Weekly chart data
        const weeklyLabels = <?php 
            $labels = [];
            $values = [];
            foreach ($weeklyReport as $report) {
                $labels[] = date('D', strtotime($report['date']));
                $values[] = $report['total'];
            }
            echo json_encode($labels);
        ?>;
        const weeklyValues = <?php echo json_encode($values); ?>;
        
        // Create weekly chart
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: weeklyLabels,
                datasets: [{
                    label: 'Water Intake (ml)',
                    data: weeklyValues,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
        
        // Quick add function
        function quickAdd(amount) {
            fetch('track-water.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'amount_ml=' + amount + '&quick_add=1'
            })
            .then(response => response.text())
            .then(() => {
                showToast('Added ' + amount + 'ml of water! 💧', 'success');
                setTimeout(() => location.reload(), 1500);
            })
            .catch(error => {
                showToast('Error adding water intake', 'error');
            });
        }
        
        // Custom add form
        document.getElementById('customAddForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('customAmount').value;
            if (amount && amount > 0) {
                quickAdd(amount);
            } else {
                showToast('Please enter a valid amount', 'error');
            }
        });
        
        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.innerHTML = `
                <div class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            setTimeout(() => {
                toast.innerHTML = '';
            }, 3000);
        }
        
        // Mobile menu toggle
        document.getElementById('menuToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
        
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
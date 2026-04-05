<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user = $auth->getUserById($_SESSION['user_id']);
$message = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $result = $auth->updateProfile(
            $_SESSION['user_id'],
            $_POST['full_name'],
            $_POST['email'],
            intval($_POST['daily_goal'])
        );
        $message = $result;
        if ($result['success']) {
            $user = $auth->getUserById($_SESSION['user_id']);
        }
    }
}

$tracker = new WaterTracker($_SESSION['user_id']);
$todayIntake = $tracker->getTodayIntake();
$stats = $tracker->getStats();

// Calculate projected savings based on goal
$monthlyProjection = ($_SESSION['daily_goal'] * 30) / 1000; // in liters
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .avatar i {
            font-size: 60px;
            color: white;
        }
        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
        <div class="profile-card">
            <div class="avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            
            <h3 class="text-center"><?php echo htmlspecialchars($user['full_name']); ?></h3>
            <p class="text-center text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['success'] ? 'success' : 'danger'; ?>">
                    <?php echo htmlspecialchars($message['message']); ?>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Daily Hydration Goal (ml)</label>
                                <input type="number" name="daily_goal" class="form-control" 
                                       value="<?php echo $user['daily_goal']; ?>" required>
                                <small class="text-muted">Recommended: 2000-3000 ml per day</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Username</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Today's progress: 
                            <?php echo number_format($todayIntake); ?> / <?php echo number_format($user['daily_goal']); ?> ml
                            (<?php echo round(($todayIntake / $user['daily_goal']) * 100); ?>%)
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                        
                        <a href="analytics.php" class="btn btn-outline-primary">
                            <i class="fas fa-chart-line"></i> View Your Analytics
                        </a>
                    </form>
                </div>
                
                <div class="col-md-4">
                    <div class="insight-card">
                        <h6><i class="fas fa-chart-line"></i> Your Stats</h6>
                        <p><strong>Total tracked days:</strong> <?php echo $stats['total_days'] ?? 0; ?></p>
                        <p><strong>Best day:</strong> <?php echo number_format($stats['best_day']['total'] ?? 0); ?> ml</p>
                        <p><strong>Monthly total:</strong> <?php echo number_format($stats['monthly_total'] ?? 0); ?> ml</p>
                        <hr>
                        <h6><i class="fas fa-bullseye"></i> Goal Insights</h6>
                        <p>Monthly projection at current goal:<br>
                        <strong><?php echo number_format($monthlyProjection); ?> liters</strong></p>
                        <a href="analytics.php" class="btn btn-sm btn-light w-100">
                            View Detailed Analytics →
                        </a>
                    </div>
                    
                    <div class="insight-card mt-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h6><i class="fas fa-lightbulb"></i> Pro Tip</h6>
                        <p class="small">Based on your profile, we recommend spreading your water intake throughout the day. Try drinking 250ml every hour!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
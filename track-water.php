<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tracker = new WaterTracker($_SESSION['user_id']);
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['amount_ml'])) {
        $amount = isset($_POST['quick_add']) ? 250 : intval($_POST['amount_ml']);
        $result = $tracker->addWaterIntake($amount, $_POST['notes'] ?? '');
        $message = $result;
    }
}

$todayIntake = $tracker->getTodayIntake();
$dailyGoal = $_SESSION['daily_goal'];
?>
<?php
// Add this at the very beginning of track-water.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once 'includes/auth.php';
    require_once 'includes/functions.php';
    
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    }
    
    $tracker = new WaterTracker($_SESSION['user_id']);
    $amount = isset($_POST['amount_ml']) ? intval($_POST['amount_ml']) : 0;
    
    if ($amount > 0) {
        $result = $tracker->addWaterIntake($amount, '');
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Water - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f0f2f5;
        }
        .water-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
        }
        .amount-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px;
            margin: 5px;
            border-radius: 10px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .amount-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <a href="dashboard.php" class="btn btn-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                
                <div class="water-card text-center">
                    <i class="fas fa-tint fa-4x"></i>
                    <h2 class="mt-3">Track Your Water Intake</h2>
                    <p>Stay hydrated! Track every glass you drink</p>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message['success'] ? 'success' : 'danger'; ?>">
                        <?php echo htmlspecialchars($message['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Today's Progress</h5>
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar bg-success" style="width: <?php echo min(($todayIntake/$dailyGoal)*100, 100); ?>%">
                                <?php echo $todayIntake; ?> / <?php echo $dailyGoal; ?> ml
                            </div>
                        </div>
                        
                        <h5 class="mt-4">Quick Add Amounts</h5>
                        <div class="text-center">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="amount_ml" value="250">
                                <button type="submit" name="quick_add" class="amount-btn">
                                    <i class="fas fa-glass-water"></i> 250ml (Glass)
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="amount_ml" value="500">
                                <button type="submit" class="amount-btn">
                                    <i class="fas fa-bottle-water"></i> 500ml (Bottle)
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="amount_ml" value="1000">
                                <button type="submit" class="amount-btn">
                                    <i class="fas fa-fill-drip"></i> 1000ml (1 Liter)
                                </button>
                            </form>
                        </div>
                        
                        <hr>
                        
                        <h5>Custom Amount</h5>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="number" name="amount_ml" class="form-control" 
                                           placeholder="Enter amount in ml" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                            </div>
                            <div class="mt-2">
                                <textarea name="notes" class="form-control" rows="2" 
                                          placeholder="Notes (optional)"></textarea>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Hydration Tips</h5>
                        <ul>
                            <li>Drink a glass of water when you wake up</li>
                            <li>Keep a water bottle at your desk</li>
                            <li>Drink water before each meal</li>
                            <li>Set reminders on your phone</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
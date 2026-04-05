<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$tracker = new WaterTracker($_SESSION['user_id']);
$result = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $result = $tracker->addHouseholdUsage($_POST['day1'], $_POST['day2'], 
                                          $_POST['day3'], $_POST['notes'] ?? '');
}

$history = $tracker->getHouseholdHistory(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Household Usage - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <a href="dashboard.php" class="btn btn-secondary mb-3">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-home"></i> Track Household Water Usage</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($result): ?>
                            <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?>">
                                <?php echo htmlspecialchars($result['message']); ?>
                                <?php if (isset($result['total'])): ?>
                                    <br><strong>Total:</strong> <?php echo $result['total']; ?> liters
                                    <br><strong>Average:</strong> <?php echo $result['average']; ?> liters/day
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label>Day 1 Usage (liters)</label>
                                    <input type="number" step="0.01" name="day1" class="form-control" 
                                           placeholder="e.g., 150.5" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Day 2 Usage (liters)</label>
                                    <input type="number" step="0.01" name="day2" class="form-control" 
                                           placeholder="e.g., 120.75" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label>Day 3 Usage (liters)</label>
                                    <input type="number" step="0.01" name="day3" class="form-control" 
                                           placeholder="e.g., 135.25" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Any notes about this reading..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Usage Data
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($history)): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-history"></i> Recent History</h5>
                        </div>
                        <div class="card-body">
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
                                        <?php foreach ($history as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['record_date']); ?></td>
                                                <td><?php echo htmlspecialchars($record['day1_liters']); ?></td>
                                                <td><?php echo htmlspecialchars($record['day2_liters']); ?></td>
                                                <td><?php echo htmlspecialchars($record['day3_liters']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($record['total_liters']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($record['average_liters']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
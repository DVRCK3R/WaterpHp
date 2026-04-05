<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

class WaterTracker {
    private $conn;
    private $user_id;
    
    public function __construct($user_id) {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->user_id = $user_id;
    }
    
    // Track personal water intake
    public function addWaterIntake($amount_ml, $notes = '') {
        $amount_ml = filter_var($amount_ml, FILTER_VALIDATE_INT);
        if (!$amount_ml || $amount_ml <= 0) {
            return ["success" => false, "message" => "Invalid amount"];
        }
        
        $query = "INSERT INTO water_intake (user_id, amount_ml, record_date, record_time, notes) 
                  VALUES (:user_id, :amount_ml, CURDATE(), CURTIME(), :notes)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":amount_ml", $amount_ml);
        $stmt->bindParam(":notes", $notes);
        
        if ($stmt->execute()) {
            return ["success" => true, "message" => "Water intake recorded!"];
        }
        return ["success" => false, "message" => "Failed to record"];
    }
    
    // Get today's total intake
    public function getTodayIntake() {
        $query = "SELECT SUM(amount_ml) as total FROM water_intake 
                  WHERE user_id = :user_id AND record_date = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }
    
    // Get today's intake records
    public function getTodayIntakeRecords() {
        $query = "SELECT * FROM water_intake 
                  WHERE user_id = :user_id AND record_date = CURDATE() 
                  ORDER BY record_time DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Track household usage
    public function addHouseholdUsage($day1, $day2, $day3, $notes = '') {
        // Sanitize inputs
        $day1 = filter_var($day1, FILTER_VALIDATE_FLOAT);
        $day2 = filter_var($day2, FILTER_VALIDATE_FLOAT);
        $day3 = filter_var($day3, FILTER_VALIDATE_FLOAT);
        
        if ($day1 === false || $day2 === false || $day3 === false) {
            return ["success" => false, "message" => "Invalid numeric values"];
        }
        
        $total = round($day1 + $day2 + $day3, 2);
        $average = round($total / 3, 2);
        
        $query = "INSERT INTO household_usage (user_id, day1_liters, day2_liters, day3_liters, 
                  total_liters, average_liters, record_date, notes) 
                  VALUES (:user_id, :day1, :day2, :day3, :total, :average, CURDATE(), :notes)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":day1", $day1);
        $stmt->bindParam(":day2", $day2);
        $stmt->bindParam(":day3", $day3);
        $stmt->bindParam(":total", $total);
        $stmt->bindParam(":average", $average);
        $stmt->bindParam(":notes", $notes);
        
        if ($stmt->execute()) {
            return ["success" => true, "message" => "Household usage recorded!", 
                    "total" => $total, "average" => $average];
        }
        return ["success" => false, "message" => "Failed to record"];
    }
    
    // Get weekly report
    public function getWeeklyReport() {
        $query = "SELECT DATE(record_date) as date, SUM(amount_ml) as total 
                  FROM water_intake 
                  WHERE user_id = :user_id 
                  AND record_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                  GROUP BY DATE(record_date)
                  ORDER BY record_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get household history
    public function getHouseholdHistory($limit = 10) {
        $query = "SELECT * FROM household_usage 
                  WHERE user_id = :user_id 
                  ORDER BY record_date DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Update daily goal
    public function updateDailyGoal($goal) {
        $goal = filter_var($goal, FILTER_VALIDATE_INT);
        if (!$goal || $goal < 500 || $goal > 10000) {
            return ["success" => false, "message" => "Goal must be between 500ml and 10000ml"];
        }
        
        $query = "UPDATE users SET daily_goal = :goal WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":goal", $goal);
        $stmt->bindParam(":user_id", $this->user_id);
        
        if ($stmt->execute()) {
            $_SESSION['daily_goal'] = $goal;
            return ["success" => true, "message" => "Daily goal updated!"];
        }
        return ["success" => false, "message" => "Failed to update goal"];
    }
    
    // Get monthly report
    public function getMonthlyReport() {
        $query = "SELECT DATE(record_date) as date, SUM(amount_ml) as total 
                  FROM water_intake 
                  WHERE user_id = :user_id 
                  AND record_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                  GROUP BY DATE(record_date)
                  ORDER BY record_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get stats
    public function getStats() {
        // Monthly total
        $monthlyQuery = "SELECT SUM(amount_ml) as total FROM water_intake 
                         WHERE user_id = :user_id AND record_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        $stmt = $this->conn->prepare($monthlyQuery);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $monthly = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Best day
        $bestDayQuery = "SELECT DATE(record_date) as date, SUM(amount_ml) as total 
                         FROM water_intake 
                         WHERE user_id = :user_id 
                         GROUP BY DATE(record_date) 
                         ORDER BY total DESC LIMIT 1";
        $stmt = $this->conn->prepare($bestDayQuery);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $bestDay = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Total days
        $totalDaysQuery = "SELECT COUNT(DISTINCT DATE(record_date)) as days FROM water_intake WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($totalDaysQuery);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $totalDays = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'monthly_total' => $monthly['total'] ?? 0,
            'best_day' => $bestDay ? ['date' => $bestDay['date'], 'total' => $bestDay['total']] : ['date' => null, 'total' => 0],
            'total_days' => $totalDays['days'] ?? 0
        ];
    }
}
?>
<?php
// includes/auth.php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    public function register($username, $email, $password, $full_name) {
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            return ["success" => false, "message" => "All fields are required"];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Invalid email format"];
        }
        
        if (strlen($password) < 6) {
            return ["success" => false, "message" => "Password must be at least 6 characters"];
        }
        
        // Check if user exists
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ["success" => false, "message" => "Username or email already exists"];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $query = "INSERT INTO users (username, email, password, full_name) 
                  VALUES (:username, :email, :password, :full_name)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":full_name", $full_name);
        
        if ($stmt->execute()) {
            return ["success" => true, "message" => "Registration successful! Please login."];
        }
        
        return ["success" => false, "message" => "Registration failed"];
    }
    
    public function login($username, $password) {
        $query = "SELECT id, username, email, password, full_name, role, daily_goal 
                  FROM users WHERE username = :username OR email = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $user['password'])) {
                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(":id", $user['id']);
                $updateStmt->execute();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['daily_goal'] = $user['daily_goal'];
                
                return ["success" => true, "message" => "Login successful!", "role" => $user['role']];
            }
        }
        
        return ["success" => false, "message" => "Invalid username or password"];
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function logout() {
        session_destroy();
        return ["success" => true, "message" => "Logged out successfully"];
    }
    
    public function getUserById($user_id) {
        $query = "SELECT id, username, email, full_name, daily_goal FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateProfile($user_id, $full_name, $email, $daily_goal) {
        if (empty($full_name) || empty($email)) {
            return ["success" => false, "message" => "All fields are required"];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Invalid email"];
        }
        
        if ($daily_goal < 500 || $daily_goal > 10000) {
            return ["success" => false, "message" => "Daily goal must be between 500 and 10000 ml"];
        }
        
        $query = "UPDATE users SET full_name = :full_name, email = :email, daily_goal = :daily_goal WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":full_name", $full_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":daily_goal", $daily_goal);
        $stmt->bindParam(":id", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['daily_goal'] = $daily_goal;
            return ["success" => true, "message" => "Profile updated successfully"];
        }
        
        return ["success" => false, "message" => "Update failed"];
    }
}
?>
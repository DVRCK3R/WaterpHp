<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

// New admin credentials
$username = 'your_admin_username';
$email = 'your_email@example.com';
$password = password_hash('YourDesiredPassword123!', PASSWORD_DEFAULT);
$full_name = 'Your Name';
$role = 'admin';

try {
    $query = "INSERT INTO users (username, email, password, full_name, role, daily_goal) 
              VALUES (:username, :email, :password, :full_name, :role, 2000)";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":username", $username);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $password);
    $stmt->bindParam(":full_name", $full_name);
    $stmt->bindParam(":role", $role);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully!<br>";
        echo "Username: " . $username . "<br>";
        echo "Password: YourDesiredPassword123!<br>";
    } else {
        echo "Failed to create admin user.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
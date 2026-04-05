<?php
require_once 'includes/auth.php';
$auth = new Auth();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $result = $auth->register($_POST['username'], $_POST['email'], 
                              $_POST['password'], $_POST['full_name']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Water Tracker Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px;
        }
        .card-header h2 {
            margin: 0;
            font-weight: bold;
        }
        .card-body {
            padding: 40px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 16px;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            color: white;
        }
        .alert {
            border-radius: 10px;
        }
        .water-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="card">
            <div class="card-header">
                <div class="water-icon">
                    <i class="fas fa-tint"></i>
                </div>
                <h2>Create Account</h2>
                <p>Join us in tracking your water usage</p>
            </div>
            <div class="card-body">
                <?php if (isset($result)): ?>
                    <div class="alert alert-<?php echo $result['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($result['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" class="form-control" required 
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-circle"></i> Username</label>
                        <input type="text" name="username" class="form-control" required 
                               placeholder="Choose a username">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" class="form-control" required 
                               placeholder="your@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" required 
                               placeholder="Minimum 6 characters">
                    </div>
                    
                    <button type="submit" class="btn btn-register">
                        <i class="fas fa-user-plus"></i> Register Now
                    </button>
                </form>
                
                <hr>
                <p class="text-center mb-0">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
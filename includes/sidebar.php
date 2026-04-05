<?php
// includes/sidebar.php - Consistent sidebar for all pages
?>
<div class="sidebar">
    <div class="text-center py-4">
        <i class="fas fa-tint fa-3x"></i>
        <h4 class="mt-2">Water Tracker</h4>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
    </div>
    <nav>
        <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="track-water.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'track-water.php' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i> Track Water
        </a>
        <a href="track-household.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'track-household.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Household Usage
        </a>
        <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Reports
        </a>
        <a href="analytics.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> Analytics
        </a>
        <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </nav>
</div>

<style>
    .sidebar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        color: white;
        position: fixed;
        width: 250px;
        transition: all 0.3s;
        z-index: 1000;
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
    
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        .sidebar.active {
            transform: translateX(0);
        }
    }
</style>
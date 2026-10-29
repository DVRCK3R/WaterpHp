# Water Tracker Pro

Water Tracker Pro is a PHP web application for tracking personal hydration and household water use. Users can set a daily intake goal, review reports and analytics, and view their recent household usage history. An admin page provides a user list and basic system-wide statistics.

## Features

- Register and sign in with a username or email address.
- Record daily water intake in milliliters, including quick-add amounts.
- Set a personal daily hydration goal.
- Record three-day household water usage in liters.
- Review weekly and monthly reports, charts, and advanced analytics.
- View and manage access to the admin dashboard.

## Requirements

- PHP with the **PDO MySQL** extension enabled.
- MySQL or MariaDB.
- A local PHP web server, such as Apache via XAMPP.
- An internet connection for the Bootstrap, Font Awesome, and Chart.js assets loaded from CDNs.

## Setup

1. Clone or download this repository into your web server's document root. For XAMPP, that is typically `C:\xampp\htdocs\WaterpHp`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Create the database and tables below using phpMyAdmin or the MySQL command line:

   ```sql
   CREATE DATABASE water_tracker
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;

   USE water_tracker;

   CREATE TABLE users (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
       username VARCHAR(50) NOT NULL UNIQUE,
       email VARCHAR(255) NOT NULL UNIQUE,
       password VARCHAR(255) NOT NULL,
       full_name VARCHAR(255) NOT NULL,
       role VARCHAR(20) NOT NULL DEFAULT 'user',
       daily_goal INT NOT NULL DEFAULT 2000,
       created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
       last_login DATETIME NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   CREATE TABLE water_intake (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
       user_id INT UNSIGNED NOT NULL,
       amount_ml INT NOT NULL,
       record_date DATE NOT NULL,
       record_time TIME NOT NULL,
       notes TEXT NULL,
       INDEX idx_water_user_date (user_id, record_date),
       CONSTRAINT fk_water_intake_user
           FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

   CREATE TABLE household_usage (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
       user_id INT UNSIGNED NOT NULL,
       day1_liters DECIMAL(10,2) NOT NULL,
       day2_liters DECIMAL(10,2) NOT NULL,
       day3_liters DECIMAL(10,2) NOT NULL,
       total_liters DECIMAL(10,2) NOT NULL,
       average_liters DECIMAL(10,2) NOT NULL,
       record_date DATE NOT NULL,
       notes TEXT NULL,
       INDEX idx_household_user_date (user_id, record_date),
       CONSTRAINT fk_household_usage_user
           FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   ```

4. Check the database settings in [`config/database.php`](config/database.php). The defaults are host `localhost`, database `water_tracker`, username `root`, and an empty password; update them to match your local MySQL credentials.
5. Open `http://localhost/WaterpHp/` in your browser and register an account.

## Creating an administrator

New registrations have the default `user` role. To grant admin access, register an account and update its role in MySQL, using that account's username:

```sql
UPDATE users
SET role = 'admin'
WHERE username = 'your_username';
```

Sign out and back in for the new role to take effect. Admin accounts are redirected to the user-management page after login.

`create__admin.php` is a development helper containing placeholder credentials. Do not rely on it as a production admin setup flow or leave it publicly accessible on a deployed server.

## Project structure

```text
admin/       Admin user-management page
assets/      CSS and JavaScript
config/      Database connection and session initialization
includes/    Authentication, tracking functions, and shared layout
*.php        Application pages (registration, tracking, reports, analytics)
```

## Notes

- Passwords are stored using PHP's `password_hash` and checked with `password_verify`.
- Water intake is recorded in milliliters; household usage is recorded in liters.
- This project does not include a migration system or automated test suite.

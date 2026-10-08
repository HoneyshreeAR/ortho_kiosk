<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Fetch admin from database
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify password (supports both plain text and hashed)
    $valid = false;
    if ($admin) {
        // Check if password is hashed (starts with $2y$)
        if (password_get_info($admin['password'])['algo'] || strpos($admin['password'], '$2y$') === 0) {
            $valid = password_verify($password, $admin['password']);
        } else {
            // Plain text comparison
            $valid = ($password === $admin['password']);
        }
    }
    
    if ($valid) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];
        header("Location: admin_dashboard.php");
        exit;
    } else {
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: admin_login.php");
        exit;
    }
} else {
    header("Location: admin_login.php");
    exit;
}
?>
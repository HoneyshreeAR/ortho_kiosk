<?php
session_start();
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | OrthoKiosk</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #2c5f8a;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #f5f7fa 0%, #e9edf2 100%); min-height: 100vh;">
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Login</h1>
            <p>OrthoKiosk Management Panel</p>
        </div>
        <?php if(isset($_SESSION['login_error'])): ?>
            <div class="error"><?= htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?></div>
        <?php endif; ?>
        <form method="POST" action="admin_auth.php">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Login</button>
        </form>
        <p style="text-align:center; margin-top:10px;">
            <a href="index.php" style="color:#2c5f8a;">← Patient Portal</a>
        </p>
    </div>
</body>
</html>
<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Mibesa CarRental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f4f7fc; }
        .login-container { background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; }
        .login-container h2 { font-size: 1.8rem; color: #2c3e50; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 0.95rem; color: #34495e; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1rem; }
        .form-group input:focus { border-color: #45a29e; outline: none; }
        .error { color: #d92d20; font-size: 0.85rem; text-align: center; margin-bottom: 15px; }
        .login-btn { width: 100%; padding: 12px; background-color: #0b0c10; color: #66fcf1; border: none; border-radius: 6px; font-size: 1rem; cursor: pointer; transition: opacity 0.3s; }
        .login-btn:hover { opacity: 0.9; }
        .footer { position: absolute; bottom: 10px; text-align: center; color: #7f8c8d; font-size: 0.85rem; width: 100%; }
        @media (max-width: 480px) {
            .login-container { padding: 20px; max-width: 90%; }
            .login-container h2 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="admin_login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required aria-describedby="emailError">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required aria-describedby="passwordError">
            </div>
            <button type="submit" class="login-btn" aria-label="Login">Login</button>
        </form>
    </div>
    <footer class="footer">
        <p>© 2025 Mibesa CarRental. All rights reserved.</p>
    </footer>
</body>
</html>
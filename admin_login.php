<?php
header('Content-Type: application/json');
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }
    if (empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Password is required']);
        exit;
    }

    // Replace with actual admin table and credentials check
    $stmt = $pdo->prepare("SELECT id, password FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email or password']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
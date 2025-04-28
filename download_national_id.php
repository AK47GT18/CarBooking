<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$user_id = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
if ($user_id === false) {
    http_response_code(400);
    echo "Invalid user ID.";
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT national_id, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !$user['national_id']) {
        http_response_code(404);
        echo "National ID not found.";
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="national_id_' . htmlspecialchars($user['username']) . '.pdf"');
    header('Content-Length: ' . strlen($user['national_id']));
    echo $user['national_id'];
    exit;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo "An error occurred.";
    exit;
}
?>
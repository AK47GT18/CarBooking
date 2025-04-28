<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not set
/*
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    // Regenerate CSRF token after successful validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}
*/
// Database connection
$host = 'localhost';
$dbname = 'carrental';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Autoload dependencies (PHPMailer, GuzzleHttp)
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer setup
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'websmtp47@gmail.com'; // Your Gmail
        $mail->Password = 'jbvkukdacbphzaet'; // Your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('websmtp47@gmail.com', 'Mbesa CarRental System');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

// PayChangu API constants
define('PAYCHANGU_PUBLIC_KEY', 'PUB-UX2TGIXxxTqeHRKHZ8sg8s3djpHwwuhT');
define('PAYCHANGU_SECRET_KEY', 'SEC-9PWNq4Ncx2uRK3m13VSgpxY8Af5bRsFV');
define('PAYCHANGU_API_URL', 'https://api.paychangu.com'); // Live mode
// define('PAYCHANGU_API_URL', 'https://api.sandbox.paychangu.com'); // Test mode
// Mobile money operator reference IDs
define('PAYCHANGU_TNM_REF_ID', '27494cb5-ba9e-437f-a114-4e7a7686bcca'); // TNM Mpamba
define('PAYCHANGU_AIRTEL_REF_ID', '20be6c20-adeb-4b5b-a7ba-0769820df4fb'); // Airtel Money


// Include payment functions
require 'payment_functions.php';

define('UPLOAD_DIR', 'tmp/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
?>
<?php
session_start();

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

// PHPMailer setup
require 'vendor/autoload.php'; // Adjust path if PHPMailer is installed via Composer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Replace with your Gmail
        $mail->Password = 'your-app-password'; // Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'CarRental System');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

define('PAYCHANGU_PUBLIC_KEY', 'your_public_key');
define('PAYCHANGU_SECRET_KEY', 'your_secret_key');
define('PAYCHANGU_API_URL', 'https://api.paychangu.com'); // Live mode
// define('PAYCHANGU_API_URL', 'https://api.sandbox.paychangu.com'); // Test mode
?>
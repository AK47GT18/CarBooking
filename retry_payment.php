<?php
require 'config.php';



// Check required GET parameters
if (!isset($_GET['user_id']) || !isset($_GET['amount']) || !isset($_GET['type'])) {
    header("Location: index.php?message=" . urlencode("Invalid retry attempt"));
    exit;
}

$user_id = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);
$amount = filter_var($_GET['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$type = filter_var($_GET['type'], FILTER_SANITIZE_STRING);
$booking_id = isset($_GET['booking_id']) ? filter_var($_GET['booking_id'], FILTER_SANITIZE_NUMBER_INT) : null;
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Payment failed. Please try again.';

// Validate payment type
if ($type !== 'signup' && $type !== 'booking') {
    header("Location: index.php?message=" . urlencode("Invalid payment type"));
    exit;
}

// Handle retry payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['retry_payment'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $temp_data = $type === 'signup' ? ($_SESSION['temp_signup_data'] ?? []) : ($_SESSION['temp_booking_data'] ?? []);

        // Handle phone number for signup
        if ($type === 'signup') {
            $phone = filter_var($_POST['phone'] ?? '', FILTER_SANITIZE_STRING);
            if (empty($phone) || !preg_match('/^0[0-9]{9}$/', $phone)) {
                $error = "Please enter a valid phone number (e.g., 0885620896).";
            } else {
                // Update temp_signup_data with the provided phone number
                $temp_data['phone'] = $phone;
                $_SESSION['temp_signup_data'] = $temp_data;
            }
        }

        // Proceed with payment if no errors
        if (!isset($error)) {
            if ($type === 'signup' && (empty($temp_data['phone']) || !preg_match('/^0[0-9]{9}$/', $temp_data['phone']))) {
                $error = "Phone number missing or invalid in temporary signup data. Please enter a valid phone number.";
            } elseif ($type === 'booking' && empty($user_id)) {
                $error = "Invalid user ID for booking payment. Please log in and try again.";
            } else {
                $payment = initiateUSSDPayment($pdo, $user_id, $amount, $type, $booking_id, $temp_data);
                if (isset($payment['error'])) {
                    $retry_url = isset($payment['retry_url']) ? $payment['retry_url'] : "retry_payment.php?user_id=$user_id&amount=$amount&type=$type" . ($booking_id ? "&booking_id=$booking_id" : "") . "&error=" . urlencode($payment['error']);
                    header("Location: $retry_url");
                    exit;
                } else {
                    // Clean up temporary files if they exist
                    if ($type === 'signup' && isset($_SESSION['temp_signup_data']['national_id_path'])) {
                        @unlink($_SESSION['temp_signup_data']['national_id_path']);
                        @unlink($_SESSION['temp_signup_data']['profile_picture_path']);
                    }
                    header("Location: index.php?message=" . urlencode($payment['message']));
                    exit;
                }
            }
        }
    }
}

// Handle cancel payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        try {
            if ($type === 'signup') {
                $pdo->prepare("DELETE FROM payments WHERE user_id IS NULL AND type = 'signup'")->execute();
                if (isset($_SESSION['temp_signup_data']['national_id_path'])) {
                    @unlink($_SESSION['temp_signup_data']['national_id_path']);
                    @unlink($_SESSION['temp_signup_data']['profile_picture_path']);
                }
                unset($_SESSION['temp_signup_data']);
            } elseif ($type === 'booking' && $booking_id) {
                $pdo->prepare("DELETE FROM payments WHERE booking_id = ?")->execute([$booking_id]);
                unset($_SESSION['temp_booking_data']);
            }
            header("Location: index.php?message=" . urlencode("Payment attempt cancelled"));
            exit;
        } catch (PDOException $e) {
            error_log("Database error in cancel payment: " . $e->getMessage());
            $error = "Database error: Unable to cancel payment. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retry Payment - MIBESA Car Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; flex-direction: column; min-height: 100vh; background: #f4f6f8; color: #333; }
        .container { max-width: 600px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h2 { color: #0b0c10; margin-bottom: 1.5rem; text-align: center; }
        .alert { background: #ffdddd; border-left: 6px solid #f44336; padding: 10px; margin-bottom: 15px; }
        form { display: flex; flex-direction: column; }
        label { margin-bottom: 0.5rem; font-weight: bold; }
        input[type="text"] { padding: 0.8rem; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 1rem; font-size: 1rem; }
        button { background: #0b0c10; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-size: 1rem; margin-top: 0.5rem; transition: background 0.3s ease; }
        button:hover { background: #66fcf1; color: #0b0c10; }
        .cancel-btn { background: #ccc; }
        .cancel-btn:hover { background: #aaa; color: #0b0c10; }
        footer { margin-top: auto; background: linear-gradient(90deg, #0b0c10, #1f2833); color: white; padding: 2rem; text-align: center; font-size: 0.9rem; }
        footer .social-icons a { color: #66fcf1; margin: 0 0.5rem; font-size: 1.2rem; transition: color 0.3s ease; }
        footer .social-icons a:hover { color: #45a29e; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Retry Payment</h2>
        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <p>Please try processing your payment again for your <?php echo $type === 'signup' ? 'registration' : 'booking'; ?> (Amount: <?php echo htmlspecialchars($amount); ?> Kwacha).</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))); ?>">
            <?php if ($type === 'signup'): ?>
                <label for="phone">Phone Number (e.g., 0885620896)</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($_SESSION['temp_signup_data']['phone'] ?? ''); ?>" placeholder="Enter your phone number" required>
            <?php endif; ?>
            <button type="submit" name="retry_payment">Try Again</button>
            <button type="submit" name="cancel" class="cancel-btn">Cancel</button>
        </form>
    </div>
    <footer>
        <p>© <?php echo date('Y'); ?> MIBESA Car Rental. All rights reserved.</p>
        <div class="social-icons">
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        </div>
    </footer>
</body>
</html>
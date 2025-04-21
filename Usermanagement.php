<?php
session_start();
require 'config.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to validate CSRF token
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Polling function for PayChangu payment verification
function pollForSuccess(string $chargeId, Client $client, int $intervalSec = 5, int $maxAttempts = 12): array
{
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            // Call verify endpoint
            $resp = $client->get(PAYCHANGU_API_URL . "/mobile-money/payments/{$chargeId}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . PAYCHANGU_SECRET_KEY,
                    'Accept' => 'application/json',
                ]
            ]);
            $data = json_decode($resp->getBody(), true);

            // Check if completed
            if ($data['status'] === 'successful' && ($data['data']['status'] ?? null) === 'success') {
                return $data['data'];
            }

            // Wait before next attempt
            sleep($intervalSec);
        } catch (RequestException $e) {
            // Log error but continue polling
            error_log("Polling attempt $attempt failed for charge_id $chargeId: " . $e->getMessage());
        }
    }

    throw new \RuntimeException(
        "Payment {$chargeId} not confirmed after " . ($intervalSec * $maxAttempts) . " seconds."
    );
}

// Function to initiate PayChangu USSD payment
function initiateUSSDPayment($pdo, $user_id, $amount, $type, $booking_id = null) {
    $stmt = $pdo->prepare("SELECT username, phone, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user['phone']) {
        return ['error' => 'Phone number not found for user'];
    }

    // Validate and format mobile number (remove +265, ensure 9 digits)
    $mobile = preg_replace('/^\+265/', '', $user['phone']);
    if (!preg_match('/^[0-9]{9}$/', $mobile)) {
        return ['error' => 'Invalid mobile number format. Must be +265 followed by 9 digits.'];
    }

    $charge_id = 'CHG-' . time() . '-' . rand(1000, 9999);
    $payload = [
        'mobile_money_operator_ref_id' => PAYCHANGU_MOBILE_MONEY_REF_ID, // Defined in config.php
        'mobile' => $mobile, // 9-digit number without +265
        'amount' => $amount,
        'charge_id' => $charge_id,
        'email' => $user['email'],
        'first_name' => $user['username'],
    ];

    // Initialize Guzzle client
    $client = new Client([
        'base_uri' => PAYCHANGU_API_URL,
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . PAYCHANGU_SECRET_KEY
        ]
    ]);

    try {
        // Initiate payment
        $response = $client->post('/mobile-money/payments/initialize', ['body' => json_encode($payload)]);
        $result = json_decode($response->getBody(), true);

        if ($result['status'] === 'success') {
            // Store payment in database
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, booking_id, charge_id, amount, status, type) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$user_id, $booking_id, $charge_id, $amount, $type]);

            // Poll for payment confirmation
            try {
                $successfulData = pollForSuccess($charge_id, $client);
                // Update payment status
                $stmt = $pdo->prepare("UPDATE payments SET status = 'success' WHERE charge_id = ?");
                $stmt->execute([$charge_id]);

                // Update user or booking status
                if ($type === 'signup') {
                    $stmt = $pdo->prepare("UPDATE users SET payment_status = 'paid' WHERE id = ?");
                    $stmt->execute([$user_id]);
                } elseif ($type === 'booking') {
                    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid', status = 'booked' WHERE id = ?");
                    $stmt->execute([$booking_id]);
                    $stmt = $pdo->prepare("UPDATE cars SET status = 'booked' WHERE id = (SELECT car_id FROM bookings WHERE id = ?)");
                    $stmt->execute([$booking_id]);
                }

                // Notify admins
                $adminStmt = $pdo->query("SELECT email FROM admins");
                while ($admin = $adminStmt->fetch()) {
                    sendEmail($admin['email'], "Payment Confirmed", "Payment (charge_id: $charge_id, Type: $type) confirmed.");
                }

                return ['success' => true, 'message' => 'Payment verified successfully', 'charge_id' => $charge_id];
            } catch (\RuntimeException $e) {
                // Handle polling timeout
                $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE charge_id = ?");
                $stmt->execute([$charge_id]);
                return ['error' => $e->getMessage()];
            }
        } else {
            error_log("Payment initiation failed for charge_id $charge_id: " . json_encode($result));
            return ['error' => 'Failed to initiate payment: ' . ($result['message'] ?? 'Unknown error')];
        }
    } catch (RequestException $e) {
        // Parse error response if available
        $errorMessage = 'Payment initiation failed';
        if ($e->hasResponse()) {
            $response = json_decode($e->getResponse()->getBody(), true);
            $errorMessage .= ': ' . (is_array($response['message']) ? json_encode($response['message']) : $response['message']);
        } else {
            $errorMessage .= ': ' . $e->getMessage();
        }
        error_log("Payment initiation error for charge_id $charge_id: " . $errorMessage);
        return ['error' => $errorMessage];
    }
}

// Initialize $user
$user = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && ($user['status'] !== 'approved' || $user['payment_status'] !== 'paid')) {
        session_destroy();
        header("Location: index.php?message=Account pending approval or payment");
        exit;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $email = filter_var($_POST['loginEmail'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['loginPassword'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] == 'approved' && $user['payment_status'] == 'paid') {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid credentials, account not approved, or payment pending";
        }
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = $_POST['phone'];
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $gender = $_POST['gender'];
        $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
        $location = $_POST['location'];
        $kin_name = filter_var($_POST['kin_name'], FILTER_SANITIZE_STRING);
        $kin_relationship = $_POST['kin_relationship'];
        $kin_phone = $_POST['kin_phone'];

        // Validate inputs
        if (!$username) {
            $error = "Username is required.";
        } elseif (!preg_match('/^\+265[0-9]{9}$/', $phone)) {
            $error = "Invalid phone number format. Use +265 followed by 9 digits.";
        } elseif (!preg_match('/^\+265[0-9]{9}$/', $kin_phone)) {
            $error = "Invalid next of kin phone number format. Use +265 followed by 9 digits.";
        } elseif (!isset($_FILES['national_id']) || $_FILES['national_id']['error'] !== UPLOAD_ERR_OK) {
            $error = "National ID upload failed.";
        } elseif ($_FILES['national_id']['size'] > 2 * 1024 * 1024) { // 2MB limit
            $error = "National ID file size exceeds 2MB.";
        } else {
            $national_id_file = $_FILES['national_id'];
            if ($national_id_file['type'] !== 'application/pdf') {
                $error = "National ID must be a PDF file.";
            } else {
                $national_id_base64 = base64_encode(file_get_contents($national_id_file['tmp_name']));
                if (!$national_id_base64) {
                    $error = "Failed to process National ID file.";
                } else {
                    // Insert user with all details, including kin_name
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, phone, password, gender, address, location, kin_name, kin_relationship, kin_phone, national_id, status, payment_status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
                    ");
                    $stmt->execute([$username, $email, $phone, $password, $gender, $address, $location, $kin_name, $kin_relationship, $kin_phone, $national_id_base64]);

                    $user_id = $pdo->lastInsertId();

                    // Initiate USSD payment for signup (500 Kwacha)
                    $payment = initiateUSSDPayment($pdo, $user_id, 500, 'signup');
                    if (isset($payment['error'])) {
                        $error = $payment['error'];
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
                    } else {
                        $adminStmt = $pdo->query("SELECT email FROM admins");
                        while ($admin = $adminStmt->fetch()) {
                            sendEmail($admin['email'], "New User Signup", "A new user ($username, $email) has signed up and completed payment (charge_id: {$payment['charge_id']}).");
                        }
                        header("Location: index.php?message=" . urlencode($payment['message']));
                        exit;
                    }
                }
            }
        }
    }
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } elseif (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please login to book");
        exit;
    } else {
        $car_id = filter_var($_POST['car_id'], FILTER_SANITIZE_NUMBER_INT);
        $pick_up_date = $_POST['pick_up_date'];
        $return_date = $_POST['return_date'];
        $price_per_day = filter_var($_POST['price_per_day'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        $today = date('Y-m-d');
        $pick_up_timestamp = strtotime($pick_up_date);
        $return_timestamp = strtotime($return_date);
        $today_timestamp = strtotime($today);

        if ($pick_up_timestamp < $today_timestamp) {
            header("Location: index.php?message=Pick-up date cannot be in the past");
            exit;
        }
        if ($return_timestamp <= $pick_up_timestamp) {
            header("Location: index.php?message=Return date must be after pick-up date");
            exit;
        }

        $total_days = ($return_timestamp - $pick_up_timestamp) / (60 * 60 * 24);
        $total_cost = $total_days * $price_per_day;
        $booking_id = '#CR' . rand(1000, 9999);

        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, car_id, booking_id, pick_up_date, return_date, total_days, total_cost, status, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
        ");
        $stmt->execute([$_SESSION['user_id'], $car_id, $booking_id, $pick_up_date, $return_date, $total_days, $total_cost]);

        $booking_db_id = $pdo->lastInsertId();

        $payment = initiateUSSDPayment($pdo, $_SESSION['user_id'], $total_cost, 'booking', $booking_db_id);
        if (isset($payment['error'])) {
            $error = $payment['error'];
            $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking_db_id]);
        } else {
            header("Location: index.php?message=" . urlencode($payment['message']));
            exit;
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } elseif (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please login to update profile");
        exit;
    } else {
        $username = filter_var($_POST['edit_username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['edit_email'], FILTER_SANITIZE_EMAIL);
        $phone = $_POST['edit_phone'];
        $gender = $_POST['edit_gender'];
        $address = filter_var($_POST['edit_address'], FILTER_SANITIZE_STRING);
        $location = $_POST['edit_location'];

        if (!preg_match('/^\+265[0-9]{9}$/', $phone)) {
            $error = "Invalid phone number format. Use +265 followed by 9 digits.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, address = ?, location = ? WHERE id = ?");
            $stmt->execute([$username, $email, $phone, $gender, $address, $location, $_SESSION['user_id']]);
            header("Location: index.php?message=Profile updated successfully");
            exit;
        }
    }
}

// Handle contact form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contact'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            $adminStmt = $pdo->query("SELECT email FROM admins");
            $success = true;
            while ($admin = $adminStmt->fetch()) {
                if (!sendEmail($admin['email'], "Contact Form Submission", "From: $email\nMessage: $message")) {
                    $success = false;
                }
            }
            if ($success) {
                header("Location: index.php?message=Message sent successfully");
                exit;
            } else {
                $error = "Failed to send message. Please try again.";
            }
        }
    }
}

// Fetch cars for display with search and filter
$search = isset($_GET['search']) ? filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING) : '';
$filter_fuel = isset($_GET['fuel']) ? filter_var(trim($_GET['fuel']), FILTER_SANITIZE_STRING) : '';
$filter_status = isset($_GET['status']) ? filter_var(trim($_GET['status']), FILTER_SANITIZE_STRING) : '';

$cars_query = "SELECT * FROM cars WHERE 1=1";
$params = [];

if ($search) {
    $cars_query .= " AND (name LIKE ? OR model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_fuel) {
    $cars_query .= " AND fuel_type = ?";
    $params[] = $filter_fuel;
}
if ($filter_status) {
    $cars_query .= " AND status = ?";
    $params[] = $filter_status;
}

$carsStmt = $pdo->prepare($cars_query);
$carsStmt->execute($params);
$cars = $carsStmt->fetchAll();

// Fetch featured cars
$featuredCarsStmt = $pdo->query("SELECT * FROM cars WHERE featured = 1 LIMIT 3");
$featuredCars = $featuredCarsStmt->fetchAll();

// Fetch available cars for booking page
$availableCarsStmt = $pdo->query("SELECT * FROM cars WHERE status = 'available'");
$availableCars = $availableCarsStmt->fetchAll();

// Get current date for JavaScript
$current_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIBESA Car Rental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; flex-direction: column; min-height: 100vh; background: #f4f6f8; color: #333; }
        nav { background: #0b0c10; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; }
        nav .logo { color: #66fcf1; font-size: 1.5rem; font-weight: bold; }
        nav ul { display: flex; list-style: none; align-items: center; }
        nav ul li { margin-left: 1.5rem; }
        nav ul li a { text-decoration: none; color: #fff; transition: color 0.3s ease; }
        nav ul li a:hover { color: #66fcf1; }
        .profile-container { display: flex; align-items: center; }
        .profile-icon { font-size: 1.2rem; margin-right: 0.5rem; }
        .username { color: #fff; font-size: 1rem; }
        .slider { flex-grow: 1; position: relative; overflow: hidden; height: 60vh; }
        .slides { height: 100%; width: 400%; display: flex; transition: transform 0.5s ease; }
        .slide { width: 100%; height: 100%; }
        .slide img { width: 100%; height: 100%; object-fit: cover; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); }
        .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 500px; max-width: 90%; max-height: 90vh; overflow-y: auto; }
        .close { position: absolute; right: 20px; top: 10px; color: #aaa; font-size: 24px; font-weight: bold; cursor: pointer; }
        form { display: flex; flex-direction: column; }
        form label { margin-bottom: 8px; font-weight: 500; color: #333; }
        form input, form textarea, form select { padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
        form input:focus, form select:focus, form textarea:focus { border-color: #66fcf1; outline: none; }
        form button { width: 100%; background: #0b0c10; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: background 0.3s ease; }
        form button:hover { background: #45a29e; }
        .alert { background: #ffdddd; border-left: 6px solid #f44336; padding: 10px; margin-bottom: 15px; }
        .success { background: #ddffdd; border-left: 6px solid #4caf50; padding: 10px; margin-bottom: 15px; }
        .inline-error { color: #f44336; font-size: 0.8rem; margin-top: -10px; margin-bottom: 10px; display: none; }
        .about-content, .bookings-content, .booking-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .about-content h2, .bookings-content h2, .booking-content h2 { color: #0b0c10; margin-bottom: 20px; }
        .about-content h3 { color: #45a29e; margin: 20px 0 10px; }
        .about-content ul, .about-content ol { margin-left: 20px; margin-bottom: 15px; }
        .about-content li { margin-bottom: 8px; }
        footer { margin-top: auto; background: #0b0c10; color: white; text-align: center; padding: 1rem; font-size: 0.9rem; }
        textarea { width: 100%; min-height: 100px; resize: vertical; }
        select { width: 100%; height: 45px; appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em; padding-right: 2.5rem; }
        .form-links { margin-top: 15px; text-align: center; font-size: 0.9rem; }
        .form-links a { color: #45a29e; text-decoration: none; transition: color 0.3s ease; }
        .form-links a:hover { color: #66fcf1; }
        .separator { margin: 0 10px; color: #ccc; }
        .modal-content h3 { color: #45a29e; border-top: 1px solid #eee; padding-top: 15px; }
        input:invalid, select:invalid, textarea:invalid { border-color: #f44336; }
        .form-section { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 20px; }
        input[type="file"] { background: #f8f9fa; padding: 12px; border-radius: 8px; border: 1px dashed #ccc; width: 100%; }
        form input:hover, form select:hover, form textarea:hover { border-color: #66fcf1; }
        .slider-container { overflow-x: auto; white-space: nowrap; background: #0b0c10; padding: 1rem 0; }
        .slider-container img { width: 300px; height: 180px; margin: 0 10px; border-radius: 10px; display: inline-block; object-fit: cover; }
        .section-header { padding: 2rem; font-size: 1.6rem; color: #0b0c10; }
        .controls { display: flex; flex-direction: row; align-items: center; gap: 1rem; margin: 1rem 0; white-space: nowrap; }
        .controls input[type="text"], .controls select, .controls button { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; height: 40px; font-size: 1rem; }
        .controls input[type="text"] { flex: 1; min-width: 200px; }
        .controls select { min-width: 150px; }
        .controls button { background: #0b0c10; color: #fff; border: none; cursor: pointer; min-width: 100px; }
        .controls button:hover { background: #66fcf1; color: #0b0c10; }
        @media (max-width: 768px) {
            .controls { flex-wrap: wrap; }
            .controls input[type="text"], .controls select, .controls button { width: 100%; margin-bottom: 0.5rem; }
        }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .car-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; }
        .car-card:hover { transform: translateY(-5px); }
        .car-card img { width: 100%; height: 200px; object-fit: cover; }
        .car-card .details { padding: 1rem; }
        .car-card h3 { margin-bottom: 0.5rem; color: #0b0c10; }
        .car-card p { font-size: 0.9rem; color: #555; }
        .car-card button { margin: 1rem; padding: 0.5rem 1rem; background: #66fcf1; border: none; border-radius: 8px; cursor: pointer; color: #0b0c10; font-weight: bold; }
        .car-card button:hover { background: #0b0c10; color: #66fcf1; }
        .car-card button:disabled { background: #ccc; cursor: not-allowed; }
        .signup-progress { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .progress-step { flex: 1; text-align: center; padding: 10px; background: #f0f0f0; border-radius: 8px; margin: 0 5px; font-size: 0.9rem; transition: background 0.3s ease, color 0.3s ease; }
        .progress-step.active { background: #66fcf1; color: #0b0c10; font-weight: bold; }
        .step-buttons { display: flex; justify-content: space-between; gap: 10px; }
        .step-buttons button { width: 48%; transition: background 0.3s ease; }
        .step-buttons button:disabled { background: #ccc; cursor: not-allowed; }
        .profile-content, .bookings-content, .booking-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .profile-header { display: flex; align-items: center; margin-bottom: 20px; }
        .profile-pic { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-right: 20px; }
        .profile-details { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .detail-item { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .detail-item label { font-weight: bold; color: #0b0c10; }
        .detail-item p { margin-top: 5px; color: #555; }
        .bookings-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .bookings-table th, .bookings-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .bookings-table th { background: #0b0c10; color: white; }
        .bookings-table tr:hover { background: #f8f9fa; }
        .edit-profile-btn, .new-booking-btn { background: #45a29e; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; }
        .edit-profile-btn:hover, .new-booking-btn:hover { background: #66fcf1; color: #0b0c10; }
        .booking-form { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        .booking-form h3 { color: #45a29e; margin-bottom: 1rem; }
        @media (min-width: 900px) {
            .grid-container { grid-template-columns: repeat(3, 1fr); }
        }
        .signup-main { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .signup-section { margin-bottom: 40px; text-align: center; }
        .signup-section h2 { font-size: 2rem; color: #1a3c34; margin-bottom: 15px; }
        .signup-success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .signup-error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; text-align: center; }
        .signup-form { max-width: 500px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .signup-form-group { margin-bottom: 15px; text-align: left; }
        .signup-form-group label { display: block; font-size: 0.9rem; color: #1a3c34; margin-bottom: 5px; font-weight: 500; }
        .signup-form-group input, .signup-form-group select, .signup-form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; color: #333; background-color: #f9f9f9; transition: border-color 0.3s ease; }
        .signup-form-group input:focus, .signup-form-group select:focus, .signup-form-group textarea:focus { border-color: #1a3c34; outline: none; background-color: #fff; }
        .signup-btn { display: inline-block; background-color: #1a3c34; color: #fff; padding: 10px 20px; border: none; border-radius: 5px; font-size: 1rem; cursor: pointer; transition: background-color 0.3s ease; }
        .signup-btn:hover { background-color: #154f45; }
        .signup-car-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px; }
        .signup-car-card { background-color: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; text-align: center; }
        .signup-car-card img { width: 100%; height: 150px; object-fit: cover; border-radius: 5px; margin-bottom: 10px; }
        .signup-car-card h3 { font-size: 1.2rem; color: #1a3c34; margin-bottom: 10px; }
        .signup-car-card p { font-size: 0.9rem; color: #555; margin-bottom: 10px; }
        .signup-info { font-size: 0.9rem; color: #721c24; font-style: italic; }
        @media (max-width: 768px) {
            .signup-section h2 { font-size: 1.5rem; }
            .signup-form { padding: 15px; }
            .signup-car-card { padding: 10px; }
            .signup-car-card img { height: 120px; }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">MIBESA</div>
        <ul>
            <li><a href="#" onclick="showSection('home')">Home</a></li>
            <li><a href="#" onclick="showSection('cars')">Cars</a></li>
            <li><a href="#" onclick="showSection('about')">About</a></li>
            <li><a href="#" onclick="showSection('contact')">Contact</a></li>
            <?php if (isset($_SESSION['user_id']) && $user): ?>
                <li><a href="#" onclick="showSection('bookings')">Bookings</a></li>
                <li>
                    <a href="#" onclick="showSection('profile')" class="profile-container">
                        <i class="fas fa-user profile-icon"></i>
                        <span class="username"><?php echo htmlspecialchars($user['username'] ?? $user['email']); ?></span>
                    </a>
                </li>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="#" id="loginBtn">Login</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <?php if (isset($_GET['message'])): ?>
        <div class="success"><?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section id="home">
        <div class="slider">
            <div class="slides">
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+1" alt="Car 1"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+2" alt="Car 2"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+3" alt="Car 3"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+4" alt="Car 4"></div>
            </div>
        </div>
        <div class="about-content">
            <h2>About Us</h2>
            <p>MIBESA Car Rental is committed to providing quality and affordable car rental services tailored to your needs. Our fleet includes a wide range of vehicles suitable for every occasion.</p>
            <h3>Our Services</h3>
            <ul>
                <li>Peer-to-peer car rental services</li>
                <li>Secure payment processing</li>
                <li>Verified user profiles</li>
                <li>24/7 customer support</li>
                <li>Comprehensive insurance coverage</li>
            </ul>
            <h3>How It Works</h3>
            <p>Getting started is simple:</p>
            <ol>
                <li>Create an account and complete verification</li>
                <li>Browse available vehicles</li>
                <li>Make your booking</li>
                <li>Process secure payment</li>
                <li>Enjoy your ride</li>
            </ol>
        </div>
    </section>

    <section id="cars" style="display:none;">
        <div class="slider-container">
            <?php foreach ($featuredCars as $car): ?>
                <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x180?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
            <?php endforeach; ?>
        </div>
        <h2 class="section-header">Featured Cars</h2>
        <div class="controls">
            <form method="GET" action="index.php" style="display: flex; flex-direction: row; align-items: center; gap: 1rem; width: 100%;">
                <input type="hidden" name="section" value="cars">
                <input type="text" name="search" placeholder="Search by name or model..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="fuel">
                    <option value="">All Fuel Types</option>
                    <option value="Petrol" <?php echo $filter_fuel == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                    <option value="Diesel" <?php echo $filter_fuel == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                    <option value="Electric" <?php echo $filter_fuel == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                </select>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo $filter_status == 'booked' ? 'selected' : ''; ?>>Booked</option>
                </select>
                <button type="submit">Search</button>
                <button type="button" onclick="window.location.href='index.php?section=cars'">Clear</button>
            </form>
        </div>
        <div class="signup-car-grid">
            <?php foreach ($featuredCars as $car): ?>
                <div class="signup-car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                    <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                    <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                    <?php if ($car['status'] == 'available' && isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button class="signup-btn" onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info"><?php echo $car['status'] == 'booked' ? 'Booked' : 'Login and get approved to book'; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <h2 class="section-header">All Available Cars</h2>
        <div class="signup-car-grid">
            <?php foreach ($cars as $car): ?>
                <div class="signup-car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                    <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                    <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                    <?php if ($car['status'] == 'available' && isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button class="signup-btn" onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info"><?php echo $car['status'] == 'booked' ? 'Booked' : 'Login and get approved to book'; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="about" style="display:none;">
        <div class="about-content">
            <h2>About MIBESA</h2>
            <p>MIBESA Car Rental is committed to providing quality and affordable car rental services tailored to your needs. Our fleet includes a wide range of vehicles suitable for every occasion.</p>
            <h3>Our Mission</h3>
            <p>To offer reliable, convenient, and affordable car rental services while ensuring the highest standards of customer satisfaction and safety.</p>
            <h3>Why Choose Us?</h3>
            <ul>
                <li>Wide selection of vehicles</li>
                <li>Competitive pricing</li>
                <li>Secure payment processing</li>
                <li>24/7 customer support</li>
                <li>Comprehensive insurance coverage</li>
            </ul>
            <h3>Our Team</h3>
            <p>Our team consists of experienced professionals dedicated to providing the best car rental experience. From customer service to technical support, we are here to assist you every step of the way.</p>
        </div>
    </section>

    <section id="contact" style="display:none;">
        <div class="about-content">
            <h2>Contact Us</h2>
            <form id="contactForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="contact_email">Email:</label>
                <input type="email" id="contact_email" name="email" required>
                <span class="inline-error" id="emailError">Valid email required.</span>
                <label for="contact_message">Message:</label>
                <textarea id="contact_message" name="message" required></textarea>
                <span class="inline-error" id="messageError">Message is required.</span>
                <button type="submit" name="contact">Send Message</button>
            </form>
        </div>
    </section>

    <section id="bookings" style="display:none;">
        <div class="bookings-content">
            <h2>Your Bookings</h2>
            <button class="new-booking-btn" onclick="showSection('booking')">New Booking</button>
            <div class="controls">
                <input type="text" id="bookingSearch" placeholder="Search by car name or booking ID...">
                <select id="bookingStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="booked">Booked</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button onclick="filterBookings()">Filter</button>
                <button onclick="resetBookingFilter()">Clear</button>
            </div>
            <table class="bookings-table" id="bookingsTable">
                <thead>
                    <tr>
                        <th>Car</th>
                        <th>Booking ID</th>
                        <th>Pick-up Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Total Cost</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_SESSION['user_id'])) {
                        $stmt = $pdo->prepare("SELECT b.*, c.name AS car_name FROM bookings b LEFT JOIN cars c ON b.car_id = c.id WHERE b.user_id = ? ORDER BY b.created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $bookings = $stmt->fetchAll();
                        foreach ($bookings as $booking):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['car_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                            <td><?php echo htmlspecialchars($booking['pick_up_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['return_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['status']); ?></td>
                            <td><?php echo htmlspecialchars($booking['total_cost']); ?> Kwacha</td>
                            <td><?php echo htmlspecialchars($booking['payment_status']); ?></td>
                        </tr>
                    <?php endforeach; }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="booking" style="display:none;">
        <div class="booking-content">
            <h2>Create a New Booking</h2>
            <div class="booking-form">
                <h3>Book a Car</h3>
                <form id="newBookingForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="bookingCarId">Select Car:</label>
                    <select id="bookingCarId" name="car_id" required onchange="updatePricePerDay()">
                        <option value="">Select a Car</option>
                        <?php foreach ($availableCars as $car): ?>
                            <option value="<?php echo $car['id']; ?>" data-name="<?php echo htmlspecialchars($car['name']); ?>" data-price="<?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>">
                                <?php echo htmlspecialchars($car['name'] . ' (' . $car['model'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="inline-error" id="bookingCarIdError">Please select a car</span>
                    <label for="carName">Car:</label>
                    <input type="text" id="carName" readonly>
                    <input type="hidden" id="pricePerDay" name="price_per_day">
                    <label for="pick_up_date">Pick-up Date:</label>
                    <input type="date" id="pick_up_date" name="pick_up_date" required min="<?php echo $current_date; ?>">
                    <span class="inline-error" id="pickUpDateError">Pick-up date must be today or later</span>
                    <label for="return_date">Return Date:</label>
                    <input type="date" id="return_date" name="return_date" required min="<?php echo $current_date; ?>">
                    <span class="inline-error" id="returnDateError">Return date must be after pick-up date</span>
                    <label for="totalCost">Estimated Cost:</label>
                    <input type="text" id="totalCost" readonly>
                    <button type="submit" name="book">Confirm Booking</button>
                </form>
            </div>
            <h2>Available Cars</h2>
            <div class="signup-car-grid">
                <?php foreach ($availableCars as $car): ?>
                    <div class="signup-car-card">
                        <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                        <button class="signup-btn" onclick="selectCarForBooking(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="profile" style="display:none;">
        <div class="profile-content">
            <h2>User Profile</h2>
            <?php if ($user): ?>
            <div class="profile-header">
                <img src="https://via.placeholder.com/100?text=Profile" alt="Profile Picture" class="profile-pic">
                <div>
                    <h3><?php echo htmlspecialchars($user['username'] ?? $user['email']); ?></h3>
                    <p>Member since <?php echo date('F Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                </div>
            </div>
            <div class="profile-details">
                <div class="detail-item">
                    <label>Username</label>
                    <p><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <p><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Phone</label>
                    <p><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Gender</label>
                    <p><?php echo htmlspecialchars($user['gender'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Address</label>
                    <p><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Location</label>
                    <p><?php echo htmlspecialchars($user['location'] ?? 'N/A'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Next of Kin</label>
                    <p><?php echo htmlspecialchars($user['kin_name'] ?? 'N/A'); ?><?php echo $user['kin_relationship'] ? ' (' . htmlspecialchars($user['kin_relationship']) . ')' : ''; ?></p>
                </div>
                <div class="detail-item">
                    <label>Kin Phone</label>
                    <p><?php echo htmlspecialchars($user['kin_phone'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <button class="edit-profile-btn" onclick="showEditProfileModal()">Edit Profile</button>
            <h3>Your Bookings</h3>
            <div class="controls">
                <input type="text" id="profileBookingSearch" placeholder="Search by car name...">
                <select id="profileBookingStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="booked">Booked</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button onclick="filterProfileBookings()">Filter</button>
                <button onclick="resetProfileBookingFilter()">Clear</button>
            </div>
            <table class="bookings-table" id="profileBookingsTable">
                <thead>
                    <tr>
                        <th>Car</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Total Cost</th>
                        <th>Payment Status</th>
                        <th>Extra Charges</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_SESSION['user_id'])) {
                        $stmt = $pdo->prepare("SELECT b.*, c.name AS car_name, e.days_late, e.extra_cost FROM bookings b LEFT JOIN cars c ON b.car_id = c.id LEFT JOIN extra_charges e ON b.id = e.booking_id WHERE b.user_id = ? ORDER BY b.created_at DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $bookings = $stmt->fetchAll();
                        foreach ($bookings as $booking):
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['car_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['pick_up_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['return_date']); ?></td>
                            <td><?php echo htmlspecialchars($booking['status']); ?></td>
                            <td><?php echo htmlspecialchars($booking['total_cost']); ?> Kwacha</td>
                            <td><?php echo htmlspecialchars($booking['payment_status']); ?></td>
                            <td><?php echo $booking['days_late'] ? htmlspecialchars($booking['days_late'] . ' days, ' . $booking['extra_cost'] . ' Kwacha') : 'None'; ?></td>
                        </tr>
                    <?php endforeach; }
                    ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>Please log in to view your profile.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="loginModal">×</span>
            <h2>Login</h2>
            <form id="loginForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="loginEmail">Email:</label>
                <input type="email" id="loginEmail" name="loginEmail" required>
                <span class="inline-error" id="loginEmailError">Valid email required.</span>
                <label for="loginPassword">Password:</label>
                <input type="password" id="loginPassword" name="loginPassword" required>
                <span class="inline-error" id="loginPasswordError">Password required.</span>
                <button type="submit" name="login">Login</button>
                <div class="form-links">
                    <a href="#" id="showSignup">Sign up</a>
                    <span class="separator">|</span>
                    <a href="#" id="showForgot">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="signupModal">×</span>
            <h2>Signup</h2>
            <div class="signup-error">Notice: A 500 Kwacha non-refundable registration fee applies.</div>
            <div class="signup-progress">
                <div class="progress-step active" id="progress1">Basic Info</div>
                <div class="progress-step" id="progress2">Personal Details</div>
                <div class="progress-step" id="progress3">Next of Kin</div>
                <div class="progress-step" id="progress4">Documents</div>
            </div>
            <form id="signupForm" class="signup-form" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="step" id="step1">
                    <h3>Step 1: Basic Information</h3>
                    <div class="signup-form-group">
                        <label for="signupUsername">Username:</label>
                        <input type="text" id="signupUsername" name="username" required>
                        <span class="inline-error" id="signupUsernameError">Username is required</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="signupEmail">Email:</label>
                        <input type="email" id="signupEmail" name="email" required>
                        <span class="inline-error" id="signupEmailError">Valid email required</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="phone">Phone Number (+265):</label>
                        <input type="tel" id="phone" name="phone" pattern="\+265[0-9]{9}" placeholder="+265123456789" required>
                        <span class="inline-error" id="phoneError">Phone number must be in +265 format</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="signupPassword">Password:</label>
                        <input type="password" id="signupPassword" name="password" required>
                        <span class="inline-error" id="signupPasswordError">Password must be at least 8 characters</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="confirmPassword">Confirm Password:</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                        <span class="inline-error" id="confirmPasswordError">Passwords do not match</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="signup-btn" onclick="nextStep(2)">Next</button>
                    </div>
                </div>
                <div class="step" id="step2" style="display:none;">
                    <h3>Step 2: Personal Details</h3>
                    <div class="signup-form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="inline-error" id="genderError">Please select a gender</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="address">Address:</label>
                        <textarea id="address" name="address" required></textarea>
                        <span class="inline-error" id="addressError">Address is required</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="location">Location in Mzuzu:</label>
                        <select id="location" name="location" required>
                            <option value="">Select Location</option>
                            <option value="luwinga">Luwinga</option>
                            <option value="sonda">Sonda</option>
                            <option value="area4">Area 4</option>
                            <option value="katoto">Katoto</option>
                            <option value="lipaso">Lipaso</option>
                            <option value="chibavi">Chibavi</option>
                            <option value="chibanja">Chibanja</option>
                            <option value="mchengatuwa">Mchengatuwa</option>
                        </select>
                        <span class="inline-error" id="locationError">Please select a location</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="signup-btn" onclick="prevStep(1)">Previous</button>
                        <button type="button" class="signup-btn" onclick="nextStep(3)">Next</button>
                    </div>
                </div>
                <div class="step" id="step3" style="display:none;">
                    <h3>Step 3: Next of Kin</h3>
                    <div class="signup-form-group">
                        <label for="kin_name">Full Name:</label>
                        <input type="text" id="kin_name" name="kin_name" required>
                        <span class="inline-error" id="kinNameError">Full name is required</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="kin_relationship">Relationship:</label>
                        <select id="kin_relationship" name="kin_relationship" required>
                            <option value="">Select Relationship</option>
                            <option value="Parent">Parent</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Child">Child</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="inline-error" id="kinRelationshipError">Please select a relationship</span>
                    </div>
                    <div class="signup-form-group">
                        <label for="kin_phone">Phone Number (+265):</label>
                        <input type="tel" id="kin_phone" name="kin_phone" pattern="\+265[0-9]{9}" placeholder="+265123456789" required>
                        <span class="inline-error" id="kinPhoneError">Phone number must be in +265 format</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="signup-btn" onclick="prevStep(2)">Previous</button>
                        <button type="button" class="signup-btn" onclick="nextStep(4)">Next</button>
                    </div>
                </div>
                <div class="step" id="step4" style="display:none;">
                    <h3>Step 4: Documents</h3>
                    <div class="signup-form-group">
                        <label for="national_id">National ID (PDF only, max 2MB):</label>
                        <input type="file" id="national_id" name="national_id" accept=".pdf" required>
                        <span class="inline-error" id="nationalIdError">A valid PDF document is required</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" class="signup-btn" onclick="prevStep(3)">Previous</button>
                        <button type="submit" name="signup" class="signup-btn">Register</button>
                    </div>
                </div>
            </form>
            <div class="form-links">
                <a href="#" id="showLogin">Already have an account? Login</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="forgotModal">×</span>
            <h2>Forgot Password</h2>
            <form id="forgotForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="forgotEmail">Email:</label>
                <input type="email" id="forgotEmail" name="forgotEmail" required>
                <span class="inline-error" id="forgotEmailError">Valid email required.</span>
                <button type="submit">Reset Password</button>
                <div class="form-links">
                    <a href="#" id="backToLogin">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="bookingModal">×</span>
            <h2>Book a Car</h2>
            <form id="bookingForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="modalBookingCarId" name="car_id">
                <input type="hidden" id="modalPricePerDay" name="price_per_day">
                <label for="modalCarName">Car:</label>
                <input type="text" id="modalCarName" readonly>
                <label for="modal_pick_up_date">Pick-up Date:</label>
                <input type="date" id="modal_pick_up_date" name="pick_up_date" required min="<?php echo $current_date; ?>">
                <span class="inline-error" id="modalPickUpDateError">Pick-up date must be today or later</span>
                <label for="modal_return_date">Return Date:</label>
                <input type="date" id="modal_return_date" name="return_date" required min="<?php echo $current_date; ?>">
                <span class="inline-error" id="modalReturnDateError">Return date must be after pick-up date</span>
                <label for="modalTotalCost">Estimated Cost:</label>
                <input type="text" id="modalTotalCost" readonly>
                <button type="submit" name="book">Confirm Booking</button>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="editProfileModal">×</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="edit_username">Username:</label>
                <input type="text" id="edit_username" name="edit_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                <span class="inline-error" id="editUsernameError">Username is required</span>
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                <span class="inline-error" id="editEmailError">Valid email required</span>
                <label for="edit_phone">Phone Number (+265):</label>
                <input type="tel" id="edit_phone" name="edit_phone" pattern="\+265[0-9]{9}" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+265123456789" required>
                <span class="inline-error" id="editPhoneError">Phone number must be in +265 format</span>
                <label for="edit_gender">Gender:</label>
                <select id="edit_gender" name="edit_gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($user['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($user['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <span class="inline-error" id="editGenderError">Please select a gender</span>
                <label for="edit_address">Address:</label>
                <textarea id="edit_address" name="edit_address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                <span class="inline-error" id="editAddressError">Address is required</span>
                <label for="edit_location">Location in Mzuzu:</label>
                <select id="edit_location" name="edit_location" required>
                    <option value="">Select Location</option>
                    <option value="luwinga" <?php echo ($user['location'] ?? '') == 'luwinga' ? 'selected' : ''; ?>>Luwinga</option>
                    <option value="sonda" <?php echo ($user['location'] ?? '') == 'sonda' ? 'selected' : ''; ?>>Sonda</option>
                    <option value="area4" <?php echo ($user['location'] ?? '') == 'area4' ? 'selected' : ''; ?>>Area 4</option>
                    <option value="katoto" <?php echo ($user['location'] ?? '') == 'katoto' ? 'selected' : ''; ?>>Katoto</option>
                    <option value="lipaso" <?php echo ($user['location'] ?? '') == 'lipaso' ? 'selected' : ''; ?>>Lipaso</option>
                    <option value="chibavi" <?php echo ($user['location'] ?? '') == 'chibavi' ? 'selected' : ''; ?>>Chibavi</option>
                    <option value="chibanja" <?php echo ($user['location'] ?? '') == 'chibanja' ? 'selected' : ''; ?>>Ch
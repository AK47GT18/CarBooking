<?php
require 'config.php';

// Function to initiate PayChangu USSD payment
function initiateUSSDPayment($pdo, $user_id, $amount, $type, $booking_id = null) {
    $stmt = $pdo->prepare("SELECT phone, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user['phone']) {
        return ['error' => 'Phone number not found for user'];
    }

    $tx_ref = 'TX-' . time() . '-' . rand(1000, 9999);
    $payload = [
        'amount' => $amount,
        'currency' => 'MWK',
        'email' => $user['email'],
        'phone_number' => $user['phone'],
        'tx_ref' => $tx_ref,
        'payment_method' => 'mobile_money',
        'customization' => [
            'title' => $type === 'signup' ? 'CarRental Signup Fee' : 'CarRental Booking Payment',
            'description' => $type === 'signup' ? '500 Kwacha Registration Fee' : 'Booking Payment'
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYCHANGU_API_URL . '/direct-charge');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . PAYCHANGU_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        if ($result['status'] == 'success') {
            // Store payment in database
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, booking_id, tx_ref, amount, status, type) VALUES (?, ?, ?, ?, 'pending', ?)");
            $stmt->execute([$user_id, $booking_id, $tx_ref, $amount, $type]);
            return ['success' => true, 'tx_ref' => $tx_ref];
        }
    }
    return ['error' => 'Failed to initiate payment: ' . ($result['message'] ?? 'Unknown error')];
}

// Function to verify PayChangu payment
function verifyPayment($pdo, $tx_ref) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYCHANGU_API_URL . "/verify-payment/$tx_ref");
    curl_setopt($ch, CURLOPT_HTTPGET, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . PAYCHANGU_SECRET_KEY
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        if ($result['status'] == 'success' && $result['data']['status'] == 'success') {
            // Update payment status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'success' WHERE tx_ref = ?");
            $stmt->execute([$tx_ref]);

            // Check payment type
            $stmt = $pdo->prepare("SELECT user_id, booking_id, type FROM payments WHERE tx_ref = ?");
            $stmt->execute([$tx_ref]);
            $payment = $stmt->fetch();

            if ($payment['type'] == 'signup') {
                $stmt = $pdo->prepare("UPDATE users SET payment_status = 'paid' WHERE id = ?");
                $stmt->execute([$payment['user_id']]);
            } elseif ($payment['type'] == 'booking') {
                $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid', status = 'booked' WHERE id = ?");
                $stmt->execute([$payment['booking_id']]);
                $stmt = $pdo->prepare("UPDATE cars SET status = 'booked' WHERE id = (SELECT car_id FROM bookings WHERE id = ?)");
                $stmt->execute([$payment['booking_id']]);
            }

            // Notify admin
            $adminStmt = $pdo->query("SELECT email FROM admins");
            while ($admin = $adminStmt->fetch()) {
                sendEmail($admin['email'], "Payment Confirmed", "Payment (tx_ref: $tx_ref, Type: {$payment['type']}) confirmed.");
            }
            return ['success' => true, 'message' => 'Payment verified successfully'];
        }
    }
    return ['success' => false, 'message' => 'Payment not yet confirmed or failed'];
}

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user['status'] !== 'approved' || $user['payment_status'] !== 'paid') {
        session_destroy();
        header("Location: index.php?message=Account pending approval or payment");
        exit;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['loginEmail'];
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

// Handle signup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $location = $_POST['location'];
    $national_id = $_FILES['national_id']['name'];

    // Validate phone number
    if (!preg_match('/^\+260[0-9]{9}$/', $phone)) {
        $error = "Invalid phone number format. Use +260 followed by 9 digits.";
    } else {
        // Upload national ID
        $target_dir = "Uploads/";
        $target_file = $target_dir . basename($_FILES['national_id']['name']);
        move_uploaded_file($_FILES['national_id']['tmp_name'], $target_file);

        // Insert user with pending status
        $stmt = $pdo->prepare("INSERT INTO users (email, phone, password, gender, address, location, national_id, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
        $stmt->execute([$email, $phone, $password, $gender, $address, $location, $national_id]);

        $user_id = $pdo->lastInsertId();

        // Initiate USSD payment for signup (500 Kwacha)
        $payment = initiateUSSDPayment($pdo, $user_id, 500, 'signup');
        if (isset($payment['error'])) {
            $error = $payment['error'];
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        } else {
            // Notify admin
            $adminStmt = $pdo->query("SELECT email FROM admins");
            while ($admin = $adminStmt->fetch()) {
                sendEmail($admin['email'], "New User Signup", "A new user ($email) has signed up and initiated payment (tx_ref: {$payment['tx_ref']}).");
            }
            $_SESSION['pending_tx_ref'] = $payment['tx_ref'];
            header("Location: index.php?message=Signup initiated. Complete the USSD payment on your phone and verify.");
            exit;
        }
    }
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please login to book");
        exit;
    }

    $car_id = $_POST['car_id'];
    $pick_up_date = $_POST['pick_up_date'];
    $return_date = $_POST['return_date'];
    $price_per_day = $_POST['price_per_day'];

    // Validate booking dates
    $today = date('Y-m-d'); // Current date: 2025-04-19
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

    // Insert booking with pending payment
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, car_id, booking_id, pick_up_date, return_date, total_days, total_cost, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')");
    $stmt->execute([$_SESSION['user_id'], $car_id, $booking_id, $pick_up_date, $return_date, $total_days, $total_cost]);

    $booking_db_id = $pdo->lastInsertId();

    // Initiate USSD payment for booking
    $payment = initiateUSSDPayment($pdo, $_SESSION['user_id'], $total_cost, 'booking', $booking_db_id);
    if (isset($payment['error'])) {
        $error = $payment['error'];
        $pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$booking_db_id]);
    } else {
        $_SESSION['pending_tx_ref'] = $payment['tx_ref'];
        header("Location: index.php?message=Booking initiated. Complete the USSD payment on your phone and verify.");
        exit;
    }
}

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_payment'])) {
    if (!isset($_SESSION['pending_tx_ref'])) {
        $error = "No pending payment to verify";
    } else {
        $tx_ref = $_SESSION['pending_tx_ref'];
        $verification = verifyPayment($pdo, $tx_ref);
        if ($verification['success']) {
            unset($_SESSION['pending_tx_ref']);
            header("Location: index.php?message=" . urlencode($verification['message']));
            exit;
        } else {
            $error = $verification['message'];
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please login to update profile");
        exit;
    }

    $email = $_POST['edit_email'];
    $phone = $_POST['edit_phone'];
    $gender = $_POST['edit_gender'];
    $address = $_POST['edit_address'];
    $location = $_POST['edit_location'];

    // Validate phone number
    if (!preg_match('/^\+260[0-9]{9}$/', $phone)) {
        $error = "Invalid phone number format. Use +260 followed by 9 digits.";
    } else {
        $stmt = $pdo->prepare("UPDATE users SET email = ?, phone = ?, gender = ?, address = ?, location = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $gender, $address, $location, $_SESSION['user_id']]);
        header("Location: index.php?message=Profile updated successfully");
        exit;
    }
}

// Handle contact form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contact'])) {
    $email = $_POST['email'];
    $message = $_POST['message'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Send email to admins
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

// Fetch cars for display with search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_fuel = isset($_GET['fuel']) ? trim($_GET['fuel']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarRental</title>
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
        .profile-icon { font-size: 1.2rem; }
        .slider { flex-grow: 1; position: relative; overflow: hidden; height: 60vh; }
        .slides { height: 100%; width: 400%; display: flex; animation: slide 15s infinite alternate; }
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
        .about-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .about-content h2 { color: #0b0c10; margin-bottom: 20px; }
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
        .controls { display: flex; justify-content: center; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
        .controls input[type="text"], .controls select, .controls button { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; }
        .controls button { background: #0b0c10; color: #fff; border: none; cursor: pointer; }
        .controls button:hover { background: #66fcf1; color: #0b0c10; }
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
        .profile-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
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
        .edit-profile-btn { background: #45a29e; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; margin-top: 20px; }
        .edit-profile-btn:hover { background: #66fcf1; color: #0b0c10; }
        .verify-btn { background: #45a29e; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; margin-top: 10px; }
        .verify-btn:hover { background: #66fcf1; color: #0b0c10; }
        @media (min-width: 900px) {
            .grid-container { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <nav>
        <div class="logo">CarRental</div>
        <ul>
            <li><a href="#" onclick="showSection('home')">Home</a></li>
            <li><a href="#" onclick="showSection('cars')">Cars</a></li>
            <li><a href="#" onclick="showSection('about')">About</a></li>
            <li><a href="#" onclick="showSection('contact')">Contact</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="#" onclick="showSection('profile')" title="Profile"><i class="fas fa-user profile-icon"></i></a></li>
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

    <?php if (isset($_SESSION['pending_tx_ref'])): ?>
        <div class="alert">
            <p>Complete the USSD payment on your phone, then verify below.</p>
            <form method="POST">
                <button type="submit" name="verify_payment" class="verify-btn">Verify Payment</button>
            </form>
        </div>
    <?php endif; ?>

    <section id="home">
        <div class="slider">
            <div class="slides">
                <div class="slide"><img src="img/car.jpg" alt="Car 1"></div>
                <div class="slide"><img src="img/car2.jpg" alt="Car 2"></div>
                <div class="slide"><img src="img/car3.jpg" alt="Car 3"></div>
                <div class="slide"><img src="img/car4.jpg" alt="Car 4"></div>
            </div>
        </div>
        <div class="about-content">
            <h2>About Us</h2>
            <p>Mibesa Car Hire is committed to providing quality and affordable car
      rental services tailored to your needs. Our fleet includes a wide range
      of vehicles suitable for every occasion.</p>
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
            <form method="GET" action="index.php" style="display: flex; gap: 1rem; flex-wrap: wrap;">
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
        <div class="grid-container">
            <?php foreach ($featuredCars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                        <?php if ($car['status'] == 'available'): ?>
                            <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                        <?php else: ?>
                            <button disabled>Booked</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 class="section-header">All Available Cars</h2>
        <div class="grid-container">
            <?php foreach ($cars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                        <?php if ($car['status'] == 'available'): ?>
                            <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                        <?php else: ?>
                            <button disabled>Booked</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="about" style="display:none;">
        <div class="about-content">
            <h2>About CarRental</h2>
            <p>CarRental is Zambia's leading car rental platform, established in 2025. We provide a seamless and secure way for individuals to rent and share vehicles within the community.</p>
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
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                <span class="inline-error" id="emailError">Valid email required.</span>
                <label for="message">Message:</label>
                <textarea id="message" name="message" required></textarea>
                <span class="inline-error" id="messageError">Message is required.</span>
                <button type="submit" name="contact">Send Message</button>
            </form>
        </div>
    </section>

    <section id="profile" style="display:none;">
        <div class="profile-content">
            <h2>User Profile</h2>
            <div class="profile-header">
                <img src="https://via.placeholder.com/100?text=Profile" alt="Profile Picture" class="profile-pic">
                <div>
                    <h3><?php echo htmlspecialchars($user['username'] ?? $user['email']); ?></h3>
                    <p>Member since <?php echo date('F Y', strtotime($user['created_at'] ?? 'now')); ?></p>
                </div>
            </div>
            <div class="profile-details">
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
            </div>
            <button class="edit-profile-btn" onclick="showEditProfileModal()">Edit Profile</button>
            <h3>Your Bookings</h3>
            <div class="controls">
                <input type="text" id="bookingSearch" placeholder="Search by car name...">
                <select id="bookingStatusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
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
        </div>
    </section>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="loginModal">×</span>
            <h2>Login</h2>
            <form id="loginForm" method="POST">
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
            <div class="alert">Notice: A 500 Kwacha non-refundable registration fee applies.</div>
            <div class="signup-progress">
                <div class="progress-step active" id="progress1">Basic Info</div>
                <div class="progress-step" id="progress2">Personal Details</div>
                <div class="progress-step" id="progress3">Documents</div>
            </div>
            <form id="signupForm" method="POST" enctype="multipart/form-data">
                <div class="step" id="step1">
                    <h3>Step 1: Basic Information</h3>
                    <label for="signupEmail">Email:</label>
                    <input type="email" id="signupEmail" name="email" required>
                    <span class="inline-error" id="signupEmailError">Valid email required</span>
                    <label for="phone">Phone Number (+260):</label>
                    <input type="tel" id="phone" name="phone" pattern="\+260[0-9]{9}" placeholder="+260123456789" required>
                    <span class="inline-error" id="phoneError">Phone number must be in +260 format</span>
                    <label for="signupPassword">Password:</label>
                    <input type="password" id="signupPassword" name="password" required>
                    <span class="inline-error" id="signupPasswordError">Password must be at least 8 characters</span>
                    <label for="confirmPassword">Confirm Password:</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                    <span class="inline-error" id="confirmPasswordError">Passwords do not match</span>
                    <div class="step-buttons">
                        <button type="button" onclick="nextStep(2)">Next</button>
                    </div>
                </div>
                <div class="step" id="step2" style="display:none;">
                    <h3>Step 2: Personal Details</h3>
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <span class="inline-error" id="genderError">Please select a gender</span>
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" required></textarea>
                    <span class="inline-error" id="addressError">Address is required</span>
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
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(1)">Previous</button>
                        <button type="button" onclick="nextStep(3)">Next</button>
                    </div>
                </div>
                <div class="step" id="step3" style="display:none;">
                    <h3>Step 3: Documents</h3>
                    <label for="national_id">National ID (PDF only):</label>
                    <input type="file" id="national_id" name="national_id" accept=".pdf" required>
                    <span class="inline-error" id="nationalIdError">National ID document is required</span>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(2)">Previous</button>
                        <button type="submit" name="signup">Register</button>
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
                <input type="hidden" id="bookingCarId" name="car_id">
                <input type="hidden" id="pricePerDay" name="price_per_day">
                <label for="carName">Car:</label>
                <input type="text" id="carName" readonly>
                <label for="pick_up_date">Pick-up Date:</label>
                <input type="date" id="pick_up_date" name="pick_up_date" required min="2025-04-19">
                <span class="inline-error" id="pickUpDateError">Pick-up date must be today or later</span>
                <label for="return_date">Return Date:</label>
                <input type="date" id="return_date" name="return_date" required min="2025-04-19">
                <span class="inline-error" id="returnDateError">Return date must be after pick-up date</span>
                <label for="totalCost">Estimated Cost:</label>
                <input type="text" id="totalCost" readonly>
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
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                <span class="inline-error" id="editEmailError">Valid email required</span>
                <label for="edit_phone">Phone Number (+260):</label>
                <input type="tel" id="edit_phone" name="edit_phone" pattern="\+260[0-9]{9}" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+260123456789" required>
                <span class="inline-error" id="editPhoneError">Phone number must be in +260 format</span>
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
                    <option value="chibanja" <?php echo ($user['location'] ?? '') == 'chibanja' ? 'selected' : ''; ?>>Chibanja</option>
                    <option value="mchengatuwa" <?php echo ($user['location'] ?? '') == 'mchengatuwa' ? 'selected' : ''; ?>>Mchengatuwa</option>
                </select>
                <span class="inline-error" id="editLocationError">Please select a location</span>
                <button type="submit" name="update_profile">Save Changes</button>
            </form>
        </div>
    </div>

    <footer>
        <p>© 2025 CarRental. All Rights Reserved.</p>
    </footer>

    <script>
        // Section Navigation
        function showSection(sectionId) {
            const sections = ['home', 'cars', 'about', 'contact', 'profile'];
            sections.forEach(id => {
                document.getElementById(id).style.display = id === sectionId ? 'block' : 'none';
            });
            if (sectionId === 'cars') {
                document.querySelector('input[name="search"]').value = '<?php echo htmlspecialchars($search); ?>';
                document.querySelector('select[name="fuel"]').value = '<?php echo htmlspecialchars($filter_fuel); ?>';
                document.querySelector('select[name="status"]').value = '<?php echo htmlspecialchars($filter_status); ?>';
            }
        }

        // Modal Management
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }

        document.querySelectorAll('.close').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.getAttribute('data-target')).style.display = 'none';
            });
        });

        document.getElementById('loginBtn')?.addEventListener('click', e => {
            e.preventDefault();
            closeAllModals();
            document.getElementById('loginModal').style.display = 'block';
        });

        window.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Modal Switching
        document.getElementById('showSignup')?.addEventListener('click', e => {
            e.preventDefault();
            closeAllModals();
            showStep(1);
            document.getElementById('signupModal').style.display = 'block';
        });

        document.getElementById('showLogin')?.addEventListener('click', e => {
            e.preventDefault();
            closeAllModals();
            document.getElementById('loginModal').style.display = 'block';
        });

        document.getElementById('showForgot')?.addEventListener('click', e => {
            e.preventDefault();
            closeAllModals();
            document.getElementById('forgotModal').style.display = 'block';
        });

        document.getElementById('backToLogin')?.addEventListener('click', e => {
            e.preventDefault();
            closeAllModals();
            document.getElementById('loginModal').style.display = 'block';
        });

        // Edit Profile Modal
        function showEditProfileModal() {
            closeAllModals();
            document.getElementById('editProfileModal').style.display = 'block';
        }

        // Booking Modal with Date Validation
        function showBookingModal(carId, carName, pricePerDay) {
            closeAllModals();
            document.getElementById('bookingCarId').value = carId;
            document.getElementById('carName').value = carName;
            document.getElementById('pricePerDay').value = pricePerDay;
            document.getElementById('bookingModal').style.display = 'block';

            const pickUpDate = document.getElementById('pick_up_date');
            const returnDate = document.getElementById('return_date');
            const totalCost = document.getElementById('totalCost');
            const pickUpDateError = document.getElementById('pickUpDateError');
            const returnDateError = document.getElementById('returnDateError');
            const today = '2025-04-19';

            function updateTotalCost() {
                pickUpDateError.style.display = 'none';
                returnDateError.style.display = 'none';
                totalCost.value = '';

                if (pickUpDate.value && returnDate.value) {
                    const start = new Date(pickUpDate.value);
                    const end = new Date(returnDate.value);
                    const todayDate = new Date(today);

                    if (start < todayDate) {
                        pickUpDateError.textContent = 'Pick-up date must be today or later';
                        pickUpDateError.style.display = 'block';
                        return;
                    }
                    if (end <= start) {
                        returnDateError.textContent = 'Return date must be after pick-up date';
                        returnDateError.style.display = 'block';
                        return;
                    }

                    const days = (end - start) / (1000 * 60 * 60 * 24);
                    totalCost.value = `${days * pricePerDay} Kwacha`;
                }
            }

            pickUpDate.addEventListener('change', updateTotalCost);
            returnDate.addEventListener('change', updateTotalCost);

            document.getElementById('bookingForm').addEventListener('submit', function(e) {
                const start = new Date(pickUpDate.value);
                const end = new Date(returnDate.value);
                const todayDate = new Date(today);

                if (start < todayDate) {
                    e.preventDefault();
                    pickUpDateError.textContent = 'Pick-up date must be today or later';
                    pickUpDateError.style.display = 'block';
                }
                if (end <= start) {
                    e.preventDefault();
                    returnDateError.textContent = 'Return date must be after pick-up date';
                    returnDateError.style.display = 'block';
                }
            });
        }

        // Signup Form
        function showStep(stepNumber) {
            document.querySelectorAll('.step').forEach(step => step.style.display = 'none');
            document.getElementById(`step${stepNumber}`).style.display = 'block';
            document.querySelectorAll('.progress-step').forEach(step => step.classList.remove('active'));
            document.getElementById(`progress${stepNumber}`).classList.add('active');
        }

        function validateStep1() {
            let valid = true;
            const email = document.getElementById('signupEmail');
            const phone = document.getElementById('phone');
            const password = document.getElementById('signupPassword');
            const confirmPassword = document.getElementById('confirmPassword');

            if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                document.getElementById('signupEmailError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('signupEmailError').style.display = 'none';
            }

            if (!phone.value.match(/^\+260[0-9]{9}$/)) {
                document.getElementById('phoneError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('phoneError').style.display = 'none';
            }

            if (password.value.length < 8) {
                document.getElementById('signupPasswordError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('signupPasswordError').style.display = 'none';
            }

            if (password.value !== confirmPassword.value) {
                document.getElementById('confirmPasswordError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('confirmPasswordError').style.display = 'none';
            }

            return valid;
        }

        function validateStep2() {
            let valid = true;
            const gender = document.getElementById('gender');
            const address = document.getElementById('address');
            const location = document.getElementById('location');

            if (!gender.value) {
                document.getElementById('genderError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('genderError').style.display = 'none';
            }

            if (!address.value.trim()) {
                document.getElementById('addressError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('addressError').style.display = 'none';
            }

            if (!location.value) {
                document.getElementById('locationError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('locationError').style.display = 'none';
            }

            return valid;
        }

        function validateStep3() {
            let valid = true;
            const nationalId = document.getElementById('national_id');

            if (!nationalId.files.length) {
                document.getElementById('nationalIdError').style.display = 'block';
                valid = false;
            } else {
                document.getElementById('nationalIdError').style.display = 'none';
            }

            return valid;
        }

        function nextStep(stepNumber) {
            let valid = true;
            if (stepNumber === 2) valid = validateStep1();
            else if (stepNumber === 3) valid = validateStep2();
            if (valid) showStep(stepNumber);
        }

        function prevStep(stepNumber) {
            showStep(stepNumber);
        }

        // Contact Form Validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const message = document.getElementById('message');
            const emailError = document.getElementById('emailError');
            const messageError = document.getElementById('messageError');
            let valid = true;

            if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                emailError.style.display = 'block';
                valid = false;
            } else {
                emailError.style.display = 'none';
            }

            if (!message.value.trim()) {
                messageError.style.display = 'block';
                valid = false;
            } else {
                messageError.style.display = 'none';
            }

            if (!valid) {
                e.preventDefault();
            }
        });

        // Booking Filter
        function filterBookings() {
            const search = document.getElementById('bookingSearch').value.toLowerCase();
            const status = document.getElementById('bookingStatusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#bookingsTable tbody tr');

            rows.forEach(row => {
                const carName = row.cells[0].textContent.toLowerCase();
                const rowStatus = row.cells[3].textContent.toLowerCase();

                const matchesSearch = search ? carName.includes(search) : true;
                const matchesStatus = status ? rowStatus === status : true;

                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }

        function resetBookingFilter() {
            document.getElementById('bookingSearch').value = '';
            document.getElementById('bookingStatusFilter').value = '';
            filterBookings();
        }

        // Image Slider
        let slideIndex = 0;
        const slides = document.querySelectorAll('.slide');

        function showSlides() {
            slides.forEach(slide => slide.style.display = 'none');
            slideIndex = (slideIndex % slides.length) + 1;
            slides[slideIndex - 1].style.display = 'block';
            setTimeout(showSlides, 3000);
        }

        showSlides();

        // Persist section state
        <?php if (isset($_GET['section'])): ?>
            showSection('<?php echo htmlspecialchars($_GET['section']); ?>');
        <?php endif; ?>
    </script>
</body>
</html>
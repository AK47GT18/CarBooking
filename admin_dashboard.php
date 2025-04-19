<?php
require 'config.php';



// Check if admin is logged in


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
                $stmt = $pdo->prepare("UPDATE users SET payment_status = 'paid', has_paid = 1 WHERE id = ?");
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

// Handle payment verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_payment'])) {
    $tx_ref = $_POST['tx_ref'];
    $verification = verifyPayment($pdo, $tx_ref);
    header("Location: admin_dashboard.php?section=manage-" . ($_POST['type'] == 'signup' ? 'users' : 'bookings') . "&message=" . urlencode($verification['message']));
    exit;
}

// Handle user approval/decline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$action, $user_id]);

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $message = $action == 'approved' 
        ? "Your CarRental account has been approved. You can now log in and book cars."
        : "Your CarRental account has been declined. Please contact support for more information.";
    sendEmail($user['email'], "Account Status Update", $message);

    header("Location: admin_dashboard.php?section=manage-users");
    exit;
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    sendEmail($user['email'], "Account Deletion Notification", "Your CarRental account has been deleted by an administrator. Contact support if this was an error.");

    header("Location: admin_dashboard.php?section=manage-users");
    exit;
}

// Handle car addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_car'])) {
    $name = $_POST['name'];
    $model = $_POST['model'];
    $license_plate = $_POST['license_plate'];
    $capacity = $_POST['capacity'];
    $fuel_type = $_POST['fuel_type'];
    $price_per_day = $_POST['price_per_day'];
    $featured = isset($_POST['featured']) ? 1 : 0;

    $imageBase64 = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
        } else {
            error_log("Invalid file type or size: Type=$fileType, Size=$fileSize");
        }
    }

    $stmt = $pdo->prepare("INSERT INTO cars (name, model, license_plate, capacity, fuel_type, price_per_day, featured, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $imageBase64]);

    header("Location: admin_dashboard.php?section=manage-cars");
    exit;
}

// Handle car update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_car'])) {
    $car_id = $_POST['car_id'];
    $name = $_POST['name'];
    $model = $_POST['model'];
    $license_plate = $_POST['license_plate'];
    $capacity = $_POST['capacity'];
    $fuel_type = $_POST['fuel_type'];
    $price_per_day = $_POST['price_per_day'];
    $featured = isset($_POST['featured']) ? 1 : 0;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
            $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $imageBase64, $car_id]);
        } else {
            error_log("Invalid file type or size: Type=$fileType, Size=$fileSize");
            $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ? WHERE id = ?");
            $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $car_id]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ? WHERE id = ?");
        $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $car_id]);
    }

    header("Location: admin_dashboard.php?section=manage-cars");
    exit;
}

// Handle car deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_car'])) {
    $car_id = $_POST['car_id'];
    $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    header("Location: admin_dashboard.php?section=manage-cars");
    exit;
}

// Handle booking approval/decline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_action'])) {
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$action, $booking_id]);

    $stmt = $pdo->prepare("SELECT u.email, c.id AS car_id FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    $message = $action == 'booked' 
        ? "Your booking has been approved. Enjoy your ride!"
        : "Your booking has been cancelled. Please contact support for more information.";
    sendEmail($booking['email'], "Booking Status Update", $message);

    if ($action == 'cancelled') {
        $stmt = $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = ?");
        $stmt->execute([$booking['car_id']]);
    }

    header("Location: admin_dashboard.php?section=manage-bookings");
    exit;
}

// Fetch data with search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Users with signup payments
$usersQuery = "SELECT u.*, p.status AS payment_status, p.amount AS payment_amount, p.tx_ref FROM users u LEFT JOIN payments p ON u.id = p.user_id AND p.type = 'signup' WHERE u.email LIKE ? OR u.address LIKE ? OR u.location LIKE ?";
$usersParams = ["%$search%", "%$search%", "%$search%"];
if ($filter != 'all') {
    $usersQuery .= " AND u.status = ?";
    $usersParams[] = $filter;
}
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute($usersParams);
$users = $usersStmt->fetchAll();

// Cars
$carsQuery = "SELECT * FROM cars WHERE name LIKE ? OR model LIKE ? OR license_plate LIKE ?";
$carsParams = ["%$search%", "%$search%", "%$search%"];
if ($filter != 'all') {
    $carsQuery .= " AND status = ?";
    $carsParams[] = $filter;
}
$carsStmt = $pdo->prepare($carsQuery);
$carsStmt->execute($carsParams);
$cars = $carsStmt->fetchAll();

// Bookings
$bookingsQuery = "SELECT b.*, u.email AS customer, c.name AS car_name, p.status AS payment_status, p.amount AS payment_amount, p.tx_ref 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN cars c ON b.car_id = c.id 
                 LEFT JOIN payments p ON b.id = p.booking_id 
                 WHERE b.booking_id LIKE ? OR u.email LIKE ? OR c.name LIKE ?";
$bookingsParams = ["%$search%", "%$search%", "%$search%"];
if ($filter != 'all') {
    $bookingsQuery .= " AND b.status = ?";
    $bookingsParams[] = $filter;
}
$bookingsStmt = $pdo->prepare($bookingsQuery);
$bookingsStmt->execute($bookingsParams);
$bookings = $bookingsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CarRental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { display: flex; min-height: 100vh; background-color: #f4f7fc; color: #333; }
        .sidebar { width: 250px; background-color: #0b0c10; color: #ffffff; padding: 20px 0; position: fixed; height: 100%; transition: width 0.3s ease; z-index: 100; }
        .sidebar.collapsed { width: 0; overflow: hidden; }
        .sidebar h2 { text-align: center; margin-bottom: 20px; font-size: 1.5rem; color: #66fcf1; font-weight: 600; }
        .welcome-text { color: #66fcf1; text-align: center; padding: 0 1rem; margin-bottom: 1rem; font-size: 0.9rem; font-weight: 400; }
        .sidebar ul { list-style: none; }
        .sidebar ul li { padding: 15px 20px; cursor: pointer; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px; color: #fff; font-size: 1rem; font-weight: 500; }
        .sidebar ul li:hover { background-color: #45a29e; }
        .sidebar ul li i { width: 24px; font-size: 1.2rem; }
        .content { margin-left: 250px; padding: 80px 20px 20px; width: calc(100% - 250px); transition: margin-left 0.3s ease, width 0.3s ease; }
        .content.collapsed { margin-left: 0; width: 100%; }
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 0 20px; background-color: #ffffff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); height: 60px; position: fixed; top: 0; right: 0; left: 250px; z-index: 99; transition: left: 0.3s ease; }
        .topbar.collapsed { left: 0; }
        .hamburger { font-size: 1.5rem; cursor: pointer; color: #333; }
        .user-actions { display: flex; align-items: center; gap: 20px; }
        .notification-bell { position: relative; cursor: pointer; font-size: 1.2rem; }
        .notification-bell .badge { position: absolute; top: -5px; right: -5px; background-color: #e74c3c; color: white; border-radius: 50%; width: 15px; height: 15px; font-size: 10px; display: flex; justify-content: center; align-items: center; }
        .user-profile-small { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 500; }
        .user-profile-small img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .slider-container { position: relative; width: 100%; height: 300px; overflow: hidden; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .slider { display: flex; width: 300%; height: 100%; transition: transform 0.5s ease-in-out; }
        .slide { width: 100%; height: 100%; position: relative; display: none; }
        .slide.active { display: block; }
        .slide img { width: 100%; height: 100%; object-fit: cover; }
        .slide-text { position: absolute; bottom: 20px; left: 20px; background: rgba(0, 0, 0, 0.7); color: #ffffff; padding: 10px 20px; border-radius: 6px; font-size: 1.2rem; font-weight: 500; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5); }
        .user-profile { display: flex; align-items: flex-start; gap: 20px; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 20px; }
        .profile-image-container { text-align: center; }
        .profile-image { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .upload-btn { background: #0b0c10; color: #66fcf1; padding: 8px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9rem; transition: opacity 0.3s ease; }
        .upload-btn:hover { opacity: 0.9; }
        .user-profile .details { flex: 1; }
        .user-profile .details h3 { font-size: 1.5rem; color: #2c3e50; margin-bottom: 15px; font-weight: 600; }
        .profile-info { display: flex; flex-direction: column; gap: 15px; }
        .profile-info label { font-weight: 500; color: #34495e; font-size: 0.95rem; }
        .profile-info input { padding: 10px; border-radius: 6px; border: 1px solid #ddd; background-color: #f5f5f5; width: 100%; font-size: 1rem; font-weight: 400; }
        .profile-info input:disabled { background-color: #e9ecef; cursor: not-allowed; }
        .profile-info button { background: #0b0c10; color: #66fcf1; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; margin-top: 10px; align-self: flex-start; transition: opacity 0.3s ease; }
        .profile-info button:hover { opacity: 0.9; }
        .user-activity { background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); margin-bottom: 60px; }
        .user-activity h2 { font-size: 1.5rem; color: #2c3e50; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; font-weight: 600; }
        .user-activity ul { list-style: none; }
        .user-activity ul li { font-size: 1rem; color: #7f8c8d; margin-bottom: 10px; padding: 10px; border-radius: 4px; background-color: #f8f9fa; border-left: 3px solid #45a29e; font-weight: 400; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dashboard-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; }
        .controls { display: flex; gap: 10px; margin-bottom: 20px; }
        .controls input[type="text"], .controls select { padding: 10px; border-radius: 6px; border: 1px solid #ddd; flex-grow: 1; max-width: 400px; font-size: 1rem; font-weight: 400; }
        .controls button, .action-btn { padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; background-color: #0b0c10; color: #66fcf1; font-weight: 500; font-size: 0.9rem; transition: opacity 0.3s ease; }
        .controls button:hover, .action-btn:hover { opacity: 0.9; }
        .content-card { background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); padding: 20px; margin-bottom: 20px; }
        .content-card h2 { font-size: 1.5rem; color: #2c3e50; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .data-table th, .data-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        .data-table th { background-color: #0b0c10; color: #66fcf1; font-weight: 500; }
        .data-table td { color: #34495e; font-weight: 400; }
        .data-table tbody tr:hover { background-color: #f8f9fa; }
        .status { padding: 4px 8px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-block; text-align: center; }
        .status-booked, .status-approved { background-color: #e3fcef; color: #00875a; }
        .status-available, .status-pending { background-color: #fff8e6; color: #b25000; }
        .status-cancelled, .status-declined { background-color: #fee4e2; color: #d92d20; }
        .action-btn-container { display: flex; gap: 5px; justify-content: flex-start; }
        .action-btn { padding: 6px 12px; font-size: 0.85rem; }
        .action-btn.approve, .verify-btn { background-color: #0b0c10; color: #66fcf1; }
        .action-btn.decline, .action-btn.delete { background-color: #fee4e2; color: #d92d20; }
        .action-btn.edit { background-color: #0b0c10; color: #66fcf1; }
        .verify-btn:hover { background-color: #66fcf1; color: #0b0c10; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: #ffffff; border-radius: 8px; padding: 20px; width: 500px; max-width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 1.5rem; color: #2c3e50; font-weight: 600; }
        .close { font-size: 1.5rem; cursor: pointer; color: #7f8c8d; }
        .modal-body form { display: flex; flex-direction: column; gap: 15px; }
        .modal-body input, .modal-body textarea, .modal-body select { padding: 10px; border-radius: 6px; border: 1px solid #ddd; width: 100%; font-size: 1rem; font-weight: 400; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .car-thumbnail { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; }
        .alert { background: #ffdddd; border-left: 6px solid #f44336; padding: 10px; margin-bottom: 15px; }
        .success { background: #ddffdd; border-left: 6px solid #4caf50; padding: 10px; margin-bottom: 15px; }
        .table-container { overflow-x: auto; }
        .footer { text-align: center; padding: 15px; background-color: #0b0c10; color: #ecf0f1; position: fixed; width: 100%; bottom: 0; left: 0; z-index: 98; font-size: 0.9rem; font-weight: 400; }
        .section { display: none; }
        .section.active { display: block; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .sidebar.collapsed { width: 0; }
            .content { margin-left: 200px; width: calc(100% - 200px); }
            .content.collapsed { margin-left: 0; width: 100%; }
            .topbar { left: 200px; }
            .topbar.collapsed { left: 0; }
            .sidebar h2, .welcome-text { font-size: 0.8rem; }
            .sidebar ul li { padding: 10px; font-size: 0.9rem; }
            .slider-container { height: 200px; }
            .slide-text { font-size: 1rem; padding: 8px 15px; }
            .data-table th, .data-table td { padding: 8px; font-size: 0.85rem; }
            .action-btn { padding: 6px 10px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h2>MIBESA</h2>
        <div class="welcome-text">Welcome, Admin!</div>
        <ul>
            <li onclick="showSection('dashboard')"><i class="fas fa-home"></i> Dashboard</li>
            <li onclick="showSection('manage-cars')"><i class="fas fa-car"></i> Manage Cars</li>
            <li onclick="showSection('manage-users')"><i class="fas fa-users"></i> Manage Users</li>
            <li onclick="showSection('manage-bookings')"><i class="fas fa-calendar-check"></i> Manage Bookings</li>
            <li onclick="showSection('profile')"><i class="fas fa-user"></i> Profile</li>
            <li onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</li>
        </ul>
    </div>

    <div class="topbar" id="topbar">
        <div class="hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-actions">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge"><?php echo count($users); ?></span>
            </div>
            <div class="user-profile-small" onclick="showSection('profile')">
                <img src="https://via.placeholder.com/80" alt="Admin Profile">
                <span>Admin</span>
            </div>
        </div>
    </div>

    <div class="content" id="content">
        <?php if (isset($_GET['message'])): ?>
            <div class="success"><?php echo htmlspecialchars($_GET['message']); ?></div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div class="section <?php echo (!isset($_GET['section']) || $_GET['section'] == 'dashboard') ? 'active' : ''; ?>" id="dashboard">
            <div class="dashboard-header">
                <h1>Manager Dashboard</h1>
            </div>
            <div class="slider-container">
                <div class="slider" id="dashboardSlider">
                    <div class="slide active">
                        <img src="https://via.placeholder.com/1200x300?text=Car+1" alt="Car 1">
                        <div class="slide-text">Manage Your Fleet Efficiently</div>
                    </div>
                    <div class="slide">
                        <img src="https://via.placeholder.com/1200x300?text=Car+2" alt="Car 2">
                        <div class="slide-text">Streamline Booking Approvals</div>
                    </div>
                    <div class="slide">
                        <img src="https://via.placeholder.com/1200x300?text=Car+3" alt="Car 3">
                        <div class="slide-text">Monitor User Activity</div>
                    </div>
                </div>
            </div>
            <div class="content-card">
                <h2>Recent Activity</h2>
                <ul>
                    <li>Approved new car booking request for Toyota Corolla.</li>
                    <li>Updated user profile for Mary Smith.</li>
                    <li>Added new car to the fleet: Nissan X-Trail.</li>
                    <li>Reviewed booking history for Paul Johnson.</li>
                </ul>
            </div>
        </div>

        <!-- Manage Cars Section -->
        <div class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-cars') ? 'active' : ''; ?>" id="manage-cars">
            <div class="dashboard-header">
                <h1>Manage Cars</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search cars by name, model, etc..." id="carSearch" value="<?php echo htmlspecialchars($search); ?>">
                <select id="carFilter">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="available" <?php echo $filter == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo $filter == 'booked' ? 'selected' : ''; ?>>Booked</option>
                </select>
                <button onclick="applyCarSearchFilter()"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>
                    Car Inventory
                    <button class="action-btn" onclick="openModal('carModal')">
                        <i class="fas fa-plus"></i> Add New Car
                    </button>
                </h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Car Image</th>
                                <th>Car Name</th>
                                <th>Model</th>
                                <th>License Plate</th>
                                <th>Capacity</th>
                                <th>Fuel Type</th>
                                <th>Price/Day</th>
                                <th>Featured</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cars as $car): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $imageSrc = !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 
                                            ? htmlspecialchars($car['image']) 
                                            : 'https://via.placeholder.com/60?text=No+Image';
                                        ?>
                                        <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>" class="car-thumbnail">
                                    </td>
                                    <td><?php echo htmlspecialchars($car['name']); ?></td>
                                    <td><?php echo htmlspecialchars($car['model']); ?></td>
                                    <td><?php echo htmlspecialchars($car['license_plate']); ?></td>
                                    <td><?php echo htmlspecialchars($car['capacity']); ?></td>
                                    <td><?php echo htmlspecialchars($car['fuel_type']); ?></td>
                                    <td><?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> MWK</td>
                                    <td><?php echo $car['featured'] ? 'Yes' : 'No'; ?></td>
                                    <td><span class="status status-<?php echo strtolower($car['status']); ?>"><?php echo htmlspecialchars($car['status']); ?></span></td>
                                    <td>
                                        <div class="action-btn-container">
                                            <button class="action-btn edit" onclick='openEditCarModal(<?php echo json_encode($car); ?>)'>Edit</button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <button type="submit" name="delete_car" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this car?')">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Users Section -->
        <div class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-users') ? 'active' : ''; ?>" id="manage-users">
            <div class="dashboard-header">
                <h1>Manage Users</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search users by email, address, etc..." id="userSearch" value="<?php echo htmlspecialchars($search); ?>">
                <select id="userFilter">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filter == 'declined' ? 'selected' : ''; ?>>Declined</option>
                </select>
                <button onclick="applyUserSearchFilter()"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>User Accounts</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Address</th>
                                <th>Location</th>
                                <th>Has Paid</th>
                                <th>Payment Status</th>
                                <th>Payment Amount</th>
                                <th>Tx Ref</th>
                                <th>National ID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($user['address']); ?></td>
                                    <td><?php echo htmlspecialchars($user['location']); ?></td>
                                    <td><?php echo $user['has_paid'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo htmlspecialchars($user['payment_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['payment_amount'] ?? 'N/A'); ?> MWK</td>
                                    <td><?php echo htmlspecialchars($user['tx_ref'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($user['national_id']): ?>
                                            <a href="Uploads/<?php echo htmlspecialchars($user['national_id']); ?>" target="_blank">View</a>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status status-<?php echo strtolower($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="action-btn-container">
                                                <?php if ($user['status'] == 'pending' && $user['payment_status'] == 'paid'): ?>
                                                    <button type="submit" name="user_action" value="approved" class="action-btn approve">Approve</button>
                                                    <button type="submit" name="user_action" value="declined" class="action-btn decline">Decline</button>
                                                <?php endif; ?>
                                                <button type="submit" name="delete_user" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                                <?php if ($user['payment_status'] == 'pending' && $user['tx_ref']): ?>
                                                    <input type="hidden" name="type" value="signup">
                                                    <button type="submit" name="verify_payment" class="action-btn verify-btn">Verify Payment</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Bookings Section -->
        <div class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-bookings') ? 'active' : ''; ?>" id="manage-bookings">
            <div class="dashboard-header">
                <h1>Manage Bookings</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search bookings by ID, customer, car..." id="bookingSearch" value="<?php echo htmlspecialchars($search); ?>">
                <select id="bookingFilter">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="booked" <?php echo $filter == 'booked' ? 'selected' : ''; ?>>Booked</option>
                    <option value="cancelled" <?php echo $filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button onclick="applyBookingSearchFilter()"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>All Bookings</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Car</th>
                                <th>Pick-up Date</th>
                                <th>Return Date</th>
                                <th>Total Cost</th>
                                <th>Status</th>
                                <th>Payment Status</th>
                                <th>Payment Amount</th>
                                <th>Tx Ref</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['customer']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['car_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['pick_up_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['return_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['total_cost']); ?> MWK</td>
                                    <td><span class="status status-<?php echo strtolower($booking['status']); ?>"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                    <td><?php echo htmlspecialchars($booking['payment_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($booking['payment_amount'] ?? 'N/A'); ?> MWK</td>
                                    <td><?php echo htmlspecialchars($booking['tx_ref'] ?? 'N/A'); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <div class="action-btn-container">
                                                <?php if ($booking['status'] == 'pending' && $booking['payment_status'] == 'paid'): ?>
                                                    <button type="submit" name="booking_action" value="booked" class="action-btn approve">Approve</button>
                                                    <button type="submit" name="booking_action" value="cancelled" class="action-btn decline">Cancel</button>
                                                <?php endif; ?>
                                                <?php if ($booking['payment_status'] == 'pending' && $booking['tx_ref']): ?>
                                                    <input type="hidden" name="type" value="booking">
                                                    <button type="submit" name="verify_payment" class="action-btn verify-btn">Verify Payment</button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Profile Section -->
        <div class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'profile') ? 'active' : ''; ?>" id="profile">
            <div class="dashboard-header">
                <h1>Profile</h1>
            </div>
            <div class="content-card">
                <h2>Profile Information</h2>
                <div class="user-profile">
                    <div class="profile-image-container">
                        <img src="https://via.placeholder.com/150" alt="Admin Profile Picture" class="profile-image">
                        <input type="file" id="profile-image-upload" hidden>
                        <label for="profile-image-upload" class="upload-btn">Change Image</label>
                    </div>
                    <div class="details">
                        <div class="profile-info">
                            <label for="name">Name</label>
                            <input type="text" id="name" value="Admin" disabled>
                            <label for="email">Email</label>
                            <input type="email" id="email" value="admin@example.com" disabled>
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" value="+260 999 888 777">
                            <label for="address">Address</label>
                            <input type="text" id="address" value="Lusaka, Zambia">
                            <button onclick="saveProfile()">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Car Modal -->
    <div class="modal" id="carModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Car</h3>
                <span class="close" onclick="closeModal('carModal')">×</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="text" name="name" placeholder="Car Name" required>
                    <input type="text" name="model" placeholder="Car Model" required>
                    <input type="text" name="license_plate" placeholder="License Plate" required>
                    <input type="number" name="capacity" placeholder="Capacity" required min="1" max="10">
                    <select name="fuel_type" required>
                        <option value="" disabled selected>Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Electric">Electric</option>
                    </select>
                    <input type="number" name="price_per_day" placeholder="Price per Day (MWK)" step="0.01" required>
                    <label><input type="checkbox" name="featured"> Featured Car</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp" required>
                    <div class="modal-footer">
                        <button type="button" class="action-btn" onclick="closeModal('carModal')">Cancel</button>
                        <button type="submit" name="add_car" class="action-btn approve">Add Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Car Modal -->
    <div class="modal" id="editCarModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Car</h3>
                <span class="close" onclick="closeModal('editCarModal')">×</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="car_id" id="editCarId">
                    <input type="text" name="name" id="editCarName" placeholder="Car Name" required>
                    <input type="text" name="model" id="editCarModel" placeholder="Car Model" required>
                    <input type="text" name="license_plate" id="editCarLicensePlate" placeholder="License Plate" required>
                    <input type="number" name="capacity" id="editCarCapacity" placeholder="Capacity" required min="1" max="10">
                    <select name="fuel_type" id="editCarFuelType" required>
                        <option value="" disabled>Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Electric">Electric</option>
                    </select>
                    <input type="number" name="price_per_day" id="editCarPricePerDay" placeholder="Price per Day (MWK)" step="0.01" required>
                    <label><input type="checkbox" name="featured" id="editCarFeatured"> Featured Car</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp">
                    <div class="modal-footer">
                        <button type="button" class="action-btn" onclick="closeModal('editCarModal')">Cancel</button>
                        <button type="submit" name="update_car" class="action-btn approve">Update Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>© 2025 CarRental. All rights reserved.</p>
    </div>

    <script>
        // Toggle sidebar function
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('collapsed');
            document.getElementById('topbar').classList.toggle('collapsed');
        }

        // Show section function
        function showSection(sectionId) {
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => section.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
        }

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Edit car modal
        function openEditCarModal(car) {
            document.getElementById('editCarId').value = car.id;
            document.getElementById('editCarName').value = car.name;
            document.getElementById('editCarModel').value = car.model;
            document.getElementById('editCarLicensePlate').value = car.license_plate;
            document.getElementById('editCarCapacity').value = car.capacity;
            document.getElementById('editCarFuelType').value = car.fuel_type;
            document.getElementById('editCarPricePerDay').value = car.price_per_day || 50;
            document.getElementById('editCarFeatured').checked = car.featured == 1;
            openModal('editCarModal');
        }

        // Profile functions
        function saveProfile() {
            alert('Profile changes saved successfully!');
        }

        // Search and filter functions
        function applyCarSearchFilter() {
            const search = document.getElementById('carSearch').value;
            const filter = document.getElementById('carFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-cars&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        function applyUserSearchFilter() {
            const search = document.getElementById('userSearch').value;
            const filter = document.getElementById('userFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-users&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        function applyBookingSearchFilter() {
            const search = document.getElementById('bookingSearch').value;
            const filter = document.getElementById('bookingFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-bookings&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        // Logout function
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Dashboard slider
        let slideIndex = 0;
        const slides = document.querySelectorAll('.slide');
        function showSlides() {
            slides.forEach(slide => slide.classList.remove('active'));
            slideIndex = (slideIndex + 1) % slides.length;
            slides[slideIndex].classList.add('active');
            setTimeout(showSlides, 3000);
        }
        if (slides.length > 0) {
            slides[0].classList.add('active');
            showSlides();
        }
    </script>
</body>
</html>
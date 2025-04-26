<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// CSRF Token Functions
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fetch admin profile
$stmt = $pdo->prepare("SELECT name, email, phone, address, profile_image FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
$admin['name'] = $admin['name'] ?? 'Admin';
$admin['email'] = $admin['email'] ?? 'admin@example.com';
$admin['phone'] = $admin['phone'] ?? '+260 999 888 777';
$admin['address'] = $admin['address'] ?? 'Lusaka, Zambia';
$admin['profile_image'] = $admin['profile_image'] && strpos($admin['profile_image'], 'data:image/') === 0 
    ? $admin['profile_image'] 
    : 'https://via.placeholder.com/150';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=profile&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($phone) || !preg_match('/^0[0-9]{9}$/', $phone)) {
        $errors[] = "Phone number must be 10 digits starting with 0.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }

    $imageBase64 = $admin['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 2 * 1024 * 1024;
        $fileType = $_FILES['profile_image']['type'];
        $fileSize = $_FILES['profile_image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['profile_image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
        } else {
            $errors[] = "Invalid image type or size (max 2MB).";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, phone = ?, address = ?, profile_image = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $address, $imageBase64, $_SESSION['admin_id']]);
        header("Location: admin_dashboard.php?section=profile&message=" . urlencode("Profile updated successfully"));
        exit;
    } else {
        header("Location: admin_dashboard.php?section=profile&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
}

// Handle user approval/decline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];
    $csrf_token = $_POST['csrf_token'];
    $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$action, $user_id]);

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    $message = $action == 'approved' 
        ? "Your CarRental account has been approved. You can now log in and book cars."
        : "Your CarRental account has been declined. Reason: $rejection_reason. Please contact support for more information.";
    sendEmail($user['email'], "Account Status Update", $message);

    header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User $action successfully"));
    exit;
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $payment_count = $stmt->fetchColumn();

    if ($payment_count > 0) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Cannot delete user: they have associated payments."));
        exit;
    }

    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    sendEmail($user['email'], "Account Deletion Notification", "Your CarRental account has been deleted by an administrator. Contact support if this was an error.");

    header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User deleted successfully"));
    exit;
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = $_POST['user_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $occupation = trim($_POST['occupation']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $location = trim($_POST['location']);
    $kin_name = trim($_POST['kin_name']);
    $kin_relationship = trim($_POST['kin_relationship']);
    $kin_phone = trim($_POST['kin_phone']);
    $status = $_POST['status'];
    $username = trim($_POST['username']);
    $age = $_POST['age'];
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (empty($phone) || !preg_match('/^0[0-9]{9}$/', $phone)) {
        $errors[] = "Phone number must be 10 digits starting with 0.";
    }
    if (empty($gender) || !in_array($gender, ['Male', 'Female', 'Other'])) {
        $errors[] = "Valid gender is required.";
    }
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    if (empty($location)) {
        $errors[] = "Location is required.";
    }
    if (empty($status) || !in_array($status, ['pending', 'approved', 'declined'])) {
        $errors[] = "Valid status is required.";
    }
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    if (empty($age) || $age < 18) {
        $errors[] = "Age must be 18 or older.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, occupation = ?, email = ?, phone = ?, gender = ?, address = ?, location = ?, kin_name = ?, kin_relationship = ?, kin_phone = ?, status = ?, username = ?, age = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $occupation, $email, $phone, $gender, $address, $location, $kin_name, $kin_relationship, $kin_phone, $status, $username, $age, $user_id]);

        sendEmail($email, "Profile Updated", "Your CarRental account details have been updated by an administrator.");

        header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User updated successfully"));
        exit;
    } else {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
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
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $imageBase64 = NULL;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
        } else {
            header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid image type or size"));
            exit;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO cars (name, model, license_plate, capacity, fuel_type, price_per_day, featured, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $imageBase64]);

    header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car added successfully"));
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
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
            $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $imageBase64, $car_id]);
        } else {
            header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid image type or size"));
            exit;
        }
    } else {
        $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ? WHERE id = ?");
        $stmt->execute([$name, $model, $license_plate, $capacity, $fuel_type, $price_per_day, $featured, $car_id]);
    }

    header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car updated successfully"));
    exit;
}

// Handle car deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_car'])) {
    $car_id = $_POST['car_id'];
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car deleted successfully"));
    exit;
}

// Handle booking approval/decline
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id'], $_POST['action'], $_POST['csrf_token'])) {
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action'];
    $csrf_token = $_POST['csrf_token'];

    if (!verify_csrf_token($csrf_token)) {
        header("Location: admin_dashboard.php?section=manage-bookings&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
    $stmt->execute([$action, $booking_id]);

    $stmt = $pdo->prepare("SELECT u.email, c.id AS car_id FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    $message = $action == 'booked' 
        ? "Your booking has been approved. Enjoy your ride!"
        : "Your booking has been cancelled. Please contact support for more information.";
    sendEmail($booking['email'], "Booking Status Update", $message);

    if ($action == 'booked') {
        $stmt = $pdo->prepare("UPDATE cars SET status = 'booked' WHERE id = ?");
        $stmt->execute([$booking['car_id']]);
    } elseif ($action == 'cancelled') {
        $stmt = $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = ?");
        $stmt->execute([$booking['car_id']]);
    }

    header("Location: admin_dashboard.php?section=manage-bookings&message=" . urlencode("Booking $action successfully"));
    exit;
}

// Fetch dashboard statistics
$customer_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$car_count = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
$booking_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$recent_bookings = $pdo->query("SELECT b.*, u.email AS customer, c.name AS car_name 
                                FROM bookings b 
                                JOIN users u ON b.user_id = u.id 
                                JOIN cars c ON b.car_id = c.id 
                                ORDER BY b.created_at DESC 
                                LIMIT 5")->fetchAll();

// Fetch data with search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Users
$usersQuery = "SELECT u.* 
               FROM users u 
               WHERE u.email LIKE ? OR u.address LIKE ? OR u.location LIKE ? OR u.kin_name LIKE ? OR u.kin_phone LIKE ? OR u.username LIKE ? 
               OR COALESCE(u.first_name, '') LIKE ? OR COALESCE(u.last_name, '') LIKE ? OR COALESCE(u.occupation, '') LIKE ?";
$usersParams = ["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"];
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
$bookingsQuery = "SELECT b.*, u.email AS customer, c.name AS car_name 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN cars c ON b.car_id = c.id 
                 WHERE b.booking_id LIKE ? OR u.email LIKE ? OR c.name LIKE ?";
$bookingsParams = ["%$search%", "%$search%", "%$search%"];
if ($filter != 'all') {
    $bookingsQuery .= " AND b.status = ?";
    $bookingsParams[] = $filter;
}
$bookingsStmt = $pdo->prepare($bookingsQuery);
$bookingsStmt->execute($bookingsParams);
$bookings = $bookingsStmt->fetchAll();

// Payments
$paymentsQuery = "SELECT p.*, u.email AS user_email, b.booking_id 
                 FROM payments p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 LEFT JOIN bookings b ON p.booking_id = b.id 
                 WHERE p.tx_ref LIKE ? OR p.charge_id LIKE ?";
$paymentsParams = ["%$search%", "%$search%"];
$paymentsStmt = $pdo->prepare($paymentsQuery);
$paymentsStmt->execute($paymentsParams);
$payments = $paymentsStmt->fetchAll();

// Reports
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$report_data = [];
if ($report_type) {
    switch ($report_type) {
        case 'users':
            $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.occupation, u.username, u.email, u.phone, u.gender, u.address, u.location, u.kin_name, u.kin_relationship, u.kin_phone, u.status, u.age 
                                   FROM users u 
                                   WHERE u.email LIKE ? OR u.address LIKE ? OR u.location LIKE ? OR u.kin_name LIKE ? OR u.kin_phone LIKE ? OR u.username LIKE ? 
                                   OR COALESCE(u.first_name, '') LIKE ? OR COALESCE(u.last_name, '') LIKE ? OR COALESCE(u.occupation, '') LIKE ?");
            $stmt->execute(["%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"]);
            $report_data = $stmt->fetchAll();
            break;
        case 'bookings':
            $stmt = $pdo->prepare("SELECT b.booking_id, c.name AS car_name, b.pick_up_date, b.return_date, b.total_days, b.total_cost, b.status, b.payment_status, u.email, u.gender 
                                   FROM bookings b 
                                   JOIN users u ON b.user_id = u.id 
                                   JOIN cars c ON b.car_id = c.id 
                                   WHERE b.booking_id LIKE ? OR u.email LIKE ? OR c.name LIKE ?");
            $stmt->execute(["%$search%", "%$search%", "%$search%"]);
            $report_data = $stmt->fetchAll();
            break;
        case 'cars':
            $stmt = $pdo->prepare("SELECT name, model, license_plate, capacity, fuel_type, price_per_day, status, featured 
                                   FROM cars 
                                   WHERE name LIKE ? OR model LIKE ? OR license_plate LIKE ?");
            $stmt->execute(["%$search%", "%$search%", "%$search%"]);
            $report_data = $stmt->fetchAll();
            break;
        case 'most_booked_cars':
            $stmt = $pdo->prepare("SELECT name, model, license_plate, capacity, fuel_type, price_per_day, status, featured, booking_count 
                                   FROM cars 
                                   WHERE name LIKE ? OR model LIKE ? OR license_plate LIKE ? 
                                   ORDER BY booking_count DESC");
            $stmt->execute(["%$search%", "%$search%", "%$search%"]);
            $report_data = $stmt->fetchAll();
            break;
        case 'payments':
            $stmt = $pdo->prepare("SELECT p.id, u.email AS user_email, b.booking_id, p.tx_ref, p.amount, p.status, p.type, p.created_at, p.charge_id 
                                   FROM payments p 
                                   LEFT JOIN users u ON p.user_id = u.id 
                                   LEFT JOIN bookings b ON p.booking_id = b.id 
                                   WHERE p.tx_ref LIKE ? OR p.charge_id LIKE ?");
            $stmt->execute(["%$search%", "%$search%"]);
            $report_data = $stmt->fetchAll();
            break;
    }

    // Export to CSV
    if (isset($_GET['export']) && $_GET['export'] == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        
        if ($report_data) {
            fputcsv($output, array_keys($report_data[0]));
            foreach ($report_data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mibesa CarRental</title>
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
        .topbar { display: flex; justify-content: space-between; align-items: center; padding: 0 20px; background-color: #ffffff; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); height: 60px; position: fixed; top: 0; right: 0; left: 250px; z-index: 99; transition: left 0.3s ease; }
        .topbar.collapsed { left: 0; }
        .hamburger { font-size: 1.5rem; cursor: pointer; color: #333; }
        .user-actions { display: flex; align-items: center; gap: 20px; }
        .user-profile-small { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; font-weight: 500; }
        .user-profile-small img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dashboard-header h1 { font-size: 2rem; color: #2c3e50; font-weight: 600; }
        .stats-container { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); flex: 1; text-align: center; }
        .stat-card h3 { font-size: 1.2rem; color: #2c3e50; margin-bottom: 10px; }
        .stat-card p { font-size: 1.8rem; color: #45a29e; font-weight: 600; }
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
        .status-booked, .status-approved, .status-paid { background-color: #e3fcef; color: #00875a; }
        .status-available, .status-pending { background-color: #fff8e6; color: #b25000; }
        .status-cancelled, .status-declined, .status-unpaid { background-color: #fee4e2; color: #d92d20; }
        .action-btn-container { display: flex; gap: 5px; justify-content: flex-start; }
        .action-btn { padding: 6px 12px; font-size: 0.85rem; }
        .action-btn.approve, .action-btn.verify { background-color: #0b0c10; color: #66fcf1; }
        .action-btn.decline, .action-btn.delete { background-color: #fee4e2; color: #d92d20; }
        .action-btn.edit { background-color: #0b0c10; color: #66fcf1; }
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
        .inline-error { color: #d92d20; font-size: 0.85rem; margin-top: 5px; display: none; }
        .table-container { overflow-x: auto; }
        .footer { text-align: center; padding: 20px; background-color: #0b0c10; color: #ecf0f1; width: 100%; font-size: 0.9rem; font-weight: 400; }
        .footer a { color: #66fcf1; text-decoration: none; margin: 0 10px; }
        .footer a:hover { text-decoration: underline; }
        .footer p { margin: 5px 0; }
        .section { display: none; }
        .section.active { display: block; }
        .national-id-links a { color: #45a29e; text-decoration: none; margin-right: 10px; }
        .national-id-links a:hover { color: #66fcf1; }
        .export-btn { background-color: #45a29e; color: #fff; }
        .user-profile { display: flex; gap: 20px; align-items: flex-start; }
        .profile-image-container { text-align: center; }
        .profile-image { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; }
        .upload-btn { display: inline-block; padding: 8px 12px; background-color: #0b0c10; color: #66fcf1; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .details { flex: 1; }
        .profile-info { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .profile-info label { font-weight: 500; color: #2c3e50; margin-bottom: 5px; display: block; }
        .profile-info input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .profile-info button { grid-column: span 2; padding: 10px; background-color: #0b0c10; color: #66fcf1; border: none; border-radius: 6px; cursor: pointer; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            U .sidebar.collapsed { width: 0; }
            .content { margin-left: 200px; width: calc(100% - 200px); }
            .content.collapsed { margin-left: 0; width: 100%; }
            .topbar { left: 200px; }
            .topbar.collapsed { left: 0; }
            .sidebar h2, .welcome-text { font-size: 0.8rem; }
            .sidebar ul li { padding: 10px; font-size: 0.9rem; }
            .stats-container { flex-direction: column; }
            .data-table th, .data-table td { padding: 8px; font-size: 0.85rem; }
            .action-btn { padding: 6px 10px; font-size: 0.8rem; }
            .modal-content { width: 90%; }
            .footer { font-size: 0.8rem; padding: 15px; }
            .user-profile { flex-direction: column; align-items: center; }
            .profile-info { grid-template-columns: 1fr; }
            .profile-info button { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h2>MIBESA</h2>
        <div class="welcome-text">Welcome, Admin!</div>
        <ul>
            <li onclick="showSection('dashboard')" aria-label="Dashboard"><i class="fas fa-home"></i> Dashboard</li>
            <li onclick="showSection('manage-cars')" aria-label="Manage Cars"><i class="fas fa-car"></i> Manage Cars</li>
            <li onclick="showSection('manage-users')" aria-label="Manage Users"><i class="fas fa-users"></i> Manage Users</li>
            <li onclick="showSection('manage-bookings')" aria-label="Manage Bookings"><i class="fas fa-calendar-check"></i> Manage Bookings</li>
            <li onclick="showSection('reports')" aria-label="Reports"><i class="fas fa-chart-line"></i> Reports</li>
            <li onclick="showSection('profile')" aria-label="Profile"><i class="fas fa-user"></i> Profile</li>
            <li onclick="logout()" aria-label="Logout"><i class="fas fa-sign-out-alt"></i> Logout</li>
        </ul>
    </div>

    <div class="topbar" id="topbar">
        <div class="hamburger" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-actions">
            <div class="user-profile-small" onclick="showSection('profile')" aria-label="Admin Profile">
                <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Admin Profile">
                <span><?php echo htmlspecialchars($admin['name']); ?></span>
            </div>
        </div>
    </div>

    <div class="content" id="content">
        <?php if (isset($_GET['message'])): ?>
            <div class="success"><?php echo htmlspecialchars($_GET['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <section class="section <?php echo (!isset($_GET['section']) || $_GET['section'] == 'dashboard') ? 'active' : ''; ?>" id="dashboard">
            <div class="dashboard-header">
                <h1>Manager Dashboard</h1>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Customers</h3>
                    <p><?php echo $customer_count; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Cars</h3>
                    <p><?php echo $car_count; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Bookings</h3>
                    <p><?php echo $booking_count; ?></p>
                </div>
            </div>
            <div class="content-card">
                <h2>Recent Bookings</h2>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['booking_id']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['customer']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['car_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['pick_up_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['return_date']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['total_cost']); ?> MWK</td>
                                    <td><span class="status status-<?php echo strtolower($booking['status']); ?>"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Manage Cars Section -->
        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-cars') ? 'active' : ''; ?>" id="manage-cars">
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
                <button onclick="applyCarSearchFilter()" aria-label="Search Cars"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>
                    Car Inventory
                    <button class="action-btn" onclick="openModal('carModal')" aria-label="Add New Car">
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
                                    <td><?php echo htmlspecialchars($car['price_per_day']); ?> MWK</td>
                                    <td><?php echo $car['featured'] ? 'Yes' : 'No'; ?></td>
                                    <td><span class="status status-<?php echo strtolower($car['status']); ?>"><?php echo htmlspecialchars($car['status']); ?></span></td>
                                    <td>
                                        <div class="action-btn-container">
                                            <button class="action-btn edit" onclick='openEditCarModal(<?php echo json_encode($car); ?>)' aria-label="Edit Car">Edit</button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this car?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <button type="submit" name="delete_car" class="action-btn delete" aria-label="Delete Car">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Manage Users Section -->
        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-users') ? 'active' : ''; ?>" id="manage-users">
            <div class="dashboard-header">
                <h1>Manage Users</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search users by email, name, occupation, etc..." id="userSearch" value="<?php echo htmlspecialchars($search); ?>">
                <select id="userFilter">
                    <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filter == 'declined' ? 'selected' : ''; ?>>Declined</option>
                </select>
                <button onclick="applyUserSearchFilter()" aria-label="Search Users"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>User Accounts</h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Occupation</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Address</th>
                                <th>Location</th>
                                <th>Kin Name</th>
                                <th>Kin Relationship</th>
                                <th>Kin Phone</th>
                                <th>National ID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($user['last_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($user['occupation'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($user['age']); ?></td>
                                    <td><?php echo htmlspecialchars($user['address']); ?></td>
                                    <td><?php echo htmlspecialchars($user['location']); ?></td>
                                    <td><?php echo htmlspecialchars($user['kin_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['kin_relationship'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['kin_phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($user['national_id'] && strpos($user['national_id'], 'data:application/pdf;base64,') === 0): ?>
                                            <div class="national-id-links">
                                                <a href="<?php echo htmlspecialchars($user['national_id']); ?>" target="_blank" aria-label="View National ID">View</a>
                                                <a href="javascript:void(0)" onclick="downloadNationalId('<?php echo htmlspecialchars($user['national_id']); ?>', '<?php echo htmlspecialchars($user['id'] . '_' . str_replace('@', '_', $user['email'])); ?>')" aria-label="Download National ID">Download</a>
                                            </div>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status status-<?php echo strtolower($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;" id="userActionForm_<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="action-btn-container">
                                                <button type="button" class="action-btn edit" onclick='openEditUserModal(<?php echo json_encode($user); ?>)' aria-label="Edit User">Edit</button>
                                                <?php if ($user['status'] == 'pending'): ?>
                                                    <button type="submit" name="user_action" value="approved" class="action-btn approve" onclick="return confirm('Are you sure you want to approve this user?')" aria-label="Approve User">Approve</button>
                                                    <button type="button" class="action-btn decline" onclick="openRejectionModal('<?php echo $user['id']; ?>')" aria-label="Decline User">Decline</button>
                                                <?php endif; ?>
                                                <button type="submit" name="delete_user" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this user?')" aria-label="Delete User">Delete</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Manage Bookings Section -->
        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'manage-bookings') ? 'active' : ''; ?>" id="manage-bookings">
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
                <button onclick="applyBookingSearchFilter()" aria-label="Search Bookings"><i class="fas fa-search"></i> Search</button>
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
                                <th>Payment Status</th>
                                <th>Status</th>
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
                                    <td><span class="status status-<?php echo strtolower($booking['payment_status']); ?>"><?php echo htmlspecialchars($booking['payment_status']); ?></span></td>
                                    <td><span class="status status-<?php echo strtolower($booking['status']); ?>"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <div class="action-btn-container">
                                                <?php if ($booking['status'] == 'pending'): ?>
                                                    <button type="submit" name="action" value="booked" class="action-btn approve" onclick="return confirm('Are you sure you want to approve this booking?')" aria-label="Approve Booking">Approve</button>
                                                    <button type="submit" name="action" value="cancelled" class="action-btn decline" onclick="return confirm('Are you sure you want to cancel this booking?')" aria-label="Cancel Booking">Cancel</button>
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
        </section>

        <!-- Reports Section -->
        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'reports') ? 'active' : ''; ?>" id="reports">
            <div class="dashboard-header">
                <h1>Generate Reports</h1>
            </div>
            <div class="controls">
                <form id="reportForm" method="GET" action="admin_dashboard.php">
                    <input type="hidden" name="section" value="reports">
                    <select name="report_type" id="reportType" required aria-label="Report Type">
                        <option value="" <?php echo $report_type == '' ? 'selected' : ''; ?>>Select Report Type</option>
                        <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>Users</option>
                        <option value="bookings" <?php echo $report_type == 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                        <option value="cars" <?php echo $report_type == 'cars' ? 'selected' : ''; ?>>Cars</option>
                        <option value="most_booked_cars" <?php echo $report_type == 'most_booked_cars' ? 'selected' : ''; ?>>Most Booked Cars</option>
                        <option value="payments" <?php echo $report_type == 'payments' ? 'selected' : ''; ?>>Payments</option>
                    </select>
                    <input type="text" name="search" id="reportSearch" placeholder="Search report data..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" aria-label="Generate Report"><i class="fas fa-chart-line"></i> Generate Report</button>
                    <?php if ($report_type): ?>
                        <a href="admin_dashboard.php?section=reports&report_type=<?php echo $report_type; ?>&search=<?php echo urlencode($search); ?>&export=csv" class="action-btn export-btn">Export to CSV</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="content-card">
                <h2><?php echo $report_type ? ucfirst(str_replace('_', ' ', $report_type)) . ' Report' : 'Select a Report'; ?></h2>
                <div class="table-container">
                    <?php if ($report_type): ?>
                        <?php if (empty($report_data)): ?>
                            <p>No records found for the selected report type.</p>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <?php if ($report_type == 'users'): ?>
                                            <th>First Name</th>
                                            <th>Last Name</th>
                                            <th>Occupation</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Gender</th>
                                            <th>Age</th>
                                            <th>Address</th>
                                            <th>Location</th>
                                            <th>Kin Name</th>
                                            <th>Kin Relationship</th>
                                            <th>Kin Phone</th>
                                            <th>Status</th>
                                        <?php elseif ($report_type == 'bookings'): ?>
                                            <th>Booking ID</th>
                                            <th>Car Name</th>
                                            <th>Pick-up Date</th>
                                            <th>Return Date</th>
                                            <th>Total Days</th>
                                            <th>Total Cost</th>
                                            <th>Status</th>
                                            <th>Payment Status</th>
                                            <th>User Email</th>
                                            <th>User Gender</th>
                                        <?php elseif ($report_type == 'cars' || $report_type == 'most_booked_cars'): ?>
                                            <th>Name</th>
                                            <th>Model</th>
                                            <th>License Plate</th>
                                            <th>Capacity</th>
                                            <th>Fuel Type</th>
                                            <th>Price/Day</th>
                                            <th>Status</th>
                                            <th>Featured</th>
                                            <th>Booking Count</th>
                                        <?php elseif ($report_type == 'payments'): ?>
                                            <th>ID</th>
                                            <th>User Email</th>
                                            <th>Booking ID</th>
                                            <th>Transaction Ref</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Type</th>
                                            <th>Created At</th>
                                            <th>Charge ID</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type == 'users'): ?>
                                                <td><?php echo htmlspecialchars($row['first_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($row['last_name'] ?? 'Unknown'); ?></td>
                                                <td><?php echo htmlspecialchars($row['occupation'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                <td><?php echo htmlspecialchars($row['age']); ?></td>
                                                <td><?php echo htmlspecialchars($row['address']); ?></td>
                                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                                <td><?php echo htmlspecialchars($row['kin_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['kin_relationship'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['kin_phone'] ?? 'N/A'); ?></td>
                                                <td><span class="status status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                            <?php elseif ($report_type == 'bookings'): ?>
                                                <td><?php echo htmlspecialchars($row['booking_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['car_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['pick_up_date']); ?></td>
                                                <td><?php echo htmlspecialchars($row['return_date']); ?></td>
                                                <td><?php echo htmlspecialchars($row['total_days']); ?></td>
                                                <td><?php echo htmlspecialchars($row['total_cost']); ?> MWK</td>
                                                <td><span class="status status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                                <td><span class="status status-<?php echo strtolower($row['payment_status']); ?>"><?php echo htmlspecialchars($row['payment_status']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                            <?php elseif ($report_type == 'cars' || $report_type == 'most_booked_cars'): ?>
                                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['model']); ?></td>
                                                <td><?php echo htmlspecialchars($row['license_plate']); ?></td>
                                                <td><?php echo htmlspecialchars($row['capacity']); ?></td>
                                                <td><?php echo htmlspecialchars($row['fuel_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['price_per_day']); ?> MWK</td>
                                                <td><span class="status status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                                <td><?php echo $row['featured'] ? 'Yes' : 'No'; ?></td>
                                                <td><?php echo htmlspecialchars($row['booking_count']); ?></td>
                                            <?php elseif ($report_type == 'payments'): ?>
                                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['user_email'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['booking_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['tx_ref'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['amount'] ?? 'N/A'); ?> MWK</td>
                                                <td><span class="status status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($row['charge_id']); ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Profile Section -->
        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] == 'profile') ? 'active' : ''; ?>" id="profile">
            <div class="dashboard-header">
                <h1>Profile</h1>
            </div>
            <div class="content-card">
                <h2>Profile Information</h2>
                <form id="profileForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="user-profile">
                        <div class="profile-image-container">
                            <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Admin Profile Picture" class="profile-image" id="profileImagePreview">
                            <input type="file" name="profile_image" id="profileImageUpload" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp" aria-describedby="profileImageError">
                            <div class="inline-error" id="profileImageError"></div>
                            <label for="profileImageUpload" class="upload-btn" aria-label="Change Profile Image">Change Image</label>
                        </div>
                        <div class="details">
                            <div class="profile-info">
                                <div>
                                    <label for="name">Name</label>
                                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required aria-describedby="nameError">
                                    <div class="inline-error" id="nameError"></div>
                                </div>
                                <div>
                                    <label for="email">Email</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required aria-describedby="emailError">
                                    <div class="inline-error" id="emailError"></div>
                                </div>
                                <div>
                                    <label for="phone">Phone Number</label>
                                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>" required aria-describedby="phoneError">
                                    <div class="inline-error" id="phoneError"></div>
                                </div>
                                <div>
                                    <label for="address">Address</label>
                                    <input type="text" name="address" id="address" value="<?php echo htmlspecialchars($admin['address']); ?>" required aria-describedby="addressError">
                                    <div class="inline-error" id="addressError"></div>
                                </div>
                                <button type="submit" name="update_profile" class="action-btn" onclick="return confirm('Are you sure you want to save these changes?')" aria-label="Save Profile Changes">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <!-- Add Car Modal -->
    <div class="modal" id="carModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Car</h3>
                <span class="close" aria-label="Close Add Car Modal">×</span>
            </div>
            <div class="modal-body">
                <form id="addCarForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="text" name="name" id="addCarName" placeholder="Car Name" required aria-describedby="addCarNameError">
                    <div class="inline-error" id="addCarNameError"></div>
                    <input type="text" name="model" id="addCarModel" placeholder="Car Model" required aria-describedby="addCarModelError">
                    <div class="inline-error" id="addCarModelError"></div>
                    <input type="text" name="license_plate" id="addCarLicensePlate" placeholder="License Plate" required aria-describedby="addCarLicensePlateError">
                    <div class="inline-error" id="addCarLicensePlateError"></div>
                    <input type="number" name="capacity" id="addCarCapacity" placeholder="Capacity" required min="1" max="10" aria-describedby="addCarCapacityError">
                    <div class="inline-error" id="addCarCapacityError"></div>
                    <select name="fuel_type" id="addCarFuelType" required aria-describedby="addCarFuelTypeError">
                        <option value="" disabled selected>Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Electric">Electric</option>
                    </select>
                    <div class="inline-error" id="addCarFuelTypeError"></div>
                    <input type="number" name="price_per_day" id="addCarPricePerDay" placeholder="Price per Day (MWK)" step="0.01" required min="0" aria-describedby="addCarPricePerDayError">
                    <div class="inline-error" id="addCarPricePerDayError"></div>
                    <label><input type="checkbox" name="featured" id="addCarFeatured"> Featured Car</label>
                    <input type="file" name="image" id="addCarImage" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp" aria-describedby="addCarImageError">
                    <div class="inline-error" id="addCarImageError"></div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn" onclick="closeModal('carModal')" aria-label="Cancel">Cancel</button>
                        <button type="submit" name="add_car" class="action-btn approve" onclick="return confirm('Are you sure you want to add this car?')" aria-label="Add Car">Add Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Car Modal -->
    <div class="modal" id="editCarModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Car</h3>
                <span class="close" aria-label="Close Edit Car Modal">×</span>
            </div>
            <div class="modal-body">
                <form id="editCarForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="car_id" id="editCarId">
                    <input type="text" name="name" id="editCarName" placeholder="Car Name"required aria-describedby="editCarNameError">

<div class="inline-error" id="editCarNameError">
</div> <input type="text" name="model" id="editCarModel" placeholder="Car Model" required aria-describedby="editCarModelError">
 <div class="inline-error" id="editCarModelError"></div> 
 <input type="text" name="license_plate" id="editCarLicensePlate" placeholder="License Plate" required aria-describedby="editCarLicensePlateError">
  <div class="inline-error" id="editCarLicensePlateError">
  </div> 
  <input type="number" name="capacity" id="editCarCapacity" placeholder="Capacity" required min="1" max="10" aria-describedby="editCarCapacityError"> 
  <div class="inline-error" id="editCarCapacityError"> </div> 
  <select name="fuel_type" id="editCarFuelType" required aria-describedby="editCarFuelTypeError"> 
  <option value="" disabled>Select Fuel Type</option> 
  <option value="Petrol">Petrol</option> 
  <option value="Diesel">Diesel</option> 
  <option value="Hybrid">Hybrid</option> 
  <option value="Electric">Electric</option> 
  </select> <div class="inline-error" id="editCarFuelTypeError">
  </div>
   <input type="number" name="price_per_day" id="editCarPricePerDay" placeholder="Price per Day (MWK)" step="0.01" required min="0" aria-describedby="editCarPricePerDayError"> 
   <div class="inline-error" id="editCarPricePerDayError">
   </div> 
   <label>
   <input type="checkbox" name="featured" id="editCarFeatured"> Featured Car</label> 
   <input type="file" name="image" id="editCarImage" accept="image/jpeg,image/png,image/gif,image/bmp,image/webp" aria-describedby="editCarImageError">
    <div class="inline-error" id="editCarImageError"></div> <div class="modal-footer"> 
    <button type="button" class="action-btn" onclick="closeModal('editCarModal')" aria-label="Cancel">Cancel</button> 
    <button type="submit" name="update_car" class="action-btn approve" onclick="return confirm('Are you sure you want to update this car?')" aria-label="Update Car">Update Car</button> 
    </div> </form> 
</div> 
</div> 
</div> 
<!-- Edit User Modal --> 
 <div class="modal" id="editUserModal" aria-hidden="true"> 
    
    <div class="modal-content">
     <div class="modal-header">
         <h3>Edit User</h3>
          <span class="close" aria-label="Close Edit User Modal">×</span> 
        </div> <div class="modal-body"> <form id="editUserForm" method="POST"> 
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
             <input type="hidden" name="user_id" id="editUserId">
              <input type="text" name="first_name" id="editUserFirstName" placeholder="First Name" required aria-describedby="editUserFirstNameError"> 
              <div class="inline-error" id="editUserFirstNameError">

              </div> 
              <input type="text" name="last_name" id="editUserLastName" placeholder="Last Name" required aria-describedby="editUserLastNameError">
               <div class="inline-error" id="editUserLastNameError">

               </div> <input type="text" name="occupation" id="editUserOccupation" placeholder="Occupation" aria-describedby="editUserOccupationError">
                <div class="inline-error" id="editUserOccupationError">

                </div>
                 <input type="text" name="username" id="editUserUsername" placeholder="Username" required aria-describedby="editUserUsernameError"> 
                 <div class="inline-error" id="editUserUsernameError">

                 </div> <input type="email" name="email" id="editUserEmail" placeholder="Email" required aria-describedby="editUserEmailError">
                  <div class="inline-error" id="editUserEmailError">

                  </div>
                   <input type="text" name="phone" id="editUserPhone" placeholder="Phone Number" required aria-describedby="editUserPhoneError">
                    <div class="inline-error" id="editUserPhoneError">

                    </div> <select name="gender" id="editUserGender" required aria-describedby="editUserGenderError">
                         <option value="" disabled>Select Gender</option> <option value="Male">Male</option> 
                         <option value="Female">Female</option> <option value="Other">Other</option>
                         </select> <div class="inline-error" id="editUserGenderError">

                         </div> <input type="number" name="age" id="editUserAge" placeholder="Age" required min="18" aria-describedby="editUserAgeError">
                          <div class="inline-error" id="editUserAgeError">

                          </div>
                           <input type="text" name="address" id="editUserAddress" placeholder="Address" required aria-describedby="editUserAddressError">
                            <div class="inline-error" id="editUserAddressError">

                            </div> 
                            <input type="text" name="location" id="editUserLocation" placeholder="Location" required aria-describedby="editUserLocationError"> 
                            <div class="inline-error" id="editUserLocationError">

                            </div> <input type="text" name="kin_name" id="editUserKinName" placeholder="Next of Kin Name" aria-describedby="editUserKinNameError">
                             <div class="inline-error" id="editUserKinNameError">

                             </div> 
                             <input type="text" name="kin_relationship" id="editUserKinRelationship" placeholder="Kin Relationship" aria-describedby="editUserKinRelationshipError">
                              <div class="inline-error" id="editUserKinRelationshipError">

                              </div> 
                              <input type="text" name="kin_phone" id="editUserKinPhone" placeholder="Kin Phone Number" aria-describedby="editUserKinPhoneError"> 
                              <div class="inline-error" id="editUserKinPhoneError">

                              </div>
                               <select name="status" id="editUserStatus" required aria-describedby="editUserStatusError">
                                 <option value="pending">Pending</option> <option value="approved">Approved</option> 
                                 <option value="declined">Declined</option>
                                 </select> <div class="inline-error" id="editUserStatusError">

                                 </div> <div class="modal-footer"> 
                                    <button type="button" class="action-btn" onclick="closeModal('editUserModal')" aria-label="Cancel">Cancel</button> 
                                    <button type="submit" name="update_user" class="action-btn approve" onclick="return confirm('Are you sure you want to update this user?')" aria-label="Update User">Update User</button> 
                                </div> 
                            </form> 
                        </div>
                     </div> 
                    </div>
                     <!-- Rejection Modal --> 
                      <div class="modal" id="rejectionModal" aria-hidden="true">
                         <div class="modal-content"> <div class="modal-header">
                             <h3>Decline User</h3> 
                             <span class="close" aria-label="Close Rejection Modal">×</span>
                             </div> <div class="modal-body"> <form id="rejectionForm" method="POST">
                                 <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                  <input type="hidden" name="user_id" id="rejectionUserId">
                                   <input type="hidden" name="action" value="declined"> 
                                   <textarea name="rejection_reason" id="rejectionReason" placeholder="Reason for rejection" required aria-describedby="rejectionReasonError">

                                   </textarea>
                                    <div class="inline-error" id="rejectionReasonError">

                                    </div>
                                     <div class="modal-footer">
                                        
                                     <button type="button" class="action-btn" onclick="closeModal('rejectionModal')" aria-label="Cancel">Cancel</button>
                                      <button type="submit" name="user_action" class="action-btn decline" onclick="return confirm('Are you sure you want to decline this user?')" aria-label="Decline User">Decline</button> 
                                    </div>
                                 </form> 
                                 </div>
                                 </div>
                                 </div>
                                 



                                     <script>
                                     function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');
    const topbar = document.getElementById('topbar');
    sidebar.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
    topbar.classList.toggle('collapsed');
}

function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
    });
    document.getElementById(sectionId).classList.add('active');
    window.history.pushState({}, '', `admin_dashboard.php?section=${sectionId}`);
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    // Reset forms and errors
    const modal = document.getElementById(modalId);
    const form = modal.querySelector('form');
    if (form) form.reset();
    modal.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });
}

function openEditCarModal(car) {
    document.getElementById('editCarId').value = car.id;
    document.getElementById('editCarName').value = car.name;
    document.getElementById('editCarModel').value = car.model;
    document.getElementById('editCarLicensePlate').value = car.license_plate;
    document.getElementById('editCarCapacity').value = car.capacity;
    document.getElementById('editCarFuelType').value = car.fuel_type;
    document.getElementById('editCarPricePerDay').value = car.price_per_day;
    document.getElementById('editCarFeatured').checked = car.featured == 1;
    document.getElementById('editCarImage').value = ''; // Clear file input
    openModal('editCarModal');
}

function openEditUserModal(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserFirstName').value = user.first_name || '';
    document.getElementById('editUserLastName').value = user.last_name || '';
    document.getElementById('editUserOccupation').value = user.occupation || '';
    document.getElementById('editUserUsername').value = user.username || '';
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserPhone').value = user.phone;
    document.getElementById('editUserGender').value = user.gender;
    document.getElementById('editUserAge').value = user.age;
    document.getElementById('editUserAddress').value = user.address;
    document.getElementById('editUserLocation').value = user.location;
    document.getElementById('editUserKinName').value = user.kin_name || '';
    document.getElementById('editUserKinRelationship').value = user.kin_relationship || '';
    document.getElementById('editUserKinPhone').value = user.kin_phone || '';
    document.getElementById('editUserStatus').value = user.status;
    openModal('editUserModal');
}

function openRejectionModal(userId) {
    document.getElementById('rejectionUserId').value = userId;
    openModal('rejectionModal');
}

function downloadNationalId(dataUrl, filename) {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = `${filename}_national_id.pdf`;
    link.click();
}

function applyUserSearchFilter() {
    const search = document.getElementById('userSearch').value;
    const filter = document.getElementById('userFilter').value;
    window.location.href = `admin_dashboard.php?section=manage-users&search=${encodeURIComponent(search)}&filter=${filter}`;
}

function applyCarSearchFilter() {
    const search = document.getElementById('carSearch').value;
    const filter = document.getElementById('carFilter').value;
    window.location.href = `admin_dashboard.php?section=manage-cars&search=${encodeURIComponent(search)}&filter=${filter}`;
}

function applyBookingSearchFilter() {
    const search = document.getElementById('bookingSearch').value;
    const filter = document.getElementById('bookingFilter').value;
    window.location.href = `admin_dashboard.php?section=manage-bookings&search=${encodeURIComponent(search)}&filter=${filter}`;
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = 'admin_logout.php';
    }
}

// Profile image preview
document.getElementById('profileImageUpload').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profileImagePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Client-side validation for profile form
document.getElementById('profileForm').addEventListener('submit', function(event) {
    let valid = true;
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    const image = document.getElementById('profileImageUpload').files[0];

    // Reset errors
    document.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });

    if (!name) {
        valid = false;
        document.getElementById('nameError').textContent = 'Name is required.';
        document.getElementById('nameError').style.display = 'block';
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        valid = false;
        document.getElementById('emailError').textContent = 'A valid email is required.';
        document.getElementById('emailError').style.display = 'block';
    }
    if (!phone || !/^0[0-9]{9}$/.test(phone)) {
        valid = false;
        document.getElementById('phoneError').textContent = 'Phone number must be 10 digits starting with 0.';
        document.getElementById('phoneError').style.display = 'block';
    }
    if (!address) {
        valid = false;
        document.getElementById('addressError').textContent = 'Address is required.';
        document.getElementById('addressError').style.display = 'block';
    }
    if (image) {
        const maxSize = 2 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        if (!allowedTypes.includes(image.type)) {
            valid = false;
            document.getElementById('profileImageError').textContent = 'Invalid image type.';
            document.getElementById('profileImageError').style.display = 'block';
        } else if (image.size > maxSize) {
            valid = false;
            document.getElementById('profileImageError').textContent = 'Image size exceeds 2MB.';
            document.getElementById('profileImageError').style.display = 'block';
        }
    }

    if (!valid) {
        event.preventDefault();
    }
});

// Client-side validation for add car form
document.getElementById('addCarForm').addEventListener('submit', function(event) {
    let valid = true;
    const name = document.getElementById('addCarName').value.trim();
    const model = document.getElementById('addCarModel').value.trim();
    const licensePlate = document.getElementById('addCarLicensePlate').value.trim();
    const capacity = document.getElementById('addCarCapacity').value;
    const fuelType = document.getElementById('addCarFuelType').value;
    const pricePerDay = document.getElementById('addCarPricePerDay').value;
    const image = document.getElementById('addCarImage').files[0];

    // Reset errors
    document.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });

    if (!name) {
        valid = false;
        document.getElementById('addCarNameError').textContent = 'Car name is required.';
        document.getElementById('addCarNameError').style.display = 'block';
    }
    if (!model) {
        valid = false;
        document.getElementById('addCarModelError').textContent = 'Car model is required.';
        document.getElementById('addCarModelError').style.display = 'block';
    }
    if (!licensePlate) {
        valid = false;
        document.getElementById('addCarLicensePlateError').textContent = 'License plate is required.';
        document.getElementById('addCarLicensePlateError').style.display = 'block';
    }
    if (!capacity || capacity < 1 || capacity > 10) {
        valid = false;
        document.getElementById('addCarCapacityError').textContent = 'Capacity must be between 1 and 10.';
        document.getElementById('addCarCapacityError').style.display = 'block';
    }
    if (!fuelType) {
        valid = false;
        document.getElementById('addCarFuelTypeError').textContent = 'Fuel type is required.';
        document.getElementById('addCarFuelTypeError').style.display = 'block';
    }
    if (!pricePerDay || pricePerDay <= 0) {
        valid = false;
        document.getElementById('addCarPricePerDayError').textContent = 'Price per day must be greater than 0.';
        document.getElementById('addCarPricePerDayError').style.display = 'block';
    }
    if (image) {
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        if (!allowedTypes.includes(image.type)) {
            valid = false;
            document.getElementById('addCarImageError').textContent = 'Invalid image type.';
            document.getElementById('addCarImageError').style.display = 'block';
        } else if (image.size > maxSize) {
            valid = false;
            document.getElementById('addCarImageError').textContent = 'Image size exceeds 5MB.';
            document.getElementById('addCarImageError').style.display = 'block';
        }
    }

    if (!valid) {
        event.preventDefault();
    }
});

// Client-side validation for edit car form
document.getElementById('editCarForm').addEventListener('submit', function(event) {
    let valid = true;
    const name = document.getElementById('editCarName').value.trim();
    const model = document.getElementById('editCarModel').value.trim();
    const licensePlate = document.getElementById('editCarLicensePlate').value.trim();
    const capacity = document.getElementById('editCarCapacity').value;
    const fuelType = document.getElementById('editCarFuelType').value;
    const pricePerDay = document.getElementById('editCarPricePerDay').value;
    const image = document.getElementById('editCarImage').files[0];

    // Reset errors
    document.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });

    if (!name) {
        valid = false;
        document.getElementById('editCarNameError').textContent = 'Car name is required.';
        document.getElementById('editCarNameError').style.display = 'block';
    }
    if (!model) {
        valid = false;
        document.getElementById('editCarModelError').textContent = 'Car model is required.';
        document.getElementById('editCarModelError').style.display = 'block';
    }
    if (!licensePlate) {
        valid = false;
        document.getElementById('editCarLicensePlateError').textContent = 'License plate is required.';
        document.getElementById('editCarLicensePlateError').style.display = 'block';
    }
    if (!capacity || capacity < 1 || capacity > 10) {
        valid = false;
        document.getElementById('editCarCapacityError').textContent = 'Capacity must be between 1 and 10.';
        document.getElementById('editCarCapacityError').style.display = 'block';
    }
    if (!fuelType) {
        valid = false;
        document.getElementById('editCarFuelTypeError').textContent = 'Fuel type is required.';
        document.getElementById('editCarFuelTypeError').style.display = 'block';
    }
    if (!pricePerDay || pricePerDay <= 0) {
        valid = false;
        document.getElementById('editCarPricePerDayError').textContent = 'Price per day must be greater than 0.';
        document.getElementById('editCarPricePerDayError').style.display = 'block';
    }
    if (image) {
        const maxSize = 5 * 1024 * 1024;
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        if (!allowedTypes.includes(image.type)) {
            valid = false;
            document.getElementById('editCarImageError').textContent = 'Invalid image type.';
            document.getElementById('editCarImageError').style.display = 'block';
        } else if (image.size > maxSize) {
            valid = false;
            document.getElementById('editCarImageError').textContent = 'Image size exceeds 5MB.';
            document.getElementById('editCarImageError').style.display = 'block';
        }
    }

    if (!valid) {
        event.preventDefault();
    }
});

// Client-side validation for edit user form
document.getElementById('editUserForm').addEventListener('submit', function(event) {
    let valid = true;
    const firstName = document.getElementById('editUserFirstName').value.trim();
    const lastName = document.getElementById('editUserLastName').value.trim();
    const username = document.getElementById('editUserUsername').value.trim();
    const email = document.getElementById('editUserEmail').value.trim();
    const phone = document.getElementById('editUserPhone').value.trim();
    const gender = document.getElementById('editUserGender').value;
    const age = document.getElementById('editUserAge').value;
    const address = document.getElementById('editUserAddress').value.trim();
    const location = document.getElementById('editUserLocation').value.trim();
    const status = document.getElementById('editUserStatus').value;

    // Reset errors
    document.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });

    if (!firstName) {
        valid = false;
        document.getElementById('editUserFirstNameError').textContent = 'First name is required.';
        document.getElementById('editUserFirstNameError').style.display = 'block';
    }
    if (!lastName) {
        valid = false;
        document.getElementById('editUserLastNameError').textContent = 'Last name is required.';
        document.getElementById('editUserLastNameError').style.display = 'block';
    }
    if (!username) {
        valid = false;
        document.getElementById('editUserUsernameError').textContent = 'Username is required.';
        document.getElementById('editUserUsernameError').style.display = 'block';
    }
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        valid = false;
        document.getElementById('editUserEmailError').textContent = 'A valid email is required.';
        document.getElementById('editUserEmailError').style.display = 'block';
    }
    if (!phone || !/^0[0-9]{9}$/.test(phone)) {
        valid = false;
        document.getElementById('editUserPhoneError').textContent = 'Phone number must be 10 digits starting with 0.';
        document.getElementById('editUserPhoneError').style.display = 'block';
    }
    if (!gender) {
        valid = false;
        document.getElementById('editUserGenderError').textContent = 'Gender is required.';
        document.getElementById('editUserGenderError').style.display = 'block';
    }
    if (!age || age < 18) {
        valid = false;
        document.getElementById('editUserAgeError').textContent = 'Age must be 18 or older.';
        document.getElementById('editUserAgeError').style.display = 'block';
    }
    if (!address) {
        valid = false;
        document.getElementById('editUserAddressError').textContent = 'Address is required.';
        document.getElementById('editUserAddressError').style.display = 'block';
    }
    if (!location) {
        valid = false;
        document.getElementById('editUserLocationError').textContent = 'Location is required.';
        document.getElementById('editUserLocationError').style.display = 'block';
    }
    if (!status) {
        valid = false;
        document.getElementById('editUserStatusError').textContent = 'Status is required.';
        document.getElementById('editUserStatusError').style.display = 'block';
    }

    if (!valid) {
        event.preventDefault();
    }
});

// Client-side validation for rejection form
document.getElementById('rejectionForm').addEventListener('submit', function(event) {
    let valid = true;
    const reason = document.getElementById('rejectionReason').value.trim();

    // Reset errors
    document.querySelectorAll('.inline-error').forEach(error => {
        error.style.display = 'none';
        error.textContent = '';
    });

    if (!reason) {
        valid = false;
        document.getElementById('rejectionReasonError').textContent = 'Rejection reason is required.';
        document.getElementById('rejectionReasonError').style.display = 'block';
    }

    if (!valid) {
        event.preventDefault();
    }
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
});

// Close modals with close button
document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', () => {
        closeModal(closeBtn.closest('.modal').id);
    });
});
</script>
</body>
</html>
<?php
ob_start();
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

if (!function_exists('sendEmail')) {
    function sendEmail($recipient, $subject, $body) {
        mail($recipient, $subject, $body, "From: no-reply@mibesacarrental.com");
    }
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=profile&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($name)) $errors[] = "Name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($phone) || !preg_match('/^0[89][0-9]{8}$/', $phone)) $errors[] = "Phone number must be 10 digits starting with 08 or 09.";
    if (empty($address)) $errors[] = "Address is required.";

    $imageBase64 = null;
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        $imageBase64 = $admin['profile_image'];
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: admin_dashboard.php?section=profile&error=" . urlencode("Database error occurred"));
        exit;
    }

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
        try {
            $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, phone = ?, address = ?, profile_image = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $address, $imageBase64, $_SESSION['admin_id']]);
            unset($_SESSION['csrf_token']);
            header("Location: admin_dashboard.php?section=profile&message=" . urlencode("Profile updated successfully"));
            exit;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            header("Location: admin_dashboard.php?section=profile&error=" . urlencode("Database error occurred"));
            exit;
        }
    } else {
        header("Location: admin_dashboard.php?section=profile&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
}

// Handle User Actions (Approve/Decline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $userId = $_POST['user_id'];
    $action = $_POST['user_action'];
    $csrfToken = $_POST['csrf_token'];
    $rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$action, $userId]);

        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $message = $action === 'approved'
            ? "Dear {$user['first_name']}, your Mibesa CarRental account has been approved. You can now log in and book cars."
            : "Dear {$user['first_name']}, your Mibesa CarRental account has been declined. Reason: $rejectionReason. Please contact support.";
        sendEmail($user['email'], "Account Status Update", $message);

        unset($_SESSION['csrf_token']);
        header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User $action successfully"));
        exit;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Database error occurred"));
        exit;
    }
}

// Handle User Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ?");
        $stmt->execute([$userId]);
        $paymentCount = $stmt->fetchColumn();

        if ($paymentCount > 0) {
            header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Cannot delete user with associated payments."));
            exit;
        }

        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);

        sendEmail($user['email'], "Account Deletion Notification", "Dear {$user['first_name']}, your Mibesa CarRental account has been deleted.");
        unset($_SESSION['csrf_token']);
        header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User deleted successfully"));
        exit;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Database error occurred"));
        exit;
    }
}

// Handle User Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = $_POST['user_id'];
    $fields = [
        'first_name' => trim($_POST['first_name']),
        'last_name' => trim($_POST['last_name']),
        'occupation' => trim($_POST['occupation']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'gender' => $_POST['gender'],
        'address' => trim($_POST['address']),
        'location' => trim($_POST['location']),
        'kin_name' => trim($_POST['kin_name']),
        'kin_relationship' => trim($_POST['kin_relationship']),
        'kin_phone' => trim($_POST['kin_phone']),
        'status' => $_POST['status'],
        'username' => trim($_POST['username']),
        'age' => $_POST['age'],
        'has_paid' => isset($_POST['has_paid']) ? 1 : 0,
        'payment_status' => $_POST['payment_status']
    ];
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($fields['first_name'])) $errors[] = "First name is required.";
    if (empty($fields['last_name'])) $errors[] = "Last name is required.";
    if (empty($fields['email']) || !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "A valid email is required.";
    if (empty($fields['phone']) || !preg_match('/^0[89][0-9]{8}$/', $fields['phone'])) $errors[] = "Phone number must be 10 digits starting with 08 or 09.";
    if (empty($fields['gender']) || !in_array($fields['gender'], ['Male', 'Female', 'Other'])) $errors[] = "Valid gender is required.";
    if (empty($fields['address'])) $errors[] = "Address is required.";
    if (empty($fields['location'])) $errors[] = "Location is required.";
    if (empty($fields['status']) || !in_array($fields['status'], ['pending', 'approved', 'declined'])) $errors[] = "Valid status is required.";
    if (empty($fields['username'])) $errors[] = "Username is required.";
    if (empty($fields['age']) || $fields['age'] < 18) $errors[] = "Age must be 18 or older.";
    if (empty($fields['kin_name'])) $errors[] = "Next of kin name is required.";
    if (empty($fields['kin_relationship'])) $errors[] = "Kin relationship is required.";
    if (empty($fields['kin_phone']) || !preg_match('/^0[89][0-9]{8}$/', $fields['kin_phone'])) $errors[] = "Kin phone number must be 10 digits starting with 08 or 09.";
    if (empty($fields['payment_status']) || !in_array($fields['payment_status'], ['pending', 'paid', 'unpaid'])) $errors[] = "Valid payment status is required.";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, occupation = ?, email = ?, phone = ?, gender = ?, address = ?, location = ?, kin_name = ?, kin_relationship = ?, kin_phone = ?, status = ?, username = ?, age = ?, has_paid = ?, payment_status = ? WHERE id = ?");
            $stmt->execute(array_values($fields + ['id' => $userId]));

            sendEmail($fields['email'], "Profile Updated", "Dear {$fields['first_name']}, your Mibesa CarRental account details have been updated.");
            unset($_SESSION['csrf_token']);
            header("Location: admin_dashboard.php?section=manage-users&message=" . urlencode("User updated successfully"));
            exit;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode("Database error occurred"));
            exit;
        }
    } else {
        header("Location: admin_dashboard.php?section=manage-users&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
}

// Handle Add Car
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_car'])) {
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $licensePlate = trim($_POST['license_plate']);
    $capacity = $_POST['capacity'];
    $fuelType = $_POST['fuel_type'];
    $pricePerDay = $_POST['price_per_day'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($name)) $errors[] = "Car name is required.";
    if (empty($model)) $errors[] = "Car model is required.";
    if (empty($licensePlate)) $errors[] = "License plate is required.";
    if (empty($capacity) || $capacity < 1) $errors[] = "Capacity must be at least 1.";
    if (empty($fuelType) || !in_array($fuelType, ['Petrol', 'Diesel', 'Hybrid', 'Electric'])) $errors[] = "Valid fuel type is required.";
    if (empty($pricePerDay) || $pricePerDay <= 0) $errors[] = "Price per day must be greater than 0.";

    $imageBase64 = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        $fileType = $_FILES['image']['type'];
        $fileSize = $_FILES['image']['size'];

        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
        } else {
            $errors[] = "Invalid image type or size (max 5MB).";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO cars (name, model, license_plate, capacity, fuel_type, price_per_day, featured, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'available')");
            $stmt->execute([$name, $model, $licensePlate, $capacity, $fuelType, $pricePerDay, $featured, $imageBase64]);
            unset($_SESSION['csrf_token']);
            header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car added successfully"));
            exit;
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Database error occurred"));
            exit;
        }
    } else {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
}

// Handle Update Car
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_car'])) {
    $carId = $_POST['car_id'];
    $name = trim($_POST['name']);
    $model = trim($_POST['model']);
    $licensePlate = trim($_POST['license_plate']);
    $capacity = $_POST['capacity'];
    $fuelType = $_POST['fuel_type'];
    $pricePerDay = $_POST['price_per_day'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    $status = $_POST['status'];
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $errors = [];
    if (empty($name)) $errors[] = "Car name is required.";
    if (empty($model)) $errors[] = "Car model is required.";
    if (empty($licensePlate)) $errors[] = "License plate is required.";
    if (empty($capacity) || $capacity < 1) $errors[] = "Capacity must be at least 1.";
    if (empty($fuelType) || !in_array($fuelType, ['Petrol', 'Diesel', 'Hybrid', 'Electric'])) $errors[] = "Valid fuel type is required.";
    if (empty($pricePerDay) || $pricePerDay <= 0) $errors[] = "Price per day must be greater than 0.";
    if (empty($status) || !in_array($status, ['available', 'booked'])) $errors[] = "Valid status is required.";

    if (empty($errors)) {
        try {
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
                $maxSize = 5 * 1024 * 1024;
                $fileType = $_FILES['image']['type'];
                $fileSize = $_FILES['image']['size'];

                if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
                    $imageData = file_get_contents($_FILES['image']['tmp_name']);
                    $imageBase64 = 'data:' . $fileType . ';base64,' . base64_encode($imageData);
                    $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ?, image = ?, status = ? WHERE id = ?");
                    $stmt->execute([$name, $model, $licensePlate, $capacity, $fuelType, $pricePerDay, $featured, $imageBase64, $status, $carId]);
                } else {
                    $errors[] = "Invalid image type or size (max 5MB).";
                }
            } else {
                $stmt = $pdo->prepare("UPDATE cars SET name = ?, model = ?, license_plate = ?, capacity = ?, fuel_type = ?, price_per_day = ?, featured = ?, status = ? WHERE id = ?");
                $stmt->execute([$name, $model, $licensePlate, $capacity, $fuelType, $pricePerDay, $featured, $status, $carId]);
            }

            if (empty($errors)) {
                unset($_SESSION['csrf_token']);
                header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car updated successfully"));
                exit;
            } else {
                header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode(implode(" ", $errors)));
                exit;
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Database error occurred"));
            exit;
        }
    } else {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode(implode(" ", $errors)));
        exit;
    }
}

// Handle Delete Car
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_car'])) {
    $carId = $_POST['car_id'];
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
        $stmt->execute([$carId]);
        unset($_SESSION['csrf_token']);
        header("Location: admin_dashboard.php?section=manage-cars&message=" . urlencode("Car deleted successfully"));
        exit;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: admin_dashboard.php?section=manage-cars&error=" . urlencode("Database error occurred"));
        exit;
    }
}

// Handle Booking Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['action'], $_POST['csrf_token'])) {
    $bookingId = $_POST['booking_id'];
    $action = $_POST['action'];
    $csrfToken = $_POST['csrf_token'];

    if (!verifyCsrfToken($csrfToken)) {
        header("Location: admin_dashboard.php?section=manage-bookings&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$action, $bookingId]);

        $stmt = $pdo->prepare("SELECT u.email, u.first_name, c.id AS car_id FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id WHERE b.id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        $message = $action === 'booked'
            ? "Dear {$booking['first_name']}, your booking has been approved. Enjoy your ride!"
            : "Dear {$booking['first_name']}, your booking has been cancelled. Please contact support.";
        sendEmail($booking['email'], "Booking Status Update", $message);

        if ($action === 'booked') {
            $stmt = $pdo->prepare("UPDATE cars SET status = 'booked' WHERE id = ?");
            $stmt->execute([$booking['car_id']]);
        } elseif ($action === 'cancelled') {
            $stmt = $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = ?");
            $stmt->execute([$booking['car_id']]);
        }

        unset($_SESSION['csrf_token']);
        header("Location: admin_dashboard.php?section=manage-bookings&message=" . urlencode("Booking $action successfully"));
        exit;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: admin_dashboard.php?section=manage-bookings&error=" . urlencode("Database error occurred"));
        exit;
    }
}

// Fetch Admin Profile
try {
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
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: admin_dashboard.php?section=profile&error=" . urlencode("Database error occurred"));
    exit;
}

// Fetch Dashboard Stats
try {
    $customerCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $carCount = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
    $bookingCount = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $recentBookings = $pdo->query("SELECT b.*, u.email AS customer, c.name AS car_name 
                                   FROM bookings b 
                                   JOIN users u ON b.user_id = u.id 
                                   JOIN cars c ON b.car_id = c.id 
                                   ORDER BY b.created_at DESC 
                                   LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $customerCount = $carCount = $bookingCount = 0;
    $recentBookings = [];
}

// Handle Search and Filters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Fetch Users
$usersQuery = "SELECT u.* 
               FROM users u 
               WHERE u.email LIKE ? OR u.address LIKE ? OR u.location LIKE ? OR u.kin_name LIKE ? OR u.kin_phone LIKE ? OR u.username LIKE ? 
               OR COALESCE(u.first_name, '') LIKE ? OR COALESCE(u.last_name, '') LIKE ? OR COALESCE(u.occupation, '') LIKE ?";
$usersParams = array_fill(0, 9, "%$searchQuery%");
if ($filterStatus !== 'all') {
    $usersQuery .= " AND u.status = ?";
    $usersParams[] = $filterStatus;
}
try {
    $stmt = $pdo->prepare($usersQuery);
    $stmt->execute($usersParams);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $users = [];
}

// Fetch Cars
$carsQuery = "SELECT * FROM cars WHERE name LIKE ? OR model LIKE ? OR license_plate LIKE ?";
$carsParams = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
if ($filterStatus !== 'all') {
    $carsQuery .= " AND status = ?";
    $carsParams[] = $filterStatus;
}
try {
    $stmt = $pdo->prepare($carsQuery);
    $stmt->execute($carsParams);
    $cars = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $cars = [];
}

// Fetch Bookings
$bookingsQuery = "SELECT b.*, u.email AS customer, c.name AS car_name 
                 FROM bookings b 
                 JOIN users u ON b.user_id = u.id 
                 JOIN cars c ON b.car_id = c.id 
                 WHERE b.booking_id LIKE ? OR u.email LIKE ? OR c.name LIKE ?";
$bookingsParams = ["%$searchQuery%", "%$searchQuery%", "%$searchQuery%"];
if ($filterStatus !== 'all') {
    $bookingsQuery .= " AND b.status = ?";
    $bookingsParams[] = $filterStatus;
}
try {
    $stmt = $pdo->prepare($bookingsQuery);
    $stmt->execute($bookingsParams);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $bookings = [];
}

// Fetch Payments
$paymentsQuery = "SELECT p.*, u.email AS user_email, b.booking_id 
                 FROM payments p 
                 LEFT JOIN users u ON p.user_id = u.id 
                 LEFT JOIN bookings b ON p.booking_id = b.id 
                 WHERE p.charge_id LIKE ?";
$paymentsParams = ["%$searchQuery%"];
try {
    $stmt = $pdo->prepare($paymentsQuery);
    $stmt->execute($paymentsParams);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $payments = [];
}

// Handle Reports
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$startDate = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : null;
$reportData = [];
$totalAmount = 0;
$reportHeaders = [];
$reportError = '';

if ($reportType || $searchQuery || $startDate || $endDate) {
    try {
        $monthMap = [
            'january' => '01', 'jan' => '01', 'february' => '02', 'feb' => '02', 'march' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04', 'may' => '05', 'june' => '06', 'jun' => '06', 'july' => '07', 'jul' => '07',
            'august' => '08', 'aug' => '08', 'september' => '09', 'sep' => '09', 'october' => '10', 'oct' => '10',
            'november' => '11', 'nov' => '11', 'december' => '12', 'dec' => '12'
        ];

        $tableMap = [
            'users' => ['table' => 'users u', 'fields' => [
                'id', 'username', 'email', 'gender', 'address', 'location', 'kin_name', 'kin_relationship', 'kin_phone',
                'status', 'created_at', 'has_paid', 'phone', 'payment_status', 'age', 'first_name', 'last_name', 'occupation'
            ], 'numeric' => ['age', 'has_paid']],
            'bookings' => ['table' => 'bookings b', 'fields' => [
                'id', 'user_id', 'car_id', 'booking_id', 'pick_up_date', 'return_date', 'total_days', 'total_cost', 'status',
                'created_at', 'payment_status'
            ], 'numeric' => ['total_days', 'total_cost']],
            'cars' => ['table' => 'cars c', 'fields' => [
                'id', 'name', 'model', 'license_plate', 'capacity', 'fuel_type', 'status', 'created_at', 'price_per_day',
                'featured', 'booking_count'
            ], 'numeric' => ['capacity', 'price_per_day', 'featured', 'booking_count']],
            'payments' => ['table' => 'payments p', 'fields' => [
                'id', 'user_id', 'booking_id', 'tx_ref', 'amount', 'status', 'type', 'created_at', 'charge_id'
            ], 'numeric' => ['amount']],
            'extra_charges' => ['table' => 'extra_charges ec', 'fields' => [
                'id', 'booking_id', 'days_late', 'extra_cost', 'created_at'
            ], 'numeric' => ['days_late', 'extra_cost']],
            'admins' => ['table' => 'admins a', 'fields' => [
                'id', 'phone', 'address', 'name', 'email', 'created_at'
            ], 'numeric' => []]
        ];

        $aggregations = ['count', 'sum', 'avg', 'min', 'max'];
        $operators = ['=', '!=', '>', '<', '>=', '<=', 'like', 'in'];
        $joinMap = [
            'bookings' => [
                'users' => 'JOIN users u ON b.user_id = u.id',
                'cars' => 'JOIN cars c ON b.car_id = c.id'
            ],
            'payments' => [
                'users' => 'LEFT JOIN users u ON p.user_id = u.id',
                'bookings' => 'LEFT JOIN bookings b ON p.booking_id = b.id'
            ],
            'extra_charges' => [
                'bookings' => 'JOIN bookings b ON ec.booking_id = b.id'
            ]
        ];

        $searchLower = strtolower($searchQuery);
        $tokens = preg_split('/\s+/', $searchLower, -1, PREG_SPLIT_NO_EMPTY);
        $mainEntity = $reportType ?: '';
        if (!$mainEntity && !empty($tokens)) {
            foreach (array_keys($tableMap) as $entity) {
                if (in_array($entity, $tokens) || in_array(substr($entity, 0, -1), $tokens)) {
                    $mainEntity = $entity;
                    break;
                }
            }
        }
        $mainEntity = $mainEntity ?: 'bookings';

        $query = '';
        $params = [];
        $select = [];
        $from = $tableMap[$mainEntity]['table'];
        $where = [];
        $joins = [];
        $groupBy = [];
        $orderBy = [];
        $limit = '';

        foreach ($tableMap[$mainEntity]['fields'] as $field) {
            $select[] = "$field AS " . str_replace('.', '_', $field);
        }

        if (isset($joinMap[$mainEntity])) {
            foreach ($joinMap[$mainEntity] as $joinEntity => $joinSql) {
                $joins[] = $joinSql;
                foreach ($tableMap[$joinEntity]['fields'] as $field) {
                    $alias = $joinEntity . '_' . $field;
                    $select[] = "$joinEntity.$field AS $alias";
                }
            }
        }

        if ($startDate && $endDate && strtotime($startDate) <= strtotime($endDate)) {
            if (in_array('created_at', $tableMap[$mainEntity]['fields']) || in_array('pick_up_date', $tableMap[$mainEntity]['fields'])) {
                $dateField = in_array('pick_up_date', $tableMap[$mainEntity]['fields']) ? 'pick_up_date' : 'created_at';
                $where[] = "$mainEntity.$dateField BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }
        } elseif ($startDate || $endDate) {
            $reportError = "Please provide both start and end dates.";
        }

        $monthPattern = '/(january|jan|february|feb|march|mar|april|apr|may|june|jun|july|jul|august|aug|september|sep|october|oct|november|nov|december|dec)(?: (\d{4}))?/i';
        if (preg_match($monthPattern, $searchLower, $monthMatches)) {
            $month = strtolower($monthMatches[1]);
            $year = isset($monthMatches[2]) ? $monthMatches[2] : date('Y');
            $monthNumber = $monthMap[$month];
            if (in_array('created_at', $tableMap[$mainEntity]['fields']) || in_array('pick_up_date', $tableMap[$mainEntity]['fields'])) {
                $dateField = in_array('pick_up_date', $tableMap[$mainEntity]['fields']) ? 'pick_up_date' : 'created_at';
                $where[] = "YEAR($mainEntity.$dateField) = ? AND MONTH($mainEntity.$dateField) = ?";
                $params[] = $year;
                $params[] = $monthNumber;
            }
        }

        $dateRangePattern = '/from (\w+)(?: (\d{4}))? to (\w+)(?: (\d{4}))?/i';
        if (preg_match($dateRangePattern, $searchLower, $dateMatches)) {
            $startMonth = strtolower($dateMatches[1]);
            $startYear = isset($dateMatches[2]) ? $dateMatches[2] : date('Y');
            $endMonth = strtolower($dateMatches[3]);
            $endYear = isset($dateMatches[4]) ? $dateMatches[4] : $startYear;
            $startMonthNumber = isset($monthMap[$startMonth]) ? $monthMap[$startMonth] : '';
            $endMonthNumber = isset($monthMap[$endMonth]) ? $monthMap[$endMonth] : '';
            if ($startMonthNumber && $endMonthNumber) {
                $startDate = "$startYear-$startMonthNumber-01";
                $endDate = date('Y-m-t', strtotime("$endYear-$endMonthNumber-01"));
                if (in_array('created_at', $tableMap[$mainEntity]['fields']) || in_array('pick_up_date', $tableMap[$mainEntity]['fields'])) {
                    $dateField = in_array('pick_up_date', $tableMap[$mainEntity]['fields']) ? 'pick_up_date' : 'created_at';
                    $where[] = "$mainEntity.$dateField BETWEEN ? AND ?";
                    $params[] = $startDate;
                    $params[] = $endDate;
                }
            }
        }

        $conditionPattern = '/(\w+)\s*(=|>|<|>=|<=|!=|like|in)\s*([^\s]+)/i';
        preg_match_all($conditionPattern, $searchLower, $conditionMatches, PREG_SET_ORDER);
        foreach ($conditionMatches as $match) {
            $field = strtolower($match[1]);
            $operator = strtolower($match[2]);
            $value = trim($match[3], "'\"");
            $table = $mainEntity;
            $fieldExists = false;

            foreach ($tableMap as $entity => $info) {
                if (in_array($field, $info['fields'])) {
                    $table = $entity;
                    $fieldExists = true;
                    break;
                }
            }

            if ($fieldExists) {
                $prefix = $table === $mainEntity ? $mainEntity : $table;
                if ($operator === 'like') {
                    $where[] = "$prefix.$field LIKE ?";
                    $params[] = "%$value%";
                } elseif ($operator === 'in') {
                    $values = array_map('trim', explode(',', $value));
                    $placeholders = implode(',', array_fill(0, count($values), '?'));
                    $where[] = "$prefix.$field IN ($placeholders)";
                    $params = array_merge($params, $values);
                } else {
                    $where[] = "$prefix.$field $operator ?";
                    $params[] = $value;
                }
            }
        }

        if (preg_match('/email\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $searchLower, $emailMatches)) {
            $where[] = "u.email = ?";
            $params[] = $emailMatches[1];
            if (!in_array('users', array_keys($joinMap[$mainEntity] ?? []))) {
                $joins[] = 'JOIN users u ON b.user_id = u.id';
            }
        }

        $aggPattern = '/(count|sum|avg|min|max)\s*\(\s*(\w+)\s*\)/i';
        if (preg_match($aggPattern, $searchLower, $aggMatches)) {
            $aggFunc = strtoupper($aggMatches[1]);
            $aggField = $aggMatches[2];
            $fieldExists = false;
            $table = $mainEntity;

            foreach ($tableMap as $entity => $info) {
                if (in_array($aggField, $info['fields'])) {
                    $table = $entity;
                    $fieldExists = true;
                    break;
                }
            }

            if ($fieldExists && in_array($aggFunc, $aggregations)) {
                $prefix = $table === $mainEntity ? $mainEntity : $table;
                $select = ["$aggFunc($prefix.$aggField) AS aggregate_result"];
                if ($table !== $mainEntity && !in_array($table, array_keys($joinMap[$mainEntity] ?? []))) {
                    $joins[] = "JOIN $tableMap[$table][table] ON b.user_id = u.id OR b.car_id = c.id";
                }
            }
        }

        if (preg_match('/group by (\w+)/i', $searchLower, $groupMatches)) {
            $groupField = $groupMatches[1];
            $fieldExists = false;
            $table = $mainEntity;

            foreach ($tableMap as $entity => $info) {
                if (in_array($groupField, $info['fields'])) {
                    $table = $entity;
                    $fieldExists = true;
                    break;
                }
            }

            if ($fieldExists) {
                $prefix = $table === $mainEntity ? $mainEntity : $table;
                $groupBy[] = "$prefix.$groupField";
                $select[] = "$prefix.$groupField AS $groupField";
            }
        }

        if (preg_match('/order by (\w+)\s*(asc|desc)?/i', $searchLower, $orderMatches)) {
            $orderField = $orderMatches[1];
            $direction = isset($orderMatches[2]) ? strtoupper($orderMatches[2]) : 'ASC';
            $fieldExists = false;
            $table = $mainEntity;

            foreach ($tableMap as $entity => $info) {
                if (in_array($orderField, $info['fields'])) {
                    $table = $entity;
                    $fieldExists = true;
                    break;
                }
            }

            if ($fieldExists) {
                $prefix = $table === $mainEntity ? $mainEntity : $table;
                $orderBy[] = "$prefix.$orderField $direction";
            }
        }

        if (preg_match('/top (\d+)/i', $searchLower, $limitMatches)) {
            $limit = "LIMIT " . (int)$limitMatches[1];
        }

        foreach ($tokens as $token) {
            if (in_array($token, ['male', 'female', 'other']) && $mainEntity === 'users') {
                $where[] = "u.gender = ?";
                $params[] = ucfirst($token);
            } elseif (in_array($token, ['petrol', 'diesel', 'hybrid', 'electric']) && $mainEntity === 'cars') {
                $where[] = "c.fuel_type = ?";
                $params[] = ucfirst($token);
            } elseif (in_array($token, ['pending', 'booked', 'cancelled']) && $mainEntity === 'bookings') {
                $where[] = "b.status = ?";
                $params[] = $token;
            } elseif (in_array($token, ['success', 'pending']) && $mainEntity === 'payments') {
                $where[] = "p.status = ?";
                $params[] = $token;
            } elseif (in_array($token, ['signup', 'booking']) && $mainEntity === 'payments') {
                $where[] = "p.type = ?";
                $params[] = $token;
            }
        }

        $query = "SELECT " . implode(', ', $select) . " FROM $from";
        if (!empty($joins)) {
            $query .= ' ' . implode(' ', $joins);
        }
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        if (!empty($groupBy)) {
            $query .= " GROUP BY " . implode(', ', $groupBy);
        }
        if (!empty($orderBy)) {
            $query .= " ORDER BY " . implode(', ', $orderBy);
        }
        if ($limit) {
            $query .= " $limit";
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($mainEntity === 'payments') {
            foreach ($reportData as $row) {
                if (isset($row['status']) && $row['status'] === 'success') {
                    $totalAmount += (float)($row['amount'] ?? 0);
                }
            }
        }

        if (!empty($reportData)) {
            $reportHeaders = array_keys($reportData[0]);
        }

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . ($reportType ?: 'custom') . '_report_' . date('Ymd') . '.csv"');
            $output = fopen('php://output', 'w');
            
            if ($reportData) {
                $headers = $reportHeaders;
                if ($mainEntity === 'payments') {
                    $headers[] = 'Total Amount';
                }
                fputcsv($output, $headers);
                foreach ($reportData as $row) {
                    if ($mainEntity === 'payments') {
                        $row['Total Amount'] = number_format($totalAmount, 2);
                    }
                    fputcsv($output, $row);
                }
            }
            fclose($output);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $reportError = "An error occurred while generating the report. Please try again.";
        $reportData = [];
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mibesa CarRental</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
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
        .controls { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .controls input[type="text"], .controls select, .controls input[type="date"] { padding: 10px; border-radius: 6px; border: 1px solid #ddd; flex-grow: 1; max-width: 200px; font-size: 1rem; font-weight: 400; }
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
        .status-booked, .status-approved, .status-paid, .status-success { background-color: #e3fcef; color: #00875a; }
        .status-available, .status-pending { background-color: #fff8e6; color: #b25000; }
        .status-cancelled, .status-declined, .status-unpaid { background-color: #fee4e2; color: #d92d20; }
        .action-btn-container { display: flex; gap: 5px; justify-content: flex-start; }
        .action-btn { padding: 6px 12px; font-size: 0.85rem; }
        .action-btn.approve, .action-btn.verify { background-color: #0b0c10; color: #66fcf1; }
        .action-btn.decline, .action-btn.delete { background-color: #fee4e2; color: #d92d20; }
        .action-btn.edit { background-color: #0b0c10; color: #66fcf1; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background-color: #ffffff; border-radius: 8px; padding: 20px; width: 500px; max-width: 90%; max-height: 80vh; display: flex; flex-direction: column; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 1.5rem; color: #2c3e50; font-weight: 600; }
        .close { font-size: 1.5rem; cursor: pointer; color: #7f8c8d; }
        .modal-body { flex: 1; overflow-y: auto; }
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
            .sidebar.collapsed { width: 0; }
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
            .controls input[type="text"], .controls select, .controls input[type="date"] { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h2>MIBESA</h2>
        <div class="welcome-text">Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</div>
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

        <section class="section <?php echo (!isset($_GET['section']) || $_GET['section'] === 'dashboard') ? 'active' : ''; ?>" id="dashboard">
            <div class="dashboard-header">
                <h1>Manager Dashboard</h1>
            </div>
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Customers</h3>
                    <p><?php echo $customerCount; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Cars</h3>
                    <p><?php echo $carCount; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Bookings</h3>
                    <p><?php echo $bookingCount; ?></p>
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
                            <?php foreach ($recentBookings as $booking): ?>
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

        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] === 'manage-cars') ? 'active' : ''; ?>" id="manage-cars">
            <div class="dashboard-header">
                <h1>Manage Cars</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search cars by name, model, etc..." id="carSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="carFilter">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="available" <?php echo $filterStatus === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo $filterStatus === 'booked' ? 'selected' : ''; ?>>Booked</option>
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
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
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

        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] === 'manage-users') ? 'active' : ''; ?>" id="manage-users">
            <div class="dashboard-header">
                <h1>Manage Users</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search users by email, name, occupation, etc..." id="userSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="usernapFilter">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="declined" <?php echo $filterStatus === 'declined' ? 'selected' : ''; ?>>Declined</option>
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
                                <th>Email</th>
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
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php if (!empty($user['national_id'])): ?>
                                            <?php if (strpos($user['national_id'], 'data:application/pdf;base64,') === 0): ?>
                                                <div class="national-id-links">
                                                    <a href="view_national_id.php?id=<?php echo $user['id']; ?>" target="_blank" aria-label="View National ID">View</a>
                                                    <a href="download_national_id.php?id=<?php echo $user['id']; ?>" aria-label="Download National ID">Download</a>
                                                </div>
                                            <?php elseif (filter_var($user['national_id'], FILTER_VALIDATE_URL)): ?>
                                                <div class="national-id-links">
                                                    <a href="<?php echo htmlspecialchars($user['national_id']); ?>" target="_blank" aria-label="View National ID">View</a>
                                                </div>
                                            <?php else: ?>
                                                <span><?php echo htmlspecialchars($user['national_id']); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status status-<?php echo strtolower($user['status']); ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;" id="userActionForm_<?php echo $user['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="action-btn-container">
                                                <button type="button" class="action-btn edit" onclick='openEditUserModal(<?php echo json_encode($user); ?>)' aria-label="Edit User">Edit</button>
                                                <?php if ($user['status'] === 'pending'): ?>
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

        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] === 'manage-bookings') ? 'active' : ''; ?>" id="manage-bookings">
            <div class="dashboard-header">
                <h1>Manage Bookings</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search bookings by ID, customer, or car..." id="bookingSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="bookingFilter">
                    <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="booked" <?php echo $filterStatus === 'booked' ? 'selected' : ''; ?>>Booked</option>
                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button onclick="applyBookingSearchFilter()" aria-label="Search Bookings"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="content-card">
                <h2>Booking List</h2>
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
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                            <div class="action-btn-container">
                                                <?php if ($booking['status'] === 'pending'): ?>
                                                    <button type="submit" name="action" value="booked" class="action-btn approve" onclick="return confirm('Are you sure you want to                                                     approve this booking?')" aria-label="Approve Booking">Approve</button>
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

        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] === 'reports') ? 'active' : ''; ?>" id="reports">
            <div class="dashboard-header">
                <h1>Reports & Analytics</h1>
            </div>
            <div class="controls">
                <input type="text" placeholder="Search reports (e.g., bookings, payments, users)" id="reportSearch" value="<?php echo htmlspecialchars($searchQuery); ?>">
                <select id="reportType">
                    <option value="" <?php echo empty($reportType) ? 'selected' : ''; ?>>Select Report Type</option>
                    <option value="users" <?php echo $reportType === 'users' ? 'selected' : ''; ?>>Users</option>
                    <option value="bookings" <?php echo $reportType === 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                    <option value="cars" <?php echo $reportType === 'cars' ? 'selected' : ''; ?>>Cars</option>
                    <option value="payments" <?php echo $reportType === 'payments' ? 'selected' : ''; ?>>Payments</option>
                    <option value="extra_charges" <?php echo $reportType === 'extra_charges' ? 'selected' : ''; ?>>Extra Charges</option>
                    <option value="admins" <?php echo $reportType === 'admins' ? 'selected' : ''; ?>>Admins</option>
                </select>
                <input type="date" id="startDate" value="<?php echo htmlspecialchars($startDate ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
                <input type="date" id="endDate" value="<?php echo htmlspecialchars($endDate ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
                <button onclick="applyReportSearchFilter()" aria-label="Generate Report"><i class="fas fa-search"></i> Generate</button>
                <?php if (!empty($reportData)): ?>
                    <button class="export-btn" onclick="exportReport()" aria-label="Export to CSV"><i class="fas fa-download"></i> Export CSV</button>
                <?php endif; ?>
            </div>
            <div class="content-card">
                <h2>Generated Report</h2>
                <?php if ($reportError): ?>
                    <div class="alert"><?php echo htmlspecialchars($reportError); ?></div>
                <?php elseif (empty($reportData)): ?>
                    <p>No data available for the selected criteria.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <?php foreach ($reportHeaders as $header): ?>
                                        <th><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($header))); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo htmlspecialchars(is_null($value) ? 'N/A' : $value); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($mainEntity === 'payments' && $totalAmount > 0): ?>
                        <p><strong>Total Amount (Successful Payments): </strong><?php echo number_format($totalAmount, 2); ?> MWK</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="section <?php echo (isset($_GET['section']) && $_GET['section'] === 'profile') ? 'active' : ''; ?>" id="profile">
            <div class="dashboard-header">
                <h1>Admin Profile</h1>
            </div>
            <div class="content-card">
                <h2>Profile Details</h2>
                <div class="user-profile">
                    <div class="profile-image-container">
                        <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Admin Profile" class="profile-image">
                        <label for="profile_image" class="upload-btn" aria-label="Upload Profile Image">Change Image</label>
                    </div>
                    <div class="details">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;">
                            <div class="profile-info">
                                <div>
                                    <label for="name">Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                                </div>
                                <div>
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                </div>
                                <div>
                                    <label for="phone">Phone</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>" required>
                                </div>
                                <div>
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($admin['address']); ?>" required>
                                </div>
                                <button type="submit" aria-label="Update Profile">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Car Modal -->
    <div class="modal" id="carModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Car</h3>
                <span class="close" onclick="closeModal('carModal')" aria-label="Close Modal">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="addCarForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="add_car" value="1">
                    <input type="text" name="name" placeholder="Car Name" required>
                    <input type="text" name="model" placeholder="Model" required>
                    <input type="text" name="license_plate" placeholder="License Plate" required>
                    <input type="number" name="capacity" placeholder="Capacity" min="1" required>
                    <select name="fuel_type" required>
                        <option value="">Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Electric">Electric</option>
                    </select>
                    <input type="number" name="price_per_day" placeholder="Price per Day (MWK)" min="0" step="0.01" required>
                    <label><input type="checkbox" name="featured"> Featured</label>
                    <input type="file" name="image" accept="image/*">
                    <div class="modal-footer">
                        <button type="submit" class="action-btn" aria-label="Add Car">Add Car</button>
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
                <span class="close" onclick="closeModal('editCarModal')" aria-label="Close Modal">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="editCarForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="update_car" value="1">
                    <input type="hidden" name="car_id" id="edit_car_id">
                    <input type="text" name="name" id="edit_car_name" placeholder="Car Name" required>
                    <input type="text" name="model" id="edit_car_model" placeholder="Model" required>
                    <input type="text" name="license_plate" id="edit_car_license_plate" placeholder="License Plate" required>
                    <input type="number" name="capacity" id="edit_car_capacity" placeholder="Capacity" min="1" required>
                    <select name="fuel_type" id="edit_car_fuel_type" required>
                        <option value="">Select Fuel Type</option>
                        <option value="Petrol">Petrol</option>
                        <option value="Diesel">Diesel</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Electric">Electric</option>
                    </select>
                    <input type="number" name="price_per_day" id="edit_car_price_per_day" placeholder="Price per Day (MWK)" min="0" step="0.01" required>
                    <select name="status" id="edit_car_status" required>
                        <option value="available">Available</option>
                        <option value="booked">Booked</option>
                    </select>
                    <label><input type="checkbox" name="featured" id="edit_car_featured"> Featured</label>
                    <input type="file" name="image" accept="image/*">
                    <div class="modal-footer">
                        <button type="submit" class="action-btn" aria-label="Update Car">Update Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit User</h3>
                <span class="close" onclick="closeModal('editUserModal')" aria-label="Close Modal">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="text" name="first_name" id="edit_user_first_name" placeholder="First Name" required>
                    <input type="text" name="last_name" id="edit_user_last_name" placeholder="Last Name" required>
                    <input type="text" name="occupation" id="edit_user_occupation" placeholder="Occupation">
                    <input type="email" name="email" id="edit_user_email" placeholder="Email" required>
                    <input type="text" name="phone" id="edit_user_phone" placeholder="Phone" required>
                    <select name="gender" id="edit_user_gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <input type="text" name="address" id="edit_user_address" placeholder="Address" required>
                    <input type="text" name="location" id="edit_user_location" placeholder="Location" required>
                    <input type="text" name="kin_name" id="edit_user_kin_name" placeholder="Next of Kin Name" required>
                    <input type="text" name="kin_relationship" id="edit_user_kin_relationship" placeholder="Kin Relationship" required>
                    <input type="text" name="kin_phone" id="edit_user_kin_phone" placeholder="Kin Phone" required>
                    <select name="status" id="edit_user_status" required>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="declined">Declined</option>
                    </select>
                    <input type="text" name="username" id="edit_user_username" placeholder="Username" required>
                    <input type="number" name="age" id="edit_user_age" placeholder="Age" min="18" required>
                    <label><input type="checkbox" name="has_paid" id="edit_user_has_paid"> Has Paid</label>
                    <select name="payment_status" id="edit_user_payment_status" required>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="unpaid">Unpaid</option>
                    </select>
                    <div class="modal-footer">
                        <button type="submit" class="action-btn" aria-label="Update User">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal" id="rejectionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Decline User</h3>
                <span class="close" onclick="closeModal('rejectionModal')" aria-label="Close Modal">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="rejectionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="user_id" id="rejection_user_id">
                    <input type="hidden" name="user_action" value="declined">
                    <textarea name="rejection_reason" placeholder="Reason for rejection" required></textarea>
                    <div class="modal-footer">
                        <button type="submit" class="action-btn decline" aria-label="Decline User">Decline</button>
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
            document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
            history.pushState(null, '', `admin_dashboard.php?section=${sectionId}`);
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if (modalId === 'editCarModal' || modalId === 'editUserModal') {
                document.getElementById(modalId === 'editCarModal' ? 'editCarForm' : 'editUserForm').reset();
            }
        }

        function openEditCarModal(car) {
            document.getElementById('edit_car_id').value = car.id;
            document.getElementById('edit_car_name').value = car.name;
            document.getElementById('edit_car_model').value = car.model;
            document.getElementById('edit_car_license_plate').value = car.license_plate;
            document.getElementById('edit_car_capacity').value = car.capacity;
            document.getElementById('edit_car_fuel_type').value = car.fuel_type;
            document.getElementById('edit_car_price_per_day').value = car.price_per_day;
            document.getElementById('edit_car_status').value = car.status;
            document.getElementById('edit_car_featured').checked = car.featured == 1;
            openModal('editCarModal');
        }

        function openEditUserModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_user_first_name').value = user.first_name || '';
            document.getElementById('edit_user_last_name').value = user.last_name || '';
            document.getElementById('edit_user_occupation').value = user.occupation || '';
            document.getElementById('edit_user_email').value = user.email;
            document.getElementById('edit_user_phone').value = user.phone || '';
            document.getElementById('edit_user_gender').value = user.gender || '';
            document.getElementById('edit_user_address').value = user.address || '';
            document.getElementById('edit_user_location').value = user.location || '';
            document.getElementById('edit_user_kin_name').value = user.kin_name || '';
            document.getElementById('edit_user_kin_relationship').value = user.kin_relationship || '';
            document.getElementById('edit_user_kin_phone').value = user.kin_phone || '';
            document.getElementById('edit_user_status').value = user.status;
            document.getElementById('edit_user_username').value = user.username || '';
            document.getElementById('edit_user_age').value = user.age || '';
            document.getElementById('edit_user_has_paid').checked = user.has_paid == 1;
            document.getElementById('edit_user_payment_status').value = user.payment_status || 'pending';
            openModal('editUserModal');
        }

        function openRejectionModal(userId) {
            document.getElementById('rejection_user_id').value = userId;
            openModal('rejectionModal');
        }

        function applyCarSearchFilter() {
            const search = document.getElementById('carSearch').value;
            const filter = document.getElementById('carFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-cars&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        function applyUserSearchFilter() {
            const search = document.getElementById('userSearch').value;
            const filter = document.getElementById('usernapFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-users&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        function applyBookingSearchFilter() {
            const search = document.getElementById('bookingSearch').value;
            const filter = document.getElementById('bookingFilter').value;
            window.location.href = `admin_dashboard.php?section=manage-bookings&search=${encodeURIComponent(search)}&filter=${filter}`;
        }

        function applyReportSearchFilter() {
            const search = document.getElementById('reportSearch').value;
            const reportType = document.getElementById('reportType').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const params = new URLSearchParams();
            params.append('section', 'reports');
            if (search) params.append('search', search);
            if (reportType) params.append('report_type', reportType);
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            window.location.href = `admin_dashboard.php?${params.toString()}`;
        }

        function exportReport() {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.append('export', 'csv');
            window.location.href = currentUrl.toString();
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'admin_logout.php';
            }
        }

        // Initialize datepickers
        $(function() {
            $("#startDate, #endDate").datepicker({
                dateFormat: 'yy-mm-dd',
                maxDate: '0',
                changeMonth: true,
                changeYear: true
            });
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        };

        // Handle profile image preview
        document.getElementById('profile_image').addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html> 
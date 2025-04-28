<?php
require 'config.php';
ob_start();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Database reconnection functions
function getPDOConnection($host, $dbname, $username, $password) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Connection failed: " . $e->getMessage());
        throw $e;
    }
}

function ensurePDOConnection($pdo, $host, $dbname, $username, $password) {
    try {
        $pdo->query("SELECT 1");
        return $pdo;
    } catch (PDOException $e) {
        error_log("Reconnecting to database due to: " . $e->getMessage());
        return getPDOConnection($host, $dbname, $username, $password);
    }
}

// CSRF token validation function
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$user = null;
$payment_error = null;
$payment_type = null;
$temp_signup_data = null;
$temp_booking_data = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && ($user['status'] !== 'approved' || $user['payment_status'] !== 'paid')) {
        session_destroy();
        header("Location: index.php?message=Your account is pending approval or payment");
        exit;
    }
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } else {
        $email = filter_var($_POST['loginEmail'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['loginPassword'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } elseif (empty($password)) {
            $error = "Password cannot be empty";
        } else {
            try {
                $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] !== 'approved') {
                        $error = "Your account is still pending approval";
                    } elseif ($user['payment_status'] !== 'paid') {
                        $error = "Please complete your payment to proceed";
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        header("Location: index.php?message=Successfully logged in");
                        exit;
                    }
                } else {
                    $error = "Incorrect email or password";
                }
            } catch (PDOException $e) {
                error_log("Database error in login: " . $e->getMessage());
                $error = "Something went wrong. Please try again later.";
            }
        }
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } else {
        $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
        $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = $_POST['phone'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $gender = $_POST['gender'];
        $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
        $location = $_POST['location'];
        $kin_name = filter_var($_POST['kin_name'], FILTER_SANITIZE_STRING);
        $kin_relationship = $_POST['kin_relationship'];
        $kin_phone = $_POST['kin_phone'];
        $age = filter_var($_POST['age'], FILTER_SANITIZE_NUMBER_INT);
        $occupation = filter_var($_POST['occupation'], FILTER_SANITIZE_STRING);
        $profile_picture_file = $_FILES['profile_picture'];
        $national_id_file = $_FILES['national_id'];

        // Server-side validation
        if (!preg_match('/^[A-Za-z]{2,50}$/', $first_name)) {
            $error = "First name must be 2-50 letters only";
        } elseif (!preg_match('/^[A-Za-z]{2,50}$/', $last_name)) {
            $error = "Last name must be 2-50 letters only";
        } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $error = "Username must be 3-50 characters (letters, numbers, or underscores)";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "This email is already registered. Please use a different email or log in.";
            } elseif (!preg_match('/^0[89][0-9]{8}$/', $phone)) {
                $error = "Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)";
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
                $error = "Password must be at least 8 characters, including uppercase, lowercase, number, and special character";
            } elseif ($password !== $confirm_password) {
                $error = "Passwords do not match";
            } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $error = "Please select a valid gender";
            } elseif (empty($address)) {
                $error = "Address is required";
            } elseif (!in_array($location, ['Lilongwe', 'Blantyre', 'Mzuzu', 'Zomba'])) {
                $error = "Please select a valid location";
            } elseif (empty($kin_name)) {
                $error = "Next of kin name is required";
            } elseif (!in_array($kin_relationship, ['Parent', 'Sibling', 'Spouse', 'Friend', 'Other'])) {
                $error = "Please select a valid relationship";
            } elseif (!preg_match('/^0[89][0-9]{8}$/', $kin_phone)) {
                $error = "Next of kin phone must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)";
            } elseif ($age < 18 || $age > 100) {
                $error = "Age must be between 18 and 100";
            } elseif (empty($occupation)) {
                $error = "Occupation is required";
            } elseif (!isset($national_id_file) || $national_id_file['error'] !== UPLOAD_ERR_OK || $national_id_file['type'] !== 'application/pdf' || $national_id_file['size'] > 2 * 1024 * 1024) {
                $error = "National ID must be a PDF file under 2MB";
            } elseif (!isset($profile_picture_file) || $profile_picture_file['error'] !== UPLOAD_ERR_OK || !in_array($profile_picture_file['type'], ['image/jpeg', 'image/png']) || $profile_picture_file['size'] > 2 * 1024 * 1024) {
                $error = "Profile picture must be a JPEG or PNG image under 2MB";
            } else {
                // Create tmp directory
                $tmp_dir = 'tmp';
                if (!is_dir($tmp_dir)) {
                    mkdir($tmp_dir, 0755, true);
                }

                // Move uploaded files
                $national_id_filename = 'national_id_' . time() . '.pdf';
                $profile_picture_filename = 'profile_picture_' . time() . '.' . ($profile_picture_file['type'] === 'image/jpeg' ? 'jpg' : 'png');
                $national_id_path = "$tmp_dir/$national_id_filename";
                $profile_picture_path = "$tmp_dir/$profile_picture_filename";

                if (!move_uploaded_file($national_id_file['tmp_name'], $national_id_path) || !move_uploaded_file($profile_picture_file['tmp_name'], $profile_picture_path)) {
                    $error = "Failed to upload files. Please try again.";
                } else {
                    // Store data in session
                    $_SESSION['temp_signup_data'] = [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'username' => $username,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => password_hash($password, PASSWORD_BCRYPT),
                        'gender' => $gender,
                        'address' => $address,
                        'location' => $location,
                        'kin_name' => $kin_name,
                        'kin_relationship' => $kin_relationship,
                        'kin_phone' => $kin_phone,
                        'age' => $age,
                        'occupation' => $occupation,
                        'national_id' => base64_encode(file_get_contents($national_id_path)),
                        'profile_picture' => base64_encode(file_get_contents($profile_picture_path)),
                        'national_id_path' => $national_id_path,
                        'profile_picture_path' => $profile_picture_path
                    ];

                    $payment = initiateUSSDPayment($pdo, 0, 50, 'signup', null, $_SESSION['temp_signup_data']);
                    if (isset($payment['error'])) {
                        $payment_error = $payment['error'];
                        $payment_type = $payment['type'];
                        $temp_signup_data = $_SESSION['temp_signup_data'];
                        @unlink($national_id_path);
                        @unlink($profile_picture_path);
                    } else {
                        $user_id = $payment['user_id'];
                        $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                        $adminStmt = $pdo->query("SELECT email FROM admins");
                        while ($admin = $adminStmt->fetch()) {
                            sendEmail($admin['email'], "New User Signup and Payment", "A new user ($username, $email) has paid the 500 Kwacha registration fee and signed up (charge_id: {$payment['charge_id']}). Please review and approve their account.");
                        }
                        @unlink($national_id_path);
                        @unlink($profile_picture_path);
                        unset($_SESSION['temp_signup_data']);
                        header("Location: index.php?message=" . urlencode("Signup successful! Please wait for admin approval to access your account."));
                        exit;
                    }
                }
            }
        }
    }
}

// Handle forgot password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forgot_password'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } else {
        $email = filter_var($_POST['forgot_email'], FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            try {
                $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $reset_token = bin2hex(random_bytes(16));
                    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
                    $stmt->execute([$reset_token, $email]);

                    $reset_link = "https://yourdomain.com/reset_password.php?token=$reset_token";
                    $message = "Click the following link to reset your password: $reset_link";
                    $success = sendEmail($email, "Password Reset Request", $message);

                    if ($success) {
                        header("Location: index.php?message=" . urlencode("Password reset link sent to your email"));
                        exit;
                    } else {
                        $error = "Failed to send reset email. Please try again.";
                    }
                } else {
                    $error = "No account found with that email address";
                }
            } catch (PDOException $e) {
                error_log("Database error in forgot password: " . $e->getMessage());
                $error = "Something went wrong. Please try again later.";
            }
        }
    }
}

// Handle booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } elseif (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please log in to make a booking");
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
            $error = "Pick-up date must be today or later";
        } elseif ($return_timestamp <= $pick_up_timestamp) {
            $error = "Return date must be after the pick-up date";
        } else {
            $total_days = ($return_timestamp - $pick_up_timestamp) / (60 * 60 * 24);
            $total_cost = $total_days * $price_per_day;
            $booking_id = '#CR' . rand(1000, 9999);

            $_SESSION['temp_booking_data'] = [
                'car_id' => $car_id,
                'booking_id' => $booking_id,
                'pick_up_date' => $pick_up_date,
                'return_date' => $return_date,
                'total_days' => $total_days,
                'total_cost' => $total_cost,
                'price_per_day' => $price_per_day
            ];

            $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
            $stmt = $pdo->prepare("SELECT name FROM cars WHERE id = ?");
            $stmt->execute([$car_id]);
            $car = $stmt->fetch();
            $_SESSION['temp_booking_data']['car_name'] = $car['name'] ?? '';

            $payment = initiateUSSDPayment($pdo, $_SESSION['user_id'], $total_cost, 'booking', null, $_SESSION['temp_booking_data']);
            if (isset($payment['error'])) {
                $payment_error = $payment['error'];
                $payment_type = $payment['type'];
                $temp_booking_data = $_SESSION['temp_booking_data'];
                unset($_SESSION['temp_booking_data']);
            } else {
                $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                $stmt = $pdo->prepare("UPDATE cars SET booking_count = booking_count + 1 WHERE id = ?");
                $stmt->execute([$car_id]);
                unset($_SESSION['temp_booking_data']);
                header("Location: index.php?message=" . urlencode($payment['message']));
                exit;
            }
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } elseif (!isset($_SESSION['user_id'])) {
        header("Location: index.php?message=Please log in to update your profile");
        exit;
    } else {
        $first_name = filter_var($_POST['edit_first_name'], FILTER_SANITIZE_STRING);
        $last_name = filter_var($_POST['edit_last_name'], FILTER_SANITIZE_STRING);
        $username = filter_var($_POST['edit_username'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['edit_email'], FILTER_SANITIZE_EMAIL);
        $phone = $_POST['edit_phone'];
        $gender = $_POST['edit_gender'];
        $address = filter_var($_POST['edit_address'], FILTER_SANITIZE_STRING);
        $location = $_POST['edit_location'];
        $age = filter_var($_POST['edit_age'], FILTER_SANITIZE_NUMBER_INT);
        $occupation = filter_var($_POST['edit_occupation'], FILTER_SANITIZE_STRING);

        if (!preg_match('/^[A-Za-z]{2,50}$/', $first_name)) {
            $error = "First name must be 2-50 letters only";
        } elseif (!preg_match('/^[A-Za-z]{2,50}$/', $last_name)) {
            $error = "Last name must be 2-50 letters only";
        } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $error = "Username must be 3-50 characters (letters, numbers, or underscores)";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } else {
            $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $error = "This email is already registered. Please use a different email.";
            } elseif (!preg_match('/^0[89][0-9]{8}$/', $phone)) {
                $error = "Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)";
            } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
                $error = "Please select a valid gender";
            } elseif (empty($address)) {
                $error = "Address is required";
            } elseif (!in_array($location, ['Lilongwe', 'Blantyre', 'Mzuzu', 'Zomba'])) {
                $error = "Please select a valid location";
            } elseif ($age < 18 || $age > 100) {
                $error = "Age must be between 18 and 100";
            } elseif (empty($occupation)) {
                $error = "Occupation is required";
            } else {
                try {
                    $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, phone = ?, gender = ?, address = ?, location = ?, age = ?, occupation = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $username, $email, $phone, $gender, $address, $location, $age, $occupation, $_SESSION['user_id']]);
                    header("Location: index.php?message=Profile updated successfully");
                    exit;
                } catch (PDOException $e) {
                    error_log("Database error in profile update: " . $e->getMessage());
                    $error = "Unable to update profile. Please try again later.";
                }
            }
        }
    }
}

// Handle contact form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['contact'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $error = "Security error: Invalid CSRF token";
    } else {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address";
        } elseif (empty($message)) {
            $error = "Message cannot be empty";
        } else {
            $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
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

// Fetch cars with search and filter (only available cars)
$search = isset($_GET['search']) ? filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING) : '';
$filter_fuel = isset($_GET['fuel']) ? filter_var(trim($_GET['fuel']), FILTER_SANITIZE_STRING) : '';

$cars_query = "SELECT * FROM cars WHERE status = 'available'";
$params = [];

if ($search) {
    $cars_query .= " AND (name LIKE ? OR model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_fuel && in_array($filter_fuel, ['Petrol', 'Diesel', 'Electric'])) {
    $cars_query .= " AND fuel_type = ?";
    $params[] = $filter_fuel;
}

try {
    $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
    $carsStmt = $pdo->prepare($cars_query);
    $carsStmt->execute($params);
    $cars = $carsStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in car fetch: " . $e->getMessage());
    $error = "Unable to fetch cars. Please try again later.";
}

// Fetch featured cars (only available)
$pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
$featuredCarsStmt = $pdo->query("SELECT * FROM cars WHERE featured = 1 AND status = 'available' LIMIT 3");
$featuredCars = $featuredCarsStmt->fetchAll();

// Fetch available cars
$pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
$availableCarsStmt = $pdo->query("SELECT * FROM cars WHERE status = 'available'");
$availableCars = $availableCarsStmt->fetchAll();

$current_date = date('Y-m-d');
$js_payment_error = json_encode($payment_error);
$js_payment_type = json_encode($payment_type);
$js_temp_signup_data = json_encode($temp_signup_data);
$js_temp_booking_data = json_encode($temp_booking_data);
ob_end_flush();
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
        .slider {
            position: relative;
            overflow: hidden;
            height: 70vh;
        }
        .slides {
            height: 100%;
            display: flex;
            transition: transform 0.5s ease;
        }
        .slide {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 8px solid #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            margin: 10px;
        }
        .slider-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: #fff;
            background: rgba(0, 0, 0, 0.6);
            padding: 20px 40px;
            border-radius: 10px;
        }
        .slider-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .slider-text p {
            font-size: 1.2rem;
        }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); }
        .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 600px; max-width: 95%; max-height: 90vh; overflow-y: auto; }
        .close { position: absolute; right: 20px; top: 10px; color: #aaa; font-size: 24px; font-weight: bold; cursor: pointer; }
        form { display: flex; flex-direction: column; }
        form label { margin-bottom: 8px; font-weight: 500; color: #45a29e; }
        form input, form textarea, form select { padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
        form input:focus, form select:focus, form textarea:focus { border-color: #66fcf1; outline: none; }
        form button { width: 100%; background: #0b0c10; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: background 0.3s ease; }
        form button:hover { background: #66fcf1; color: #0b0c10; }
        form button:disabled { background: #ccc; cursor: not-allowed; }
        .alert { background: #ffdddd; border-left: 6px solid #f44336; padding: 10px; margin-bottom: 15px; }
        .success { background: #ddffdd; border-left: 6px solid #4caf50; padding: 10px; margin-bottom: 15px; }
        .inline-error { color: #f44336; font-size: 0.8rem; margin-top: -10px; margin-bottom: 10px; display: none; }
        .about-content, .bookings-content, .booking-content, .home-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .about-content h2, .bookings-content h2, .booking-content h2, .home-content h2 { color: #0b0c10; margin-bottom: 20px; }
        .about-content h3, .home-content h3 { color: #45a29e; margin: 20px 0 10px; }
        .about-content ul, .home-content ul { margin-left: 20px; margin-bottom: 15px; }
        .about-content li, .home-content li { margin-bottom: 8px; }
        footer { margin-top: auto; background: linear-gradient(90deg, #0b0c10, #1f2833); color: white; padding: 2rem; text-align: center; font-size: 0.9rem; box-shadow: 0 -2px 10px rgba(0,0,0,0.3); }
        footer .social-icons { margin-top: 1rem; }
        footer .social-icons a { color: #66fcf1; margin: 0 0.5rem; font-size: 1.2rem; transition: color 0.3s ease; }
        footer .social-icons a:hover { color: #45a29e; }
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
        .slider-container img { width: 280px; height: 160px; margin: 0 10px; border-radius: 10px; display: inline-block; object-fit: cover; }
        .section-header { padding: 2rem; font-size: 1.6rem; color: #0b0c10; text-align: center; }
        .controls { display: flex; flex-direction: row; justify-content: center; align-items: center; gap: 1rem; margin: 1rem auto; max-width: 1200px; padding: 0 1rem; }
        .controls form { flex-direction: row; align-items: center; gap: 1rem; }
        .controls input[type="text"], .controls select, .controls button { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; font-size: 1rem; height: 40px; box-sizing: border-box; }
        .controls input[type="text"] { flex: 2; min-width: 150px; }
        .controls select { flex: 1; min-width: 120px; }
        .controls button { background: #0b0c10; color: #fff; border: none; cursor: pointer; flex: 1; min-width: 80px; }
        .controls button:hover { background: #66fcf1; color: #0b0c10; }
        .grid-container { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .car-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s ease; display: flex; flex-direction: column; height: 100%; max-width: 360px; margin: 0 auto; }
        .car-card:hover { transform: translateY(-5px); }
        .car-card img { width: 100%; height: 160px; object-fit: cover; }
        .car-card .details { padding: 1rem; flex-grow: 1; }
        .car-card h3 { margin-bottom: 0.5rem; color: #0b0c10; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .car-card p { font-size: 0.85rem; color: #555; margin-bottom: 0.5rem; line-height: 1.4; }
        .car-card button { margin: 0.5rem 1rem 1rem; padding: 0.5rem 1rem; background: #66fcf1; border: none; border-radius: 8px; cursor: pointer; color: #0b0c10; font-weight: bold; transition: background 0.3s ease, color 0.3s ease; width: calc(100% - 2rem); }
        .car-card button:hover { background: #0b0c10; color: #66fcf1; }
        .car-card button:disabled { background: #ccc; cursor: not-allowed; }
        .car-card .signup-info { margin: 0.5rem 1rem 1rem; color: #721c24; font-size: 0.8rem; font-style: italic; text-align: center; }
        .home-content { text-align: center; }
        .home-content p { font-size: 1.1rem; line-height: 1.6; color: #555; margin-bottom: 20px; }
        .home-content .cta-button { display: inline-block; background: #0b0c10; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: background 0.3s ease, color 0.3s ease; }
        .home-content .cta-button:hover { background: #66fcf1; color: #0b0c10; }
        #signupModal .modal-content { background: #ffffff; border: 2px solid #0b0c10; border-radius: 15px; padding: 2.5rem; width: 600px; max-width: 95%; }
        #signupModal h2 { color: #0b0c10; font-size: 1.8rem; margin-bottom: 1.5rem; text-align: center; }
        #signupModal h3 { color: #45a29e; font-size: 1.2rem; margin-bottom: 1rem; border-top: none; }
        #signupModal .signup-progress { display: flex; justify-content: space-between; background: #f4f6f8; padding: 10px; border-radius: 10px; margin-bottom: 1.5rem; }
        #signupModal .progress-step { background: #ffffff; color: #0b0c10; border: 2px solid #66fcf1; box-shadow: 0 2px 5px rgba(0,0,0,0.1); font-size: 0.9rem; padding: 8px; flex: 1; text-align: center; margin: 0 5px; border-radius: 8px; transition: background 0.3s ease, color 0.3s ease; }
        #signupModal .progress-step.active { background: #0b0c10; color: #66fcf1; border-color: #0b0c10; }
        #signupModal .form-group { margin-bottom: 1.2rem; }
        #signupModal .form-group label { display: block; color: #45a29e; font-size: 0.95rem; font-weight: 600; margin-bottom: 8px; }
        #signupModal .form-group input, #signupModal .form-group select, #signupModal .form-group input[type="file"] { width: 100%; background: #f8f9fa; border: 1px solid #cccccc; border-radius: 8px; padding: 12px; font-size: 0.95rem; transition: border-color 0.3s ease, background 0.3s ease; }
        #signupModal .form-group input:focus, #signupModal .form-group select:focus, #signupModal .form-group input[type="file"]:focus { border-color: #66fcf1; background: #ffffff; outline: none; }
        #signupModal .form-group input:hover, #signupModal .form-group select:hover, #signupModal .form-group input[type="file"]:hover { border-color: #66fcf1; }
        #signupModal .form-group input[type="file"] { border-style: dashed; border-color: #45a29e; background: #f9f9f9; cursor: pointer; }
        #signupModal .inline-error { color: #d32f2f; font-size: 0.75rem; font-style: italic; margin-top: 4px; }
        #signupModal .step-buttons { display: flex; gap: 15px; justify-content: space-between; }
        #signupModal .step-buttons button { width: 48%; }
        #signupModal .form-links a { color: #45a29e; }
        #signupModal .form-links a:hover { color: #66fcf1; }
        #signupModal .profile-picture-preview { margin: 10px 0; max-width: 100px; max-height: 100px; border-radius: 8px; display: none; }
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
        @media (max-width: 992px) {
            .grid-container { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .car-card { max-width: 100%; }
            .car-card img { height: 150px; }
            .slider-container img { width: 260px; height: 150px; }
            .controls { gap: 0.5rem; padding: 0 0.5rem; }
            .controls input[type="text"], .controls select, .controls button { font-size: 0.9rem; padding: 0.4rem 0.8rem; height: 36px; }
            .controls input[type="text"] { min-width: 120px; }
            .controls select { min-width: 100px; }
            .controls button { min-width: 70px; }
            .slide img { border-width: 6px; margin: 8px; }
            .slider-text h2 { font-size: 2rem; }
            .slider-text p { font-size: 1rem; }
        }
        @media (max-width: 600px) {
            .grid-container { grid-template-columns: 1fr; gap: 1rem; padding: 1rem; }
            .car-card { max-width: 100%; }
            .car-card img { height: 140px; }
            .car-card h3 { font-size: 1rem; }
            .car-card p { font-size: 0.8rem; }
            .car-card button { font-size: 0.9rem; padding: 0.4rem 0.8rem; }
            .slider-container img { width: 240px; height: 140px; }
            .controls { flex-wrap: wrap; gap: 0.3rem; padding: 0.5rem; }
            .controls input[type="text"], .controls select, .controls button { font-size: 0.8rem; padding: 0.3rem 0.6rem; height: 32px; width: auto; }
            .controls input[type="text"] { min-width: 100px; }
            .controls select { min-width: 80px; }
            .controls button { min-width: 60px; }
            .slide img { border-width: 4px; margin: 6px; }
            .slider-text h2 { font-size: 1.5rem; }
            .slider-text p { font-size: 0.9rem; }
            #signupModal .modal-content { width: 90%; padding: 1.5rem; }
            #signupModal .signup-progress { flex-direction: column; gap: 10px; }
            #signupModal .progress-step { margin: 0; font-size: 0.8rem; }
            #signupModal h2 { font-size: 1.5rem; }
            #signupModal .form-group label { font-size: 0.9rem; }
            #signupModal .form-group input, #signupModal .form-group select, #signupModal .form-group input[type="file"] { font-size: 0.9rem; padding: 10px; }
            #signupModal .step-buttons { flex-direction: column; }
            #signupModal .step-buttons button { width: 100%; }
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
                <div class="slide"><img src="Image1.jpg" alt="Car 1"></div>
                <div class="slide"><img src="Image2.jpg" alt="Car 2"></div>
                <div class="slide"><img src="Image3.jpg" alt="Car 3"></div>
                <div class="slide"><img src="Image4.jpg" alt="Car 4"></div>
            </div>
            <div class="slider-text">
                <h2>MIBESA Car Rental</h2>
                <p>Rent your perfect car today!</p>
            </div>
        </div>
        <div class="home-content">
            <h2>Explore Our Fleet</h2>
            <p>Choose from a variety of vehicles for any journey. Book now for a seamless rental experience.</p>
            <a href="#" onclick="showSection('cars')" class="cta-button">View Cars</a>
        </div>
    </section>

    <section id="cars" style="display:none;">
        <div class="slider-container">
            <?php foreach ($featuredCars as $car): ?>
                <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x180?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
            <?php endforeach; ?>
        </div>
        <h2 class="section-header">Featured Cars</h2>
        <div class="grid-container">
            <?php foreach ($featuredCars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                    </div>
                    <?php if (isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info">Please log in and get approved to book</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <h2 class="section-header">All Available Cars</h2>
        <div class="controls">
            <form method="GET" action="index.php">
                <input type="hidden" name="section" value="cars">
                <input type="text" name="search" placeholder="Search by name or model..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="fuel">
                    <option value="">All Fuel Types</option>
                    <option value="Petrol" <?php echo $filter_fuel == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                    <option value="Diesel" <?php echo $filter_fuel == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                    <option value="Electric" <?php echo $filter_fuel == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                </select>
                <button type="submit">Search</button>
                <button type="button" onclick="window.location.href='index.php?section=cars'">Clear</button>
            </form>
        </div>
        <div class="grid-container">
            <?php foreach ($cars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                    </div>
                    <?php if (isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info">Please log in and get approved to book</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="about" style="display:none;">
        <div class="about-content">
            <h2>About MIBESA</h2>
            <p>MIBESA Car Rental is committed to providing quality and affordable car rental services tailored to your needs.</p>
            <h3>Our Mission</h3>
            <p>To offer reliable, convenient, and affordable car rental services with high customer satisfaction.</p>
            <h3>Why Choose Us?</h3>
            <ul>
                <li>Wide selection of vehicles</li>
                <li>Competitive pricing</li>
                <li>Secure payment processing</li>
                <li>24/7 customer support</li>
            </ul>
        </div>
    </section>

    <section id="contact" style="display:none;">
        <div class="about-content">
            <h2>Contact Us</h2>
            <form id="contactForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="contact_email">Email:</label>
                <input type="email" id="contact_email" name="email" required aria-describedby="emailError">
                <span class="inline-error" id="emailError">Please enter a valid email address</span>
                <label for="contact_message">Message:</label>
                <textarea id="contact_message" name="message" required aria-describedby="messageError"></textarea>
                <span class="inline-error" id="messageError">Message cannot be empty</span>
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
                        $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
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
                    <?php endforeach; ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section id="booking" style="display:none;">
        <div class="booking-content">
            <h2>New Booking</h2>
            <?php if (empty($availableCars)): ?>
                <div class="alert">No cars available for booking at the moment.</div>
            <?php else: ?>
                <div class="booking-form">
                    <form id="newBookingForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <label for="bookingCarId">Select Car:</label>
                        <select id="bookingCarId" name="car_id" onchange="updatePricePerDay()" required aria-describedby="bookingCarIdError">
                            <option value="">Select a car</option>
                            <?php foreach ($availableCars as $car): ?>
                                <option value="<?php echo $car['id']; ?>" data-name="<?php echo htmlspecialchars($car['name']); ?>" data-price="<?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>">
                                    <?php echo htmlspecialchars($car['name']); ?> (<?php echo htmlspecialchars($car['model']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="inline-error" id="bookingCarIdError">Please select a car</span>
                        <label for="carName">Car Name:</label>
                        <input type="text" id="carName" readonly>
                        <label for="pricePerDay">Price per Day (Kwacha):</label>
                        <input type="text" id="pricePerDay" name="price_per_day" readonly>
                        <label for="pick_up_date">Pick-up Date:</label>
                        <input type="date" id="pick_up_date" name="pick_up_date" required aria-describedby="pickUpDateError">
                        <span class="inline-error" id="pickUpDateError">Pick-up date must be today or later</span>
                        <label for="return_date">Return Date:</label>
                        <input type="date" id="return_date" name="return_date" required aria-describedby="returnDateError">
                        <span class="inline-error" id="returnDateError">Return date must be after pick-up date</span>
                        <label for="totalCost">Total Cost:</label>
                        <input type="text" id="totalCost" readonly>
                        <button type="submit" name="book">Book Now</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section id="profile" style="display:none;">
        <div class="profile-content">
            <h2>Your Profile</h2>
            <div class="profile-header">
                <img src="<?php echo !empty($user['profile_picture']) && strpos($user['profile_picture'], 'data:image/') === 0 ? htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/100?text=Profile'; ?>" class="profile-pic" alt="Profile Picture">
                <div>
                    <h3><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></h3>
                    <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                </div>
            </div>
            <button class="edit-profile-btn" onclick="showEditProfileModal()">Edit Profile</button>
            <div class="profile-details">
                <div class="detail-item">
                    <label>Name:</label>
                    <p><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></p>
                </div>
                <div class="detail-item">
                    <label>Phone:</label>
                    <p><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Gender:</label>
                    <p><?php echo htmlspecialchars($user['gender'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Age:</label>
                    <p><?php echo htmlspecialchars($user['age'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Occupation:</label>
                    <p><?php echo htmlspecialchars($user['occupation'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Address:</label>
                    <p><?php echo htmlspecialchars($user['address'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Location:</label>
                    <p><?php echo htmlspecialchars($user['location'] ?? 'Not set'); ?></p>
                </div>
                <div class="detail-item">
                    <label>Next of Kin:</label>
                    <p><?php echo htmlspecialchars($user['kin_name'] ?? 'Not set'); ?> (<?php echo htmlspecialchars($user['kin_relationship'] ?? ''); ?>)</p>
                </div>
                <div class="detail-item">
                    <label>Kin Phone:</label>
                    <p><?php echo htmlspecialchars($user['kin_phone'] ?? 'Not set'); ?></p>
                </div>
            </div>
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
                        <th>Pick-up Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                        <th>Total Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (isset($_SESSION['user_id'])) {
                        $pdo = ensurePDOConnection($pdo, $host, $dbname, $username, $password);
                        $stmt = $pdo->prepare("SELECT b.*, c.name AS car_name FROM bookings b LEFT JOIN cars c ON b.car_id = c.id WHERE b.user_id = ? ORDER BY b.created_at DESC");
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
                        </tr>
                    <?php endforeach; ?>
                    <?php } ?>
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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="loginEmail">Email:</label>
                <input type="email" id="loginEmail" name="loginEmail" required aria-describedby="loginEmailError">
                <span class="inline-error" id="loginEmailError">Please enter a valid email address</span>
                <label for="loginPassword">Password:</label>
                <input type="password" id="loginPassword" name="loginPassword" required aria-describedby="loginPasswordError">
                <span class="inline-error" id="loginPasswordError">Password cannot be empty</span>
                <button type="submit" name="login">Login</button>
            </form>
            <div class="form-links">
                <a href="#" id="showSignup">Don't have an account? Sign Up</a>
                <span class="separator">|</span>
                <a href="#" id="showForgotPassword">Forgot Password?</a>
            </div>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="signupModal">×</span>
            <h2>Sign Up</h2>
            <p style="color: #d32f2f; font-style: italic; margin-bottom: 1rem;">Note: A registration fee of 500 Malawi Kwacha is required to complete signup.</p>
            <div class="signup-progress">
                <div class="progress-step active" id="progress1">1. Personal Info</div>
                <div class="progress-step" id="progress2">2. Contact Info</div>
                <div class="progress-step" id="progress3">3. Next of Kin</div>
                <div class="progress-step" id="progress4">4. Documents</div>
            </div>
            <form id="signupForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <!-- Step 1 -->
                <div class="step" id="step1">
                    <h3>Personal Information</h3>
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required aria-describedby="firstNameError" minlength="2" maxlength="50" pattern="[A-Za-z]+" title="First name can only contain letters">
                        <span class="inline-error" id="firstNameError">First name must be 2-50 letters only</span>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required aria-describedby="lastNameError" minlength="2" maxlength="50" pattern="[A-Za-z]+" title="Last name can only contain letters">
                        <span class="inline-error" id="lastNameError">Last name must be 2-50 letters only</span>
                    </div>
                    <div class="form-group">
                        <label for="signupUsername">Username:</label>
                        <input type="text" id="signupUsername" name="username" required aria-describedby="signupUsernameError" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" title="Username can only contain letters, numbers, and underscores">
                        <span class="inline-error" id="signupUsernameError">Username must be 3-50 characters (letters, numbers, or underscores)</span>
                    </div>
                    <div class="form-group">
                        <label for="signupEmail">Email:</label>
                        <input type="email" id="signupEmail" name="email" required aria-describedby="signupEmailError">
                        <span class="inline-error" id="signupEmailError">Please enter a valid email address</span>
                    </div>
                    <div class="form-group">
                        <label for="signupPassword">Password:</label>
                        <input type="password" id="signupPassword" name="password" required aria-describedby="signupPasswordError" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}" title="Password must be at least 8 characters, including uppercase, lowercase, number, and special character">
                        <span class="inline-error" id="signupPasswordError">Password must be at least 8 characters, including uppercase, lowercase, number, and special character</span>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password:</label>
                        <input type="password" id="confirmPassword" name="confirm_password" required aria-describedby="confirmPasswordError">
                        <span class="inline-error" id="confirmPasswordError">Passwords do not match</span>
                    </div>
                    <div class="form-group">
                        <label for="signupAge">Age:</label>
                        <input type="number" id="signupAge" name="age" min="18" max="100" required aria-describedby="signupAgeError">
                        <span class="inline-error" id="signupAgeError">Age must be between 18 and 100</span>
                    </div>
                    <div class="form-group">
                        <label for="occupation">Occupation:</label>
                        <input type="text" id="occupation" name="occupation" required aria-describedby="occupationError" maxlength="100">
                        <span class="inline-error" id="occupationError">Occupation is required</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" onclick="nextStep(2)">Next</button>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="step" id="step2" style="display:none;">
                    <h3>Contact Information</h3>
                    <div class="form-group">
                        <label for="phone">Phone Number:</label>
                        <input type="text" id="phone" name="phone" required aria-describedby="phoneError" pattern="0[89][0-9]{8}" title="Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)">
                        <span class="inline-error" id="phoneError">Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)</span>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required aria-describedby="genderError">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="inline-error" id="genderError">Please select a gender</span>
                    </div>
                    <div class="form-group">
                        <label for="address">Address:</label>
                        <input type="text" id="address" name="address" required aria-describedby="addressError" maxlength="255">
                        <span class="inline-error" id="addressError">Address is required</span>
                    </div>
                    <div class="form-group">
                        <label for="location">Location:</label>
                        <select id="location" name="location" required aria-describedby="locationError">
                            <option value="">Select Location</option>
                            <option value="Lilongwe">Lilongwe</option>
                            <option value="Blantyre">Blantyre</option>
                            <option value="Mzuzu">Mzuzu</option>
                            <option value="Zomba">Zomba</option>
                        </select>
                        <span class="inline-error" id="locationError">Please select a location</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(1)">Previous</button>
                        <button type="button" onclick="nextStep(3)">Next</button>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="step" id="step3" style="display:none;">
                    <h3>Next of Kin</h3>
                    <div class="form-group">
                        <label for="kin_name">Full Name:</label>
                        <input type="text" id="kin_name" name="kin_name" required aria-describedby="kinNameError" maxlength="100">
                        <span class="inline-error" id="kinNameError">Full name is required</span>
                    </div>
                    <div class="form-group">
                        <label for="kin_relationship">Relationship:</label>
                        <select id="kin_relationship" name="kin_relationship" required aria-describedby="kinRelationshipError">
                            <option value="">Select Relationship</option>
                            <option value="Parent">Parent</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Friend">Friend</option>
                            <option value="Other">Other</option>
                        </select>
                        <span class="inline-error" id="kinRelationshipError">Please select a relationship</span>
                    </div>
                    <div class="form-group">
                        <label for="kin_phone">Phone Number:</label>
                        <input type="text" id="kin_phone" name="kin_phone" required aria-describedby="kinPhoneError" pattern="0[89][0-9]{8}" title="Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)">
                        <span class="inline-error" id="kinPhoneError">Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)</span>
                    </div>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(2)">Previous</button>
                        <button type="button" onclick="nextStep(4)">Next</button>
                    </div>
                </div>
                <!-- Step 4 -->
                <div class="step" id="step4" style="display:none;">
                    <h3>Documents</h3>
                    <div class="form-group">
                        <label for="national_id">National ID (PDF):</label>
                        <input type="file" id="national_id" name="national_id" accept="application/pdf" required aria-describedby="nationalIdError">
                        <span class="inline-error" id="nationalIdError">Please upload a PDF file under 2MB</span>
                    </div>
                    <div class="form-group">
                        <label for="profile_picture">Profile Picture (JPEG/PNG):</label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png" required aria-describedby="profilePictureError">
                        <span class="inline-error" id="profilePictureError">Please upload a JPEG or PNG image under 2MB</span>
                        <img id="profilePicturePreview" class="profile-picture-preview" alt="Profile Picture Preview">
                    </div>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(3)">Previous</button>
                        <button type="submit" name="signup">Sign Up</button>
                    </div>
                </div>
            </form>
            <div class="form-links">
                <a href="#" id="showLoginFromSignup">Already have an account? Login</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="forgotPasswordModal">×</span>
            <h2>Forgot Password</h2>
            <form id="forgotPasswordForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="forgot_email">Email:</label>
                <input type="email" id="forgot_email" name="forgot_email" required aria-describedby="forgotEmailError">
                <span class="inline-error" id="forgotEmailError">Please enter a valid email address</span>
                <button type="submit" name="forgot_password">Reset Password</button>
            </form>
            <div class="form-links">
                <a href="#" id="showLoginFromForgot">Back to Login</a>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="bookingModal">×</span>
            <h2>Book a Car</h2>
            <form id="bookingForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="modalCarId" name="car_id">
                <label for="modalCarName">Car Name:</label>
                <input type="text" id="modalCarName" readonly>
                <label for="modalPricePerDay">Price per Day (Kwacha):</label>
                <input type="text" id="modalPricePerDay" name="price_per_day" readonly>
                <label for="modalPickUpDate">Pick-up Date:</label>
                <input type="date" id="modalPickUpDate" name="pick_up_date" required aria-describedby="modalPickUpDateError">
                <span class="inline-error" id="modalPickUpDateError">Pick-up date must be today or later</span>
                <label for="modalReturnDate">Return Date:</label>
                <input type="date" id="modalReturnDate" name="return_date" required aria-describedby="modalReturnDateError">
                <span class="inline-error" id="modalReturnDateError">Return date must be after pick-up date</span>
                <label for="modalTotalCost">Total Cost:</label>
                <input type="text" id="modalTotalCost" readonly>
                <button type="submit" name="book">Confirm Booking</button>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="paymentModal">×</span>
            <h2>Payment</h2>
            <div id="paymentDetails"></div>
            <label for="paymentPhone">Phone Number:</label>
            <input type="text" id="paymentPhone" placeholder="e.g., 0885620896 or 0999123456" required aria-describedby="paymentPhoneError">
            <span class="inline-error" id="paymentPhoneError">Phone number must be 10 digits starting with 08 or 09</span>
            <button onclick="processPayment()">Process Payment</button>
        </div>
    </div>

    <!-- Payment Failure Modal -->
    <div id="paymentFailureModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="paymentFailureModal">×</span>
            <h2>Payment Failed</h2>
            <p id="paymentFailureMessage"></p>
            <button onclick="hideModal('paymentFailureModal')">Close</button>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="editProfileModal">×</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="edit_first_name">First Name:</label>
                <input type="text" id="edit_first_name" name="edit_first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required aria-describedby="editFirstNameError" minlength="2" maxlength="50" pattern="[A-Za-z]+" title="First name can only contain letters">
                <span class="inline-error" id="editFirstNameError">First name must be 2-50 letters only</span>
                <label for="edit_last_name">Last Name:</label>
                <input type="text" id="edit_last_name" name="edit_last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required aria-describedby="editLastNameError" minlength="2" maxlength="50" pattern="[A-Za-z]+" title="Last name can only contain letters">
                <span class="inline-error" id="editLastNameError">Last name must be 2-50 letters only</span>
                <label for="edit_username">Username:</label>
                <input type="text" id="edit_username" name="edit_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required aria-describedby="editUsernameError" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" title="Username can only contain letters, numbers, and underscores">
                <span class="inline-error" id="editUsernameError">Username must be 3-50 characters (letters, numbers, or underscores)</span>
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required aria-describedby="editEmailError">
                <span class="inline-error" id="editEmailError">Please enter a valid email address</span>
                <label for="edit_phone">Phone Number:</label>
                <input type="text" id="edit_phone" name="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required aria-describedby="editPhoneError" pattern="0[89][0-9]{8}" title="Phone number must be 10 digits starting with 08 or 09 (e.g., 0885620896 or 0999123456)">
                <span class="inline-error" id="editPhoneError">Phone number must be 10 digits starting with 08 or 09</span>
                <label for="edit_gender">Gender:</label>
                <select id="edit_gender" name="edit_gender" required aria-describedby="editGenderError">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($user['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($user['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <span class="inline-error" id="editGenderError">Please select a gender</span>
                <label for="edit_address">Address:</label>
                <input type="text" id="edit_address" name="edit_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required aria-describedby="editAddressError" maxlength="255">
                <span class="inline-error" id="editAddressError">Address is required</span>
                <label for="edit_location">Location:</label>
                <select id="edit_location" name="edit_location" required aria-describedby="editLocationError">
                    <option value="">Select Location</option>
                    <option value="Lilongwe" <?php echo ($user['location'] ?? '') == 'Lilongwe' ? 'selected' : ''; ?>>Lilongwe</option>
                    <option value="Blantyre" <?php echo ($user['location'] ?? '') == 'Blantyre' ? 'selected' : ''; ?>>Blantyre</option>
                    <option value="Mzuzu" <?php echo ($user['location'] ?? '') == 'Mzuzu' ? 'selected' : ''; ?>>Mzuzu</option>
                    <option value="Zomba" <?php echo ($user['location'] ?? '') == 'Zomba' ? 'selected' : ''; ?>>Zomba</option>
                </select>
                <span class="inline-error" id="editLocationError">Please select a location</span>
                <label for="edit_age">Age:</label>
                <input type="number" id="edit_age" name="edit_age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" min="18" max="100" required aria-describedby="editAgeError">
                <span class="inline-error" id="editAgeError">Age must be between 18 and 100</span>
                <label for="edit_occupation">Occupation:</label>
                <input type="text" id="edit_occupation" name="edit_occupation" value="<?php echo htmlspecialchars($user['occupation'] ?? ''); ?>" required aria-describedby="editOccupationError" maxlength="100">
                <span class="inline-error" id="editOccupationError">Occupation is required</span>
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
    </div>

    <footer>
        <p>© <?php echo date('Y'); ?> MIBESA Car Rental. All rights reserved.</p>
        <div class="social-icons">
            <a href="#"><i class="fab fa-facebook-f"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
        </div>
    </footer>
    <script>
const currentDate = '<?php echo $current_date; ?>';
let currentStep = 1;
let paymentError = <?php echo $js_payment_error; ?>;
let paymentType = <?php echo $js_payment_type; ?>;
let tempSignupData = <?php echo $js_temp_signup_data; ?>;
let tempBookingData = <?php echo $js_temp_booking_data; ?>;

// Initialize modals
const modals = document.querySelectorAll('.modal');
const closeButtons = document.querySelectorAll('.close');
const loginBtn = document.getElementById('loginBtn');
const showSignup = document.getElementById('showSignup');
const showLoginFromSignup = document.getElementById('showLoginFromSignup');
const showForgotPassword = document.getElementById('showForgotPassword');
const showLoginFromForgot = document.getElementById('showLoginFromForgot');

// Show section
function showSection(sectionId) {
    document.querySelectorAll('section').forEach(section => section.style.display = 'none');
    document.getElementById(sectionId).style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (sectionId === 'cars') {
        initializeSlider();
    }
}

// Modal handling
function showModal(modalId) {
    modals.forEach(modal => modal.style.display = 'none');
    document.getElementById(modalId).style.display = 'block';
}

function hideModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

closeButtons.forEach(button => {
    button.addEventListener('click', () => {
        hideModal(button.dataset.target);
    });
});

window.addEventListener('click', (event) => {
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});

if (loginBtn) {
    loginBtn.addEventListener('click', () => showModal('loginModal'));
}

if (showSignup) {
    showSignup.addEventListener('click', () => {
        hideModal('loginModal');
        showModal('signupModal');
    });
}

if (showLoginFromSignup) {
    showLoginFromSignup.addEventListener('click', () => {
        hideModal('signupModal');
        showModal('loginModal');
    });
}

if (showForgotPassword) {
    showForgotPassword.addEventListener('click', () => {
        hideModal('loginModal');
        showModal('forgotPasswordModal');
    });
}

if (showLoginFromForgot) {
    showLoginFromForgot.addEventListener('click', () => {
        hideModal('forgotPasswordModal');
        showModal('loginModal');
    });
}

// Signup steps
function nextStep(step) {
    if (validateStep(currentStep)) {
        document.getElementById(`step${currentStep}`).style.display = 'none';
        document.getElementById(`step${step}`).style.display = 'block';
        document.getElementById(`progress${currentStep}`).classList.remove('active');
        document.getElementById(`progress${step}`).classList.add('active');
        currentStep = step;
    }
}

function prevStep(step) {
    document.getElementById(`step${currentStep}`).style.display = 'none';
    document.getElementById(`step${step}`).style.display = 'block';
    document.getElementById(`progress${currentStep}`).classList.remove('active');
    document.getElementById(`progress${step}`).classList.add('active');
    currentStep = step;
}

// Client-side validation for signup steps
function validateStep(step) {
    let isValid = true;
    const showError = (id, message) => {
        const errorElement = document.getElementById(id);
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        isValid = false;
    };

    if (step === 1) {
        const firstName = document.getElementById('first_name').value;
        const lastName = document.getElementById('last_name').value;
        const username = document.getElementById('signupUsername').value;
        const email = document.getElementById('signupEmail').value;
        const password = document.getElementById('signupPassword').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const age = document.getElementById('signupAge').value;
        const occupation = document.getElementById('occupation').value;

        if (!/^[A-Za-z]{2,50}$/.test(firstName)) {
            showError('firstNameError', 'First name must be 2-50 letters only');
        }
        if (!/^[A-Za-z]{2,50}$/.test(lastName)) {
            showError('lastNameError', 'Last name must be 2-50 letters only');
        }
        if (!/^[A-Za-z0-9_]{3,50}$/.test(username)) {
            showError('signupUsernameError', 'Username must be 3-50 characters (letters, numbers, or underscores)');
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('signupEmailError', 'Please enter a valid email address');
        }
        if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/.test(password)) {
            showError('signupPasswordError', 'Password must be at least 8 characters, including uppercase, lowercase, number, and special character');
        }
        if (password !== confirmPassword) {
            showError('confirmPasswordError', 'Passwords do not match');
        }
        if (age < 18 || age > 100) {
            showError('signupAgeError', 'Age must be between 18 and 100');
        }
        if (!occupation) {
            showError('occupationError', 'Occupation is required');
        }
    } else if (step === 2) {
        const phone = document.getElementById('phone').value;
        const gender = document.getElementById('gender').value;
        const address = document.getElementById('address').value;
        const location = document.getElementById('location').value;

        if (!/^0[89][0-9]{8}$/.test(phone)) {
            showError('phoneError', 'Phone number must be 10 digits starting with 08 or 09');
        }
        if (!['Male', 'Female', 'Other'].includes(gender)) {
            showError('genderError', 'Please select a gender');
        }
        if (!address) {
            showError('addressError', 'Address is required');
        }
        if (!['Lilongwe', 'Blantyre', 'Mzuzu', 'Zomba'].includes(location)) {
            showError('locationError', 'Please select a location');
        }
    } else if (step === 3) {
        const kinName = document.getElementById('kin_name').value;
        const kinRelationship = document.getElementById('kin_relationship').value;
        const kinPhone = document.getElementById('kin_phone').value;

        if (!kinName) {
            showError('kinNameError', 'Full name is required');
        }
        if (!['Parent', 'Sibling', 'Spouse', 'Friend', 'Other'].includes(kinRelationship)) {
            showError('kinRelationshipError', 'Please select a relationship');
        }
        if (!/^0[89][0-9]{8}$/.test(kinPhone)) {
            showError('kinPhoneError', 'Phone number must be 10 digits starting with 08 or 09');
        }
    } else if (step === 4) {
        const nationalId = document.getElementById('national_id').files[0];
        const profilePicture = document.getElementById('profile_picture').files[0];

        if (!nationalId || nationalId.type !== 'application/pdf' || nationalId.size > 2 * 1024 * 1024) {
            showError('nationalIdError', 'Please upload a PDF file under 2MB');
        }
        if (!profilePicture || !['image/jpeg', 'image/png'].includes(profilePicture.type) || profilePicture.size > 2 * 1024 * 1024) {
            showError('profilePictureError', 'Please upload a JPEG or PNG image under 2MB');
        }
    }

    return isValid;
}

// Profile picture preview
document.getElementById('profile_picture').addEventListener('change', function (event) {
    const file = event.target.files[0];
    const preview = document.getElementById('profilePicturePreview');
    if (file && ['image/jpeg', 'image/png'].includes(file.type)) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
});

// Initialize slider
function initializeSlider() {
    const slides = document.querySelector('.slides');
    const slideElements = document.querySelectorAll('.slide');
    const totalSlides = slideElements.length;

    slides.style.width = `${100 * totalSlides}%`;
    slideElements.forEach(slide => {
        slide.style.width = `${100 / totalSlides}%`;
    });

    let currentIndex = 0;

    function showSlide(index) {
        slides.style.transform = `translateX(-${index * (100 / totalSlides)}%)`;
    }

    function nextSlide() {
        currentIndex++;
        if (currentIndex >= totalSlides) {
            currentIndex = 0;
            slides.style.transition = 'none';
            showSlide(currentIndex);
            setTimeout(() => {
                slides.style.transition = 'transform 0.5s ease';
            }, 100);
        } else {
            slides.style.transition = 'transform 0.5s ease';
            showSlide(currentIndex);
        }
    }

    showSlide(currentIndex);
    setInterval(nextSlide, 10000);
}

document.addEventListener('DOMContentLoaded', initializeSlider);

// Show booking modal
function showBookingModal(carId, carName, pricePerDay) {
    document.getElementById('modalCarId').value = carId;
    document.getElementById('modalCarName').value = carName;
    document.getElementById('modalPricePerDay').value = pricePerDay;
    document.getElementById('modalPickUpDate').setAttribute('min', currentDate);
    showModal('bookingModal');
    calculateTotalCost();
}

// Calculate total cost for booking
function calculateTotalCost() {
    const pickUpDate = document.getElementById('modalPickUpDate').value;
    const returnDate = document.getElementById('modalReturnDate').value;
    const pricePerDay = parseFloat(document.getElementById('modalPricePerDay').value);

    if (pickUpDate && returnDate) {
        const pickUp = new Date(pickUpDate);
        const returnD = new Date(returnDate);
        const diffTime = Math.abs(returnD - pickUp);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 0) {
            const totalCost = diffDays * pricePerDay;
            document.getElementById('modalTotalCost').value = totalCost.toFixed(2) + ' Kwacha';
        } else {
            document.getElementById('modalTotalCost').value = 'Invalid dates';
        }
    }
}

// Update price per day for new booking form
function updatePricePerDay() {
    const select = document.getElementById('bookingCarId');
    const selectedOption = select.options[select.selectedIndex];
    const carName = selectedOption.dataset.name;
    const price = selectedOption.dataset.price;

    document.getElementById('carName').value = carName || '';
    document.getElementById('pricePerDay').value = price || '';
    calculateNewBookingTotalCost();
}

// Calculate total cost for new booking form
function calculateNewBookingTotalCost() {
    const pickUpDate = document.getElementById('pick_up_date').value;
    const returnDate = document.getElementById('return_date').value;
    const pricePerDay = parseFloat(document.getElementById('pricePerDay').value);

    if (pickUpDate && returnDate && !isNaN(pricePerDay)) {
        const pickUp = new Date(pickUpDate);
        const returnD = new Date(returnDate);
        const diffTime = Math.abs(returnD - pickUp);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 0) {
            const totalCost = diffDays * pricePerDay;
            document.getElementById('totalCost').value = totalCost.toFixed(2) + ' Kwacha';
        } else {
            document.getElementById('totalCost').value = 'Invalid dates';
        }
    }
}

// Event listeners for date inputs
document.getElementById('modalPickUpDate').addEventListener('change', calculateTotalCost);
document.getElementById('modalReturnDate').addEventListener('change', calculateTotalCost);
document.getElementById('pick_up_date').addEventListener('change', calculateNewBookingTotalCost);
document.getElementById('return_date').addEventListener('change', calculateNewBookingTotalCost);

// Show edit profile modal
function showEditProfileModal() {
    showModal('editProfileModal');
}

// Filter bookings
function filterBookings() {
    const search = document.getElementById('bookingSearch').value.toLowerCase();
    const status = document.getElementById('bookingStatusFilter').value;
    const rows = document.querySelectorAll('#bookingsTable tbody tr');

    rows.forEach(row => {
        const carName = row.cells[0].textContent.toLowerCase();
        const bookingId = row.cells[1].textContent.toLowerCase();
        const rowStatus = row.cells[4].textContent.toLowerCase();

        const matchesSearch = carName.includes(search) || bookingId.includes(search);
        const matchesStatus = !status || rowStatus === status.toLowerCase();

        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

// Reset booking filter
function resetBookingFilter() {
    document.getElementById('bookingSearch').value = '';
    document.getElementById('bookingStatusFilter').value = '';
    filterBookings();
}

// Filter profile bookings
function filterProfileBookings() {
    const search = document.getElementById('profileBookingSearch').value.toLowerCase();
    const status = document.getElementById('profileBookingStatusFilter').value;
    const rows = document.querySelectorAll('#profileBookingsTable tbody tr');

    rows.forEach(row => {
        const carName = row.cells[0].textContent.toLowerCase();
        const rowStatus = row.cells[3].textContent.toLowerCase();

        const matchesSearch = carName.includes(search);
        const matchesStatus = !status || rowStatus === status.toLowerCase();

        row.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

// Reset profile booking filter
function resetProfileBookingFilter() {
    document.getElementById('profileBookingSearch').value = '';
    document.getElementById('profileBookingStatusFilter').value = '';
    filterProfileBookings();
}

// Show payment failure modal
function showPaymentFailureModal(type) {
    const message = type === 'signup' 
        ? 'Signup failed due to payment processing error. Please try signing up again.'
        : 'Booking failed due to payment processing error. Please try booking again.';
    document.getElementById('paymentFailureMessage').textContent = message;
    showModal('paymentFailureModal');
}

// Handle payment modal
if (paymentError && paymentType) {
    let details = '';
    if (paymentType === 'signup' && tempSignupData) {
        details = `<p>Amount: 500 Kwacha</p><p>Purpose: Account Signup</p>`;
        document.getElementById('paymentPhone').value = tempSignupData.phone || '';
        showModal('paymentModal');
    } else if (paymentType === 'booking' && tempBookingData) {
        details = `<p>Car: ${tempBookingData.car_name}</p><p>Amount: ${tempBookingData.total_cost} Kwacha</p><p>Booking ID: ${tempBookingData.booking_id}</p>`;
        showModal('paymentModal');
    }
    document.getElementById('paymentDetails').innerHTML = details;
    showPaymentFailureModal(paymentType);
}

// Process payment (placeholder)
function processPayment() {
    const phone = document.getElementById('paymentPhone').value;
    if (!/^0[89][0-9]{8}$/.test(phone)) {
        document.getElementById('paymentPhoneError').style.display = 'block';
        return;
    }
    alert('Payment processing not implemented in this demo.');
    hideModal('paymentModal');
}

// Form validation for login
document.getElementById('loginForm').addEventListener('submit', function (event) {
    let isValid = true;
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    document.getElementById('loginEmailError').style.display = 'none';
    document.getElementById('loginPasswordError').style.display = 'none';

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('loginEmailError').style.display = 'block';
        isValid = false;
    }
    if (!password) {
        document.getElementById('loginPasswordError').style.display = 'block';
        isValid = false;
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Form validation for forgot password
document.getElementById('forgotPasswordForm').addEventListener('submit', function (event) {
    const email = document.getElementById('forgot_email').value;
    document.getElementById('forgotEmailError').style.display = 'none';

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('forgotEmailError').style.display = 'block';
        event.preventDefault();
    }
});

// Form validation for contact
document.getElementById('contactForm').addEventListener('submit', function (event) {
    let isValid = true;
    const email = document.getElementById('contact_email').value;
    const message = document.getElementById('contact_message').value;

    document.getElementById('emailError').style.display = 'none';
    document.getElementById('messageError').style.display = 'none';

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('emailError').style.display = 'block';
        isValid = false;
    }
    if (!message.trim()) {
        document.getElementById('messageError').style.display = 'block';
        isValid = false;
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Form validation for booking form
document.getElementById('bookingForm').addEventListener('submit', function (event) {
    let isValid = true;
    const pickUpDate = document.getElementById('modalPickUpDate').value;
    const returnDate = document.getElementById('modalReturnDate').value;
    const today = new Date(currentDate);
    const pickUp = new Date(pickUpDate);
    const returnD = new Date(returnDate);

    document.getElementById('modalPickUpDateError').style.display = 'none';
    document.getElementById('modalReturnDateError').style.display = 'none';

    if (!pickUpDate || pickUp < today.setHours(0, 0, 0, 0)) {
        document.getElementById('modalPickUpDateError').style.display = 'block';
        isValid = false;
    }
    if (!returnDate || returnD <= pickUp) {
        document.getElementById('modalReturnDateError').style.display = 'block';
        isValid = false;
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Form validation for new booking form
document.getElementById('newBookingForm').addEventListener('submit', function (event) {
    let isValid = true;
    const carId = document.getElementById('bookingCarId').value;
    const pickUpDate = document.getElementById('pick_up_date').value;
    const returnDate = document.getElementById('return_date').value;
    const today = new Date(currentDate);
    const pickUp = new Date(pickUpDate);
    const returnD = new Date(returnDate);

    document.getElementById('bookingCarIdError').style.display = 'none';
    document.getElementById('pickUpDateError').style.display = 'none';
    document.getElementById('returnDateError').style.display = 'none';

    if (!carId) {
        document.getElementById('bookingCarIdError').style.display = 'block';
        isValid = false;
    }
    if (!pickUpDate || pickUp < today.setHours(0, 0, 0, 0)) {
        document.getElementById('pickUpDateError').style.display = 'block';
        isValid = false;
    }
    if (!returnDate || returnD <= pickUp) {
        document.getElementById('returnDateError').style.display = 'block';
        isValid = false;
    }

    if (!isValid) {
        event.preventDefault();
    }
});

// Form validation for edit profile
d// Form validation for edit profile
document.getElementById('editProfileForm').addEventListener('submit', function (event) {
    let isValid = true;
    const firstName = document.getElementById('edit_first_name').value;
    const lastName = document.getElementById('edit_last_name').value;
    const username = document.getElementById('edit_username').value;
    const email = document.getElementById('edit_email').value;
    const phone = document.getElementById('edit_phone').value;
    const gender = document.getElementById('edit_gender').value;
    const address = document.getElementById('edit_address').value;
    const location = document.getElementById('edit_location').value;
    const age = document.getElementById('edit_age').value;
    const occupation = document.getElementById('edit_occupation').value;

    // Hide all existing error messages
    document.querySelectorAll('#editProfileForm .inline-error').forEach(error => error.style.display = 'none');

    // Validate first name (2-50 letters)
    if (!/^[A-Za-z]{2,50}$/.test(firstName)) {
        document.getElementById('editFirstNameError').style.display = 'block';
        isValid = false;
    }

    // Validate last name (2-50 letters)
    if (!/^[A-Za-z]{2,50}$/.test(lastName)) {
        document.getElementById('editLastNameError').style.display = 'block';
        isValid = false;
    }

    // Validate username (3-50 alphanumeric characters or underscores)
    if (!/^[A-Za-z0-9_]{3,50}$/.test(username)) {
        document.getElementById('editUsernameError').style.display = 'block';
        isValid = false;
    }

    // Validate email (basic email format)
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        document.getElementById('editEmailError').style.display = 'block';
        isValid = false;
    }

    // Validate phone number (starts with 08 or 09, exactly 10 digits)
    if (!/^0[89][0-9]{8}$/.test(phone)) {
        document.getElementById('editPhoneError').style.display = 'block';
        isValid = false;
    }

    // Validate gender (must be Male, Female, or Other)
    if (!['Male', 'Female', 'Other'].includes(gender)) {
        document.getElementById('editGenderError').style.display = 'block';
        isValid = false;
    }

    // Validate address (not empty)
    if (!address) {
        document.getElementById('editAddressError').style.display = 'block';
        isValid = false;
    }

    // Validate location (must be one of the specified cities)
    if (!['Lilongwe', 'Blantyre', 'Mzuzu', 'Zomba'].includes(location)) {
        document.getElementById('editLocationError').style.display = 'block';
        isValid = false;
    }

    // Validate age (between 18 and 100)
    if (age < 18 || age > 100) {
        document.getElementById('editAgeError').style.display = 'block';
        isValid = false;
    }

    // Validate occupation (not empty)
    if (!occupation) {
        document.getElementById('editOccupationError').style.display = 'block';
        isValid = false;
    }

    // Prevent form submission if validation fails
    if (!isValid) {
        event.preventDefault();
    }
});

// Clear inline errors when user starts typing
document.querySelectorAll('input, select, textarea').forEach(input => {
    input.addEventListener('input', function () {
        const errorId = this.getAttribute('aria-describedby');
        if (errorId) {
            document.getElementById(errorId).style.display = 'none';
        }
    });
});

// Initialize page when DOM is fully loaded
document.addEventListener('DOMContentLoaded', function () {
    showSection('home'); // Show the home section by default
    initializeSlider();  // Initialize any slider components

    // Populate signup form with temporary data if available
    if (tempSignupData) {
        document.getElementById('first_name').value = tempSignupData.first_name || '';
        document.getElementById('last_name').value = tempSignupData.last_name || '';
        document.getElementById('signupUsername').value = tempSignupData.username || '';
        document.getElementById('signupEmail').value = tempSignupData.email || '';
        document.getElementById('phone').value = tempSignupData.phone || '';
        document.getElementById('gender').value = tempSignupData.gender || '';
        document.getElementById('address').value = tempSignupData.address || '';
        document.getElementById('location').value = tempSignupData.location || '';
        document.getElementById('kin_name').value = tempSignupData.kin_name || '';
        document.getElementById('kin_relationship').value = tempSignupData.kin_relationship || '';
        document.getElementById('kin_phone').value = tempSignupData.kin_phone || '';
        document.getElementById('signupAge').value = tempSignupData.age || '';
        document.getElementById('occupation').value = tempSignupData.occupation || '';
    }
});

</script>
</body>
</html>
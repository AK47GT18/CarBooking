<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
$error = null;

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
        $age = filter_var($_POST['age'], FILTER_SANITIZE_NUMBER_INT);
        $profile_picture_file = $_FILES['profile_picture'];
        $national_id_file = $_FILES['national_id'];

        // Validate inputs
        if (!$username) {
            $error = "Username is required.";
        } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
            $error = "Invalid phone number format. Must be 10 digits starting with 0 (e.g., 0885620896).";
        } elseif (!preg_match('/^0[0-9]{9}$/', $kin_phone)) {
            $error = "Invalid next of kin phone number format. Must be 10 digits starting with 0 (e.g., 0885620896).";
        } elseif (!isset($national_id_file) || $national_id_file['error'] !== UPLOAD_ERR_OK || $national_id_file['type'] !== 'application/pdf' || $national_id_file['size'] > MAX_FILE_SIZE) {
            $error = "Invalid national ID file. Must be a PDF under 2MB.";
        } elseif (!isset($profile_picture_file) || $profile_picture_file['error'] !== UPLOAD_ERR_OK || !in_array($profile_picture_file['type'], ['image/jpeg', 'image/png']) || $profile_picture_file['size'] > MAX_FILE_SIZE) {
            $error = "Invalid profile picture file. Must be JPEG or PNG under 2MB.";
        } elseif ($age < 18 || $age > 100) {
            $error = "Age must be between 18 and 100.";
        } else {
            // Create tmp directory if it doesn't exist
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }

            // Move uploaded files to tmp directory
            $national_id_filename = 'national_id_' . time() . '.pdf';
            $profile_picture_filename = 'profile_picture_' . time() . '.' . ($profile_picture_file['type'] === 'image/jpeg' ? 'jpg' : 'png');
            $national_id_path = UPLOAD_DIR . $national_id_filename;
            $profile_picture_path = UPLOAD_DIR . $profile_picture_filename;

            if (!move_uploaded_file($national_id_file['tmp_name'], $national_id_path) || !move_uploaded_file($profile_picture_file['tmp_name'], $profile_picture_path)) {
                $error = "Failed to process uploaded files.";
            } else {
                // Store data temporarily in session
                $_SESSION['temp_signup_data'] = [
                    'username' => $username,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'gender' => $gender,
                    'address' => $address,
                    'location' => $location,
                    'kin_name' => $kin_name,
                    'kin_relationship' => $kin_relationship,
                    'kin_phone' => $kin_phone,
                    'national_id' => base64_encode(file_get_contents($national_id_path)),
                    'profile_picture' => base64_encode(file_get_contents($profile_picture_path)),
                    'age' => $age,
                    'national_id_path' => $national_id_path,
                    'profile_picture_path' => $profile_picture_path
                ];

                $payment = initiateUSSDPayment($pdo, 0, 50, 'signup', null, $_SESSION['temp_signup_data']);
                if (isset($payment['error'])) {
                    $payment_error = $payment['error'];
                    $payment_type = $payment['type'];
                    $temp_signup_data = $_SESSION['temp_signup_data'];
                    // Clean up temporary files
                    @unlink($national_id_path);
                    @unlink($profile_picture_path);
                    unset($_SESSION['temp_signup_data']);
                } else {
                    $user_id = $payment['user_id'];
                    $adminStmt = $pdo->query("SELECT email FROM admins");
                    while ($admin = $adminStmt->fetch()) {
                        sendEmail($admin['email'], "New User Signup", "A new user ($username, $email) has signed up and completed payment (charge_id: {$payment['charge_id']}).");
                    }
                    // Clean up temporary files
                    @unlink($national_id_path);
                    @unlink($profile_picture_path);
                    header("Location: index.php?message=" . urlencode($payment['message']));
                    exit;
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

        // Store data temporarily in session
        $_SESSION['temp_booking_data'] = [
            'car_id' => $car_id,
            'booking_id' => $booking_id,
            'pick_up_date' => $pick_up_date,
            'return_date' => $return_date,
            'total_days' => $total_days,
            'total_cost' => $total_cost,
            'price_per_day' => $price_per_day
        ];

        // Get car name for error handling
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
        $age = filter_var($_POST['edit_age'], FILTER_SANITIZE_NUMBER_INT);

        if (!preg_match('/^0[0-9]{9}$/', $phone)) {
            $error = "Invalid phone number format. Must be 10 digits starting with 0 (e.g., 0885620896).";
        } elseif ($age < 18 || $age > 100) {
            $error = "Age must be between 18 and 100.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, gender = ?, address = ?, location = ?, age = ? WHERE id = ?");
                $stmt->execute([$username, $email, $phone, $gender, $address, $location, $age, $_SESSION['user_id']]);
                header("Location: index.php?message=Profile updated successfully");
                exit;
            } catch (PDOException $e) {
                error_log("Database error in profile update: " . $e->getMessage());
                $error = "Database error: Unable to update profile. Please try again later.";
            }
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
?>
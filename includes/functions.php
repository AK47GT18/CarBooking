<?php
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/*function initiateUSSDPayment($pdo, $user_id, $amount, $type, $charge_id = null, $temp_data = null) {
    // Placeholder: Implement USSD payment logic
    try {
        // Simulate payment processing
        if ($amount <= 0) {
            return ['error' => 'Invalid payment amount', 'type' => $type];
        }
        // Insert user or booking after payment success
        if ($type === 'signup' && $temp_data) {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, phone, password, gender, address, location, kin_name, kin_relationship, kin_phone, age, national_id, profile_picture, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paid')
            ");
            $stmt->execute([
                $temp_data['username'],
                $temp_data['email'],
                $temp_data['phone'],
                $temp_data['password'],
                $temp_data['gender'],
                $temp_data['address'],
                $temp_data['location'],
                $temp_data['kin_name'],
                $temp_data['kin_relationship'],
                $temp_data['kin_phone'],
                $temp_data['age'],
                $temp_data['national_id'],
                $temp_data['profile_picture']
            ]);
            $user_id = $pdo->lastInsertId();
            return ['user_id' => $user_id, 'charge_id' => 'charge_' . time(), 'message' => 'Signup successful. Account pending approval.'];
        } elseif ($type === 'booking' && $temp_data) {
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, car_id, booking_id, pick_up_date, return_date, total_days, total_cost, price_per_day, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paid')
            ");
            $stmt->execute([
                $user_id,
                $temp_data['car_id'],
                $temp_data['booking_id'],
                $temp_data['pick_up_date'],
                $temp_data['return_date'],
                $temp_data['total_days'],
                $temp_data['total_cost'],
                $temp_data['price_per_day']
            ]);
            return ['message' => 'Booking successful.'];
        }
    } catch (Exception $e) {
        error_log("Payment error: " . $e->getMessage());
        return ['error' => 'Payment processing failed. Please try again.', 'type' => $type];
    }
}
*/ /*
function sendEmail($to, $subject, $message) {
    // Placeholder: Implement email sending logic
    return true; // Assume success for now
}*/
?>
<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Polling function for PayChangu payment verification
function pollForSuccess(string $chargeId, Client $client, int $intervalSec = 5, int $maxAttempts = 12): array
{
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $resp = $client->get(PAYCHANGU_API_URL . "/mobile-money/payments/{$chargeId}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . PAYCHANGU_SECRET_KEY,
                    'Accept' => 'application/json',
                ]
            ]);
            $data = json_decode($resp->getBody(), true);

            if ($data['status'] === 'successful' && ($data['data']['status'] ?? null) === 'success') {
                return $data['data'];
            }

            sleep($intervalSec);
        } catch (RequestException $e) {
            error_log("Polling attempt $attempt failed for charge_id $chargeId: " . $e->getMessage());
        }
    }

    throw new \RuntimeException(
        "Payment {$chargeId} not confirmed after " . ($intervalSec * $maxAttempts) . " seconds."
    );
}

// Function to initiate PayChangu USSD payment
function initiateUSSDPayment($pdo, $user_id, $amount, $type, $booking_id = null, $temp_data = []) {
    try {
        // Fetch user details (use temp_data for signup if user_id is not yet created)
        if ($type === 'signup' && empty($user_id)) {
            $user = [
                'username' => $temp_data['username'] ?? '',
                'phone' => $temp_data['phone'] ?? '',
                'email' => $temp_data['email'] ?? ''
            ];
        } else {
            $stmt = $pdo->prepare("SELECT username, phone, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }

        if (!$user['phone']) {
            return ['error' => 'Phone number not found for user', 'type' => $type];
        }

        // Validate and format mobile number
        $mobile = preg_replace('/[^0-9]/', '', $user['phone']);
        if (!preg_match('/^0[89][0-9]{8}$/', $mobile)) {
            return ['error' => 'Invalid mobile number format. Must be 10 digits starting with 08 (TNM) or 09 (Airtel) (e.g., 0885620896 or 0999123456).', 'type' => $type];
        }

        // Determine mobile operator and reference ID based on prefix
        $prefix = substr($mobile, 0, 2);
        if ($prefix === '08') {
            $operator = 'TNM';
            $mobile_money_ref_id = PAYCHANGU_TNM_REF_ID;
        } elseif ($prefix === '09') {
            $operator = 'Airtel';
            $mobile_money_ref_id = PAYCHANGU_AIRTEL_REF_ID;
        } else {
            return ['error' => 'Unsupported mobile operator. Use 08 for TNM or 09 for Airtel.', 'type' => $type];
        }

        $charge_id = 'CHG-' . time() . '-' . rand(1000, 9999);
        $payload = [
            'mobile_money_operator_ref_id' => $mobile_money_ref_id,
            'mobile' => $mobile,
            'amount' => $amount,
            'charge_id' => $charge_id,
            'email' => $user['email'],
            'first_name' => $user['username'],
            'operator' => $operator
        ];

        $client = new Client([
            'base_uri' => PAYCHANGU_API_URL,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . PAYCHANGU_SECRET_KEY
            ]
        ]);

        $response = $client->post('/mobile-money/payments/initialize', ['body' => json_encode($payload)]);
        $result = json_decode($response->getBody(), true);

        if ($result['status'] !== 'success') {
            error_log("Payment initiation failed for charge_id $charge_id: " . json_encode($result));
            return ['error' => 'Failed to initiate payment: ' . ($result['message'] ?? 'Unknown error'), 'type' => $type];
        }

        // Store pending payment in database
        $tx_ref = $result['data']['tx_ref'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, booking_id, charge_id, tx_ref, amount, status, type, operator) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)");
        $stmt->execute([$user_id ?: null, $booking_id, $charge_id, $tx_ref, $amount, $type, $operator]);

        try {
            // Enforce 1-minute timeout (60 seconds / 5-second intervals = 12 attempts)
            $successfulData = pollForSuccess($charge_id, $client, 5, 12);
            $tx_ref = $successfulData['tx_ref'] ?? $tx_ref;
            $stmt = $pdo->prepare("UPDATE payments SET status = 'success', tx_ref = ? WHERE charge_id = ?");
            $stmt->execute([$tx_ref, $charge_id]);

            // Insert data into database after payment confirmation
            if ($type === 'signup') {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, phone, password, gender, address, location, kin_name, kin_relationship, kin_phone, national_id, profile_picture, age, status, payment_status, first_name, last_name, occupation)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'paid', ?, ?, ?)
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
                    $temp_data['national_id'],
                    $temp_data['profile_picture'],
                    $temp_data['age'],
                    $temp_data['first_name'],
                    $temp_data['last_name'],
                    $temp_data['occupation']
                ]);
                $user_id = $pdo->lastInsertId();

                $emailBody = "Dear {$user['username']},\n\nYour registration payment of {$amount} Kwacha (Charge ID: {$charge_id}) has been successfully processed.\n\nThank you for joining MIBESA Car Rental!\n\nBest regards,\nMIBESA Team";
                sendEmail($user['email'], "Registration Payment Confirmation", $emailBody);

                // Update payment with user_id
                $stmt = $pdo->prepare("UPDATE payments SET user_id = ? WHERE charge_id = ?");
                $stmt->execute([$user_id, $charge_id]);

                // Clean up temporary files
                @unlink($temp_data['national_id_path'] ?? '');
                @unlink($temp_data['profile_picture_path'] ?? '');
            } elseif ($type === 'booking') {
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, car_id, booking_id, pick_up_date, return_date, total_days, total_cost, status, payment_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'booked', 'paid')
                ");
                $stmt->execute([
                    $user_id,
                    $temp_data['car_id'],
                    $temp_data['booking_id'],
                    $temp_data['pick_up_date'],
                    $temp_data['return_date'],
                    $temp_data['total_days'],
                    $temp_data['total_cost']
                ]);
                $booking_db_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("UPDATE cars SET status = 'booked', booking_count = booking_count + 1 WHERE id = ?");
                $stmt->execute([$temp_data['car_id']]);

                $stmt = $pdo->prepare("SELECT b.booking_id, b.pick_up_date, b.return_date, b.total_cost, c.name AS car_name FROM bookings b LEFT JOIN cars c ON b.car_id = c.id WHERE b.id = ?");
                $stmt->execute([$booking_db_id]);
                $booking = $stmt->fetch();

                $emailBody = "Dear {$user['username']},\n\nYour booking payment of {$amount} Kwacha (Charge ID: {$charge_id}) has been successfully processed.\n\nBooking Details:\n- Car: {$booking['car_name']}\n- Booking ID: {$booking['booking_id']}\n- Pick-up Date: {$booking['pick_up_date']}\n- Return Date: {$booking['return_date']}\n- Total Cost: {$booking['total_cost']} Kwacha\n\nThank you for choosing MIBESA Car Rental!\n\nBest regards,\nMIBESA Team";
                sendEmail($user['email'], "Booking Payment Confirmation", $emailBody);

                $adminStmt = $pdo->query("SELECT email FROM admins");
                while ($admin = $adminStmt->fetch()) {
                    $adminEmailBody = "A new booking has been confirmed:\n\n- User: {$user['username']} ({$user['email']})\n- Car: {$booking['car_name']}\n- Booking ID: {$booking['booking_id']}\n- Dates: {$booking['pick_up_date']} to {$booking['return_date']}\n- Total Cost: {$booking['total_cost']} Kwacha";
                    sendEmail($admin['email'], "New Booking Confirmed", $adminEmailBody);
                }
            }

            // Clear temporary data
            unset($_SESSION['temp_signup_data']);
            unset($_SESSION['temp_booking_data']);

            return ['success' => true, 'message' => 'Payment verified successfully', 'charge_id' => $charge_id, 'user_id' => $user_id];
        } catch (\RuntimeException $e) {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE charge_id = ?");
            $stmt->execute([$charge_id]);
            $error = $e->getMessage();
            return ['error' => $error, 'type' => $type, 'car_id' => $temp_data['car_id'] ?? null, 'car_name' => $temp_data['car_name'] ?? null, 'price_per_day' => $temp_data['price_per_day'] ?? null];
        }
    } catch (RequestException $e) {
        $errorMessage = 'Payment initiation failed';
        if ($e->hasResponse()) {
            $response = json_decode($e->getResponse()->getBody(), true);
            $errorMessage .= ': ' . (is_array($response['message']) ? json_encode($response['message']) : $response['message']);
        } else {
            $errorMessage .= ': ' . $e->getMessage();
        }
        error_log("Payment initiation error for charge_id $charge_id: " . $errorMessage);
        return ['error' => $errorMessage, 'type' => $type];
    } catch (PDOException $e) {
        error_log("Database error in initiateUSSDPayment: " . $e->getMessage());
        return ['error' => 'Database error: Unable to process payment. Please try again later.', 'type' => $type];
    }
}
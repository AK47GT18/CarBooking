<?php
// $user, $pdo are available from auth.php and index.php
?>
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
        <table class="bookings-table" id="bookings        <table class="bookings-table" id="bookingsTable">
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
                <?php endforeach; ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>
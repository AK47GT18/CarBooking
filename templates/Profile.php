<?php
// $user, $pdo are available from auth.php and index.php
?>
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
<?php

require 'config.php';
/*
session_start();

if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
    header("Location: login.php");
    exit();
}

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
?>
*/



// Fetch user bookings
$stmt = $pdo->prepare("SELECT b.booking_id, c.name AS car_name, b.pick_up_date, b.return_date, b.status, b.payment_status FROM bookings b JOIN cars c ON b.car_id = c.id WHERE b.user_id = ?");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch available cars
$stmt2 = $pdo->query("SELECT * FROM cars WHERE status = 'available'");
$cars = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Customer Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f4f6f8; color: #333; }
        header { background: #0b0c10; color: #66fcf1; padding: 1rem 2rem; display: flex; justify-content: flex-end; align-items: center; position: relative; }
        .profile-icon { cursor: pointer; width: 40px; height: 40px; border-radius: 50%; background: #66fcf1; display: flex; justify-content: center; align-items: center; color: #0b0c10; font-weight: bold; font-size: 1.2rem; user-select: none; }
        .profile-dropdown { display: none; position: absolute; top: 60px; right: 2rem; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .profile-dropdown.active { display: block; }
        .profile-dropdown ul { list-style: none; }
        .profile-dropdown ul li { padding: 0.75rem 1.5rem; cursor: pointer; }
        .profile-dropdown ul li:hover { background: #0b0c10; color: #66fcf1; }
        main { padding: 2rem; }
        h2 { margin-bottom: 1rem; color: #0b0c10; }
        .bookings-table { width: 100%; border-collapse: collapse; margin-bottom: 3rem; }
        .bookings-table th, .bookings-table td { padding: 0.75rem; border-bottom: 1px solid #ddd; text-align: left; }
        .status-booked { background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-weight: 500; }
        .status-pending { background-color: #ffc107; color: black; padding: 4px 8px; border-radius: 4px; font-weight: 500; }
        .cars-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .car-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
        .car-card img { width: 100%; height: 200px; object-fit: cover; }
        .car-card .details { padding: 1rem; }
        .car-card h3 { margin-bottom: 0.5rem; color: #0b0c10; }
        .car-card p { font-size: 0.9rem; color: #555; }
        .car-card button { margin: 1rem; padding: 0.5rem 1rem; background: #66fcf1; border: none; border-radius: 8px; cursor: pointer; color: #0b0c10; font-weight: bold; }
        .car-card button:hover { background: #0b0c10; color: #66fcf1; }
    </style>
</head>
<body>
    <header>
        <div class="profile-icon" id="profileIcon"><?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U'; ?></div>
        <div class="profile-dropdown" id="profileDropdown">
            <ul>
                <li><a href="customer_dashboard.php">Dashboard</a></li>
                <li><a href="UserProfile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </header>
    <main>
        <section>
            <h2>My Bookings</h2>
            <?php if (count($bookings) > 0): ?>
            <table class="bookings-table">
                <thead>
                    <tr>
<th>Booking ID</th>
<th>Car</th>
<th>Date</th>
<th>Status</th>
<th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($bookings as $booking): ?>
<tr>
<td>#<?php echo htmlspecialchars($booking['booking_id']); ?></td>
<td><?php echo htmlspecialchars($booking['car_name']); ?></td>
<td><?php echo htmlspecialchars($booking['pick_up_date']); ?></td>
<td>
    <?php if ($booking['status'] === 'booked'): ?>
        <span class="status-booked">Booked</span>
    <?php else: ?>
        <span class="status-pending"><?php echo htmlspecialchars(ucfirst($booking['status'])); ?></span>
    <?php endif; ?>
</td>
<td>
    <?php if ($booking['payment_status'] === 'paid'): ?>
        <span class="status-booked">Paid</span>
    <?php else: ?>
        <span class="status-pending">Unpaid</span>
    <?php endif; ?>
</td>
</tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>You have no bookings yet.</p>
            <?php endif; ?>
        </section>
        <section>
            <h2>Available Cars</h2>
            <div class="cars-grid">
                <?php foreach ($cars as $car): ?>
                <div class="car-card">
<img src="img/<?php echo !empty($car['image']) ? htmlspecialchars($car['image']) : 'placeholder.jpg'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>" />
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
<p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars(isset($car['fuel_type']) ? $car['fuel_type'] : (isset($car['fuel']) ? $car['fuel'] : 'N/A')); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <button onclick="alert('Booking feature coming soon!')">Book Now</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
    <script>
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');
        profileIcon.addEventListener('click', () => {
            profileDropdown.classList.toggle('active');
        });
        window.addEventListener('click', (e) => {
            if (!profileIcon.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>

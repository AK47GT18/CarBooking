<section id="cars">
    <h2>Our Cars</h2>
    <div class="controls">
        <form method="GET" action="index.php" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center;">
            <input type="hidden" name="section" value="cars">
            <input type="text" name="search" placeholder="Search by name or model..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="fuel">
                <option value="">All Fuel Types</option>
                <option value="Petrol" <?php echo $filter_fuel == 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                <option value="Diesel" <?php echo $filter_fuel == 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                <option value="Electric" <?php echo $filter_fuel == 'Electric' ? 'selected' : ''; ?>>Electric</option>
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                <option value="booked" <?php echo $filter_status == 'booked' ? 'selected' : ''; ?>>Booked</option>
            </select>
            <button type="submit">Search</button>
            <button type="button" onclick="window.location.href='index.php?section=cars'">Clear</button>
        </form>
    </div>
    <div class="grid-container">
        <?php if (empty($cars)): ?>
            <p>No cars found.</p>
        <?php else: ?>
            <?php foreach ($cars as $car): ?>
                <div class="car-card">
                    <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                    <div class="details">
                        <h3><?php echo htmlspecialchars($car['name']); ?></h3>
                        <p>Model: <?php echo htmlspecialchars($car['model']); ?> | Fuel: <?php echo htmlspecialchars($car['fuel_type']); ?> | Capacity: <?php echo htmlspecialchars($car['capacity']); ?> Seater</p>
                        <p>Price: <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?> Kwacha/day</p>
                    </div>
                    <?php if ($car['status'] == 'available' && isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info"><?php echo $car['status'] == 'booked' ? 'Booked' : 'Login and get approved to book'; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
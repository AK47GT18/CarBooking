<section id="home">
    <h2>Welcome to MIBESA Car Rental</h2>
    <p>Explore our wide range of vehicles for your travel needs.</p>
    <?php if (!empty($featuredCars)): ?>
        <div class="slider">
            <div class="slides">
                <?php foreach ($featuredCars as $car): ?>
                    <div class="slide">
                        <img src="<?php echo !empty($car['image']) && strpos($car['image'], 'data:image/') === 0 ? htmlspecialchars($car['image']) : 'https://via.placeholder.com/300x200?text=Car'; ?>" alt="<?php echo htmlspecialchars($car['name']); ?>">
                        <h3><?php echo htmlspecialchars($car['name']); ?> (<?php echo htmlspecialchars($car['model']); ?>)</h3>
                        <p>Price: <?php echo number_format($car['price_per_day'] ?? 50); ?> Kwacha/day</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
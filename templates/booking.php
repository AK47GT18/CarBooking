<?php
// $availableCars is available from index.php
?>
<section id="booking" style="display:none;">
    <div class="booking-content">
        <h2>New Booking</h2>
        <?php if (empty($availableCars)): ?>
            <div class="alert">No cars available for booking at the moment. Please check back later.</div>
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
                    <span class="inline-error" id="bookingCarIdError">Please select a car.</span>
                    <label for="carName">Car Name:</label>
                    <input type="text" id="carName" readonly>
                    <label for="pricePerDay">Price per Day (Kwacha):</label>
                    <input type="text" id="pricePerDay" name="price_per_day" readonly>
                    <label for="pick_up_date">Pick-up Date:</label>
                    <input type="date" id="pick_up_date" name="pick_up_date" required aria-describedby="pickUpDateError">
                    <span class="inline-error" id="pickUpDateError">Pick-up date must be today or later.</span>
                    <label for="return_date">Return Date:</label>
                    <input type="date" id="return_date" name="return_date" required aria-describedby="returnDateError">
                    <span class="inline-error" id="returnDateError">Return date must be after pick-up date.</span>
                    <label for="totalCost">Total Cost:</label>
                    <input type="text" id="totalCost" readonly>
                    <button type="submit" name="book">Book Now</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
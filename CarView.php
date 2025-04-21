<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Cars</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: #f4f6f8; color: #333; }
        nav { background: #0b0c10; display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; }
        nav .logo { color: #66fcf1; font-size: 1.5rem; font-weight: bold; }
        nav ul { display: flex; list-style: none; }
        nav ul li { margin-left: 1.5rem; }
        nav ul li a { text-decoration: none; color: #fff; transition: color 0.3s ease; }
        nav ul li a:hover { color: #66fcf1; }

        .slider-container { overflow-x: auto; white-space: nowrap; background: #0b0c10; padding: 1rem 0; }
        .slider-container img { width: 300px; height: 180px; margin: 0 10px; border-radius: 10px; display: inline-block; object-fit: cover; }

        .section-header { padding: 2rem; font-size: 1.6rem; color: #0b0c10; }

        .controls { display: flex; justify-content: center; gap: 1rem; margin: 1rem 0; }
        .controls input[type="text"], .controls button { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; }
        .controls button { background: #0b0c10; color: #fff; border: none; cursor: pointer; }
        .controls button:hover { background: #66fcf1; color: #0b0c10; }

        .grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; padding: 2rem; }
        .car-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; }
        .car-card:hover { transform: translateY(-5px); }
        .car-card img { width: 100%; height: 200px; object-fit: cover; }
        .car-card .details { padding: 1rem; }
        .car-card h3 { margin-bottom: 0.5rem; color: #0b0c10; }
        .car-card p { font-size: 0.9rem; color: #555; }
        .car-card button { margin: 1rem; padding: 0.5rem 1rem; background: #66fcf1; border: none; border-radius: 8px; cursor: pointer; color: #0b0c10; font-weight: bold; }
        .car-card button:hover { background: #0b0c10; color: #66fcf1; }
    </style>
</head>
<body>
<nav>
    <div class="logo">CarRental</div>
    <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">Cars</a></li>
        <li><a href="#">About</a></li>
        <li><a href="#">Contact</a></li>
        <li><a href="#">Profile</a></li>
        <li><a href="#">Login</a></li>
    </ul>
</nav>
<div class="slider-container">
    <img src="car1.jpg" alt="Car 1">
    <img src="car2.jpg" alt="Car 2">
    <img src="car3.jpg" alt="Car 3">
</div>

<h2 class="section-header">Featured Cars</h2>
<div class="controls">
    <input type="text" placeholder="Search cars...">
    <button>Search</button>
    <button>Filter</button>
</div>
<section class="grid-container">
    <div class="car-card">
        <img src="car1.jpg" alt="Toyota Corolla">
        <div class="details">
            <h3>Toyota Corolla</h3>
            <p>Model: 2022 | Fuel: Petrol | Capacity: 5 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
    <div class="car-card">
        <img src="car2.jpg" alt="Honda Civic">
        <div class="details">
            <h3>Honda Civic</h3>
            <p>Model: 2023 | Fuel: Petrol | Capacity: 5 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
    <div class="car-card">
        <img src="car3.jpg" alt="Ford Ranger">
        <div class="details">
            <h3>Ford Ranger</h3>
            <p>Model: 2024 | Fuel: Diesel | Capacity: 4 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
</section>

<h2 class="section-header">All Available Cars</h2>
<section class="grid-container">
    <div class="car-card">
        <img src="car1.jpg" alt="Toyota Corolla">
        <div class="details">
            <h3>Toyota Corolla</h3>
            <p>Model: 2022 | Fuel: Petrol | Capacity: 5 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
    <div class="car-card">
        <img src="car2.jpg" alt="Honda Civic">
        <div class="details">
            <h3>Honda Civic</h3>
            <p>Model: 2023 | Fuel: Petrol | Capacity: 5 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
    <div class="car-card">
        <img src="car3.jpg" alt="Ford Ranger">
        <div class="details">
            <h3>Ford Ranger</h3>
            <p>Model: 2024 | Fuel: Diesel | Capacity: 4 Seater</p>
            <button>Book Now</button>
        </div>
    </div>
</section>

<script>
    document.querySelectorAll('.car-card button').forEach(button => {
        button.addEventListener('click', () => {
            alert('Car booking feature coming soon!');
        });
    });
</script>
</body>
</html>
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
        .slider { flex-grow: 1; position: relative; overflow: hidden; height: 60vh; }
        .slides { height: 100%; width: 400%; display: flex; transition: transform 0.5s ease; }
        .slide { width: 100%; height: 100%; }
        .slide img { width: 100%; height: 100%; object-fit: cover; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); }
        .modal-content { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 600px; max-width: 95%; max-height: 90vh; overflow-y: auto; }
        .close { position: absolute; right: 20px; top: 10px; color: #aaa; font-size: 24px; font-weight: bold; cursor: pointer; }
        form { display: flex; flex-direction: column; }
        form label { margin-bottom: 8px; font-weight: 500; color: #45a29e; }
        form input, form textarea, form select { padding: 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; }
        form input:focus, form select:focus, form textarea:focus { border-color: #66fcf1; outline: none; }
        form button { width: 100%; background: #0b0c10; color: white; border: none; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-size: 1rem; transition: background 0.3s ease; }
        form button:hover { background: #66fcf1; color: #0b0c10; }
        .alert { background: #ffdddd; border-left: 6px solid #f44336; padding: 10px; margin-bottom: 15px; }
        .success { background: #ddffdd; border-left: 6px solid #4caf50; padding: 10px; margin-bottom: 15px; }
        .inline-error { color: #f44336; font-size: 0.8rem; margin-top: -10px; margin-bottom: 10px; display: none; }
        .about-content, .bookings-content, .booking-content { padding: 40px; max-width: 1200px; margin: 0 auto; }
        .about-content h2, .bookings-content h2, .booking-content h2 { color: #0b0c10; margin-bottom: 20px; }
        .about-content h3 { color: #45a29e; margin: 20px 0 10px; }
        .about-content ul, .about-content ol { margin-left: 20px; margin-bottom: 15px; }
        .about-content li { margin-bottom: 8px; }
        footer { 
            margin-top: auto; 
            background: linear-gradient(90deg, #0b0c10, #1f2833); 
            color: white; 
            padding: 2rem; 
            text-align: center; 
            font-size: 0.9rem; 
            box-shadow: 0 -2px 10px rgba(0,0, feasibility:0.3);
        }
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

        /* Cars Section Styles (Aligned with Provided Template) */
        .slider-container { overflow-x: auto; white-space: nowrap; background: #0b0c10; padding: 1rem 0; }
        .slider-container img { width: 300px; height: 180px; margin: 0 10px; border-radius: 10px; display: inline-block; object-fit: cover; }
        .section-header { padding: 2rem; font-size: 1.6rem; color: #0b0c10; text-align: center; }
        .controls { display: flex; justify-content: center; gap: 1rem; margin: 1rem 0; flex-wrap: wrap; }
        .controls input[type="text"], .controls select, .controls button { padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid #ccc; font-size: 1rem; height: 40px; }
        .controls input[type="text"] { flex: 1; min-width: 200px; }
        .controls select { min-width: 150px; }
        .controls button { background: #0b0c10; color: #fff; border: none; cursor: pointer; min-width: 100px; }
        .controls button:hover { background: #66fcf1; color: #0b0c10; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .car-card { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; }
        .car-card:hover { transform: translateY(-5px); }
        .car-card img { width: 100%; height: 200px; object-fit: cover; }
        .car-card .details { padding: 1rem; }
        .car-card h3 { margin-bottom: 0.5rem; color: #0b0c10; font-size: 1.2rem; }
        .car-card p { font-size: 0.9rem; color: #555; margin-bottom: 0.5rem; }
        .car-card button { margin: 1rem; padding: 0.5rem 1rem; background: #66fcf1; border: none; border-radius: 8px; cursor: pointer; color: #0b0c10; font-weight: bold; transition: background 0.3s ease, color 0.3s ease; }
        .car-card button:hover { background: #0b0c10; color: #66fcf1; }
        .car-card button:disabled { background: #ccc; cursor: not-allowed; }
        .car-card .signup-info { margin: 1rem; color: #721c24; font-size: 0.85rem; font-style: italic; text-align: center; }

        /* Signup Modal Styles */
        #signupModal .modal-content {
            background: #ffffff;
            border: 2px solid #0b0c10;
            border-radius: 15px;
            padding: 2.5rem;
            width: 600px;
            max-width: 95%;
        }
        #signupModal h2 {
            color: #0b0c10;
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        #signupModal h3 {
            color: #45a29e;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            border-top: none;
        }
        #signupModal .signup-progress {
            display: flex;
            justify-content: space-between;
            background: #f4f6f8;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        #signupModal .progress-step {
            background: #ffffff;
            color: #0b0c10;
            border: 2px solid #66fcf1;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 0.9rem;
            padding: 8px;
            flex: 1;
            text-align: center;
            margin: 0 5px;
            border-radius: 8px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        #signupModal .progress-step.active {
            background: #0b0c10;
            color: #66fcf1;
            border-color: #0b0c10;
        }
        #signupModal form label {
            color: #45a29e;
            font-size: 0.95rem;
            font-weight: 600;
        }
        #signupModal form input,
        #signupModal form select,
        #signupModal form input[type="file"] {
            background: #f4f6f8;
            border: 1px solid #cccccc;
            border-radius: 6px;
            padding: 12px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease, background 0.3s ease;
        }
        #signupModal form input:focus,
        #signupModal form select:focus,
        #signupModal form input[type="file"]:focus {
            border-color: #66fcf1;
            background: #ffffff;
        }
        #signupModal form input:hover,
        #signupModal form select:hover,
        #signupModal form input[type="file"]:hover {
            border-color: #66fcf1;
        }
        #signupModal form input[type="file"] {
            border-style: dashed;
            border-color: #45a29e;
            background: #f9f9f9;
        }
        #signupModal .inline-error {
            color: #d32f2f;
            font-size: 0.75rem;
            font-style: italic;
            margin-top: -8px;
        }
        #signupModal .step-buttons {
            display: flex;
            gap: 15px;
            justify-content: space-between;
        }
        #signupModal .step-buttons button {
            width: 48%;
        }
        #signupModal .form-links a {
            color: #45a29e;
        }
        #signupModal .form-links a:hover {
            color: #66fcf1;
        }
        #signupModal .profile-picture-preview { 
            margin: 10px 0; 
            max-width: 100px; 
            max-height: 100px; 
            border-radius: 8px; 
            display: none; 
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .controls { flex-direction: column; align-items: center; }
            .controls input[type="text"], .controls select, .controls button { width: 100%; margin-bottom: 0.5rem; }
            #signupModal .modal-content { width: 90%; padding: 1.5rem; }
            #signupModal .signup-progress { flex-direction: column; gap: 10px; }
            #signupModal .progress-step { margin: 0; font-size: 0.8rem; }
            #signupModal h2 { font-size: 1.5rem; }
            #signupModal form label { font-size: 0.9rem; }
            #signupModal form input,
            #signupModal form select,
            #signupModal form input[type="file"] { font-size: 0.9rem; padding: 10px; }
            #signupModal .step-buttons { flex-direction: column; }
            #signupModal .step-buttons button { width: 100%; }
        }

        /* Profile and Bookings Styles */
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
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+1" alt="Car 1"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+2" alt="Car 2"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+3" alt="Car 3"></div>
                <div class="slide"><img src="https://via.placeholder.com/1200x600?text=Car+4" alt="Car 4"></div>
            </div>
        </div>
        <div class="about-content">
            <h2>About Us</h2>
            <p>MIBESA Car Rental is committed to providing quality and affordable car rental services tailored to your needs. Our fleet includes a wide range of vehicles suitable for every occasion.</p>
            <h3>Our Services</h3>
            <ul>
                <li>Peer-to-peer car rental services</li>
                <li>Secure payment processing</li>
                <li>Verified user profiles</li>
                <li>24/7 customer support</li>
                <li>Comprehensive insurance coverage</li>
            </ul>
            <h3>How It Works</h3>
            <p>Getting started is simple:</p>
            <ol>
                <li>Create an account and complete verification</li>
                <li>Browse available vehicles</li>
                <li>Make your booking</li>
                <li>Process secure payment</li>
                <li>Enjoy your ride</li>
            </ol>
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
                    <?php if ($car['status'] == 'available' && isset($_SESSION['user_id']) && $user && $user['status'] == 'approved'): ?>
                        <button onclick="showBookingModal(<?php echo $car['id']; ?>, '<?php echo htmlspecialchars($car['name']); ?>', <?php echo htmlspecialchars($car['price_per_day'] ?? '50'); ?>)">Book Now</button>
                    <?php else: ?>
                        <p class="signup-info"><?php echo $car['status'] == 'booked' ? 'Booked' : 'Login and get THATapproved to book'; ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <h2 class="section-header">All Available Cars</h2>
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
        </div>
    </section>

    <section id="about" style="display:none;">
        <div class="about-content">
            <h2>About MIBESA</h2>
            <p>MIBESA Car Rental is committed to providing quality and affordable car rental services tailored to your needs. Our fleet includes a wide range of vehicles suitable for every occasion.</p>
            <h3>Our Mission</h3>
            <p>To offer reliable, convenient, and affordable car rental services while ensuring the highest standards of customer satisfaction and safety.</p>
            <h3>Why Choose Us?</h3>
            <ul>
                <li>Wide selection of vehicles</li>
                <li>Competitive pricing</li>
                <li>Secure payment processing</li>
                <li>24/7 customer support</li>
                <li>Comprehensive insurance coverage</li>
            </ul>
            <h3>Our Team</h3>
            <p>Our team consists of experienced professionals dedicated to providing the best car rental experience. From customer service to technical support, we are here to assist you every step of the way.</p>
        </div>
    </section>

    <section id="contact" style="display:none;">
        <div class="about-content">
            <h2>Contact Us</h2>
            <form id="contactForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="contact_email">Email:</label>
                <input type="email" id="contact_email" name="email" required aria-describedby="emailError">
                <span class="inline-error" id="emailError">Valid email required.</span>
                <label for="contact_message">Message:</label>
                <textarea id="contact_message" name="message" required aria-describedby="messageError"></textarea>
                <span class="inline-error" id="messageError">Message is required.</span>
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

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="loginModal">×</span>
            <h2>Login</h2>
            <form id="loginForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="loginEmail">Email:</label>
                <input type="email" id="loginEmail" name="loginEmail" required aria-describedby="loginEmailError">
                <span class="inline-error" id="loginEmailError">Valid email required.</span>
                <label for="loginPassword">Password:</label>
                <input type="password" id="loginPassword" name="loginPassword" required aria-describedby="loginPasswordError">
                <span class="inline-error" id="loginPasswordError">Password required.</span>
                <button type="submit" name="login">Login</button>
                <div class="form-links">
                    <a href="#" id="showSignup">Sign Up</a>
                    <span class="separator">|</span>
                    <a href="#" id="showForgot">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="signupModal">×</span>
            <h2>Sign Up</h2>
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
                    <label for="signupUsername">Username:</label>
                    <input type="text" id="signupUsername" name="username" required aria-describedby="signupUsernameError">
                    <span class="inline-error" id="signupUsernameError">Username is required.</span>
                    <label for="signupEmail">Email:</label>
                    <input type="email" id="signupEmail" name="email" required aria-describedby="signupEmailError">
                    <span class="inline-error" id="signupEmailError">Valid email required.</span>
                    <label for="signupPassword">Password:</label>
                    <input type="password" id="signupPassword" name="password" required aria-describedby="signupPasswordError">
                    <span class="inline-error" id="signupPasswordError">Password must be at least 8 characters.</span>
                    <label for="confirmPassword">Confirm Password:</label>
                    <input type="password" id="confirmPassword" name="confirm_password" required aria-describedby="confirmPasswordError">
                    <span class="inline-error" id="confirmPasswordError">Passwords do not match.</span>
                    <label for="signupAge">Age:</label>
                    <input type="number" id="signupAge" name="age" min="18" max="100" required aria-describedby="signupAgeError">
                    <span class="inline-error" id="signupAgeError">Age must be between 18 and 100.</span>
                    <div class="step-buttons">
                        <button type="button" disabled>Previous</button>
                        <button type="button" onclick="nextStep(2)">Next</button>
                    </div>
                </div>
                <!-- Step 2 -->
                <div class="step" id="step2" style="display:none;">
                    <h3>Contact Information</h3>
                    <label for="phone">Phone Number:</label>
                    <input type="text" id="phone" name="phone" required aria-describedby="phoneError">
                    <span class="inline-error" id="phoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
                    <label for="gender">Gender:</label>
                    <select id="gender" name="gender" required aria-describedby="genderError">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                    <span class="inline-error" id="genderError">Please select a gender.</span>
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" required aria-describedby="addressError">
                    <span class="inline-error" id="addressError">Address is required.</span>
                    <label for="location">Location:</label>
                    <select id="location" name="location" required aria-describedby="locationError">
                        <option value="">Select Location</option>
                        <option value="Lilongwe">Lilongwe</option>
                        <option value="Blantyre">Blantyre</option>
                        <option value="Mzuzu">Mzuzu</option>
                        <option value="Zomba">Zomba</option>
                    </select>
                    <span class="inline-error" id="locationError">Please select a location.</span>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(1)">Previous</button>
                        <button type="button" onclick="nextStep(3)">Next</button>
                    </div>
                </div>
                <!-- Step 3 -->
                <div class="step" id="step3" style="display:none;">
                    <h3>Next of Kin</h3>
                    <label for="kin_name">Full Name:</label>
                    <input type="text" id="kin_name" name="kin_name" required aria-describedby="kinNameError">
                    <span class="inline-error" id="kinNameError">Full name is required.</span>
                    <label for="kin_relationship">Relationship:</label>
                    <select id="kin_relationship" name="kin_relationship" required aria-describedby="kinRelationshipError">
                        <option value="">Select Relationship</option>
                        <option value="Parent">Parent</option>
                        <option value="Sibling">Sibling</option>
                        <option value="Spouse">Spouse</option>
                        <option value="Friend">Friend</option>
                        <option value="Other">Other</option>
                    </select>
                    <span class="inline-error" id="kinRelationshipError">Please select a relationship.</span>
                    <label for="kin_phone">Phone Number:</label>
                    <input type="text" id="kin_phone" name="kin_phone" required aria-describedby="kinPhoneError">
                    <span class="inline-error" id="kinPhoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(2)">Previous</button>
                        <button type="button" onclick="nextStep(4)">Next</button>
                    </div>
                </div>
                <!-- Step 4 -->
                <div class="step" id="step4" style="display:none;">
                    <h3>Documents</h3>
                    <label for="national_id">National ID (PDF):</label>
                    <input type="file" id="national_id" name="national_id" accept="application/pdf" required aria-describedby="nationalIdError">
                    <span class="inline-error" id="nationalIdError">A valid PDF document (max 2MB) is required.</span>
                    <label for="profile_picture">Profile Picture (JPEG/PNG):</label>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png" required aria-describedby="profilePictureError">
                    <span class="inline-error" id="profilePictureError">A valid image (JPEG/PNG, max 2MB) is required.</span>
                    <img id="profilePicturePreview" class="profile-picture-preview" alt="Profile Picture Preview">
                    <div class="step-buttons">
                        <button type="button" onclick="prevStep(3)">Previous</button>
                        <button type="submit" name="signup">Sign Up</button>
                    </div>
                </div>
            </form>
            <div class="form-links">
                <a href="#" id="showLogin">Already have an account? Login</a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="forgotModal">×</span>
            <h2>Forgot Password</h2>
            <form id="forgotForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="forgotEmail">Email:</label>
                <input type="email" id="forgotEmail" name="forgotEmail" required aria-describedby="forgotEmailError">
                <span class="inline-error" id="forgotEmailError">Valid email required.</span>
                <button type="submit" name="forgot">Reset Password</button>
                <div class="form-links">
                    <a href="#" id="backToLogin">Back to Login</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="bookingModal">×</span>
            <h2>Book a Car</h2>
            <form id="bookingForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="modalBookingCarId" name="car_id">
                <label for="modalCarName">Car Name:</label>
                <input type="text" id="modalCarName" readonly>
                <label for="modalPricePerDay">Price per Day (Kwacha):</label>
                <input type="text" id="modalPricePerDay" name="price_per_day" readonly>
                <label for="modal_pick_up_date">Pick-up Date:</label>
                <input type="date" id="modal_pick_up_date" name="pick_up_date" required aria-describedby="modalPickUpDateError">
                <span class="inline-error" id="modalPickUpDateError">Pick-up date must be today or later.</span>
                <label for="modal_return_date">Return Date:</label>
                <input type="date" id="modal_return_date" name="return_date" required aria-describedby="modalReturnDateError">
                <span class="inline-error" id="modalReturnDateError">Return date must be after pick-up date.</span>
                <label for="modalTotalCost">Total Cost:</label>
                <input type="text" id="modalTotalCost" readonly>
                <button type="submit" name="book">Book Now</button>
            </form>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <span class="close" data-target="editProfileModal">×</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <label for="edit_username">Username:</label>
                <input type="text" id="edit_username" name="edit_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required aria-describedby="editUsernameError">
                <span class="inline-error" id="editUsernameError">Username is required.</span>
                <label for="edit_email">Email:</label>
                <input type="email" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required aria-describedby="editEmailError">
                <span class="inline-error" id="editEmailError">Valid email required.</span>
                <label for="edit_phone">Phone Number:</label>
                <input type="text" id="edit_phone" name="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required aria-describedby="editPhoneError">
                <span class="inline-error" id="editPhoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
                <label for="edit_gender">Gender:</label>
                <select id="edit_gender" name="edit_gender" required aria-describedby="editGenderError">
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($user['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($user['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <span class="inline-error" id="editGenderError">Please select a gender.</span>
                <label for="edit_address">Address:</label>
                <input type="text" id="edit_address" name="edit_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required aria-describedby="editAddressError">
                <span class="inline-error" id="editAddressError">Address is required.</span>
                <label for="edit_location">Location:</label>
                <select id="edit_location" name="edit_location" required aria-describedby="editLocationError">
                    <option value="">Select Location</option>
                    <option value="Lilongwe" <?php echo ($user['location'] ?? '') == 'Lilongwe' ? 'selected' : ''; ?>>Lilongwe</option>
                    <option value="Blantyre" <?php echo ($user['location'] ?? '') == 'Blantyre' ? 'selected' : ''; ?>>Blantyre</option>
                    <option value="Mzuzu" <?php echo ($user['location'] ?? '') == 'Mzuzu' ? 'selected' : ''; ?>>Mzuzu</option>
                    <option value="Zomba" <?php echo ($user['location'] ?? '') == 'Zomba' ? 'selected' : ''; ?>>Zomba</option>
                </select>
                <span class="inline-error" id="editLocationError">Please select a location.</span>
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
            <a href="#"><i class="fab fa-linkedin-in"></i></a>
        </div>
    </footer>

    <script>
        // Previous JavaScript from the last response should be included here
        // For brevity, I'll highlight the additional script for profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('profilePicturePreview');
            if (file && ['image/jpeg', 'image/png'].includes(file.type) && file.size <= 2 * 1024 * 1024) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
                showError('profilePictureError', 'A valid image (JPEG/PNG, max 2MB) is required.');
            }
        });

        // Ensure the JavaScript from the previous response is included to handle:
        // - Section navigation
        // - Modal management
        // - Form validation
        // - Booking modal calculations
        // - Bookings table filtering
    </script>
</body>
</html>
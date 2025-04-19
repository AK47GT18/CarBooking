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

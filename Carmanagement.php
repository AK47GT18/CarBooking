<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cars - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            display: flex;
            min-height: 100vh;
            background: #f4f6f8;
            color: #333;
        }
        .sidebar {
            background: #0b0c10;
            width: 250px;
            transition: width 0.3s ease;
            overflow: hidden;
            height: 100vh;
            position: fixed;
            left: 0;
        }
        .sidebar.collapsed {
            width: 0;
        }
        .sidebar h2 {
            color: #66fcf1;
            text-align: center;
            margin: 1rem 0;
        }
        .sidebar ul {
            list-style: none;
        }
        .sidebar ul li {
            padding: 15px 20px;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .sidebar ul li:hover {
            background: #45a29e;
        }
        .content {
            margin-left: 250px;
            padding: 2rem;
            padding-top: 1rem;
            flex: 1;
            transition: margin-left 0.3s ease;
        }
        .content.collapsed {
            margin-left: 0;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .hamburger {
            position: fixed;
            top: 20px;
            left: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1000;
            color: white;
        }
        .hamburger.collapsed {
            left: 20px;
            color: #333;
        }
        .overview {
            display: flex;
            justify-content: space-around;
            margin-bottom: 2rem;
        }
        .welcome-text {
            color: #66fcf1;
            text-align: center;
            padding: 0 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .manage-cars {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .manage-cars h3 {
            margin-bottom: 1rem;
        }
        .add-car-btn {
            background: #0b0c10;
            color: #66fcf1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            width: 400px;
        }
        .modal-content input, .modal-content textarea {
            width: 100%;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .close {
            float: right;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .cars-table {
            margin-top: 2rem;
            overflow-x: auto;
        }
        .cars-table table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .cars-table th,
        .cars-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .cars-table th {
            background: #0b0c10;
            color: #66fcf1;
            font-weight: 500;
        }
        .cars-table tr:hover {
            background: #f8f9fa;
        }
        .car-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status.available {
            background: #e3fcef;
            color: #00875a;
        }
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            margin: 0 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .action-btn.edit {
            background: #0b0c10;
            color: #66fcf1;
        }
        .action-btn.delete {
            background: #fee4e2;
            color: #d92d20;
        }
        .action-btn:hover {
            opacity: 0.9;
        }

        /* Search and Filter Style */
        .search-filter-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-input {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: #f5f5f5;
            width: 250px;
        }
        .filter-btn, .search-btn {
            background: #0b0c10;
            color: #66fcf1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .filter-btn:hover, .search-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
<div class="hamburger" id="hamburger" onclick="toggleSidebar()">&#9776;</div>
    <div class="sidebar" id="sidebar">
        <h2>Admin</h2>
        <div class="welcome-text">Welcome, Admin Kingsley!</div>
        <ul>
            <li>Home</li>
            <li>Manage Cars</li>
            <li>Manage Users</li>
            <li>Manage Bookings</li>
            <li>Settings</li>
            <li>Profile</li>
            <li>Logout</li>
        </ul>
    </div>

    <div class="content" id="content">
        <div class="topbar">
            <div class="search-filter-container">
                <input type="text" class="search-input" placeholder="Search Cars..." />
                <button class="search-btn">Search</button>
                <button class="filter-btn">Filter</button>
            </div>
        </div>

    <div class="manage-cars">
        <h3>Manage Cars</h3>
        <button class="add-car-btn" onclick="openModal()">+ Add New Car</button>
        
        <div class="cars-table">
            <table>
                <thead>
                    <tr>
                        <th>Car Image</th>
                        <th>Car Name</th>
                        <th>Model</th>
                        <th>License Plate</th>
                        <th>Capacity</th>
                        <th>Fuel Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><img src="car1.jpg" alt="Car" class="car-thumbnail"></td>
                        <td>Toyota Camry</td>
                        <td>2024</td>
                        <td>ABC-123</td>
                        <td>5</td>
                        <td>Petrol</td>
                        <td><span class="status available">Available</span></td>
                        <td>
                            <button class="action-btn edit">Edit</button>
                            <button class="action-btn delete">Delete</button>
                        </td>
                    </tr>
                    <!-- Add more rows as needed -->
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="modal" id="carModal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Add New Car</h3>
        <input type="text" placeholder="Car Name">
        <input type="text" placeholder="Car Model">
        <textarea placeholder="Car Description"></textarea>
        <input type="text" placeholder="Car License Plate">
        <input type="file" accept="image/*">
        <input type="number" placeholder="Capacity">
        <input type="text" placeholder="Fuel Type">
        <button class="add-car-btn">Save Car</button>
    </div>
</div>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('content').classList.toggle('collapsed');
    }
    function openModal() {
        document.getElementById('carModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('carModal').style.display = 'none';
    }
</script>
</body>
</html>

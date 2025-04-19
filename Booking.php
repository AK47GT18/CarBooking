<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Car Booking System</title>
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
            padding-top: 4rem; /* Added extra top padding to clear the hamburger */
            flex: 1;
            transition: margin-left 0.3s ease;
        }
        .content.collapsed {
            margin-left: 0;
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
        .welcome-text {
            color: #66fcf1;
            text-align: center;
            padding: 0 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .controls {
            display: flex;
            justify-content: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .controls input[type="text"], .controls button {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        .controls button {
            background: #0b0c10;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .controls button:hover {
            background: #66fcf1;
            color: #0b0c10;
        }
        .recent-bookings {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .recent-bookings table {
            width: 100%;
            border-collapse: collapse;
        }
        .recent-bookings th, .recent-bookings td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .recent-bookings th {
            background: #0b0c10;
            color: white;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .status-booked {
            background-color: #28a745;
            color: white;
        }
        .status-available {
            background-color: #ffc107;
            color: black;
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
        <h1>Booking Management</h1>
        <div class="controls">
            <input type="text" placeholder="Search bookings...">
            <button>Search</button>
            <button>Filter</button>
        </div>

        <div class="recent-bookings">
            <h2>All Bookings</h2>
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Car</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>#001</td>
                        <td>John Doe</td>
                        <td>Toyota Corolla</td>
                        <td>2025-04-17</td>
                        <td><span class="status status-booked">Booked</span></td>
                    </tr>
                    <tr>
                        <td>#002</td>
                        <td>Jane Smith</td>
                        <td>Honda Civic</td>
                        <td>2025-04-18</td>
                        <td><span class="status status-available">Available</span></td>
                    </tr>
                    <tr>
                        <td>#003</td>
                        <td>Mike Johnson</td>
                        <td>Ford Ranger</td>
                        <td>2025-04-19</td>
                        <td><span class="status status-booked">Booked</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('collapsed');
        }
    </script>
</body>
</html>

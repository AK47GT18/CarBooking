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
        .sidebar .welcome-text {
            color: #fff;
            text-align: center;
            margin-bottom: 1rem;
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
            margin-top: 3rem;
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
        .manage-cars {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .manage-cars h3 {
            margin-bottom: 1rem;
        }
        .search-filter-btns {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            max-width: 400px;
        }
        .search-btn, .filter-btn, .search-input {
            background: #0b0c10;
            color: #66fcf1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .search-input {
            padding: 0.5rem;
            margin-right:0.6rem;
            width: 250px;
            background-color: white;
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
            font-size: 0.9rem;
        }

        .cars-table th,
        .cars-table td {
            padding: 1rem;
            text-align: center;
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

        .action-btn-container {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
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
    </style>
</head>
<body>
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
    <div class="hamburger" id="hamburger" onclick="toggleSidebar()">&#9776;</div>

    <div class="topbar">
        <div class="search-filter-btns">
            <input type="text" class="search-input" placeholder="Search users...">
            <button class="filter-btn">Filter</button>
        </div>
    </div>

    <div class="manage-cars">
        <h3>Manage Users</h3>
        
        <div class="cars-table">
            <table>
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Home Address</th>
                        <th>Location in Mzuzu</th>
                        <th>Kin Name</th>
                        <th>Kin Relationship</th>
                        <th>Kin Phone Number</th>
                        <th>Nation ID Doc</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>John Doe</td>
                        <td>johndoe@example.com</td>
                        <td>Male</td>
                        <td>123 Main St, Mzuzu</td>
                        <td>Area 1, Mzuzu</td>
                        <td>Jane Doe</td>
                        <td>Sister</td>
                        <td>+265 999 123 456</td>
                        <td>ID123456</td>
                        <td class="action-btn-container">
                            <button class="action-btn edit">Approve</button>
                            <button class="action-btn delete">Decline</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Mary Smith</td>
                        <td>marysmith@example.com</td>
                        <td>Female</td>
                        <td>456 Oak St, Mzuzu</td>
                        <td>Area 2, Mzuzu</td>
                        <td>John Smith</td>
                        <td>Brother</td>
                        <td>+265 888 987 654</td>
                        <td>ID987654</td>
                        <td class="action-btn-container">
                            <button class="action-btn edit">Approve</button>
                            <button class="action-btn delete">Decline</button>
                        </td>
                    </tr>
                    <tr>
                        <td>Paul Johnson</td>
                        <td>pauljohnson@example.com</td>
                        <td>Male</td>
                        <td>789 Pine St, Mzuzu</td>
                        <td>Area 3, Mzuzu</td>
                        <td>Lucy Johnson</td>
                        <td>Mother</td>
                        <td>+265 777 654 321</td>
                        <td>ID654321</td>
                        <td class="action-btn-container">
                            <button class="action-btn edit">Approve</button>
                            <button class="action-btn delete">Decline</button>
                        </td>
                    </tr>
                    <!-- Add more rows as needed -->
                </tbody>
            </table>
        </div>
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

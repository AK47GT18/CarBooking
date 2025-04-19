<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Profile</title>
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
        .profile {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        .profile h3 {
            margin-bottom: 1rem;
        }
        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .profile-info label {
            font-weight: 500;
        }
        .profile-info input {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: #f5f5f5;
            width: 100%;
        }
        .profile-info button {
            background: #0b0c10;
            color: #66fcf1;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 1rem;
        }
        .profile-info button:hover {
            opacity: 0.9;
        }
        .profile-image-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 1rem;
        }
        .upload-btn {
            background: #0b0c10;
            color: #66fcf1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .upload-btn:hover {
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
            <h1>Profile</h1>
        </div>
        
        <div class="profile">
            <h3>Profile Information</h3>
            <div class="profile-image-container">
                <img src="profile.jpg" alt="Profile Image" class="profile-image">
                <input type="file" accept="image/*" class="upload-btn" />
            </div>
            
            <div class="profile-info">
                <label for="name">Name</label>
                <input type="text" id="name" value="Kingsley Kanjira" disabled>
                
                <label for="email">Email</label>
                <input type="email" id="email" value="admin@domain.com" disabled>
                
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" value="+265 999 888 777">
                
                <label for="address">Address</label>
                <input type="text" id="address" value="Mzuzu, Malawi">
                
                <button onclick="saveProfile()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('collapsed');
        }

        function saveProfile() {
            alert('Profile changes saved!');
        }
    </script>
</body>
</html>

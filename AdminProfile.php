<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Car Booking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Body */
        body {
            display: flex;
            min-height: 100vh;
            background-color: #f4f7fc;
            color: #333;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: #0b0c10;
            color: #ffffff;
            padding: 20px 0;
            position: fixed;
            height: 100%;
            transition: width 0.3s ease;
            z-index: 100;
        }

        .sidebar.collapsed {
            width: 0;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.5rem;
            color: #66fcf1;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            padding: 15px 20px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar ul li:hover {
            background-color: #45a29e;
        }

        .sidebar ul li i {
            width: 24px;
        }

        /* Main Content Area */
        .content {
            margin-left: 250px;
            padding: 80px 20px 20px;
            width: calc(100% - 250px);
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .content.collapsed {
            margin-left: 0;
            width: 100%;
        }

        /* Topbar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            background-color: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 60px;
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            transition: left 0.3s ease;
        }

        .topbar.collapsed {
            left: 0;
        }

        .hamburger {
            font-size: 1.5rem;
            cursor: pointer;
            color: #333;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
        }

        .notification-bell .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 15px;
            height: 15px;
            font-size: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .user-profile-small {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-profile-small img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .dashboard-header h1 {
            font-size: 2rem;
            color: #2c3e50;
        }

        /* User Profile Section */
        .user-profile {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .profile-image-container {
            text-align: center;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .upload-btn {
            background: #0b0c10;
            color: #66fcf1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .upload-btn:hover {
            opacity: 0.9;
        }

        .user-profile .details {
            flex: 1;
        }

        .user-profile .details h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .profile-info label {
            font-weight: 500;
            color: #34495e;
        }

        .profile-info input {
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: #f5f5f5;
            width: 100%;
            font-size: 1rem;
        }

        .profile-info input:disabled {
            background-color: #e9ecef;
            cursor: not-allowed;
        }

        .profile-info button {
            background: #0b0c10;
            color: #66fcf1;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 10px;
            align-self: flex-start;
        }

        .profile-info button:hover {
            opacity: 0.9;
        }

        /* Activity Section */
        .user-activity {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 60px;
        }

        .user-activity h2 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .user-activity ul {
            list-style: none;
        }

        .user-activity ul li {
            font-size: 1rem;
            color: #7f8c8d;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border-left: 3px solid #45a29e;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 15px;
            background-color: #0b0c10;
            color: #ecf0f1;
            position: fixed;
            width: 100%;
            bottom: 0;
            left: 0;
            z-index: 98;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h2>Car Booking</h2>
        <ul>
            <li onclick="navigate('index.html')"><i class="fas fa-home"></i> Home</li>
            <li onclick="navigate('manage-cars.html')"><i class="fas fa-car"></i> Manage Cars</li>
            <li onclick="navigate('manage-users.html')"><i class="fas fa-users"></i> Manage Users</li>
            <li onclick="navigate('manage-bookings.html')"><i class="fas fa-calendar-check"></i> Manage Bookings</li>
            <li onclick="navigate('profile.html')"><i class="fas fa-user"></i> Profile</li>
            <li onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</li>
        </ul>
    </div>

    <div class="topbar" id="topbar">
        <div class="hamburger" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </div>
        <div class="user-actions">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <span class="badge">3</span>
            </div>
            <div class="user-profile-small" onclick="navigate('profile.html')">
                <img src="/api/placeholder/80/80" alt="Admin Profile">
                <span>Admin Kingsley</span>
            </div>
        </div>
    </div>

    <div class="content" id="content">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
        </div>

        <div class="user-profile">
            <div class="profile-image-container">
                <img src="/api/placeholder/150/150" alt="Admin Profile Picture" class="profile-image">
                <input type="file" id="profile-image-upload" hidden>
                <label for="profile-image-upload" class="upload-btn">Change Image</label>
            </div>
            <div class="details">
                <h3>Profile Information</h3>
                <div class="profile-info">
                    <label for="name">Name</label>
                    <input type="text" id="name" value="Admin Kingsley" disabled>
                    
                    <label for="email">Email</label>
                    <input type="email" id="email" value="admin.kingsley@example.com" disabled>
                    
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" value="+265 999 888 777">
                    
                    <label for="address">Address</label>
                    <input type="text" id="address" value="Mzuzu, Malawi">
                    
                    <button onclick="saveProfile()">Save Changes</button>
                </div>
            </div>
        </div>

        <div class="user-activity">
            <h2>Recent Activity</h2>
            <ul>
                <li>Approved new car booking request for Toyota Corolla.</li>
                <li>Updated user profile for Mary Smith.</li>
                <li>Reviewed booking history for Paul Johnson.</li>
                <li>Added new car to the fleet: Nissan X-Trail.</li>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>&copy; 2025 Car Booking System. All rights reserved.</p>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
            document.getElementById('content').classList.toggle('collapsed');
            document.getElementById('topbar').classList.toggle('collapsed');
        }

        function saveProfile() {
            alert('Profile changes saved successfully!');
        }

        function navigate(page) {
            // In a real application, this would navigate to the specified page
            console.log('Navigating to ' + page);
            if (page === 'index.html') {
                alert('Navigating to Dashboard');
            } else if (page === 'profile.html') {
                alert('Already on Profile page');
            } else {
                alert('Navigating to ' + page);
            }
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                alert('Logged out successfully');
                // In a real application, this would redirect to the login page
            }
        }
    </script>
</body>
</html>
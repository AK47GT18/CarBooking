<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile & Complaints</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: #f4f6f8;
            color: #333;
        }
        nav {
            background: #0b0c10;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }
        nav .logo {
            color: #66fcf1;
            font-size: 1.5rem;
            font-weight: bold;
        }
        nav ul {
            display: flex;
            list-style: none;
        }
        nav ul li {
            margin-left: 1.5rem;
        }
        nav ul li a {
            text-decoration: none;
            color: #fff;
            transition: color 0.3s ease;
        }
        nav ul li a:hover {
            color: #66fcf1;
        }
        .profile-container, .complaints-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        h2 {
            color: #0b0c10;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #66fcf1;
            padding-bottom: 0.5rem;
        }
        .filter-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .filter-section input[type="text"] {
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
        }
        .filter-section button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            background: #66fcf1;
            color: #0b0c10;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .filter-section button:hover {
            background: #45a29e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        table th, table td {
            padding: 0.8rem;
            border: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background: #66fcf1;
            color: #0b0c10;
        }
        .complaints-container form {
            display: flex;
            flex-direction: column;
        }
        .complaints-container label {
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #0b0c10;
        }
        .complaints-container textarea {
            padding: 0.8rem;
            margin-bottom: 1.5rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.3s ease;
        }
        .complaints-container textarea:focus {
            border-color: #66fcf1;
        }
        .complaints-container button {
            padding: 0.8rem;
            background: #66fcf1;
            border: none;
            color: #0b0c10;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .complaints-container button:hover {
            background: #45a29e;
        }
    </style>
</head>
<body>
<nav>
    <div class="logo">CarRental</div>
    <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">About</a></li>
        <li><a href="#">Logout</a></li>
    </ul>
</nav>
<section class="profile-container">
    <h2>User Profile - Booking History</h2>
    <div class="filter-section">
        <input type="text" placeholder="Search by Car Name or Status...">
        <button>Filter</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>Car Name</th>
                <th>Model</th>
                <th>Rental Date</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Toyota Corolla</td>
                <td>2022</td>
                <td>2025-04-01</td>
                <td>2025-04-05</td>
                <td>Completed</td>
            </tr>
            <tr>
                <td>Honda Civic</td>
                <td>2023</td>
                <td>2025-04-10</td>
                <td>2025-04-15</td>
                <td>Booked</td>
            </tr>
        </tbody>
    </table>
</section>
<section class="complaints-container">
    <h2>Submit a Complaint</h2>
    <form>
        <label for="complaint">Your Complaint</label>
        <textarea id="complaint" name="complaint" rows="5" required></textarea>
        <button type="submit">Submit Complaint</button>
    </form>
</section>
</body>
</html>

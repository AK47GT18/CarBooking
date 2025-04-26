<?php
// Ensure $user is available from auth.php
?>
<nav>
    <div class="logo">MIBESA</div>
    <ul>
        <li><a href="?section=home" onclick="showSection('home'); return false;">Home</a></li>
        <li><a href="?section=cars" onclick="showSection('cars'); return false;">Cars</a></li>
        <li><a href="?section=about" onclick="showSection('about'); return false;">About</a></li>
        <li><a href="?section=contact" onclick="showSection('contact'); return false;">Contact</a></li>
        <?php if (isset($_SESSION['user_id']) && $user): ?>
            <li><a href="?section=bookings" onclick="showSection('bookings'); return false;">Bookings</a></li>
            <li>
                <a href="?section=profile" onclick="showSection('profile'); return false;" class="profile-container">
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
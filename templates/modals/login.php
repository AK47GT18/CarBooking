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
            <span class="inline-error" id="loginPasswordError">Password is required.</span>
            <button type="submit" name="login">Login</button>
        </form>
        <div class="form-links">
            <a href="#" id="showSignup">Don't have an account? Sign Up</a>
        </div>
    </div>
</div>
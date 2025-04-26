<?php
// $user is available from auth.php
?>
<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <span class="close" data-target="editProfileModal">×</span>
        <h2>Edit Profile</h2>
        <form id="editProfileForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="edit_username">Username:</label>
            <input type="text" id="edit_username" name="edit_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required aria-describedby="editUsernameError" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+">
            <span class="inline-error" id="editUsernameError">Username must be 3-50 characters, using letters, numbers, or underscores.</span>
            <label for="edit_email">Email:</label>
            <input type="email" id="edit_email" name="edit_email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required aria-describedby="editEmailError">
            <span class="inline-error" id="editEmailError">Valid email required.</span>
            <label for="edit_phone">Phone:</label>
            <input type="text" id="edit_phone" name="edit_phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required aria-describedby="editPhoneError" pattern="0[0-9]{9}">
            <span class="inline-error" id="editPhoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
            <label for="edit_gender">Gender:</label>
            <select id="edit_gender" name="edit_gender" required aria-describedby="editGenderError">
                <option value="Male" <?php echo ($user['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($user['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($user['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
            </select>
            <span class="inline-error" id="editGenderError">Please select a gender.</span>
            <label for="edit_age">Age:</label>
            <input type="number" id="edit_age" name="edit_age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" min="18" max="100" required aria-describedby="editAgeError">
            <span class="inline-error" id="editAgeError">Age must be between 18 and 100.</span>
            <label for="edit_address">Address:</label>
            <input type="text" id="edit_address" name="edit_address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required aria-describedby="editAddressError">
            <span class="inline-error" id="editAddressError">Address is required.</span>
            <label for="edit_location">Location:</label>
            <select id="edit_location" name="edit_location" required aria-describedby="editLocationError">
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
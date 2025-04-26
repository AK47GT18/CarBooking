<div id="signupModal" class="modal">
    <div class="modal-content">
        <span class="close" data-target="signupModal">×</span>
        <h2>Sign Up</h2>
        <div class="signup-progress">
            <div class="progress-step active" id="progress1">1. Personal Info</div>
            <div class="progress-step" id="progress2">2. Contact Info</div>
            <div class="progress-step" id="progress3">3. Next of Kin</div>
            <div class="progress-step" id="progress4">4. Documents</div>
        </div>
        <form id="signupForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <!-- Step 1 -->
            <div class="step" id="step1">
                <h3>Personal Information</h3>
                <label for="signupUsername">Username:</label>
                <input type="text" id="signupUsername" name="username" required aria-describedby="signupUsernameError" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" title="Username can only contain letters, numbers, and underscores">
                <span class="inline-error" id="signupUsernameError">Username must be 3-50 characters, using letters, numbers, or underscores.</span>
                <label for="signupEmail">Email:</label>
                <input type="email" id="signupEmail" name="email" required aria-describedby="signupEmailError">
                <span class="inline-error" id="signupEmailError">Valid email required.</span>
                <label for="signupPassword">Password:</label>
                <input type="password" id="signupPassword" name="password" required aria-describedby="signupPasswordError" minlength="8">
                <span class="inline-error" id="signupPasswordError">Password must be at least 8 characters.</span>
                <label for="confirmPassword">Confirm Password:</label>
                <input type="password" id="confirmPassword" name="confirm_password" required aria-describedby="confirmPasswordError">
                <span class="inline-error" id="confirmPasswordError">Passwords do not match.</span>
                <label for="signupAge">Age:</label>
                <input type="number" id="signupAge" name="age" min="18" max="100" required aria-describedby="signupAgeError">
                <span class="inline-error" id="signupAgeError">Age must be between 18 and 100.</span>
                <div class="step-buttons">
                    <button type="button" disabled>Previous</button>
                    <button type="button" onclick="nextStep(2)">Next</button>
                </div>
            </div>
            <!-- Step 2 -->
            <div class="step" id="step2" style="display:none;">
                <h3>Contact Information</h3>
                <label for="phone">Phone Number:</label>
                <input type="text" id="phone" name="phone" required aria-describedby="phoneError" pattern="0[0-9]{9}" title="Phone number must be 10 digits starting with 0 (e.g., 0885620896)">
                <span class="inline-error" id="phoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
                <label for="gender">Gender:</label>
                <select id="gender" name="gender" required aria-describedby="genderError">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
                <span class="inline-error" id="genderError">Please select a gender.</span>
                <label for="address">Address:</label>
                <input type="text" id="address" name="address" required aria-describedby="addressError" maxlength="255">
                <span class="inline-error" id="addressError">Address is required.</span>
                <label for="location">Location:</label>
                <select id="location" name="location" required aria-describedby="locationError">
                    <option value="">Select Location</option>
                    <option value="Lilongwe">Lilongwe</option>
                    <option value="Blantyre">Blantyre</option>
                    <option value="Mzuzu">Mzuzu</option>
                    <option value="Zomba">Zomba</option>
                </select>
                <span class="inline-error" id="locationError">Please select a location.</span>
                <div class="step-buttons">
                    <button type="button" onclick="prevStep(1)">Previous</button>
                    <button type="button" onclick="nextStep(3)">Next</button>
                </div>
            </div>
            <!-- Step 3 -->
            <div class="step" id="step3" style="display:none;">
                <h3>Next of Kin</h3>
                <label for="kin_name">Full Name:</label>
                <input type="text" id="kin_name" name="kin_name" required aria-describedby="kinNameError" maxlength="100">
                <span class="inline-error" id="kinNameError">Full name is required.</span>
                <label for="kin_relationship">Relationship:</label>
                <select id="kin_relationship" name="kin_relationship" required aria-describedby="kinRelationshipError">
                    <option value="">Select Relationship</option>
                    <option value="Parent">Parent</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Spouse">Spouse</option>
                    <option value="Friend">Friend</option>
                    <option value="Other">Other</option>
                </select>
                <span class="inline-error" id="kinRelationshipError">Please select a relationship.</span>
                <label for="kin_phone">Phone Number:</label>
                <input type="text" id="kin_phone" name="kin_phone" required aria-describedby="kinPhoneError" pattern="0[0-9]{9}" title="Phone number must be 10 digits starting with 0 (e.g., 0885620896)">
                <span class="inline-error" id="kinPhoneError">Phone number must be 10 digits starting with 0 (e.g., 0885620896).</span>
                <div class="step-buttons">
                    <button type="button" onclick="prevStep(2)">Previous</button>
                    <button type="button" onclick="nextStep(4)">Next</button>
                </div>
            </div>
            <!-- Step 4 -->
            <div class="step" id="step4" style="display:none;">
                <h3>Documents</h3>
                <label for="national_id">National ID (PDF):</label>
                <input type="file" id="national_id" name="national_id" accept="application/pdf" required aria-describedby="nationalIdError">
                <span class="inline-error" id="nationalIdError">A valid PDF document (max 2MB) is required.</span>
                <label for="profile_picture">Profile Picture (JPEG/PNG):</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png" required aria-describedby="profilePictureError">
                <span class="inline-error" id="profilePictureError">A valid image (JPEG/PNG, max 2MB) is required.</span>
                <img id="profilePicturePreview" class="profile-picture-preview" alt="Profile Picture Preview">
                <div class="step-buttons">
                    <button type="button" onclick="prevStep(3)">Previous</button>
                    <button type="submit" name="signup">Sign Up</button>
                </div>
            </div>
        </form>
        <div class="form-links">
            <a href="#" id="showLogin">Already have an account? Login</a>
        </div>
    </div>
</div>
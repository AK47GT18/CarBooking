<section id="contact" style="display:none;">
    <div class="about-content">
        <h2>Contact Us</h2>
        <form id="contactForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="contact_email">Email:</label>
            <input type="email" id="contact_email" name="email" required aria-describedby="emailError">
            <span class="inline-error" id="emailError">Valid email required.</span>
            <label for="contact_message">Message:</label>
            <textarea id="contact_message" name="message" required aria-describedby="messageError"></textarea>
            <span class="inline-error" id="messageError">Message is required.</span>
            <button type="submit" name="contact">Send Message</button>
        </form>
    </div>
</section>
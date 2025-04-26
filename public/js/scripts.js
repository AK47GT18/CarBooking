document.addEventListener('DOMContentLoaded', () => {
    // Section Navigation
    function showSection(sectionId) {
        document.querySelectorAll('section').forEach(section => {
            section.style.display = 'none';
        });
        document.getElementById(sectionId).style.display = 'block';
        window.history.pushState({}, '', `?section=${sectionId}`);
    }

    // Modal Handling
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'block';
    }

    function hideModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modals
    document.querySelectorAll('.close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            hideModal(closeBtn.dataset.target);
        });
    });

    // Login/Signup Modal Links
    document.getElementById('loginBtn')?.addEventListener('click', () => showModal('loginModal'));
    document.getElementById('showSignup')?.addEventListener('click', (e) => {
        e.preventDefault();
        hideModal('loginModal');
        showModal('signupModal');
    });
    document.getElementById('showLogin')?.addEventListener('click', (e) => {
        e.preventDefault();
        hideModal('signupModal');
        showModal('loginModal');
    });

    // Edit Profile Modal
    window.showEditProfileModal = function() {
        showModal('editProfileModal');
    };

    // Signup Form Steps
    let currentStep = 1;
    window.nextStep = function(step) {
        document.getElementById(`step${currentStep}`).style.display = 'none';
        document.getElementById(`step${step}`).style.display = 'block';
        document.getElementById(`progress${currentStep}`).classList.remove('active');
        document.getElementById(`progress${step}`).classList.add('active');
        currentStep = step;
    };

    window.prevStep = function(step) {
        document.getElementById(`step${currentStep}`).style.display = 'none';
        document.getElementById(`step${step}`).style.display = 'block';
        document.getElementById(`progress${currentStep}`).classList.remove('active');
        document.getElementById(`progress${step}`).classList.add('active');
        currentStep = step;
    };

    // Profile Picture Preview
    const profilePictureInput = document.getElementById('profile_picture');
    const profilePicturePreview = document.getElementById('profilePicturePreview');
    if (profilePictureInput && profilePicturePreview) {
        profilePictureInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && ['image/jpeg', 'image/png'].includes(file.type)) {
                const reader = new FileReader();
                reader.onload = () => {
                    profilePicturePreview.src = reader.result;
                    profilePicturePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                profilePicturePreview.style.display = 'none';
            }
        });
    }

    // Slider for Featured Cars
    const slides = document.querySelector('.slides');
    if (slides) {
        let currentSlide = 0;
        const totalSlides = slides.children.length;
        setInterval(() => {
            currentSlide = (currentSlide + 1) % totalSlides;
            slides.style.transform = `translateX(-${currentSlide * 100}%)`;
        }, 5000);
    }

    // Booking Modal
    window.showBookingModal = function(carId, carName, pricePerDay) {
        showSection('booking');
        const carSelect = document.getElementById('bookingCarId');
        if (carSelect) {
            carSelect.value = carId;
            document.getElementById('carName').value = carName;
            document.getElementById('pricePerDay').value = pricePerDay;
            updateTotalCost();
        }
    };

    // Update Price Per Day in Booking Form
    window.updatePricePerDay = function() {
        const carSelect = document.getElementById('bookingCarId');
        const selectedOption = carSelect.options[carSelect.selectedIndex];
        document.getElementById('carName').value = selectedOption.dataset.name || '';
        document.getElementById('pricePerDay').value = selectedOption.dataset.price || '';
        updateTotalCost();
    };

    // Calculate Total Cost
    function updateTotalCost() {
        const pricePerDay = parseFloat(document.getElementById('pricePerDay').value) || 0;
        const pickUpDate = new Date(document.getElementById('pick_up_date').value);
        const returnDate = new Date(document.getElementById('return_date').value);
        if (pickUpDate && returnDate && returnDate > pickUpDate) {
            const days = (returnDate - pickUpDate) / (1000 * 60 * 60 * 24);
            const totalCost = days * pricePerDay;
            document.getElementById('totalCost').value = totalCost.toFixed(2) + ' Kwacha';
        } else {
            document.getElementById('totalCost').value = '';
        }
    }

    // Date Validation
    const pickUpDateInput = document.getElementById('pick_up_date');
    const returnDateInput = document.getElementById('return_date');
    if (pickUpDateInput && returnDateInput) {
        pickUpDateInput.addEventListener('change', () => {
            pickUpDateInput.min = currentDate;
            updateTotalCost();
        });
        returnDateInput.addEventListener('change', updateTotalCost);
    }

    // Form Validation
    function validateForm(formId) {
        const form = document.getElementById(formId);
        let isValid = true;
        form.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
            const errorElement = document.getElementById(`${input.id}Error`);
            if (!input.value.trim() || (input.type === 'email' && !/^\S+@\S+\.\S+$/.test(input.value))) {
                errorElement.style.display = 'block';
                isValid = false;
            } else {
                errorElement.style.display = 'none';
            }
        });
        return isValid;
    }

    // Handle Form Submissions
    ['loginForm', 'signupForm', 'editProfileForm', 'contactForm', 'newBookingForm'].forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', (e) => {
                if (!validateForm(formId)) {
                    e.preventDefault();
                }
            });
        }
    });

    // Booking Filter
    window.filterBookings = function() {
        const search = document.getElementById('bookingSearch').value.toLowerCase();
        const status = document.getElementById('bookingStatusFilter').value;
        const rows = document.querySelectorAll('#bookingsTable tbody tr');
        rows.forEach(row => {
            const carName = row.cells[0].textContent.toLowerCase();
            const bookingId = row.cells[1].textContent.toLowerCase();
            const rowStatus = row.cells[4].textContent.toLowerCase();
            const matchesSearch = carName.includes(search) || bookingId.includes(search);
            const matchesStatus = !status || rowStatus === status.toLowerCase();
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    };

    window.resetBookingFilter = function() {
        document.getElementById('bookingSearch').value = '';
        document.getElementById('bookingStatusFilter').value = '';
        filterBookings();
    };

    // Profile Booking Filter
    window.filterProfileBookings = function() {
        const search = document.getElementById('profileBookingSearch').value.toLowerCase();
        const status = document.getElementById('profileBookingStatusFilter').value;
        const rows = document.querySelectorAll('#profileBookingsTable tbody tr');
        rows.forEach(row => {
            const carName = row.cells[0].textContent.toLowerCase();
            const rowStatus = row.cells[3].textContent.toLowerCase();
            const matchesSearch = carName.includes(search);
            const matchesStatus = !status || rowStatus === status.toLowerCase();
            row.style.display = matchesSearch && matchesStatus ? '' : 'none';
        });
    };

    window.resetProfileBookingFilter = function() {
        document.getElementById('profileBookingSearch').value = '';
        document.getElementById('profileBookingStatusFilter').value = '';
        filterProfileBookings();
    };

    // Handle Payment Errors
    if (paymentError && paymentType) {
        alert(`Payment Error: ${paymentError}`);
        if (paymentType === 'signup' && tempSignupData) {
            showModal('signupModal');
            // Populate signup form with temp data
            document.getElementById('signupUsername').value = tempSignupData.username || '';
            document.getElementById('signupEmail').value = tempSignupData.email || '';
            document.getElementById('phone').value = tempSignupData.phone || '';
            // ... populate other fields as needed
        } else if (paymentType === 'booking' && tempBookingData) {
            showSection('booking');
            document.getElementById('bookingCarId').value = tempBookingData.car_id || '';
            document.getElementById('carName').value = tempBookingData.car_name || '';
            document.getElementById('pricePerDay').value = tempBookingData.price_per_day || '';
            document.getElementById('pick_up_date').value = tempBookingData.pick_up_date || '';
            document.getElementById('return_date').value = tempBookingData.return_date || '';
            updateTotalCost();
        }
    }

    // Initialize Section
    const urlParams = new URLSearchParams(window.location.search);
    const section = urlParams.get('section') || 'home';
    showSection(section);
});
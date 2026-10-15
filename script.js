document.addEventListener('DOMContentLoaded', function () {

    var params = new URLSearchParams(window.location.search);
    var status = params.get('status');
    if (status === 'success' || status === 'error') {
        var alert = document.createElement('div');
        if (status === 'success') {
            alert.className = 'alert alert-success';
            alert.textContent = document.querySelector('.booking-form')
                ? 'Booking received! We will contact you within 24 hours to confirm your tutor.'
                : 'Message sent! We will get back to you as soon as possible.';
        } else {
            alert.className = 'alert alert-error';
            alert.textContent = 'Something went wrong. Please check all required fields and try again.';
        }
        var main = document.querySelector('main');
        if (main) {
            main.insertBefore(alert, main.firstChild);
        }

        if (window.history.replaceState) {
            window.history.replaceState(null, '', window.location.pathname + window.location.hash);
        }
    }

    var bookingForm = document.querySelector('.booking-form');
    if (bookingForm) {

        var dateInput = document.getElementById('preferred-date');
        if (dateInput) {
            var today = new Date();
            var yyyy = today.getFullYear();
            var mm = String(today.getMonth() + 1).padStart(2, '0');
            var dd = String(today.getDate()).padStart(2, '0');
            dateInput.min = yyyy + '-' + mm + '-' + dd;
        }

        var studentNumber = document.getElementById('student-number');
        if (studentNumber) {
            studentNumber.setAttribute('pattern', '\\d{9}');
            studentNumber.setAttribute('title', 'Enter your 9-digit student number, e.g. 221234567');
        }

        var phone = document.getElementById('phone');
        if (phone) {
            phone.setAttribute('pattern', '[0-9+()\\s-]{7,15}');
            phone.setAttribute('title', 'Enter a valid phone number, e.g. 071 669 3108');
        }

        var courseCode = document.getElementById('course-code');
        if (courseCode) {
            courseCode.setAttribute('pattern', '[A-Za-z0-9]{3,10}');
            courseCode.setAttribute('title', 'Enter a valid course code, e.g. ASPLX1C');
        }

        bookingForm.addEventListener('submit', function (event) {
            if (!bookingForm.checkValidity()) {
                return;
            }
            var name = document.getElementById('student-name').value;
            var code = courseCode.value.toUpperCase();
            var date = dateInput.value;
            var timeSlot = document.getElementById('preferred-time');
            var timeText = timeSlot.options[timeSlot.selectedIndex].text;
            if (!window.confirm('Please confirm your booking:\n\nStudent: ' + name +
                    '\nCourse: ' + code +
                    '\nDate: ' + date +
                    '\nTime: ' + timeText +
                    '\n\nClick OK to submit.')) {
                event.preventDefault();
            }
        });
    }

    var contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (event) {
            if (!contactForm.checkValidity()) {
                return;
            }
            var name = document.getElementById('contact-name').value;
            if (!window.confirm('Send this message, ' + name + '?')) {
                event.preventDefault();
            }
        });
    }

    document.querySelectorAll('button[type="reset"]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            if (!window.confirm('Clear all fields in this form?')) {
                event.preventDefault();
            }
        });
    });
});

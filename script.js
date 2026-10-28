document.addEventListener('DOMContentLoaded', function () {

    var lang = (document.documentElement.lang || 'en').toLowerCase();
    var TXT = {
        en: {
            booking: 'Booking received! We will contact you within 24 hours to confirm your tutor.',
            message: 'Message sent! We will get back to you as soon as possible.',
            error: 'Something went wrong. Please check all required fields and try again.',
            numTitle: 'Enter your 9-digit student number, e.g. 221234567',
            phoneTitle: 'Enter a valid phone number, e.g. 071 669 3108',
            codeTitle: 'Enter a valid course code, e.g. AIWEX3A',
            confirm: function (n, c, d, t) {
                return 'Please confirm your booking:\n\nStudent: ' + n + '\nCourse: ' + c + '\nDate: ' + d + '\nTime: ' + t + '\n\nClick OK to submit.';
            },
            send: function (n) { return 'Send this message, ' + n + '?'; },
            clear: 'Clear all fields in this form?'
        },
        af: {
            booking: 'Bespreking ontvang! Ons sal jou binne 24 uur kontak om jou tutor te bevestig.',
            message: 'Boodskap gestuur! Ons sal so gou moontlik terugkom na jou.',
            error: 'Iets het skeefgeloop. Kontroleer asseblief alle verpligte velde en probeer weer.',
            numTitle: 'Voer jou 9-syfer studentenommer in, bv. 221234567',
            phoneTitle: 'Voer \'n geldige foonnommer in, bv. 071 669 3108',
            codeTitle: 'Voer \'n geldige kursuskode in, bv. AIWEX3A',
            confirm: function (n, c, d, t) {
                return 'Bevestig asseblief jou bespreking:\n\nStudent: ' + n + '\nKursus: ' + c + '\nDatum: ' + d + '\nTyd: ' + t + '\n\nKlik OK om in te dien.';
            },
            send: function (n) { return 'Stuur hierdie boodskap, ' + n + '?'; },
            clear: 'Maak al die velde in hierdie vorm leeg?'
        }
    };
    var t = TXT[lang] || TXT.en;

    var params = new URLSearchParams(window.location.search);
    var status = params.get('status');
    if (status === 'success' || status === 'error') {
        var alertBox = document.createElement('div');
        alertBox.setAttribute('role', 'alert');
        if (status === 'success') {
            alertBox.className = 'alert alert-success';
            alertBox.textContent = document.querySelector('.booking-form') ? t.booking : t.message;
        } else {
            alertBox.className = 'alert alert-error';
            alertBox.textContent = t.error;
        }
        var main = document.querySelector('main');
        if (main) {
            main.insertBefore(alertBox, main.firstChild);
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
            studentNumber.setAttribute('title', t.numTitle);
        }

        var phone = document.getElementById('phone');
        if (phone) {
            phone.setAttribute('pattern', '[0-9+()\\s-]{7,15}');
            phone.setAttribute('title', t.phoneTitle);
        }

        var courseCode = document.getElementById('course-code');
        if (courseCode) {
            courseCode.setAttribute('pattern', '[A-Za-z0-9]{3,10}');
            courseCode.setAttribute('title', t.codeTitle);
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
            if (!window.confirm(t.confirm(name, code, date, timeText))) {
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
            if (!window.confirm(t.send(name))) {
                event.preventDefault();
            }
        });
    }

    document.querySelectorAll('button[type="reset"]').forEach(function (btn) {
        btn.addEventListener('click', function (event) {
            if (!window.confirm(t.clear)) {
                event.preventDefault();
            }
        });
    });
});

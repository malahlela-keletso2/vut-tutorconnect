# VUT TutorConnect

Connecting you to success.

TutorConnect is a tutoring website for Vaal University of Technology students. Students can browse the courses that have tutors, book a tutoring session online and contact the tutoring team. Bookings and messages are emailed to the team through Gmail.

## Features

- Home page with the four faculties: Applied and Computer Sciences, Engineering and Technology, Human Sciences, and Management Sciences
- Course catalog table with course codes, year levels and tutor availability
- Booking form with validation, a confirmation prompt and a confirmation email to the student
- Contact form that emails the tutoring team
- Every booking is also saved to `bookings.csv`
- Responsive layout for phones, tablets and computers

## Built with

- HTML, CSS and JavaScript
- PHP 8 for the forms
- Gmail SMTP for sending email (no external libraries)

## Project structure

```
TutorConnect/
├── images/                  all pictures and the favicon
├── index.html               home page
├── courses.html             course catalog
├── booking.html             booking form
├── contact.html             contact form
├── styles.css               styling
├── script.js                form checks and messages
├── booking.php              handles the booking form
├── contact.php              handles the contact form
├── smtp_mailer.php          sends email through Gmail
├── smtp_config.example.php  sample email settings
└── .htaccess                blocks web access to private files
```

## Run it locally

1. Install [XAMPP](https://www.apachefriends.org/download.html) (download the Windows version with PHP 8) and start **Apache** in the XAMPP Control Panel.
2. Copy this folder into `C:\xampp\htdocs\` so that `index.html` is directly inside `C:\xampp\htdocs\TutorConnect\`.
3. Open `http://localhost/TutorConnect/` in your browser.

## Set up email

1. Turn on 2-Step Verification for the Gmail account.
2. Create a 16-character App Password at https://myaccount.google.com/apppasswords.
3. Copy `smtp_config.example.php` to `smtp_config.php`.
4. Put the App Password in `smtp_config.php`.

If email does not send, open `smtp_error.log` in the project folder to see the reason.

## Security

`smtp_config.php`, `smtp_error.log` and `bookings.csv` are listed in `.gitignore` and must never be uploaded to GitHub. They contain a password and students' personal details.

## Author

Keletso Malahlela

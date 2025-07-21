# Skill to Startup Matcher

A web platform connecting talented students with innovative startups.

## Features

- **Student Registration & Profiles**: Students can register, verify email, and create detailed profiles
- **Startup Registration & Approval**: Startups register and get approved by admins
- **Job Posting**: Startups can post job opportunities and events
- **Application System**: Students can apply to multiple opportunities with email notifications
- **Admin Dashboard**: Admins can manage users, review startups, and oversee the platform

## Key Components

### For Students:
- Profile management
- Browse jobs and startups
- Apply to opportunities
- Track application status

### For Startups:
- Company profile setup
- Post job opportunities
- Review student applications
- Send email notifications on status updates

### For Admins:
- Review and approve startups
- View all students and startups
- Platform oversight

## Installation

1. Place files in your web server directory
2. Configure database connection in `includes/db.php`
3. Import database structure
4. Set up email configuration in `includes/otp_mailer.php`

## Email Notifications

The platform includes automated email notifications for:
- Email verification (OTP-based)
- Application status updates
- Startup approval notifications

## Technology Stack

- PHP 7.4+
- MySQL
- HTML5/CSS3/JavaScript
- PHPMailer for email functionality
- Responsive design

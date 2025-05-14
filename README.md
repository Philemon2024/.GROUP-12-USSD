# USSD Appointment System

A USSD-based appointment booking system that allows users to book, view, and manage appointments through USSD menus.

## Features

- Book new appointments
- View existing appointments
- Cancel appointments
- Accept confirmed appointments
- Admin panel for managing appointments
- Secure admin authentication

## Requirements

- PHP 7.0 or higher
- MySQL database
- USSD gateway integration
- XAMPP (for local development)

## Installation

1. Clone the repository:
```bash
git clone [your-repository-url]
```

2. Import the database schema (if provided)

3. Configure your database connection in `db.php`

4. Set up your USSD gateway to point to the `ussd.php` endpoint

## Configuration

- Update the admin phone number and PIN in `ussd.php`
- Configure your database credentials in `db.php`

## Security

- Admin authentication is required for administrative functions
- Input validation for all user inputs
- Prepared statements for database queries

## License

[Your chosen license] 
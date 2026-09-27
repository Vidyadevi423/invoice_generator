# Invoice Generator

A lightweight PHP and MySQL invoice management application for creating, saving, viewing, and managing invoices through a simple web interface.

## Features

- Create professional invoices
- Add customer information and multiple invoice items
- Automatic subtotal, tax, and total calculation
- Server-side validation and calculation
- Generate downloadable PDF invoices
- Multi-page PDF support for long invoices
- Secure public invoice access using random access tokens
- Authenticated administrator dashboard
- Invoice search and filtering
- Update invoice status
- Delete invoices securely
- CSRF protection for administrative actions
- Login protection with failed-attempt throttling
- Secure password hashing
- MySQL database with prepared statements
- Environment-based database configuration
- Responsive frontend interface

## Technology Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8+
- **Database:** MySQL / MariaDB
- **PDF:** jsPDF + html2canvas
- **Authentication:** PHP sessions + password hashing

## Project Structure

```text
invoice_generator/
│
├── admin.php
├── database.sql
├── db.php
├── index.php
├── invoice.php
├── login.php
├── logout.php
├── save_invoice.php
├── migration_secure_invoice_tokens.sql
│
├── css/
│   └── style.css
│
└── js/
    └── app.js

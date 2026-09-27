# Invoice Generator

A lightweight PHP/MySQL invoice generator with a browser-based invoice form, PDF export, and an authenticated admin panel.

## Features

- Create invoices with customer details, line items, tax, notes, and status.
- Calculate invoice totals on the server before saving.
- Generate downloadable PDFs in the browser.
- Share saved invoices through secure, random access tokens.
- Manage invoices from the authenticated admin panel.
- CSRF protection for administrative state-changing actions.

## Requirements

- PHP 8.x with MySQLi enabled
- MySQL 8.x or a compatible MySQL/MariaDB setup
- A web server capable of serving PHP
- JavaScript enabled in the browser

The PDF feature uses the CDN-hosted **jsPDF** and **html2canvas** libraries loaded by the application pages.

## Installation

1. Create the database and tables:

   ```text
   mysql -u <user> -p < database.sql
   ```

2. Configure the application database environment variables:

   - `APP_ENV`
   - `DB_HOST`
   - `DB_USER`
   - `DB_PASS`
   - `DB_NAME`

   For production, all five variables should be explicitly configured.

3. Open `login.php` after installation.

4. On a fresh database, set the `ADMIN_SETUP_TOKEN` environment variable and use that token to create the first administrator account.

5. After the administrator account is created, remove or rotate the setup token from the runtime environment.

## Existing database migration

If the application is being upgraded from a database created before secure invoice tokens were added, run:

```text
mysql -u <user> -p invoice_db < migration_secure_invoice_tokens.sql
```

The migration is designed to be safely repeatable.

## Main files

- `index.php` — invoice creation interface
- `save_invoice.php` — invoice validation and persistence API
- `invoice.php` — saved invoice view and PDF export
- `admin.php` — authenticated invoice management
- `login.php` / `logout.php` — administrator authentication
- `db.php` — database/session configuration and shared helpers
- `database.sql` — fresh database schema and sample invoice
- `migration_secure_invoice_tokens.sql` — secure-token migration
- `js/app.js` — invoice form, preview, save, and PDF behavior
- `css/style.css` — application styles

## Security notes

- Do not commit production database credentials or setup tokens.
- Use HTTPS in production.
- Use a strong, unique administrator password.
- The public invoice URL uses a random access token; administrative invoice access uses the authenticated admin session.

## Development

Before deploying changes, syntax-check the PHP files and JavaScript file where possible. Test creating an invoice, saving it, opening the secure invoice URL, downloading a multi-page PDF, and using the admin actions.

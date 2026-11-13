# AIA Pharmacy Management System

A PHP and MySQL web application developed as a pharmacy operations and inventory management project. This repository is a **portfolio copy** prepared for code review; it is not a hosted demo or a claim of production certification.

## Highlights

- Point of Sale (POS) and sales recording
- Medicine/product catalogue and automatically generated SKU support
- Batch and expiry-date tracking
- Inventory and stock movement records
- Purchases and supplier management
- Retail and wholesale pricing, including piece/pack/carton handling
- Printable receipts
- Customer records and payment tracking
- Expenses, reports, user roles and audit logs

## Technology

- PHP 8.x
- MySQL / MariaDB
- HTML, CSS and JavaScript
- XAMPP for local development

## Local setup

1. Install XAMPP and start Apache and MySQL.
2. Copy this project folder into `C:\xampp\htdocs\`.
3. Create a database named `aia_pharmacy` in phpMyAdmin.
4. Import `database/schema.sql`. It contains table definitions, indexes and foreign keys only; it contains no sample users or business records.
5. Update `config/config.php` with your local database username and password.
6. Open `http://localhost/aia_pharmacy/`.
7. Create an administrator account using the application's user-management workflow or a secure password-hash setup. No default login credentials are published in this repository.

## Security and portfolio notes

- This public portfolio copy excludes database records, customer/supplier details, sales history, password hashes, backup utilities and obsolete backup/duplicate PHP files.
- Replace the local database placeholders in `config/config.php` before running locally. Do not commit real credentials.
- Review access control, deployment configuration, backups and pharmacy-specific regulatory requirements before any real-world deployment.
- Screenshots should use synthetic/demo data only.

## Project status

Prepared as a development portfolio project. Features should be tested in the target environment before production use.

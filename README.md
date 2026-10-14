# Smart Inventory System

**ISU Cauayan Food Innovation Center**

A comprehensive, enterprise-grade inventory management system for raw materials and production supplies. Built with PHP 8+, MySQL, Tailwind CSS, and vanilla JavaScript — no PHP frameworks.

## Features

### Core Modules
- **Dashboard** — KPI cards, charts (Chart.js), low stock alerts, expiration alerts, recent transactions, skeleton loading
- **Inventory Management** — Full CRUD, stock in/out, categories, units, batch/lot tracking (FIFO)
- **Production Requests** — Multi-status workflow (Pending → Approved → Released → Completed)
- **Purchase Requisitions** — Multi-level approval workflow (Draft → Submitted → Dept → Inventory → Admin → Purchased → Received → Completed)
- **Borrow & Return** — Equipment tracking, overdue detection, penalty tracking
- **Suppliers** — Full management with performance analytics and star ratings

### Analytics & Intelligence
- **Inventory Forecasting** — SMA & WMA algorithms, demand prediction, reorder date, suggested purchase qty
- **KPI Dashboard** — Turnover ratio, dead stock %, fast/slow moving, monthly consumption (6 Chart.js charts)
- **Recommendations Engine** — Rule-based (reorder, overstock, expiring, slow moving, dead stock)

### Administration
- **Dynamic RBAC** — 36+ granular permissions, matrix editor, role-based menu visibility
- **Email Settings** — SMTP config, notification toggles, test email, email logs
- **Backup & Restore** — One-click SQL backup, download, restore, history
- **Audit Logs** — Every action logged with IP, browser, timestamp

### Additional
- **Label Designer** — Custom label templates (5 sizes), field toggles, bulk print
- **Activity Timeline** — Per-item chronological history with filters
- **Dark Mode** — Persistent toggle with zero-flash initialization

## Tech Stack

| Technology | Purpose |
|------------|---------|
| PHP 8+ | Backend logic (no framework, PDO prepared statements) |
| MySQL | Database (29 tables, foreign keys, indexes) |
| Tailwind CSS | UI framework (compiled, no JIT delay) |
| Vanilla JS + jQuery | Frontend interactivity |
| Chart.js | Dashboard charts |
| DataTables | Table sorting, searching, pagination |
| Flatpickr | Date pickers |

## Installation Guide

### Requirements

- PHP 8.0 or newer with PDO and PDO MySQL enabled
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite` enabled, or PHP's built-in development server
- Composer, only if optional PHP packages are required by the deployment

### Local installation with XAMPP

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Clone or copy the project into `C:\xampp\htdocs\smart-inventory\`.
3. Create a MySQL database named `smart_inventory`.
4. Import `database/schema.sql`, followed by `database/schema_extended.sql`, using phpMyAdmin or the MySQL client.
5. Edit `config/database.php` and set the database host, database name, username, and password.
6. Seed the demo data:

   ```bash
   php database/seeder.php
   ```

7. Open `http://localhost/smart-inventory/` in a browser.
8. Sign in with one of the demo accounts below and change demo credentials before production use.

### PHP built-in development server

From the project directory, run:

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/`.

### Production installation

1. Upload the project files to the web server document root.
2. Create an empty MySQL/MariaDB database and import both schema files in order.
3. Configure `config/database.php` with production credentials.
4. Ensure `uploads/` is writable by the web server because item and logo uploads are stored there.
5. Disable PHP error display in `config/config.php` and enable application/server logging.
6. Point the virtual host document root at the project directory and enable URL rewriting if using Apache.
7. Remove or change the demo accounts and passwords before exposing the system publicly.

### Database updates

The current release does not require a new schema migration. Report Settings stores its value in the existing `system_settings` table using the unique key `report_prepared_by`. Existing installations can update the code and use the new Report Settings page directly.

### Rebuild Tailwind (if adding new classes)

```bash
npx tailwindcss -i assets/css/tailwind-input.css -o assets/css/tailwind.css
```

### Production Server

1. Upload all files to `public_html/`
2. Import database schema + seeder
3. Update `config/database.php` with hosting MySQL credentials

## Demo Accounts

| Role | Username | Password |
|------|----------|----------|
| Administrator | admin | admin123 |
| Inventory Manager | inventory | inventory123 |
| Production Staff | production | production123 |
| Faculty | faculty | faculty123 |

## Deployment

To push updates to FTP server:

```powershell
powershell -ExecutionPolicy Bypass -File deploy.ps1
```

## Database

29 tables with proper normalization, foreign keys, indexes, and cascading deletes:

`roles, users, categories, units, suppliers, inventory_items, stock_in, stock_out, production_requests, production_request_items, inventory_transactions, audit_logs, notifications, system_settings, activity_logs, batches, batch_movements, purchase_requests, purchase_request_items, purchase_request_approvals, borrow_records, permissions, role_permissions, email_logs, backup_history, activity_timeline, forecast_data, supplier_performance, recommendations`

---

*Capstone Project — ISU Cauayan Food Innovation Center*

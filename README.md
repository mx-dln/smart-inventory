# Smart Inventory System

**ISU Cauayan Food Innovation Center**

A comprehensive, enterprise-grade inventory management system for raw materials and production supplies. Built with PHP 8+, MySQL, Tailwind CSS, and vanilla JavaScript — no PHP frameworks.

## Features

### Core Modules
- **Dashboard** — KPI cards, charts (Chart.js), low stock alerts, expiration alerts, recent transactions, skeleton loading
- **Inventory Management** — Full CRUD with barcode/QR generation, stock in/out, categories, units, batch/lot tracking (FIFO)
- **Production Requests** — Multi-status workflow (Pending → Approved → Released → Completed)
- **Purchase Requisitions** — Multi-level approval workflow (Draft → Submitted → Dept → Inventory → Admin → Purchased → Received → Completed)
- **Borrow & Return** — Equipment tracking with barcode scanning, overdue detection, penalty tracking
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
- **Label Designer** — Custom barcode/QR templates (5 sizes), field toggles, bulk print
- **Activity Timeline** — Per-item chronological history with filters
- **Dark Mode** — Persistent toggle with zero-flash initialization
- **Barcode/QR Scanner** — USB scanner support + mobile camera (html5-qrcode)

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
| JsBarcode | Barcode generation |
| QRCode.js | QR code generation |
| html5-qrcode | Camera barcode scanning |

## Installation

### Local (XAMPP)

1. Clone to `C:\xampp\htdocs\smart-inventory\`
2. Import `database/schema.sql` and `database/schema_extended.sql` via phpMyAdmin
3. Run `php database/seeder.php` to seed sample data
4. Configure `config/database.php` if MySQL credentials differ
5. Access `http://localhost/smart-inventory/`

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

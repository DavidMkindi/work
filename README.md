<div align="center">

# 🖨️ PICS — Print Inventory Control System

### Inventory, production, & waste management platform for printing operations

**PHP 8 · MySQL (MariaDB) · Vanilla JS · Tailwind-style UI**

</div>

---

> [!IMPORTANT]
> The default administrator account ships **only** with a username & plain-text password
> (the password hash in `work.sql` is stale). See [First login](#-first-login--reset-the-password) below.

---

## 📖 Table of Contents

- [What is PICS?](#-what-is-pics)
- [✨ Features](#-features)
- [🗂️ Module Breakdown](#️-module-breakdown)
- [👥 Roles & Access](#-roles--access)
- [🚀 Getting Started](#-getting-started)
  - [1. Prerequisites](#1-prerequisites)
  - [2. Database setup](#2-database-setup)
  - [3. Authentication & default login](#3-authentication--default-login)
  - [4. Run the app](#4-run-the-app)
- [🔐 First login — reset the password](#-first-login--reset-the-password)
- [📁 Project Structure](#-project-structure)
- [🗄️ Database Schema](#️-database-schema)
- [🔧 Troubleshooting](#-troubleshooting)
- [🛡️ Security Notes](#️-security-notes)
- [📜 License](#-license)

---

## 💡 What is PICS?

**PICS** (Print Inventory Control System) is a role-based web application built for
**printing and production companies** to manage their day-to-day operations in one place.
It covers the full operational loop:

> **Customers & Sales Requests → Production Jobs → Material Requests → Stock Control → Waste Tracking → Approvals & Notifications**

Built on a classic LAMP-style stack — PHP with `mysqli`, a MariaDB/MySQL database, vanilla
JavaScript, and a hand-rolled Tailwind-like CSS design system (`style.css`) — PICS needs **no
composer dependencies and no build step**. Download, import the SQL, configure, and run.

---

## ✨ Features

| Area | Capabilities |
|------|--------------|
| 🔐 **Authentication** | Login / register / password reset, session-based auth, audit logging (`audit_logs`), per-page access control |
| 👤 **User Management** | Role-based users, permissions (`permissions` table), admin user management |
| 👥 **Customers & Sales** | Customer directory + printing service request capture |
| 🏭 **Production** | Production jobs, services & categories, job creation/approval workflow, BOM support (`bill_of_materials`) |
| 📦 **Inventory / Stock** | Multi-warehouse stock management, stock movements, goods receipts, purchase orders & requests, low-stock visibility |
| 📋 **Material Requests** | Request creation, per-item lines (`material_request_items`), submit → pending → approve workflow |
| ♻️ **Waste Management** | Waste record capture & reporting, printable reports via **FPDF** |
| 🔔 **Notifications** | Per-user notification center with unread counts & mark-as-read |
| 🌐 **Close the loop** | Supplier directories, units of measure, cross-module approval chains |

---

## 🗂️ Module Breakdown

| Module | Pages | Purpose |
|--------|-------|---------|
| Dashboard | `index.php` | Metrics, trends, role-aware cards, notification center |
| Customers & Sales | `customers.php`, `customer-request.php` | Manage customers & printing service requests |
| Production | `production-jobs.php`, `services.php` | Jobs, services, machine/category setup |
| Inventory | `stock-management.php`, `warehouses.php`, `categories.php` | Stock levels, multi-warehouse, item categories |
| Material Requests | `material-request.php`, `material-requests.php` | Create & approve/fulfill material requests |
| Waste | `waste-records.php` | Waste capture + FPDF reports |
| Users & Roles | `view-users.php`, `userregister.php` | User directory & admin user management |
| Auth | `auth-basic-login.php`, `auth-basic-register.php`, `auth-basic-reset-password.html` | Login, registration, password reset |

---

## 👥 Roles & Access

Access is enforced **per page** (`requirePageAccess()` in `backend/auth.php`) and in the
**role-aware sidebar** (`backend/sidebar.php`).

| Role | Typical access |
|------|----------------|
| `administrator` / `admin` | Everything |
| `production manager` | Customer requests, production jobs, services, material requests, waste |
| `production supervisor` | Production jobs, material requests, waste |
| `store manager` | Production jobs, material requests (approve), stock, warehouses, categories, waste |
| `sales officer` | Customers |

Sidebar visibility follows the same rules, so users only ever see what they can use.

---

## 🚀 Getting Started

### 1. Prerequisites

- **PHP 8+** with the `mysqli` extension
- **MySQL 5.7+ / MariaDB 10.4+**
- A web server (**Apache/XAMPP**, Nginx + PHP-FPM, or PHP's built-in server)
- **FPDF** — already bundled in the `fpdf/` directory, no install needed

### 2. Database setup

1. Create a database named **`work`**:

   ```sql
   CREATE DATABASE work;
   ```

2. Import the schema & seed data:

   ```bash
   mysql -u root -p work < work.sql
   ```

   *(or import `work.sql` via phpMyAdmin > Import)*

3. Configure credentials in **`backend/config.php`**:

   ```php
   $connect = new mysqli("localhost", "root", "", "work");
   ```

   Update the host / username / password to match your environment.

### 3. Authentication & default login

The SQL dump seeds a default user. **Default credentials (from the original `README.md`):**

| | |
|---|---|
| **Username** | `Moses` |
| **Password** | `Novaya` |

*Note: the hash stored in `work.sql` predates the current `users` table schema — see the [password reset](#-first-login--reset-the-password) section before relying on it.*

### 4. Run the app

**Option A — XAMPP / Apache:**

1. Copy the project into `htdocs/work` (or the web root of your choice).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open **http://localhost/work** in your browser.

**Option B — PHP built-in server:**

```bash
php -S localhost:8000
# then open http://localhost:8000
```

---

## 🔐 First login — reset the password

Because the seeded password hash in `work.sql` is stale, do this once after importing:

```sql
-- Replace 'YourNewPassword' with a strong password
UPDATE users SET Password = '$2y$12$CBmMrprPN4wgaRVZeGnSGu1H7hoMUFd1SxOFNN2RNsd8WcxdmsLai'
WHERE Username = 'Moses';
```

> ⚠️ That hash is the **same stale hash** from the dump. If it no longer authenticates, use the
> **password reset flow** (`auth-basic-reset-password.html`) from the login page instead —
> it generates a valid `password_hash()` value for the current schema.

Afterwards, sign in as **Moses / Novaya**, go to **Management → Manage Users**, and update
the password to one of your own.

---

## 📁 Project Structure

```
work/
├── index.php                     # Dashboard (metrics, trends, notifications)
├── auth-basic-login.php          # Login page
├── auth-basic-register.php       # Registration page
├── auth-basic-reset-password.html# Password reset
├── categories.php                # Item/service categories
├── customers.php                 # Customer directory
├── customer-request.php          # Printing service requests
├── material-request.php          # Create material requests
├── material-requests.php         # Approve / manage material requests
├── notifications.php             # Notification center
├── production-jobs.php           # Production job management
├── services.php                  # Services & machines
├── stock-management.php          # Stock levels & movements
├── userregister.php              # Admin user management
├── view-users.php                # User directory
├── warehouses.php                # Warehouse management
├── waste-records.php             # Waste records & reports
├── work.sql                      # Database schema + seed data
├── style.css                     # Full design system (Tailwind-like)
├── favicon.ico
├── backend/
│   ├── auth.php                  # Auth, sessions, role checks, audit logging
│   ├── config.php                # DB connection (edit me)
│   ├── sidebar.php               # Role-aware sidebar menu
│   ├── logo_sm.php               # Small brand logo
│   ├── create_job.php            # Job creation handler
│   ├── job_approve.php           # Job approval handler
│   ├── material_request_save.php # M/R save handler
│   ├── material_request_approve.php # M/R approval handler
│   ├── stock_helpers.php         # Stock helpers
│   ├── production_helpers.php    # Production helpers
│   ├── waste_record_save.php     # Waste record save handler
│   ├── waste_record_delete.php   # Waste record delete handler
│   ├── waste_records_report.php  # FPDF report generator
│   ├── dashboard_trends.php      # Dashboard chart data (AJAX)
│   ├── notifications_dropdown.php# Notification AJAX endpoint
│   ├── mark_notification_read.php# Mark-as-read AJAX endpoint
│   ├── customer_delete.php       # Customer delete handler
│   └── authentication/           # Auth support code
└── fpdf/                         # Bundled FPDF library (reports)
```

---

## 🗄️ Database Schema

Database: **`work`** — 20 tables (imported from `work.sql`).

```
audit_logs                bill_of_materials       categories
customers                 goods_receipts          material_request_items
material_requests         notifications           permissions
production_jobs           purchase_orders         purchase_request
role                      services                stock
stock_movements           suppliers               units
users                     warehouses              waste_records
```

Key relationships:

- `users` → `role` (role-based access) · `permissions` (granular access)
- `stock` → `warehouses` · `categories` · `stock_movements` · `goods_receipts`
- `production_jobs` → `services` · `bill_of_materials` (BOM)
- `material_requests` → `material_request_items` (request lines)
- `purchase_request` → `purchase_orders` · `suppliers`
- `waste_records` · `audit_logs` · `notifications` (cross-cutting)

---

## 🔧 Troubleshooting

| Issue | Fix |
|-------|-----|
| `mysqli` class not found | Enable the `mysqli` extension in `php.ini` |
| Blank page on login | Check DB credentials in `backend/config.php`; confirm DB `work` is imported |
| Login loops back to login page | Clear PHP sessions; verify `users` table seed row exists and hash is valid |
| Default login fails | Reset the password via [the reset flow](#-first-login--reset-the-password) |
| Import errors in phpMyAdmin | Import with **SQL compatibility mode: MySQL 40xx** and UTF-8 |

---

## 🛡️ Security Notes

- Passwords use PHP's `password_hash()` / `password_verify()` (bcrypt) in `backend/auth.php`.
- All actions are gated by **session auth** + **per-page role checks** (`requirePageAccess()`).
- Sensitive operations write to the **`audit_logs`** table (user, action, entity, IP, timestamp).
- SQL statements use prepared statements with bound parameters throughout the application.
- ⚠️ The seeded default credential (**Moses / Novaya**) ships in the dump for convenience —
  **change it immediately** after your first login in any non-local environment.

---

## 📜 License

Private/internal project. Contact the repository owner for usage rights.

---

<div align="center">

Made with 🖨️ + ☕ — PICS · Print Inventory Control System

</div>
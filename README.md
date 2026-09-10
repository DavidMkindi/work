<div align="center">

<!-- ===== Animated Hero ===== -->
<div class="pics-hero">

  <!-- orbiting badge -->
  <div class="pics-orb pics-orb-1">🖨️</div>
  <div class="pics-orb pics-orb-2">📦</div>
  <div class="pics-orb pics-orb-3">♻️</div>

  <div class="pics-badge">
    <span class="pics-pulse"></span>
    Print Inventory Control System
  </div>

  <h1 class="pics-title">
    <span class="pics-letter" style="--i:1">P</span><span class="pics-letter" style="--i:2">I</span><span class="pics-letter" style="--i:3">C</span><span class="pics-letter" style="--i:4">S</span>
    <span class="pics-title-spacer"></span>
    <span class="pics-letter" style="--i:5">🖨️</span>
  </h1>

  <p class="pics-tagline">Inventory · Production · Waste Management — one seamless platform for printing operations</p>

  <div class="pics-pills">
    <span class="pics-pill">✨ PHP 8</span>
    <span class="pics-pill">🗄️ MySQL / MariaDB</span>
    <span class="pics-pill">⚡ Vanilla JS</span>
    <span class="pics-pill">🎨 Tailwind-Style UI</span>
    <span class="pics-pill">🐘 XAMPP Ready</span>
  </div>

  <div class="pics-divider"></div>

</div>

<!-- ===== Animated Hero CSS (100% GitHub-flavoured Markdown compatible) ===== -->
<style>
  /* ---- hero container ---- */
  .pics-hero {
    position: relative;
    padding: 3.2rem 1.5rem 2.4rem;
    margin: 1.5rem auto 2rem;
    background: linear-gradient(160deg, rgba(129,140,248,.08), rgba(52,211,153,.05) 45%, rgba(251,191,36,.08));
    background-size: 300% 300%;
    animation: picsBgShift 12s ease infinite;
    border-radius: 28px;
    border: 1px solid rgba(129,140,248,.18);
    box-shadow: 0 20px 60px -18px rgba(99,102,241,.35);
    overflow: hidden;
  }
  @keyframes picsBgShift { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

  /* ---- animated gradient title (sweeping rainbow) ---- */
  .pics-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .1em;
    font-size: clamp(3.2rem, 9vw, 6.2rem);
    font-weight: 900;
    line-height: 1.05;
    letter-spacing: .06em;
    margin: .6rem 0 .8rem;
    width: 100%;
    background: linear-gradient(90deg, #60a5fa, #a78bfa, #f472b6, #fbbf24, #34d399, #60a5fa);
    background-size: 300% 100%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    animation: picsGradient 5s linear infinite;
    filter: drop-shadow(0 4px 22px rgba(167,139,250,.45));
  }
  @keyframes picsGradient { 0% { background-position: 0% 50%; } 100% { background-position: 300% 50%; } }

  /* ---- per-letter pop-in (staggered) ---- */
  .pics-letter {
    display: inline-block;
    opacity: 0;
    transform: translateY(26px) scale(.6) rotate(-8deg);
    animation: picsPop .7s cubic-bezier(.2,.8,.2,1) forwards;
    animation-delay: calc(var(--i) * .14s);
  }
  @keyframes picsPop {
    0%   { opacity: 0; transform: translateY(26px) scale(.6) rotate(-8deg); }
    70%  { opacity: 1; transform: translateY(-4px) scale(1.08) rotate(2deg); }
    100% { opacity: 1; transform: translateY(0) scale(1) rotate(0); }
  }
  .pics-title-spacer { width: .28em; display: inline-block; }

  /* ---- top badge with live pulse dot ---- */
  .pics-badge {
    display: inline-flex;
    align-items: center;
    gap: .55em;
    padding: .45em 1.3em;
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: #e0e7ff;
    background: linear-gradient(90deg, rgba(99,102,241,.22), rgba(236,72,153,.18));
    border: 1px solid rgba(129,140,248,.35);
    box-shadow: 0 6px 22px -8px rgba(99,102,241,.5);
  }
  .pics-pulse {
    width: 9px; height: 9px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 0 0 rgba(52,211,153,.7);
    animation: picsPulse 1.8s infinite;
  }
  @keyframes picsPulse {
    0%   { box-shadow: 0 0 0 0 rgba(52,211,153,.7); }
    70%  { box-shadow: 0 0 0 12px rgba(52,211,153,0); }
    100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); }
  }

  /* ---- tagline ---- */
  .pics-tagline {
    font-size: clamp(1rem, 2.6vw, 1.35rem);
    font-weight: 500;
    color: #c7d2fe;
    margin: 0 auto 1.4rem;
    max-width: 46rem;
    animation: picsFadeUp 1s ease .8s both;
  }

  /* ---- tech-stack pills ---- */
  .pics-pills {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .6rem;
    margin-bottom: 1.4rem;
    animation: picsFadeUp 1s ease 1.1s both;
  }
  .pics-pill {
    padding: .42em 1.05em;
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 600;
    color: #e2e8f0;
    background: rgba(30,41,59,.72);
    border: 1px solid rgba(148,163,184,.3);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
  }
  .pics-pill:hover {
    transform: translateY(-4px) scale(1.05);
    border-color: rgba(129,140,248,.7);
    box-shadow: 0 10px 24px -8px rgba(99,102,241,.55);
  }

  /* ---- shimmer divider ---- */
  .pics-divider {
    height: 5px;
    width: min(340px, 78%);
    margin: .4rem auto 0;
    border-radius: 999px;
    background: linear-gradient(90deg, transparent, #60a5fa, #f472b6, #34d399, transparent);
    background-size: 220% 100%;
    animation: picsShimmer 3.2s linear infinite;
  }
  @keyframes picsShimmer { 0% { background-position: 0% 0; } 100% { background-position: 220% 0; } }

  /* ---- floating emoji orbs ---- */
  .pics-orb {
    position: absolute;
    font-size: 1.7rem;
    opacity: .5;
    filter: drop-shadow(0 0 10px rgba(129,140,248,.6));
    animation: picsFloat 6s ease-in-out infinite;
    pointer-events: none;
    user-select: none;
  }
  .pics-orb-1 { top: 14%; left: 6%; animation-delay: 0s; }
  .pics-orb-2 { top: 58%; right: 5%; animation-delay: -2s; }
  .pics-orb-3 { top: 12%; right: 10%; animation-delay: -4s; }
  @keyframes picsFloat {
    0%, 100% { transform: translateY(0) rotate(-8deg); }
    50%      { transform: translateY(-14px) rotate(8deg); }
  }

  /* ---- shared fade-up ---- */
  @keyframes picsFadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* ---- light-theme polish (GitHub default) ---- */
  @media (prefers-color-scheme: light) {
    .pics-pill {
      background: rgba(241,245,249,.9);
      color: #334155;
      border-color: rgba(100,116,139,.25);
    }
    .pics-tagline { color: #475569; }
    .pics-badge { color: #4338ca; background: linear-gradient(90deg, rgba(99,102,241,.12), rgba(236,72,153,.1)); border-color: rgba(99,102,241,.3); }
  }

  /* ---- reduce-motion accessibility ---- */
  @media (prefers-reduced-motion: reduce) {
    .pics-hero { animation: none; }
    .pics-title { animation: none; }
    .pics-letter { animation: none; opacity: 1; transform: none; }
    .pics-pulse, .pics-divider, .pics-orb { animation: none; }
  }
</style>

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
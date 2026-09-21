# 📦 PICS — Print Inventory Control System

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

> **PICS (Print Inventory Control System)** is a role-based web application built for the **printing industry** to manage production jobs, inventory, material requests, warehouses, and waste — all from a single, theme-aware dashboard.

---

## 📖 Table of Contents

- [About the Project](#-about-the-project)
- [What PICS Deals With](#-what-pics-deals-with)
- [Key Features](#-key-features)
- [Role-Based Access](#-role-based-access)
- [Dashboard Overview](#-dashboard-overview)
- [Project Structure](#-project-structure)
- [Tech Stack](#-tech-stack)
- [Prerequisites](#-prerequisites)
- [Installation](#-installation)
- [Database Setup](#-database-setup)
- [Usage](#-usage)
- [Customization](#-customization)
- [Contributing](#-contributing)
- [License](#-license)
- [Author](#-author)

---

## 🧩 About the Project

**PICS** is a full-featured **Print Inventory Control System** designed for print houses, packaging companies, and production facilities. It gives store managers, production managers, supervisors, project managers, and administrators a real-time view of:

- What's in stock and what's running low
- Which production jobs are running, completed, or pending
- Which material requests need approval
- How much waste is being generated

The system is built on **plain PHP + MySQL** with a modern **Tailwind-based admin theme (Tailwick)**, featuring light/dark mode, RTL/LTR support, and customizable sidebar layouts.

---

## 🎯 What PICS Deals With

| Area | Description |
|------|-------------|
| 🏭 **Production Jobs** | Tracks total jobs, running jobs, completed jobs, and pending/approved jobs |
| 📦 **Stock / Inventory** | Monitors total distinct products, flags low-stock items (quantity < 10) |
| 🏢 **Warehouses** | Manages multiple warehouse locations |
| 🏷️ **Categories** | Organizes products and materials into categories |
| 📝 **Material Requests** | Tracks submitted/pending material requests requiring approval |
| ♻️ **Waste Records** | Logs and monitors production waste for compliance and loss tracking |
| 👥 **Customers** | Stores customer information and customer requests |
| 🔐 **Users & Auth** | Basic login, registration, password reset, and role-based page access |
| 🔔 **Notifications** | Per-user notification system with read/unread counts |
| 📊 **Analytics** | Inventory trends, production trends, and waste trends via ApexCharts |
| 🖨️ **PDF Export** | Document generation via the FPDF library |

---

## ✨ Key Features

- **🔐 Role-Based Access Control** — Different dashboards for Store Managers, Production Staff, and Admins
- **📊 Live Metrics Dashboard** — Real-time counters for jobs, stock, requests, and waste
- **📈 Interactive Charts** — Inventory, production, and waste trend charts using ApexCharts
- **🌗 Light / Dark Mode** — Theme-aware UI that respects system preferences
- **🌍 RTL / LTR Support** — Full right-to-left language support
- **🔔 Notification Dropdown** — Unread counts with quick access to recent alerts
- **🧭 Customizable Sidebar** — Default, hover, compact, small, mobile, and hidden layouts
- **📱 Responsive Design** — Works on desktop, tablet, and mobile
- **🧾 PDF Generation** — Built-in FPDF library for reports and documents
- **⚡ No Build Step Required** — Plain HTML/CSS/JS with linked assets

---

## 👤 Role-Based Access

| Role | Access |
|------|--------|
| **Store Manager** | Inventory, warehouses, categories, material requests, waste records |
| **Production Manager / Supervisor / Project Manager** | Production jobs, material requests, waste records |
| **Administrator / Admin** | Full access — both store and production dashboards |
| **Other Users** | Default view with both store and production cards |

---

## 📊 Dashboard Overview

### 🏭 Production Overview
- Total Production Jobs
- Running Jobs
- Completed Jobs
- Pending / Approved Jobs
- Material Requests Pending
- Waste Records

### 📦 Inventory & Sales Overview
- Total Products
- Low Stock Items
- Total Warehouses
- Total Categories
- Material Requests Pending
- Waste Records

### 📈 Trend Charts
- **Inventory Trends** — Stock movement over time
- **Production Trends** — Job throughput over time
- **Waste Trends** — Waste generation over time

---

## 📁 Project Structure

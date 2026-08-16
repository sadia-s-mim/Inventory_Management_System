# Perfect Choice Inventory Management System 🛍️

**A full-stack Daily Lifestyle Retail Inventory Management System built with PHP & MySQL, featuring role-based access control, multi-branch stock tracking, and a Power BI–style analytics dashboard.**
> *Software Development 3 Lab Course project — Northern University Bangladesh*

---

## 📋 Overview

Perfect Choice is an **Internal Management Dashboard** (not a customer-facing storefront) that helps a daily lifestyle/clothing retail business track products, suppliers, stock movement, and sales performance across multiple branches:

- **🔐 Secure Authentication** — login, Forgot Password with email OTP, role-based access
- **📦 Inventory Management** — products, categories, suppliers, live per-branch stock
- **🔄 Stock Operations** — Stock In, Stock Out, and inter-branch Stock Transfers
- **📊 Analytics Dashboard** — Power BI–style KPI cards and charts across every report
- **🏢 Multi-Branch Support** — 4 branches, each with its own manager, sales staff, and inventory
- **🎨 Old-Money Retail Theme** — deep brown sidebar, ivory content area, gold accents

---

## ✨ Key Features

### 🔒 **Authentication & Security**
- Login with `password_hash()` / `password_verify()` (bcrypt)
- Forgot Password via 3-step, single-use, 30-minute email OTP flow (Gmail SMTP / PHPMailer, with a local-dev on-screen fallback when SMTP isn't configured)
- Role-based access control (Admin / Branch Manager / Sales User), enforced in both the UI and at the page level
- Prepared statements (mysqli) throughout to prevent SQL injection
- Stock In/Out wrapped in DB transactions so a partial failure can't corrupt inventory

### 👤 **Role-Based Features**
- **Admin** — full access: users, branches, deletions, activity log across all branches
- **Branch Manager** — manages products, categories, suppliers, and stock for their own branch; scoped Activity Log view
- **Sales User** — records sales (Stock Out) and views their branch's reports only; supplier data hidden from view

### 🏪 **Inventory & Stock System**
- Products with photo upload (JPG/PNG/WEBP), search/filter, full CRUD
- Self-referencing category tree (Gender → Group → Type), including a dedicated Kids section
- Stock In / Stock Out using a header + detail (invoice-style) structure, multi-line per transaction
- Stock Transfers between branches, with in-stock and same-branch validation
- Automatic low-stock notifications plus low-stock **email alerts** to Admins and the relevant Branch Manager, firing only when stock first crosses below threshold

### 📊 **Reports & Dashboard**
- Dashboard: KPI cards, 7-day stock-in/out chart, daily sales trend, inventory-value donut, low-stock table, recent activity feed
- Reports: Current Inventory, Stock Movement, Sales, Low Stock, Supplier — each filterable by date range with its own charts and **CSV export**
- Activity Log viewer (Admin full view; Branch Manager scoped to their own branch)

---

## 🛠️ Technology Stack

- **Frontend:** HTML, CSS, Bootstrap 5, vanilla JS, Chart.js
- **Backend:** PHP (mysqli, prepared statements)
- **Database:** MySQL / MariaDB
- **Email:** PHPMailer via Gmail SMTP (bundled, no Composer needed)
- **Server:** XAMPP (Apache + MySQL)

---

## 📁 Project Structure

```
perfect_choice/
├── config/
│   ├── database.php        # DB connection settings
│   ├── constants.php       # BASE_URL and app constants
│   └── mail.php            # SMTP credentials for OTP email
│
├── auth/
│   ├── login.php
│   ├── forgot_password.php # Step 1: request OTP
│   ├── verify_otp.php      # Step 2: enter OTP, resend option
│   └── reset_password.php  # Step 3: set new password
│
├── products/                # Product CRUD, search/filter, photo upload
├── categories/               # Category tree, cyclic-reference protection
├── suppliers/                 # Supplier CRUD
├── branches/                   # Branch CRUD (Admin only)
├── stock_in/                    # Purchase entry (header + detail)
├── stock_out/                    # Sales entry (header + detail)
├── transfers/                     # Inter-branch stock transfers
├── reports/                        # Inventory, Sales, Stock Movement, Low Stock, Supplier
├── activity_log/                    # Audit trail viewer (Admin / scoped Manager)
├── users/                             # User management (Admin only)
│
├── includes/
│   └── PHPMailer/                     # Bundled email library
│
├── database/
│   └── schema.sql                      # Full schema + sample dataset
│
└── assets/
    └── css/
        └── style.css                    # Old-money theme, CSS variables
```

---

## 🗄️ Database Schema

The system includes **15 normalized tables**:

- `roles`, `branches`, `users`
- `categories` — self-referencing tree
- `suppliers`, `products`
- `inventory` — live qty per product per branch
- `stock_in`, `stock_in_details`
- `stock_out`, `stock_out_details`
- `activity_logs`, `notifications`, `settings`
- `password_resets` — OTP-based, single-use, expiring

---

## 🚀 Installation & Setup

### Prerequisites
- **PHP 8.1+** with mysqli extension
- **MySQL 8.0+** or MariaDB
- **XAMPP** (Apache + MySQL)

### Installation Steps

#### 🐧 Linux (Arch / XAMPP)

1. **Copy the project into `htdocs`:**
   ```bash
   cp -r perfect_choice /opt/lampp/htdocs/        # Arch Linux XAMPP path
   ```

2. **Start Apache and MySQL** from the XAMPP control panel:
   ```bash
   sudo /opt/lampp/lampp start
   ```

#### 🪟 Windows (XAMPP)

1. **Copy the project into `htdocs`:**
   - Copy the `perfect_choice` folder into:
     ```
     C:\xampp\htdocs\
     ```

2. **Start Apache and MySQL** from the **XAMPP Control Panel** (`xampp-control.exe`) by clicking **Start** next to both Apache and MySQL.
   - If Apache fails to start, another process (often Skype or IIS) may already be using port 80 — either close it or change Apache's port in `httpd.conf`.

3. **Import the database:**
   - Open phpMyAdmin (`http://localhost/phpmyadmin`)
   - Click **Import** → choose `database/schema.sql` → Go
   - This creates all 15 tables with a full sample dataset (4 branches, 9 users, categories including Kids, suppliers, products, inventory, and realistic stock-in/out history)

4. **Configure database connection** in `config/database.php` (defaults: `root` / no password, matching stock XAMPP on both Linux and Windows).

5. **Configure `BASE_URL`** in `config/constants.php` if the folder was renamed inside `htdocs`.

6. **(Optional) Enable real OTP emails** — see Email Setup below. Until configured, OTP codes are shown on screen so Forgot Password stays testable.

7. Visit the app in your browser:
   - Linux: `http://localhost/perfect_choice/`
   - Windows: `http://localhost/perfect_choice/`

### Demo Login
| Role | Email | Password |
|---|---|---|
| Admin | admin@perfectchoice.com | password123 |
| Branch Manager | manager.gulshan@perfectchoice.com | password123 |
| Sales User | sales.uttara@perfectchoice.com | password123 |

---

## 📧 Email Setup (Forgot Password OTP)

1. On the sender account, enable **2-Step Verification**.
2. Generate an **App Password** for Mail.
3. Paste it into `SMTP_PASSWORD` in `config/mail.php`.
4. Once set, `MAIL_DEV_FALLBACK` turns itself off automatically — codes are emailed instead of shown on screen.

Never commit a real App Password to a public repository — `config/mail.php` ships with the password field blank.

---

## 🔒 Role Permissions

| Action | Admin | Branch Manager | Sales User |
|---|:---:|:---:|:---:|
| View dashboard / reports | ✅ | ✅ (own branch) | ✅ (own branch) |
| Manage products / categories | ✅ | ✅ | ❌ view only |
| Manage suppliers | ✅ | ✅ | ❌ |
| Manage branches | ✅ | ❌ | ❌ |
| Record stock in | ✅ | ✅ | ❌ |
| Record stock out (sales) | ✅ | ✅ | ✅ |
| Stock transfers | ✅ | ✅ | ❌ |
| View activity log | ✅ (all branches) | ✅ (own branch) | ❌ |
| Manage users | ✅ | ❌ | ❌ |
| Delete records | ✅ | ❌ | ❌ |

---

## 🎨 UI/UX Features

- **🌟 Old-Money Retail Theme** — deep brown sidebar, ivory content, gold accents, serif-leaning typography
- **📱 Mobile Responsive** — every data table wrapped in `.table-responsive`
- **📊 Power BI–Style Charts** — consistent sizing across dashboard and all 5 reports
- **⏱️ Live OTP Timers** — server-enforced countdown and resend cooldown
- **🖼️ Product Photos** — upload with placeholder fallback

---

## 🚀 Future Enhancements

- [ ] **Advanced Sales Analytics** — deeper insights into what's selling where and why: region/branch-wise sales comparison, top-demand and trending products, seasonal demand patterns, and predictive restock suggestions based on sales velocity
- [ ] Customer-facing order history
- [ ] Barcode/QR scanning for stock in/out
- [ ] Purchase order workflow with supplier approval
- [ ] Mobile app companion
- [ ] API for third-party integration

---

## 👥 Development Team

**Northern University Bangladesh**
Department of Computer Science & Engineering
*Software Development 3 — Lab Course*

1. **Sadia Sultana Mim** — [GitHub](https://github.com/sadia-s-mim)
2. **Samantha Khan** — [GitHub](https://github.com/samantakhan)
3. **Rayhan Islam** — [GitHub](https://github.com/rayhan22-11)
4. **Mahmuda Akter Rina** — [GitHub](https://github.com/ayesha09nur-png)

---

## 📄 License

This project is developed for educational purposes as part of university coursework.

---

## 🤝 Contributing

This is an educational project. For suggestions or improvements, please create an issue or submit a pull request.

---

**⭐ If you find this project helpful, please give it a star!**

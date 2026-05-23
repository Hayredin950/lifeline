# LifeLine Blood Network — Local Setup Guide

A blood donor network connecting hospitals with voluntary donors across India.

## Requirements

- **PHP 8.1+** (with PDO + pgsql extension)
- **PostgreSQL 14+**

## Quick Start

### 1. Create the Database

```bash
createdb lifeline_db
psql lifeline_db < database.sql
```

### 2. Create Admin User

```bash
psql lifeline_db -c "
  INSERT INTO users (email, password, role, is_active)
  VALUES ('admin@bloodsystem.com', '$(php -r "echo password_hash(\"SecureAdmin2024!\", PASSWORD_DEFAULT);")', 'admin', true)
  ON CONFLICT (email) DO NOTHING;
"
```

Or run the helper script:

```bash
php setup_admin.php
```

### 3. Configure Environment

Copy `.env.example` to `app/.env` and fill in your values:

```bash
cp .env.example app/.env
```

Edit `app/.env`:

```
APP_ENV=development
APP_DEBUG=true
APP_PATH=

PGHOST=localhost
PGPORT=5432
PGDATABASE=lifeline_db
PGUSER=your_postgres_user
PGPASSWORD=your_postgres_password
```

> **Note:** Leave `APP_PATH` empty when running locally at the root (e.g. `http://localhost:8000`).
> Set it to `/lifeline` only if you're serving from a subdirectory.

### 4. Start the PHP Development Server

```bash
php -S localhost:8000 -t app/
```

Open **<http://localhost:8000>** in your browser.

### 5. Admin Login

- URL: <http://localhost:8000/admin/dashboard.php>
- Email: `admin@bloodsystem.com`
- Password: `SecureAdmin2024!`

## Project Structure

```
lifeline-blood-network/
├── app/                    # PHP application (document root)
│   ├── lifeline/           # All PHP pages + assets
│   │   ├── index.php       # Homepage
│   │   ├── login.php
│   │   ├── register.php
│   │   ├── find_donors.php
│   │   ├── blood_banks.php
│   │   ├── emergency.php
│   │   ├── leaderboard.php
│   │   ├── admin/          # Admin panel
│   │   ├── donor/          # Donor dashboard
│   │   ├── hospital/       # Hospital dashboard
│   │   ├── includes/       # Shared config, DB, functions
│   │   └── assets/         # CSS, JS, images
│   └── (PHP server root)
├── database.sql            # Full PostgreSQL schema + seed data
├── .env.example            # Environment variable template
├── setup_admin.php         # Admin user creation helper
└── README.md               # This file
```

## Roles

| Role     | Can Do                                                  |
| -------- | ------------------------------------------------------- |
| Donor    | Register, set availability, view matches, track history |
| Hospital | Post blood requests, view matching donors               |
| Admin    | Manage all users, requests, view activity logs          |

## Blood Types Supported

A+, A-, B+, B-, O+, O-, AB+, AB-

## Tech Stack

- **Backend:** PHP 8.2 (vanilla, no framework)
- **Database:** PostgreSQL 14+ with PDO
- **Frontend:** Vanilla HTML/CSS/JS (no frameworks)
- **CSS:** Custom dark theme with CSS variables


# LifeLine Blood Network

A real-time, geo-aware blood-donor network connecting hospitals and people in emergencies with
voluntary donors. Built on a deliberately simple, portable stack so it can run in a district
hospital and scale to a continent.

> **Full engineering blueprint:** see [`docs/`](docs/00-Documentation-Index.md) — vision, SRS,
> architecture, data model, security & compliance, scalability, the path-to-billions roadmap, and the
> living [task checklist](docs/15-Project-Task-Checklist.md).

## Tech Stack

| Layer | Technology |
| --- | --- |
| Markup | HTML5 (server-rendered by PHP) |
| Styling | CSS3 — custom dark, crimson-accented design system |
| Interactivity | JavaScript + **jQuery 3.x** (self-hosted) |
| Backend | **PHP 8.1+** (vanilla, PDO, no framework) |
| Database | **MySQL 8.0+** (InnoDB, utf8mb4) |

## Requirements

- **PHP 8.1+** with extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `json`
- **MySQL 8.0+**
- (For scale) Redis 7.x — sessions, rate-limit, cache, queue. Optional for local dev.

## Quick Start

### 1. Create the database & apply migrations

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lifeline_db_mysql CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

mysql -u root -p lifeline_db_mysql < schema/001_init.sql
# optional hardening once the app validates the same value sets:
mysql -u root -p lifeline_db_mysql < schema/002_enum_and_index_hardening.sql
```

Migrations live in [`schema/`](schema/README.md) — forward-only, numbered, idempotent, and tracked in
a `schema_migrations` ledger. They replace the legacy `database.sql` / `database_mysql.sql` /
`fix_db.sql` files.

### 2. Configure environment

Copy the template and fill in your values:

```bash
cp .env.example app/lifeline/.env
```

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_NAME="LifeLine Blood Network"
APP_URL=http://localhost:8000
APP_PATH=

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=lifeline_db_mysql
DB_USERNAME=your_mysql_user
DB_PASSWORD=your_mysql_password

# Optional — email; if unset, mail is logged instead of sent (dev-safe)
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@lifelineblood.network
MAIL_FROM_NAME="LifeLine Blood Network"
MAIL_ENCRYPTION=tls

SESSION_LIFETIME=1440
MAX_LOGIN_ATTEMPTS=5
LOGIN_LOCKOUT_MINUTES=15
```

> Leave `APP_PATH` empty when serving from the root (e.g. `http://localhost:8000`). Set it to
> `/lifeline` only if you serve from a subdirectory.

### 3. Create the admin user

```bash
php setup_admin.php
```

This creates the admin account (`admin@bloodsystem.com`, override with `ADMIN_EMAIL=`) and prints a
**strong, random one-time password** to the terminal. There is no hard-coded default password.

> 🔐 **Security:** the one-time password is shown only once, at creation. On first login you are
> **required to set a new password** before you can use the account (enforced by `must_change_password`).
> Re-running `setup_admin.php` rotates the one-time password and re-arms the forced change.
> See `docs/07-Security-Privacy-and-Compliance.md`.

### 4. Run the development server

```bash
php -S localhost:8000 -t app/lifeline/
```

Open **<http://localhost:8000>**.

- Admin login: <http://localhost:8000/admin/dashboard.php>

## Project Structure

```
lifeline-blood-network/
├── app/lifeline/            # PHP application (document root)
│   ├── index.php            # Homepage
│   ├── login.php register.php logout.php forgot_password.php reset_password.php
│   ├── find_donors.php blood_banks.php emergency.php eligibility.php
│   ├── leaderboard.php testimonials.php view_request.php messages.php health.php
│   ├── admin/               # Admin console (dashboard, manage_*, activity, edit/delete)
│   ├── donor/               # Donor dashboard + profile
│   ├── hospital/            # Hospital dashboard, requests, matches
│   ├── api/                 # Messaging AJAX endpoints (get/send/edit/delete)
│   ├── includes/            # Shared kernel: config, db, functions, email_service, header/footer
│   └── assets/              # css/ js/ images/ uploads/
├── schema/                  # Numbered MySQL migrations (source of truth)
├── docs/                    # Engineering blueprint (00–16)
├── setup_admin.php          # Admin user helper
└── README.md
```

## Roles

| Role | Can do |
| --- | --- |
| **Donor** | Register, set availability, view compatible requests, express interest, track history, earn tiers/points |
| **Hospital** | Post blood requests, view matching donors, manage matches, record donations |
| **Admin** | Manage users/requests, view the audit log, export CSV |

## Blood Types

A+, A−, B+, B−, O+, O−, AB+, AB− — donor↔patient compatibility is encoded both directions in
`includes/functions.php`.

## Contributing

Read `docs/10-Coding-Standards-and-Git-Workflow.md` first. In short: PSR-12 PHP, prepared statements
only, escape on output, jQuery (self-hosted) for interactivity, numbered migrations, audit every
mutation, update the relevant `docs/` file in the same PR. The current defect backlog and roadmap
live in `docs/15-Project-Task-Checklist.md`.

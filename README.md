<div align="center">

# &#9764; LifeLine Blood Network

**Real-time, geo-aware blood donor network connecting hospitals and voluntary donors in emergencies.**

*Built on a deliberately simple, portable stack — runs in a district hospital today, scales to a continent tomorrow.*

[![CI](https://github.com/Hayredin950/lifeline/actions/workflows/ci.yml/badge.svg)](https://github.com/Hayredin950/lifeline/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.3-777bb4?logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479a1?logo=mysql&logoColor=white)](https://mysql.com)
[![Redis](https://img.shields.io/badge/Redis-7-dc382d?logo=redis&logoColor=white)](https://redis.io)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ed?logo=docker&logoColor=white)](./Dockerfile)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%203-8892be?logo=php&logoColor=white)](./phpstan.neon)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)](./docs/10-Coding-Standards-and-Git-Workflow.md)

</div>

---

## Overview

LifeLine is a full-stack PHP application that bridges the gap between voluntary blood donors and hospitals facing emergencies. The platform handles the full lifecycle — donor registration and availability, hospital blood requests, geo-ranked donor matching, cold-chain inter-facility transfers, clinical trial recruitment, and platform-wide analytics — all in a single deployable unit with no framework dependency.

---

## Features

### For Donors
- Profile with blood type, location, availability toggle, and cool-off tracking
- Blood **component registry** — opt in for plasma, platelets, bone marrow, organs, and 5 other types
- **Geo-ranked request matching** via `ST_Distance_Sphere` — nearest compatible donors first
- Donation history, **points system**, and tiered **leaderboard** (weekly / monthly / all-time)
- **Clinical trial enrolment** with explicit consent and eligibility matching
- Testimonial submission, 2FA (TOTP), data export, and GDPR/DPDP erasure request

### For Hospitals
- Post blood requests with urgency level (routine → critical) and component type
- **Donor matching** ranked by distance from the hospital + donor reliability score
- **Blood unit inventory** — track lots by component, storage temperature, and expiry
- **Inter-facility cold-chain transfers** — full lifecycle (requested → accepted → dispatched → received)
- Hospital analytics dashboard and license verification workflow

### For Administrators
- **User management** — donors, hospitals, verification, suspension
- **Audit trail** — every mutation logged with user, timestamp, and context; CSV export
- **DPO compliance** — DSAR queue, breach incident log, BAA register, DPIA tracker (DPDP/HIPAA)
- **Demand forecasting** — 4-week WMA per blood type + de-identified donor propensity scoring
- **Shortage analytics** — public-health heatmap, fulfillment rates, time-to-fill by region
- **SLO dashboard** — notification queue depth, critical request age, DB latency, replica lag, worker health
- Country configuration, donation component catalogue, API key management, clinical trial management

### Platform
- **Redis fragment cache** with file-based fallback — homepage and leaderboard served from cache
- **REST API** (`/api/v1`) with issued API keys for machine-to-machine integrations
- **PWA** — installable, offline-capable via service worker and Web App Manifest
- **i18n** — English and Amharic (አማርኛ) with session-scoped locale switching
- **GitHub Actions CI** — lint, PHPStan static analysis, security grep, migration integration test
- **Docker** — single-image deployment with MySQL 8.0 + Redis 7 + background worker
- **Formal migration runner** (`schema/run_migrations.php`) — forward-only, idempotent, tracked in ledger

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | **PHP 8.3** — vanilla, PDO, no framework |
| Database | **MySQL 8.0** — InnoDB, utf8mb4, spatial indexes |
| Cache / Sessions | **Redis 7** (optional — file fallback for dev) |
| Frontend | HTML5 · CSS3 (custom dark design system) · **jQuery 3.7** (self-hosted) |
| Auth | Native sessions · TOTP 2FA · CSRF tokens · rate limiting |
| Container | **Docker** (PHP 8.3-Apache + phpredis + GD + opcache) |
| CI | **GitHub Actions** — lint · PHPStan level 3 · security scan · migration test |
| Background | PHP CLI workers — notifications · forecasting · archiving |

---

## Roles

| Role | Capabilities |
|---|---|
| **Guest** | Browse homepage and donor stories |
| **Donor** | Full donor profile · request matching · leaderboard · clinical trials · messaging · 2FA |
| **Hospital** | Blood requests · donor matching · inventory · transfers · analytics · messaging |
| **Admin** | Everything above + user management · audit logs · forecasting · compliance · SLO · country config |

---

## Quick Start

### Option A — Docker (recommended)

```bash
git clone https://github.com/Hayredin950/lifeline.git
cd lifeline

# Copy and configure environment
cp lifeline/.env.example lifeline/.env   # edit DB_*, REDIS_*, MAIL_* as needed

# Start all services (app + MySQL + Redis + worker)
docker compose up -d

# Create the admin account (prints a one-time password)
docker compose exec app php setup_admin.php
```

Open **http://localhost:8080** — admin panel at `/admin/dashboard.php`.

---

### Option B — Local PHP dev server

#### 1. Prerequisites

| Requirement | Version |
|---|---|
| PHP | 8.1+ with `pdo_mysql` `mbstring` `openssl` `fileinfo` `curl` `gd` |
| MySQL | 8.0+ |
| Redis | 7.x *(optional — cache falls back to file)* |

#### 2. Create the database

```bash
mysql -u root -p -e "
  CREATE DATABASE IF NOT EXISTS lifeline_db_mysql
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'lifeline_user'@'localhost' IDENTIFIED BY 'lifeline_pass';
  GRANT ALL PRIVILEGES ON lifeline_db_mysql.* TO 'lifeline_user'@'localhost';
"
```

#### 3. Apply migrations

```bash
php schema/run_migrations.php
```

The runner applies every `schema/*.sql` file that hasn't been recorded in the `schema_migrations` ledger — safe to re-run at any time.

#### 4. Configure environment

```bash
cp lifeline/.env.example lifeline/.env
```

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
APP_PATH=

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lifeline_db_mysql
DB_USERNAME=lifeline_user
DB_PASSWORD=lifeline_pass

# Optional — mail. When unset, messages are logged to PHP error log.
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@lifelineblood.network

# Optional — Redis. When unset, cache/sessions fall back to file.
# REDIS_HOST=127.0.0.1
# REDIS_PORT=6379
```

#### 5. Create the admin user

```bash
php setup_admin.php
```

Prints a **strong, random one-time password** — no hard-coded default. On first login you are required to set a new password before accessing anything.

> Re-running `setup_admin.php` rotates the one-time password and re-arms the forced change. Override the email with `ADMIN_EMAIL=you@example.com php setup_admin.php`.

#### 6. Start the dev server

```bash
php -S 127.0.0.1:8000 -t lifeline/
```

Open **http://127.0.0.1:8000**

#### 7. (Optional) Run background workers

```bash
php worker/process_notifications.php   # email / SMS outbox
php worker/compute_forecasts.php       # demand forecasting + propensity scores
```

In production these run on a cron schedule inside the Docker worker container.

---

## Project Structure

```
lifeline-blood-network/
├── lifeline/                    # PHP application (document root)
│   ├── index.php                # Public homepage
│   ├── find_donors.php          # Geo-ranked donor search (login required)
│   ├── blood_banks.php          # Blood bank directory
│   ├── eligibility.php          # Donor eligibility self-check
│   ├── emergency.php            # Emergency SOS broadcast
│   ├── leaderboard.php          # Donor points leaderboard
│   ├── testimonials.php         # Public donor stories
│   ├── messages.php             # In-app messaging
│   ├── login.php / register.php / logout.php
│   ├── forgot_password.php / reset_password.php
│   │
│   ├── admin/                   # Admin console (23 pages)
│   │   ├── dashboard.php        # Hub — KPIs, grouped action cards
│   │   ├── manage_donors.php / manage_hospitals.php / manage_requests.php
│   │   ├── activity.php         # Audit log + CSV export
│   │   ├── analytics.php        # Platform KPIs
│   │   ├── verify_hospitals.php # License review
│   │   ├── dpo_dashboard.php    # DSAR / breach / BAA / DPIA
│   │   ├── shortage_analytics.php
│   │   ├── clinical_trials.php / trial_enrolments.php
│   │   ├── forecasting.php      # Demand forecast viewer
│   │   ├── transfers.php        # Cold-chain transfer oversight
│   │   ├── component_types.php  # Donation component catalogue
│   │   ├── country_config.php   # Multi-country settings
│   │   ├── api_keys.php         # REST API key management
│   │   └── slo_dashboard.php    # Real-time health signals
│   │
│   ├── donor/                   # Donor portal (8 pages)
│   │   ├── dashboard.php / edit_profile.php
│   │   ├── component_registry.php  # Component opt-in
│   │   ├── clinical_trials.php     # Trial enrolment
│   │   ├── data_export.php / request_erasure.php  # GDPR/DPDP rights
│   │   └── submit_testimonial.php / notification_prefs.php
│   │
│   ├── hospital/                # Hospital portal (8 pages)
│   │   ├── dashboard.php / edit_profile.php
│   │   ├── create_request.php / request_matches.php
│   │   ├── inventory.php        # Blood unit lot management
│   │   ├── transfers.php        # Inter-facility transfer requests
│   │   ├── analytics.php        # Hospital-level KPIs
│   │   └── submit_verification.php
│   │
│   ├── api/v1/                  # REST API endpoints
│   ├── auth/                    # 2FA setup
│   ├── includes/                # Shared kernel
│   │   ├── config.php / db.php / functions.php
│   │   ├── security.php         # CSP / HSTS / CSRF / rate-limit headers
│   │   ├── email_service.php    # PHPMailer wrapper
│   │   └── header.php / footer.php
│   └── assets/
│       ├── css/style.css        # Custom dark design system
│       ├── js/app.js
│       └── vendor/jquery-3.7.1.min.js
│
├── schema/                      # 19 MySQL migrations + runner
│   ├── 001_init.sql … 019_forecasting.sql
│   └── run_migrations.php       # Forward-only, idempotent CLI runner
│
├── worker/                      # Background job scripts
│   ├── process_notifications.php
│   ├── compute_forecasts.php
│   ├── backup.sh                # mysqldump + gzip + N-day rotation
│   └── restore_drill.sh         # Monthly restore verification drill
│
├── docker/
│   ├── php.ini                  # Production PHP tuning
│   └── apache.conf              # Security-hardened vhost
│
├── docs/                        # Engineering blueprint (16 documents)
│   ├── 00-Documentation-Index.md
│   ├── 01-Vision … 15-Project-Task-Checklist.md
│   └── 16-API-Reference.md
│
├── .github/workflows/ci.yml     # GitHub Actions CI pipeline
├── Dockerfile                   # PHP 8.3-Apache + phpredis + GD + opcache
├── docker-compose.yml           # app + MySQL 8.0 + Redis 7 + worker
├── phpstan.neon                 # Static analysis config (level 3)
└── setup_admin.php              # One-shot admin account helper
```

---

## Database Migrations

19 forward-only migrations live in `schema/` and are tracked in a `schema_migrations` ledger table. The formal runner handles idempotency, comment stripping, and tolerates "object already exists" errors for safe re-runs.

```bash
# Apply all pending migrations
php schema/run_migrations.php

# Preview without applying
php schema/run_migrations.php --dry-run
```

| Range | What it covers |
|---|---|
| 001 | Full consolidated schema — 19 tables, indexes, seed data |
| 002–005 | ENUM hardening, rate limits, notification queue, email-change verification |
| 006–009 | Spatial geo (POINT + SPATIAL INDEX), 2FA, API keys, PWA / i18n |
| 010–012 | Analytics, archive partitions, country configuration |
| 013–016 | DPO compliance (DSAR, breach, BAA, DPIA), REST API, donor points |
| 017 | Cold-chain inventory + inter-facility transfers |
| 018 | Clinical trial recruitment + consented enrolments |
| 019 | Demand forecasts + de-identified donor propensity scores |

> **Never edit a shipped migration.** Add a new numbered file for any schema change.

---

## CI / CD

Every pull request runs four jobs via **GitHub Actions**:

| Job | What it checks |
|---|---|
| `lint` | PHP 8.3 syntax check on all `.php` files (parallel `xargs`) |
| `static-analysis` | PHPStan level 3 across `lifeline/` and `worker/` |
| `security` | Grep for dangerous functions (`eval`, `shell_exec`, `exec`, …) and hardcoded secret patterns |
| `migration-check` | Applies all `schema/*.sql` against a real MySQL 8.0 service container |

---

## Security Highlights

- **No framework** — zero third-party attack surface beyond jQuery and PHPMailer
- **PDO prepared statements** everywhere — SQL injection is structurally impossible
- **CSRF tokens** on every mutation; replay protection via per-form token rotation
- **Security headers** on every response — CSP, HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy, COOP, CORP (`includes/security.php`)
- **Rate limiting** on login, registration, password reset, and all public endpoints (durable `rate_limits` table)
- **TOTP 2FA** for admin and donor accounts
- **Upload hardening** — MIME validation + GD re-encode strips embedded payloads
- **Audit log** — every create / update / delete recorded with user, IP, and old/new value snapshot
- **GDPR / DPDP** — soft-delete, data export, DSAR workflow, breach runbook, BAAs

See [`docs/07-Security-Privacy-and-Compliance.md`](docs/07-Security-Privacy-and-Compliance.md) for the full threat model.

---

## Contributing

Read [`docs/10-Coding-Standards-and-Git-Workflow.md`](docs/10-Coding-Standards-and-Git-Workflow.md) before opening a PR.

**Key rules:**
- PSR-12 PHP style
- PDO prepared statements — no string interpolation into SQL
- `htmlspecialchars()` on every output, `.text()` in jQuery (never `.html()` for user data)
- Every schema change gets a new numbered migration file
- Every mutation gets an `audit_log()` call
- Update the relevant `docs/` file in the same PR as the behaviour change
- Defect backlog and roadmap live in [`docs/15-Project-Task-Checklist.md`](docs/15-Project-Task-Checklist.md)

---

<div align="center">

Made with &#10084; for humanity &nbsp;·&nbsp; LifeLine Blood Network &copy; 2026

</div>

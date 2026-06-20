# 04 · Data Model & Database Schema

**Status:** 🟡 In review · **Owner:** Engineering (Data) · **Traces to:** Doc 02, Doc 05, Doc 07.

Engine: **MySQL 8.0+, InnoDB, `utf8mb4`**, accessed via PDO with `EMULATE_PREPARES=false`. All
identifiers are surrogate `INT AUTO_INCREMENT` PKs; timestamps default to `CURRENT_TIMESTAMP`.

---

## 1. ⚠️ Schema-fragmentation defect (must fix first)

The DDL is currently split across **three** files, and the application reads/writes columns and
tables that the base schema does not create. This is the single most important data-layer issue.

| File | Creates | Problem |
|---|---|---|
| `database.sql` | All 11 base tables (**PostgreSQL** dialect, `public.` schema) | Wrong engine for the mandated stack |
| `database_mysql.sql` | Same 11 tables in MySQL dialect | **Missing** `messages`, `notifications`; **missing** `donor_profiles.donation_points`, `donor_profiles.is_verified` |
| `fix_db.sql` | `messages`, `notifications`; `ALTER` adds `donation_points`, `is_verified` | A patch that *must* be applied or the app breaks; **still missing `messages.is_edited`** |

**Consequences (defects):**

- **DEF-11 🔴** — `api/edit_message.php` writes `messages.is_edited`, but **no** schema file creates
  that column. Editing a message throws on a clean install.
- **DEF-17 🟠** — A fresh `database_mysql.sql`-only install has no `messages`/`notifications` tables;
  the nav unread-badge query (`getUnreadMessageCount`) and messaging fail.
- **DEF-18 🟠** — `donation_points`/`is_verified` are read by `leaderboard.php` but only exist after
  `fix_db.sql`.
- **DEF-19 🟡** — Two dialects of truth (`database.sql` PostgreSQL vs MySQL) invite drift; the
  `README` still documents PostgreSQL/`createdb`, contradicting the mandated MySQL stack.

**Resolution (FR/Doc-15 task):** collapse everything into **one** canonical, idempotent,
migration-ordered MySQL file — `schema/001_init.sql` … — with `messages.is_edited` included. The
consolidated DDL is given in §6. Retire `database.sql` and `fix_db.sql`.

---

## 2. Entity-relationship overview

```
                         ┌─────────────┐
                         │   users     │  (id, email, password, role, is_active, …)
                         └──────┬──────┘
        ┌───────────────┬───────┼────────────┬───────────────┬──────────────┐
        │1:1            │1:1    │1:N          │1:N            │1:N           │1:N
        ▼               ▼       ▼             ▼               ▼              ▼
 donor_profiles  hospital_   blood_requests  messages    notifications  audit_logs
 (user_id FK)    profiles    (hospital_id FK) (sender_id, (user_id FK)   (user_id FK,
        │        (user_id FK)      │           receiver_id)                SET NULL)
        │                          │
        │1:N (donor_id)            │1:N (request_id)
        ▼                          ▼
 donation_history ◄───────── donor_matches (request_id FK, donor_id FK; UNIQUE(request,donor))
 achievements (donor_id FK, UNIQUE(donor,type))

 blood_banks      (standalone reference/seed data)
 password_resets  (keyed by email; standalone)
 testimonials     (donor_id FK → users, SET NULL; is_approved moderation)
```

Cardinality notes: a `users` row owns **exactly one** profile matching its role. `donor_matches`
enforces `UNIQUE(request_id, donor_id)` (a donor can match a request once). `achievements` enforces
`UNIQUE(donor_id, type)` (one badge per type).

---

## 3. Table catalog (as-built + required additions)

### `users` — identity root
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| email | VARCHAR(255) UNIQUE | login identity |
| password | VARCHAR(255) | bcrypt hash only |
| role | VARCHAR(20) = 'donor' | `donor`/`hospital`/`admin` → **migrate to ENUM** (FR-21 ethos) |
| is_active | TINYINT(1)=1 | soft-disable |
| email_verified_at | TIMESTAMP NULL | **unused today** → wire up (FR-08) |
| created_at / updated_at | TIMESTAMP | auto |

### `donor_profiles` — 1:1 with a donor user
Key columns: `user_id` FK→users (CASCADE), `full_name`, `phone`, `blood_type`, address/`city`/`state`/`country`(=India), `date_of_birth`, `gender`, `is_available`(=1), `last_donation_date`, `latitude`/`longitude` DECIMAL(10,7), `total_donations`(=0), `tier`(='bronze'), **`donation_points`(=0)**, **`is_verified`(=0)** *(last two only via `fix_db.sql` — fold in)*.

### `hospital_profiles` — 1:1 with a hospital user
`user_id` FK→users (CASCADE), `hospital_name`, `phone`, address/city/state/country, `license_number`, `latitude`/`longitude`, `is_verified`(=0), timestamps.

### `blood_requests` — demand
`hospital_id` FK→users (CASCADE, **NULL for anonymous Emergency SOS**), `patient_blood_type`, `units_needed`(=1), `urgency`('normal'/'urgent'/'critical'), `status`('open'/'fulfilled'/'cancelled'), `required_date`, `city`/`state`, `hospital_address`, `notes`, timestamps.

### `donor_matches` — the join between supply & demand
`request_id` FK (CASCADE), `donor_id` FK→users (CASCADE), `status`('pending'/'contacted'/'confirmed'/'donated'/'declined'), `UNIQUE(request_id,donor_id)`, `created_at`.

### `donation_history` — immutable record of completed donations
`donor_id` FK (CASCADE), `request_id` FK (SET NULL), `hospital_id` FK→users (SET NULL), `donation_date`, `blood_type`, `units`(=1), `created_at`.

### `achievements` — gamification badges
`donor_id` FK (CASCADE), `type`, `title`, `description`, `earned_at`, `UNIQUE(donor_id,type)`.

### `audit_logs` — governance trail
`user_id` FK (SET NULL), `action`, `entity_type`, `entity_id`, `old_values` (JSON text), `new_values` (JSON text), `ip_address`, `user_agent`(≤500), `created_at`. → **migrate JSON-text columns to native `JSON`**.

### `blood_banks` — reference directory (seeded, 8 Indian banks)
`name`, `address`, `city`/`state`, `phone`, `email`, `license_number`, `working_hours`, `has_24h_service`, `latitude`/`longitude`, `created_at`.

### `password_resets` — reset tokens
`email` UNIQUE, `token`, `expires_at`, `used_at` NULL, `created_at`. (One active token per email via `ON DUPLICATE KEY`.) → consider `token` hashing at rest (Doc 07).

### `testimonials` — moderated social proof
`donor_id` FK→users (SET NULL), `recipient_name`, `story`, `rating`(=5), `is_approved`(=0), `created_at`. (3 approved rows seeded.)

### `messages` — direct messaging *(only in `fix_db.sql`)*
`sender_id` FK→users (CASCADE), `receiver_id` FK→users (CASCADE), `subject`, `content`, `is_read`(=0), **`is_edited`(=0) ← MISSING, must add (DEF-11)**, `created_at`.

### `notifications` — in-app alerts *(only in `fix_db.sql`)*
`user_id` FK→users (CASCADE), `type`, `title`, `message`, `link`, `is_read`(=0), `created_at`.

---

## 4. Indexing plan (performance hardening — NFR-01)

The current schema indexes only PKs, UNIQUE keys, and FK columns implicitly. At scale the hot query
paths need explicit composite indexes:

| Table | Index | Serves |
|---|---|---|
| donor_profiles | `(blood_type, is_available, city)` | donor discovery / matching (FR-18) |
| donor_profiles | `(latitude, longitude)` or spatial `POINT` SPATIAL index | geo ranking (FR-20) |
| blood_requests | `(status, urgency, created_at)` | dashboards & queues |
| blood_requests | `(city, state, patient_blood_type)` | SOS / discovery |
| donor_matches | `(donor_id, status)`, `(request_id, status)` | match views, donor status |
| messages | `(receiver_id, is_read)`, `(sender_id, receiver_id, created_at)` | unread badge, thread load |
| notifications | `(user_id, is_read, created_at)` | bell list |
| donation_history | `(donor_id, donation_date)` | leaderboard period filters |
| audit_logs | `(action, created_at)`, `(user_id, created_at)` | activity filters |

**Geo upgrade:** store location as a `POINT` with a `SPATIAL` index and use `ST_Distance_Sphere`
instead of app-side Haversine over a full scan — turns matching from O(N) into an index range.

---

## 5. Data-integrity & lifecycle rules

1. **Cool-off invariant** — a donor is "eligible" only if `last_donation_date` is NULL or ≥ cool-off
   days ago. The canonical cool-off value MUST be **one constant** shared by `isDonorEligible()`,
   the eligibility questionnaire, and the welcome email (DEF-01 — today they disagree: 90 vs 56 days).
2. **Donation atomicity** — recording a donation MUST be one transaction touching
   `donation_history` + `donor_profiles` + `notifications` (FR-25, already transactional).
3. **Enum discipline** — `role`, `blood_type`, `urgency`, `status` (request & match), `tier` MUST be
   `ENUM` or FK-to-lookup, not free `VARCHAR` (prevents DEF-05/06).
4. **Soft delete** — introduce `deleted_at` on `users`, `blood_requests` for retention/audit instead
   of cascade hard-deletes (FR-49).
5. **PII columns** — `phone`, `address`, `date_of_birth`, lat/lng are PII; classify & protect per Doc 07.

---

## 6. Canonical consolidated DDL (target)

Ship a single, migration-numbered, idempotent MySQL file set. Sketch:

```sql
-- schema/001_init.sql  (replaces database.sql, database_mysql.sql, fix_db.sql)
SET sql_mode = 'STRICT_ALL_TABLES';

CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(255) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,
  role          ENUM('donor','hospital','admin') NOT NULL DEFAULT 'donor',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  email_verified_at TIMESTAMP NULL,
  deleted_at    TIMESTAMP NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- … donor_profiles WITH donation_points, is_verified, SPATIAL location …
-- … messages WITH is_edited …   -- closes DEF-11/17/18
CREATE TABLE messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  sender_id   INT NOT NULL,
  receiver_id INT NOT NULL,
  subject     VARCHAR(255) NULL,
  content     TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  is_edited   TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_msg_inbox (receiver_id, is_read),
  KEY ix_msg_thread (sender_id, receiver_id, created_at),
  CONSTRAINT fk_msg_sender   FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Migrations are forward-only, numbered, and recorded in a `schema_migrations` table so every
environment converges deterministically (Doc 09).

---

## 7. Seed & fixtures

- `blood_banks` — 8 real Indian banks (AIIMS, Rotary, KEM, Lilavati, Apollo, NIMHANS, PGI, SGPGI).
- `testimonials` — 3 approved stories.
- Admin user — created by `setup_admin.php` (`admin@bloodsystem.com`), idempotent via
  `ON DUPLICATE KEY UPDATE`. **Rotate this default credential before any non-local deploy** (Doc 07).

*Back to the [Documentation Index](00-Documentation-Index.md).*

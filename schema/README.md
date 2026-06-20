# Database Schema & Migrations

Forward-only, numbered MySQL migrations. This directory is the **single source of truth** for the
schema and replaces the three legacy files (`database.sql`, `database_mysql.sql`, `fix_db.sql`),
which should be removed once this is adopted. See `docs/04-Data-Model-and-Database-Schema.md`.

| File | What it does | Apply when |
|---|---|---|
| `001_init.sql` | Full consolidated schema (all 13 tables incl. `messages` with `is_edited`, `notifications`, `donation_points`, `is_verified`), indexes, seed data, and a `schema_migrations` ledger. Drop-in compatible with the current app. | Always, first, on a fresh DB. |
| `002_enum_and_index_hardening.sql` | Converts closed-set columns to `ENUM` (closes DEF-05/06 at the DB layer); optional native-JSON audit + spatial geo blocks (commented). | After the app validates the same value sets (docs/15 Phase 0.3). |
| `003_async_and_rate_limit.sql` | Adds `rate_limits` (durable fixed-window counters, DEF-12) and `notification_queue` (durable email outbox drained by the worker, DEF-03). | Always, after 001. |
| `004_email_change_verification.sql` | Adds `email_change_requests` (hashed, expiring tokens) so an account email only swaps after the new address proves ownership (DEF-07). | Always, after 001. |

## Apply

```bash
# create the database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS lifeline_db_mysql CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# run migrations in order
mysql -u root -p lifeline_db_mysql < schema/001_init.sql
mysql -u root -p lifeline_db_mysql < schema/003_async_and_rate_limit.sql
mysql -u root -p lifeline_db_mysql < schema/004_email_change_verification.sql
mysql -u root -p lifeline_db_mysql < schema/002_enum_and_index_hardening.sql   # optional/when ready
```

Each migration records itself in `schema_migrations`, so re-running is safe and every environment
converges deterministically. **Never edit a migration that has shipped** — add a new numbered file.

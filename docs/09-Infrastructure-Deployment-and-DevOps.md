# 09 · Infrastructure, Deployment & DevOps

**Status:** 🟡 In review · **Owner:** Platform/DevOps · **Traces to:** Doc 03 §8, Doc 12, NFR-03…06,10.

How LifeLine is built, configured, shipped, and operated — from a single pilot VM to a multi-region
fleet — without leaving the PHP/MySQL stack.

---

## 1. Runtime & dependencies

| Component | Version | Role |
|---|---|---|
| PHP | **8.1+** (target 8.3) | App runtime; PDO, `password_*`, `random_bytes`, `finfo` |
| Web server | Apache 2.4 **or** Nginx + **PHP-FPM** | TLS termination (or behind LB), static serving |
| MySQL | **8.0+** InnoDB, utf8mb4 | System of record |
| Redis | 7.x | Sessions, rate-limit, cache, queue/pubsub (added for scale) |
| jQuery | 3.7.x (self-hosted) | Client interactivity |
| PHPMailer | optional | SMTP; `mail()` fallback |

PHP extensions required: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `json`.

---

## 2. Configuration (12-factor)

All config is environment-driven via `.env` → `Config::`. Canonical keys (consolidate the
drifting examples — `.env`, `.env.example`, `app/lifeline/.env*` — into **one** documented template):

```
APP_ENV=production            APP_DEBUG=false        APP_NAME="LifeLine Blood Network"
APP_URL=https://…             APP_PATH=
DB_CONNECTION=mysql  DB_HOST=  DB_PORT=3306  DB_DATABASE=  DB_USERNAME=  DB_PASSWORD=
MAIL_HOST=  MAIL_PORT=587  MAIL_USERNAME=  MAIL_PASSWORD=  MAIL_FROM_ADDRESS=  MAIL_FROM_NAME=  MAIL_ENCRYPTION=tls
SESSION_LIFETIME=1440
MAX_LOGIN_ATTEMPTS=5  LOGIN_LOCKOUT_MINUTES=15
# scale additions:
SESSION_DRIVER=redis  REDIS_URL=  QUEUE_DRIVER=redis  GEO_PROVIDER=  CDN_URL=
```

**Production rules:** `APP_DEBUG=false`; secrets from a secrets manager, **not** a file on disk;
`.env*` files never committed (they currently sit in the tree — gitignore + scrub history).

---

## 3. Environments & promotion

| Env | Purpose | Data | Deploy |
|---|---|---|---|
| **Local** | Dev | seed + `setup_admin.php` | `php -S localhost:8000 -t app/` |
| **CI** | Automated tests | ephemeral MySQL (Docker) | on every PR |
| **Staging** | Pre-prod mirror | anonymized prod-like | auto on merge to `main` |
| **Production** | Live | real (encrypted, backed up) | tagged release, approved |

Promotion path: PR → CI green → merge → staging → smoke + QA (Doc 11) → tagged release → prod.
Forward-only, numbered DB migrations run as a gated deploy step against a `schema_migrations` ledger.

---

## 4. Topologies

**T0 — single VM (pilot):** Apache/Nginx + PHP-FPM + MySQL + Redis on one host. Matches the current
two-command install. Good for a district/city pilot.

**T1 — horizontally scaled (Doc 03 §8):** CDN for static assets → load balancer/TLS → N stateless
PHP-FPM nodes (identical image) → Redis (sessions/cache/queue) + worker pool (async email, SOS,
geocode) → ProxySQL → MySQL primary + read replicas. Code is identical to T0; only config changes.

**Containerization:** ship an OCI image (`php:8.3-fpm` base + app + self-hosted jQuery), Nginx
sidecar, Redis and MySQL as managed services. One image runs web nodes and (with a different command)
queue workers.

---

## 5. CI/CD pipeline

```
PR ─► [lint: php -l, phpcs PSR-12, eslint, stylelint]
   ─► [static: psalm/phpstan]
   ─► [unit + integration tests on Dockerized MySQL]  (Doc 11)
   ─► [security: dependency/CVE scan, secret scan, SAST]
   ─► [build image]
merge ─► [migrate + deploy staging] ─► [smoke + e2e] ─► [manual approve] ─► [deploy prod (blue/green)]
```

- **Zero-downtime:** blue/green or rolling; health-gated cutover via `healthz`.
- **Migrations:** run before traffic shift; backward-compatible (expand/contract pattern) so old and
  new app versions coexist during rollout.
- **Rollback:** previous image + down-migration (or compatible-forward) is one command.

The current repo carries **Vercel** artifacts (`.vercel/`), which is a poor fit for a stateful
PHP/MySQL app — standardize on container hosting (managed Kubernetes or a PaaS that runs PHP-FPM +
MySQL + Redis) and remove the Vercel config to avoid confusion.

---

## 6. Data operations

- **Backups:** automated nightly full + binlog for **PITR**; **test restores monthly** (NFR-06,
  RPO ≤ 5 min / RTO ≤ 1 h).
- **Migrations:** numbered, idempotent, reviewed; never edit a shipped migration.
- **Seeds/fixtures:** `blood_banks`, sample data, and an **enforced admin-credential rotation** step.
- **PII:** encrypted at rest; staging/CI use anonymized copies only.

---

## 7. Observability

| Signal | Tool/Source | Use |
|---|---|---|
| Health | `healthz` (shallow, anon) + deep `/health` (auth) | LB checks, alerts (fix DEF-15) |
| Metrics | RPS, p50/p95/p99 latency, error rate, queue depth, email success, DB pool | Dashboards, SLO tracking (NFR-01/05) |
| Logs | Structured JSON access + app + security logs, centralized | Debug, audit, forensics |
| Traces | Request-ID propagation across app→worker | Latency hunts |
| Alerts | Uptime, error-rate, queue backlog, replica lag, cert expiry, SOS spike | On-call paging |

SLOs: 99.9% availability; p95 < 400 ms; SOS enqueue < 200 ms; alert when burn-rate threatens budget.

---

## 8. DevOps acceptance gate

- [ ] One canonical `.env.example`; secrets out of repo; `.env*` gitignored + history scrubbed.
- [ ] Containerized; identical image across envs; Vercel config removed.
- [ ] CI runs lint + static + tests + security on every PR; red blocks merge.
- [ ] Numbered migrations with a `schema_migrations` ledger; expand/contract rollout proven on staging.
- [ ] Backups automated + restore-tested; PITR verified.
- [ ] Dashboards + alerts live for the SLOs; on-call rota defined.

*Back to the [Documentation Index](00-Documentation-Index.md).*

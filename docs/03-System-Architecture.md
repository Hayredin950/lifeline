# 03 · System Architecture

**Status:** 🟡 In review · **Owner:** Engineering · **Traces to:** Doc 02, Doc 04, Doc 12.

This document describes the *how* at the structural level: the layers, the request lifecycle, the
session and security model, and the target topology that lets the same code scale from a single VM
to a continent.

---

## 1. Architectural style

LifeLine is a **server-rendered, multi-page web application (MPA)** on a classic **LAMP** stack with
a thin AJAX layer for the live surfaces (messaging, notifications). There is no framework and no
build step: PHP composes HTML, the browser enhances it with jQuery.

Design tenets:

- **Stateless app tier.** All durable state lives in MySQL; the only server-local state is the PHP
  session — which is externalized to Redis before the second app node (NFR-04).
- **Shared kernel via includes.** Every page bootstraps the same `includes/` kernel: config → DB →
  functions → header/footer. There is exactly one DB connection policy, one auth model, one CSRF
  implementation.
- **Convention over configuration.** Role folders (`donor/`, `hospital/`, `admin/`) + guard
  functions (`requireDonor()`, …) make authorization legible at the top of every file.
- **Progressive enhancement.** Pages work as plain HTML forms; jQuery adds polling, optimistic UI,
  toasts, and inline actions on top.

---

## 2. Layered view

```
┌──────────────────────────────────────────────────────────────────────┐
│  CLIENT (browser)                                                      │
│  HTML5 · CSS3 design system · jQuery 3.x (DOM, AJAX, polling) ·        │
│  app.js (particles, toasts, charts, interactions)                      │
└───────────────▲───────────────────────────────┬──────────────────────┘
                │ HTML over HTTPS                │ JSON over XHR (api/*)
┌───────────────┴───────────────────────────────▼──────────────────────┐
│  PRESENTATION (PHP page controllers)                                   │
│  index, login, register, find_donors, emergency, leaderboard, …        │
│  donor/* · hospital/* · admin/* · api/*                                │
│  Each page = controller + view; includes header.php / footer.php       │
└───────────────▲───────────────────────────────────────────────────────┘
                │ require_once
┌───────────────┴───────────────────────────────────────────────────────┐
│  DOMAIN / SHARED KERNEL  (app/lifeline/includes/)                      │
│  config.php       — 12-factor .env loader (Config::)                   │
│  db.php           — PDO bootstrap + session policy                     │
│  functions.php    — auth guards, CSRF, flash, pagination, blood-type   │
│                     compatibility, eligibility, geo, audit, CSV, upload│
│  email_service.php— EmailService (PHPMailer→mail() fallback)           │
│  header/footer    — chrome, nav, flash rendering                       │
└───────────────▲───────────────────────────────────────────────────────┘
                │ PDO (prepared statements, utf8mb4)
┌───────────────┴───────────────────────────────────────────────────────┐
│  DATA (MySQL 8 / InnoDB)                                                │
│  users · donor_profiles · hospital_profiles · blood_requests ·         │
│  donor_matches · donation_history · achievements · audit_logs ·        │
│  blood_banks · password_resets · testimonials · messages · notifications│
└───────────────▲───────────────────────────────────────────────────────┘
                │ outbound
┌───────────────┴───────────────────────────────────────────────────────┐
│  EXTERNAL SERVICES                                                      │
│  SMTP (transactional email) · OSM Nominatim (geocoding) ·              │
│  ui-avatars.com (fallback avatars)                                     │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. The shared kernel (`includes/`)

This is the architectural heart. Every entrypoint includes it, so cross-cutting concerns are defined
exactly once.

| File | Responsibility | Key surface |
|---|---|---|
| `config.php` | Loads `.env` into a static `Config` registry; typed getters (`getInt`, `getBool`); DB & mail config; `isDebug`/`isProduction`. | `Config::get()`, `Config::getDatabaseConfig()` |
| `db.php` | Builds the PDO DSN (`mysql:host=…;charset=utf8mb4`), sets `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, **`EMULATE_PREPARES=false`**, **`PERSISTENT=true`**; configures secure session cookies and `session_start()`. | `$pdo` |
| `functions.php` | The domain library (621 lines): auth state & guards, CSRF, flash, login rate-limiting, password policy, sanitizers, pagination, blood-type maps, eligibility & donor-status, profile/photo helpers, geocode + Haversine, audit logging, CSV export, base-URL resolution. | `requireRole()`, `csrfToken()`, `getCompatibleDonorBloodTypes()`, `getDonorCurrentStatus()`, `auditLog()` |
| `email_service.php` | `EmailService` static class. Uses PHPMailer/SMTP if the class exists, else PHP `mail()`; if SMTP unconfigured, **logs instead of erroring** (dev-safe). HTML templates for welcome/reset/request. | `EmailService::send*()` |
| `header.php` / `footer.php` | Page chrome, role-aware nav, unread-message badge, flash banner. | — |

> **Architectural note (DEF-16):** persistent PDO connections (`ATTR_PERSISTENT=true`) combined with
> per-request `session_start()` is fine on a single node but interacts badly with connection pools at
> scale. Doc 12 replaces this with an external pooler (ProxySQL) and reconsiders persistence.

---

## 4. Identity, session & authorization model

```
Guest ── register/login ──► users(role) ──► session{user_id, role, email}
                                              │
        requireAuth() ─────────────────────────┤ gate every protected page
        requireDonor()/requireHospital()/requireAdmin() ─ role gate
```

- **Authentication** — `login.php` looks up `users` by email + `is_active`, `password_verify()`,
  regenerates the session, stores `user_id`/`role`/`email`, audits the event. Failures feed the
  rate-limiter.
- **Authorization** — page controllers call a guard as their first statement. Guards live in
  `functions.php`; they redirect with a flash on failure. Role folders make the intended audience
  obvious.
- **CSRF** — `csrfToken()` issues a 32-byte random token stored in session; `validateCsrf()` checks
  it with `hash_equals()` on every state-changing POST. Forms embed it as a hidden field; the
  messaging API posts it as a field.
- **Session hardening** — production sets `Secure`, `HttpOnly`, `SameSite=Strict`, strict-mode, and a
  configurable lifetime.

See Doc 07 for the full threat model and the gaps in this model.

---

## 5. Request lifecycle (server-rendered page)

```
1. HTTP request → web server (Apache/Nginx+PHP-FPM) → PHP page (e.g. donor/dashboard.php)
2. require includes/db.php  → Config::load() → new PDO → session_start()
3. require functions.php     → guard (requireDonor) → redirect on failure
4. (POST) validateCsrf() → validate input → prepared-statement write → setFlash() → redirect (PRG)
5. (GET)  prepared-statement reads → build view model
6. include header.php → echo HTML view → include footer.php
7. Browser: app.js + jQuery enhance the DOM
```

The **Post/Redirect/Get** pattern (write → `redirect()` → GET) is used consistently, so refreshes
don't double-submit and flash messages survive one hop.

## 6. Request lifecycle (AJAX surface — messaging)

```
messages.php renders shell + embeds csrf_token
  └─ jQuery polls api/get_messages.php?conversation=ID every 3 s
       → JSON {messages[], current_user_id}; marks inbound is_read=1
  └─ send → POST api/send_message.php (csrf) → INSERT messages + notifications
  └─ edit → POST api/edit_message.php (csrf, sender-scoped) → UPDATE …, is_edited=1
  └─ delete → POST api/delete_message.php (csrf, sender-scoped) → DELETE
Client de-dupes via (id-content-is_edited) fingerprints; optimistic append on send.
```

This is a **pragmatic near-real-time** design. Doc 06 specifies its evolution to SSE/WebSocket and
the server-push fan-out for SOS.

---

## 7. Cross-cutting concerns

| Concern | Mechanism today | Target (Doc 12/07) |
|---|---|---|
| Config | `.env` via `Config` | Same; secrets via vault/KMS in prod |
| Errors | `APP_DEBUG` toggles verbose vs generic; `404.php`/`500.php` | Centralized structured logging |
| Audit | `auditLog()` → `audit_logs` | Extend to **all** mutations (FR-46) |
| Email | PHPMailer/`mail()` w/ dev log fallback | Provider API (SES/SendGrid) + queue |
| Geocoding | Nominatim on save | Cached geocode service; rate-limit-aware |
| Sessions | Local files | **Redis** (NFR-04) |
| Rate limiting | Per-session counters | Redis/edge per-IP+identifier (DEF-12) |

---

## 8. Deployment topologies

**T0 — Single VM (pilot / district):** Apache + PHP-FPM + MySQL + Redis on one box. Two-command
install. Serves a city pilot comfortably.

**T1 — Scaled (state / nation):**

```
            ┌────────── CDN (static: css/js/images) ──────────┐
Internet ─► │  Load Balancer / TLS  │
            └──────────┬────────────┘
        ┌──────────────┼───────────────┐
        ▼              ▼               ▼
   PHP-FPM node   PHP-FPM node    PHP-FPM node   (stateless; identical image)
        └──────┬───────┴───────┬───────┘
               ▼               ▼
          Redis (sessions,   Queue (SOS/email fan-out)
          rate-limit, cache)   │
               │               ▼
               ▼          Worker pool (async email, geocode)
        ProxySQL / MySQL primary ──► read replicas
```

The application code is **identical** across T0 and T1 — the only changes are configuration
(session handler → Redis, email → queue). That portability is the architectural payoff of the
stateless, kernel-centric design and is the basis of the scale story in Doc 12.

*Back to the [Documentation Index](00-Documentation-Index.md).*

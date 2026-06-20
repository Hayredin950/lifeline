# 05 · Module & Page Specifications

**Status:** 🟡 In review · **Owner:** Engineering · **Traces to:** Doc 02 (FR), Doc 04 (tables), Doc 07 (DEF).

Every page/endpoint is specified as a contract: **route · actor/guard · methods · data touched ·
business logic · CSRF · known defects**. Paths are relative to `app/lifeline/`.

Legend: 🔒 guarded · 🌐 public · ✉️ sends email · 🗺️ geocodes · 📝 audited.

---

## 1. Shared kernel (`includes/`)

| Module | Public surface | Notes |
|---|---|---|
| `config.php` | `Config::get/getInt/getBool`, `getDatabaseConfig`, `getMailConfig`, `isDebug`, `isProduction`, `isEmailConfigured` | Static `.env` registry; falls back to `getenv()`/`$_ENV`. |
| `db.php` | `$pdo` | MySQL DSN + utf8mb4; exception mode; **persistent** connections (DEF-16); secure session policy in prod. |
| `functions.php` | auth (`isLoggedIn/isAdmin/isDonor/isHospital`, `requireAuth/requireAdmin/requireDonor/requireHospital`), CSRF (`csrfToken/validateCsrf`), flash, rate-limit (`isRateLimited/recordLoginAttempt/...`), `validatePassword`, sanitizers, `getPaginationParams/renderPagination`, blood-type maps, `isDonorEligible/getDonorCurrentStatus`, `getProfilePic/handleImageUpload`, `geocodeLocation/calculateDistance`, `auditLog`, `exportToCsv`, `baseUrl/fullBaseUrl` | The domain library. See Doc 02 for the rules each helper enforces. |
| `email_service.php` | `EmailService::send`, `sendDonorWelcome`, `sendHospitalWelcome`, `sendPasswordReset`, `sendBloodRequestNotification` | PHPMailer→`mail()` fallback; dev-safe logging when unconfigured. |

---

## 2. Authentication & account pages

### `login.php` 🌐📝
- **GET** renders the login form (+CSRF). **POST** validates CSRF, sanitizes email, checks
  `isRateLimited()`, looks up `users WHERE email=? AND is_active`, `password_verify()`.
- On success: `session_regenerate_id(true)`, set session, `clearLoginAttempts()`, audit `login`,
  route by role. On failure: `recordLoginAttempt()`, audit `login_failed`, show remaining attempts.
- **Tables:** `users` (SELECT). **Solid**; no defects.

### `register.php` 🌐✉️
- **GET** shows role selector, then role-specific form. **POST** validates CSRF + email + password
  policy + confirmation + uniqueness; INSERT `users`, then INSERT `donor_profiles` **or**
  `hospital_profiles`; auto-login (regenerate session); send welcome email.
- **Tables:** `users`, `donor_profiles`/`hospital_profiles` (INSERT). **Gaps:** non-credential
  fields validated by trim only; no email verification before activation (FR-08).

### `logout.php` 🔒
- `session_unset()` + `session_destroy()` + flash + redirect. Idempotent.

### `forgot_password.php` 🌐✉️
- **POST** generates `bin2hex(random_bytes(32))`, 24-h expiry, UPSERT `password_resets`, emails the
  link; always shows success (anti-enumeration, FR-07).

### `reset_password.php` 🌐
- Validates token existence/used/expiry; on valid POST, password policy + confirm, UPDATE
  `users.password`, mark token `used_at`. One-time use. **Gap:** no rate-limit on token guesses.

---

## 3. Public discovery pages

### `index.php` 🌐
- Homepage. Live counts (donors, hospitals, open/fulfilled requests, total donations), featured
  donors, top testimonials, blood-type distribution, recent urgent requests.
- **Tables:** read-only aggregates over `donor_profiles`, `hospital_profiles`, `blood_requests`,
  `donation_history`, `testimonials`. Properly escaped.

### `find_donors.php` 🌐/🔒-gated contact
- Search donors by blood type / city / state. Excludes donors inside the 90-day cool-off unless
  `show_all=1`. **Contact details (phone/email) shown only to logged-in users when the donor is
  `available` (or to admins).** Uses `getDonorCurrentStatus()`.
- **Tables:** `donor_profiles` ⨝ `users`. Parameterized `LIKE` — SQLi-safe.

### `blood_banks.php` 🌐
- Searchable directory over `blood_banks` (name/city/state `LIKE`), ordered by state/city/name.

### `eligibility.php` 🌐
- Client-side 10-question self-assessment (age, weight, hemoglobin, recent donation, infection,
  tattoo, bloodborne disease, pregnancy, alcohol, chronic illness). No persistence. **DEF-01:** its
  "3 months / 56 days" copy must match the server's canonical cool-off constant.

### `leaderboard.php` 🌐
- Ranks donors by period donations (all/year/month) then `total_donations`, `donation_points`.
  Podium for top-3, tiers, verified badge, active/inactive (vs −90 days).
- **Tables:** `donor_profiles` ⨝ `users`, subqueries on `donation_history`, `donor_matches`.
  **Requires** `donation_points`/`is_verified` (DEF-18).

### `testimonials.php` 🌐
- Lists `is_approved=1` testimonials. "Share Your Story" link shown to logged-in users. Submission
  flow is incomplete (FR-42).

### `view_request.php` 🌐/🔒-action 📝-able
- Shows one `blood_requests` row (+ hospital). Lists compatible donors. A **donor** who is
  `available` may **express interest** (POST, CSRF): UPSERT `donor_matches` → `contacted`, INSERT a
  `notifications` row for the hospital. **DEF-20:** donor status is read at render but acted on at
  POST — possible TOCTOU; re-check at POST.

---

## 4. Emergency

### `emergency.php` 🌐✉️📝 — **highest-risk surface**
- **POST** (any visitor): create a `critical`, `open` `blood_requests` row with **NULL hospital_id**
  and contact info folded into `notes`; find compatible available donors; **loop and email each**
  via `EmailService::sendBloodRequestNotification()`; audit `emergency_sos`.
- **Defects:**
  - **DEF-02 🔴** unauthenticated + unthrottled → spam/abuse and unsolicited-email vector. Needs
    rate-limit + CAPTCHA + OTP (FR-30).
  - **DEF-03 🟠** synchronous fan-out loop blocks the request and can time out / partially send.
    Must enqueue (FR-31).

---

## 5. Messaging

### `messages.php` 🔒
- Renders conversation list (bidirectional grouping, last message, unread counts) + active thread.
  Embeds CSRF for the API. jQuery polls every 3 s; optimistic send; hover copy/edit/delete.
- **DEF-04 🟠 (XSS):** message content rendered with `nl2br()` and injected into JS `onclick`
  via fragile quote-escaping — must be escaped/encoded properly and length-capped.

### `api/get_messages.php` 🔒 (GET, JSON)
- Returns last 100 messages of a conversation (bidirectional), marks inbound `is_read=1`.
  **DEF-08:** doesn't assert the caller is a party beyond using their own id on both sides — verify
  conversation membership; add pagination beyond 100.

### `api/send_message.php` 🔒 (POST, CSRF, JSON)
- INSERT `messages`; INSERT `notifications` for receiver. **Gaps:** no receiver-exists check, no
  length cap, raw content (feeds DEF-04).

### `api/edit_message.php` 🔒 (POST, CSRF)
- `UPDATE messages SET content=?, is_edited=1 WHERE id=? AND sender_id=?` (ownership-scoped).
  **DEF-11 🔴:** `is_edited` column does not exist in any schema file — breaks on clean install.

### `api/delete_message.php` 🔒 (POST, CSRF)
- `DELETE … WHERE id=? AND sender_id=?` (hard delete; sender-only). Orphan `notifications` not cleaned.

---

## 6. Donor area (`donor/`) 🔒 `requireDonor()`

### `donor/dashboard.php`
- Profile + computed status (`getDonorCurrentStatus`: available/busy/cool_off), active engagements
  (`donor_matches` ⨝ `blood_requests` ⨝ `hospital_profiles`), compatible open requests, last 5
  notifications, tier/points. Read-only.

### `donor/edit_profile.php`
- Update `donor_profiles`; optional email change (uniqueness-checked) and password change
  (current-password verified). Photo upload via `handleImageUpload()`. **DEF-10:** upload validated
  by extension/size only — add MIME/content validation; **DEF-07:** email change unverified.

---

## 7. Hospital area (`hospital/`) 🔒 `requireHospital()`

### `hospital/dashboard.php`
- Own `blood_requests` (open-first, critical-first, recent), last 5 notifications. Read-only.

### `hospital/edit_profile.php`
- Update `hospital_profiles`; email/password change like donor. (Same DEF-07.)

### `hospital/create_request.php`
- INSERT `blood_requests` for the authenticated hospital; city/state prefilled from profile.
  **DEF-05:** `patient_blood_type`/`urgency` not enum-validated server-side.

### `hospital/request_matches.php` 📝-partial
- Ownership-checked (`WHERE id=? AND hospital_id=?`). Lists compatible available donors. POST updates
  match status and/or request status. On `donated`: transactional — INSERT `donation_history`,
  bump `donor_profiles` (total/points/tier/last_donation_date), INSERT donor `notifications`.
  **DEF-06:** match/request status not validated against an allowed set.

---

## 8. Admin area (`admin/`) 🔒 `requireAdmin()`

### `admin/dashboard.php`
- Stat cards (donor/hospital counts, open & critical requests) + nav.

### `admin/manage_donors.php` / `manage_hospitals.php` / `manage_requests.php`
- Paginated lists (25/page) with edit/delete links. Joins to `users` for email/active.

### `admin/edit_record.php`
- Edit donor/hospital/request by `type`+`id`; UPDATE the profile/request (+`users.email/is_active`).
  **DEF-13:** edits are **not** written to `audit_logs` (FR-46); **DEF-05** enum gaps repeat here.

### `admin/delete_record.php` 📝
- Confirm + DELETE user (cascade) or request; audited. **Hard delete** — no retention (FR-49).

### `admin/activity.php`
- Audit-log viewer with action/date filters + stat cards; CSV export of donors/hospitals/requests.
  **DEF-14 🟡:** CSV cells not neutralized against formula injection (`=,+,-,@`).

---

## 9. Ops & error pages

| Page | Behavior | Defect |
|---|---|---|
| `health.php` 🌐 | JSON: DB ping (3 s timeout, 503 on fail), email-configured, env, debug, version. | **DEF-15:** leaks version/env/debug to anonymous callers. |
| `404.php` / `500.php` | Friendly static error pages. | — |

---

## 10. Defect index (consolidated)

| ID | Sev | Where | Summary | Closes when |
|---|---|---|---|---|
| DEF-01 | 🟡 | cool-off | 90 vs 56 vs "3 months" inconsistency | one shared constant |
| DEF-02 | 🔴 | emergency.php | unauth, unthrottled SOS | rate-limit+CAPTCHA+OTP |
| DEF-03 | 🟠 | emergency.php | sync email fan-out | queue/worker |
| DEF-04 | 🟠 | messaging | stored-content XSS | encode + cap length |
| DEF-05 | 🟡 | requests/edit | no enum validation | ENUM/whitelist |
| DEF-06 | 🟡 | matches | status state-machine unchecked | server FSM |
| DEF-07 | 🟠 | edit_profile | email change unverified | verify-new-email flow |
| DEF-08 | 🟠 | get_messages | weak conversation authz | membership check |
| DEF-09 | 🟡 | profiles | geocode not invoked on save | call on write |
| DEF-10 | 🟠 | uploads | extension-only validation | MIME/content + isolate |
| DEF-11 | 🔴 | messages | `is_edited` column missing | consolidated schema |
| DEF-12 | 🟠 | rate-limit | per-session only (bypassable) | Redis per-IP+id |
| DEF-13 | 🟠 | admin edit | edits unaudited | auditLog on all mutations |
| DEF-14 | 🟡 | CSV | formula injection | neutralize cells |
| DEF-15 | 🟡 | health | info disclosure | minimal anon payload |
| DEF-16 | 🟡 | db.php | persistent PDO at scale | pooler/ProxySQL |
| DEF-17/18/19 | 🟠/🟠/🟡 | schema | fragmentation/dialect drift | consolidate (Doc 04 §6) |
| DEF-20 | 🟡 | view_request | donor-status TOCTOU | re-check at POST |

Each `DEF-xx` is a checkbox in **Doc 15** and a negative test in **Doc 11**.

*Back to the [Documentation Index](00-Documentation-Index.md).*

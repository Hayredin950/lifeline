# 02 · Software Requirements Specification (SRS)

**Status:** 🟡 In review · **Owner:** Engineering + Product · **Traces to:** every other doc.

Each requirement is **numbered**, **testable**, and tagged with a **status**:

- ✅ **Built** — present and working in the current codebase.
- 🟨 **Partial** — present but incomplete, inconsistent, or insecure (see linked `DEF-xx`).
- ⬜ **Target** — required for the billion-dollar build; not yet implemented.

Obligation graded with **MUST / SHOULD / MAY** (RFC 2119). Functional = `FR-xx`, non-functional =
`NFR-xx`. Defects are defined in Docs 04/05/07 and tracked in Doc 15.

---

## 1. Actors

| Actor | Definition |
|---|---|
| **Guest** | Unauthenticated visitor. |
| **Donor** | `users.role='donor'` + `donor_profiles` row. |
| **Hospital** | `users.role='hospital'` + `hospital_profiles` row. |
| **Admin** | `users.role='admin'`. |
| **System** | Background/automatic behavior (email, geocode, audit). |

---

## 2. Functional requirements

### 2.1 Identity, authentication & accounts (`FR-Auth`)

| ID | Requirement | Status |
|---|---|---|
| FR-01 | The system MUST let a Guest register as a Donor or Hospital, creating a `users` row plus the role profile. | ✅ |
| FR-02 | Passwords MUST be stored only as `password_hash()` (bcrypt/`PASSWORD_DEFAULT`) and verified with `password_verify()`. | ✅ |
| FR-03 | Registration MUST enforce password strength: ≥8 chars, upper, lower, digit, special (`validatePassword()`). | ✅ |
| FR-04 | Login MUST be rate-limited per identifier (default 5 attempts / 15-min lockout). | ✅ (session-scoped — see DEF-12) |
| FR-05 | On successful login the system MUST regenerate the session ID and route by role. | ✅ |
| FR-06 | The system MUST support self-service password reset via a single-use, 24-hour, cryptographically-random token (`password_resets`). | ✅ |
| FR-07 | Password-reset request MUST NOT reveal whether an email exists (anti-enumeration). | ✅ |
| FR-08 | Email change MUST require re-verification of the new address before it becomes the login identity. | ⬜ (DEF-07) |
| FR-09 | The system MUST support 2-factor authentication (TOTP/SMS) for Hospital and Admin accounts. | ⬜ |
| FR-10 | Sessions MUST use `HttpOnly`, `Secure`, `SameSite=Strict` cookies in production. | ✅ (prod branch in `db.php`) |

### 2.2 Profiles & geolocation (`FR-Geo`)

| ID | Requirement | Status |
|---|---|---|
| FR-11 | Donors MUST maintain: full name, phone, blood type, address, city/state/country, DOB, gender, availability, last donation date. | ✅ |
| FR-12 | Hospitals MUST maintain: name, phone, address, city/state/country, license number, verification flag. | ✅ |
| FR-13 | The system SHOULD geocode city/state to lat/lng for distance ranking (`geocodeLocation()` via Nominatim). | 🟨 (helper exists; not consistently invoked on save — DEF-09) |
| FR-14 | Distance between two points MUST use the Haversine formula (`calculateDistance()`). | ✅ (helper present) |
| FR-15 | Donors MUST be able to upload a profile photo (≤2 MB, jpg/png/webp) or fall back to a generated initials avatar. | ✅ |
| FR-16 | Profile-photo uploads MUST be validated by MIME content (not just extension) and stored outside the web root or served via a sanitizing handler. | 🟨 (extension-only — DEF-10) |

### 2.3 Blood-type compatibility & matching (`FR-Match`)

| ID | Requirement | Status |
|---|---|---|
| FR-17 | The system MUST encode ABO/Rh donor→patient compatibility in both directions (`getCompatibleDonorBloodTypes`, `getPatientBloodTypesForDonor`). | ✅ |
| FR-18 | Donor discovery MUST filter to active users, available donors, and compatible types, ranked by locality. | ✅ (locality via `city LIKE`; geo-distance ranking is Target) |
| FR-19 | A hospital viewing a request MUST see a ranked list of compatible donors and current match status. | ✅ |
| FR-20 | Matching SHOULD rank by true geo-distance, recency of availability, and donor reliability — not just city string match. | ⬜ |
| FR-21 | The system MUST validate `patient_blood_type` against the 8-value enum on write. | 🟨 (free-text today — DEF-05) |

### 2.4 Requests & donation lifecycle (`FR-Donation`)

| ID | Requirement | Status |
|---|---|---|
| FR-22 | A Hospital MUST be able to create a blood request (type, units, urgency, required date, location, notes). | ✅ |
| FR-23 | A request MUST move through `open → fulfilled / cancelled`; only its owning hospital (or admin) may change it. | ✅ |
| FR-24 | A donor↔request match MUST move through `pending → contacted → confirmed → donated / declined`. | ✅ |
| FR-25 | Recording a donation MUST be transactional and MUST: insert `donation_history`, set `last_donation_date`, increment `total_donations`, award points, recompute tier, and notify the donor. | ✅ (`request_matches.php`, wrapped in a transaction) |
| FR-26 | A Donor MUST be able to express interest in an open, compatible request (`view_request.php`). | ✅ |
| FR-27 | The system MUST enforce a post-donation cool-off before a donor is shown as eligible. | 🟨 (90 days in `isDonorEligible()` vs "56 days"/"3 months" elsewhere — DEF-01) |
| FR-28 | Match-status transitions MUST be validated against an allowed state machine on the server. | ⬜ (DEF-06) |

### 2.5 Emergency SOS (`FR-Emergency`)

| ID | Requirement | Status |
|---|---|---|
| FR-29 | Any user (incl. Guest) MUST be able to raise an Emergency SOS that creates a `critical` request and notifies compatible, available donors. | ✅ |
| FR-30 | SOS MUST be abuse-resistant: rate-limited per IP/phone, CAPTCHA, and SHOULD require lightweight verification (OTP) before broadcasting. | ⬜ 🔴 (DEF-02) |
| FR-31 | Donor notification fan-out MUST be asynchronous (queue/worker), not a synchronous request-time loop. | ⬜ (DEF-03) |
| FR-32 | SOS notifications MUST honor donor notification preferences and unsubscribe. | ⬜ |

### 2.6 Messaging & notifications (`FR-Msg`)

| ID | Requirement | Status |
|---|---|---|
| FR-33 | Authenticated users MUST be able to exchange direct messages, grouped into conversations, with unread counts. | ✅ (`messages.php`, `api/*`) |
| FR-34 | A user MUST only read/modify messages they are a party to; edit/delete restricted to the sender. | 🟨 (sender-scoped writes ✅; read-side conversation authz weak — DEF-08) |
| FR-35 | Message content MUST be treated as untrusted: stored raw, escaped on output, length-capped, never injected into HTML/JS unescaped. | 🟨 (XSS surface — DEF-04) |
| FR-36 | In-app `notifications` MUST be generated on key events (new message, match, donation recorded). | ✅ |
| FR-37 | The messaging transport SHOULD evolve from 3-second polling to server push (SSE/WebSocket) at scale. | ⬜ (Doc 06) |
| FR-38 | The `messages` table MUST include the `is_edited` column the API writes to. | ⬜ 🔴 (DEF-11 — schema/code mismatch) |

### 2.7 Engagement: gamification & social proof (`FR-Engage`)

| ID | Requirement | Status |
|---|---|---|
| FR-39 | The system MUST track donation tiers (bronze/silver/gold/platinum) and points (+100/donation). | ✅ |
| FR-40 | The system MUST publish a leaderboard filterable by all-time / year / month. | ✅ |
| FR-41 | The system MUST award `achievements` for milestones (unique per donor+type). | 🟨 (table exists; award logic thin) |
| FR-42 | The system MUST display approved `testimonials`; submission MUST be moderated (`is_approved`). | 🟨 (display ✅; submission flow incomplete) |

### 2.8 Directory & self-service (`FR-Directory`)

| ID | Requirement | Status |
|---|---|---|
| FR-43 | The system MUST provide a searchable blood-bank directory (`blood_banks`). | ✅ |
| FR-44 | The system MUST provide a donor-eligibility self-check questionnaire. | ✅ (client-side; criteria MUST match server cool-off — DEF-01) |

### 2.9 Administration & governance (`FR-Admin`)

| ID | Requirement | Status |
|---|---|---|
| FR-45 | Admins MUST view dashboards and CRUD donors, hospitals, and requests. | ✅ |
| FR-46 | All security-relevant actions MUST be written to `audit_logs` (actor, action, entity, before/after, IP, UA). | 🟨 (login/delete logged; **edits not** — DEF-13) |
| FR-47 | Admins MUST be able to export donors/hospitals/requests to CSV. | ✅ |
| FR-48 | CSV export MUST neutralize formula-injection (`=,+,-,@` leading cells). | ⬜ (DEF-14) |
| FR-49 | Destructive admin actions SHOULD be soft-deletes with retention, not hard deletes. | ⬜ |
| FR-50 | Hospital verification (`is_verified`) MUST be an explicit admin workflow with evidence. | ⬜ |

### 2.10 Observability (`FR-Ops`)

| ID | Requirement | Status |
|---|---|---|
| FR-51 | The system MUST expose a health endpoint reporting DB/email/env. | ✅ (`health.php`) |
| FR-52 | The health endpoint MUST NOT leak version, debug, or config detail to unauthenticated callers. | ⬜ (DEF-15) |

---

## 3. Non-functional requirements

| ID | Category | Requirement | Target |
|---|---|---|---|
| NFR-01 | Performance | p95 server render time | **< 400 ms** at 1k RPS |
| NFR-02 | Performance | Emergency SOS broadcast MUST NOT block the request thread | Enqueue < 200 ms; deliver async |
| NFR-03 | Scalability | Stateless app tier; horizontal scale behind a load balancer | 0 → N app nodes, no code change |
| NFR-04 | Scalability | Session store MUST be externalized (Redis), not local files, for multi-node | Required before node #2 |
| NFR-05 | Availability | Uptime | **99.9%** monthly |
| NFR-06 | Reliability | No single-record data loss; nightly backups + PITR | RPO ≤ 5 min, RTO ≤ 1 h |
| NFR-07 | Security | Pass external pen-test; OWASP Top-10 clean | Before public launch |
| NFR-08 | Privacy | PII minimization, need-to-know exposure, encryption at rest & in transit | Doc 07 |
| NFR-09 | Compliance | Align to India **DPDP Act 2023**, GDPR (EU users), HIPAA-style safeguards for health data | Doc 07 |
| NFR-10 | Portability | Two-command install on a single Linux VM; 12-factor config via `.env` | ✅ baseline |
| NFR-11 | Maintainability | PSR-12 PHP, documented modules, ≥70% test coverage on core logic | Doc 10/11 |
| NFR-12 | Accessibility | WCAG 2.1 AA | Doc 08 |
| NFR-13 | i18n | UTF-8 end-to-end (utf8mb4 ✅); externalized strings; RTL-ready | Strings: Target |
| NFR-14 | Compatibility | Modern evergreen browsers + Android Chrome/iOS Safari; graceful JS-off degradation | jQuery baseline |
| NFR-15 | Cost | Run a district pilot on ≤ 1 vCPU / 2 GB; scale linearly with traffic | Doc 12 |

---

## 4. Constraints

- **C1** — Stack is fixed: HTML, CSS, jQuery, JS, MySQL, PHP. No server or SPA frameworks (Doc 00).
- **C2** — Clinical eligibility/matching rules require medical-advisor sign-off before any change ships.
- **C3** — The app is connective tissue, not an EHR or inventory ERP (Doc 01 §10).
- **C4** — All new tables/columns ship in the **canonical consolidated schema** (Doc 04), never as ad-hoc `fix_db.sql` patches.

---

## 5. Acceptance & traceability

Every `FR/NFR` maps to at least one test in **Doc 11** and one module in **Doc 05**. Every 🟨/⬜ item
and every `DEF-xx` is a checkbox in **Doc 15**. A milestone is "done" only when its FRs are ✅ and
their tests are green.

*Back to the [Documentation Index](00-Documentation-Index.md).*

# 07 · Security, Privacy & Compliance

**Status:** 🟡 In review · **Owner:** Security & Privacy officer · **Traces to:** Doc 02 (NFR-07…09), Doc 05 (DEF).

LifeLine processes **health-adjacent personal data** (blood type, contact, location, donation
history) about real people at moments of crisis. Security is not a feature here — it is the license
to operate. This document is the threat model, the graded findings, the remediation plan, and the
compliance posture. **No public launch ships until the 🔴/🟠 findings are closed and an external
pen-test passes (NFR-07).**

---

## 1. What we're protecting (data classification)

| Class | Examples | Handling |
|---|---|---|
| **Sensitive PII / health** | blood type, DOB, gender, donation history, lat/lng, phone, address | Encrypt at rest; need-to-know exposure; audit access |
| **Credentials** | password hashes, reset tokens, session ids, future API keys | Hash/rotate; never log; short TTL |
| **Operational** | audit logs, IPs, user-agents | Retain per policy; restrict to admins |
| **Public** | blood-bank directory, approved testimonials, aggregate stats | Freely served |

**Privacy principle:** a donor's contact details are exposed only when (a) the viewer is
authenticated **and** (b) the donor is `available` (or the viewer is admin) — already enforced in
`find_donors.php`/`view_request.php`. Harden, don't relax, this rule.

---

## 2. Security controls already in place (credit where due)

The prototype has a respectable baseline — uncommon for an MVP:

- ✅ **Parameterized queries everywhere** (PDO prepared statements, `EMULATE_PREPARES=false`) → SQLi-resistant.
- ✅ **bcrypt password hashing** (`password_hash`/`password_verify`).
- ✅ **Strong password policy** (`validatePassword`).
- ✅ **CSRF tokens** on state-changing POSTs (`hash_equals`).
- ✅ **Login rate-limiting + lockout** (per identifier).
- ✅ **Session regeneration** on login; **secure cookie flags** in production.
- ✅ **Anti-enumeration** on password reset.
- ✅ **Output escaping** via `htmlspecialchars`/`sanitizeString` on most rendered fields.
- ✅ **Audit logging** for login & delete; **single-use, expiring** reset tokens.

---

## 3. Threat model (STRIDE, abridged)

| Threat | Vector | Current state | Control / target |
|---|---|---|---|
| **Spoofing** | Credential stuffing, session theft | Rate-limit (session-scoped), secure cookies | + Redis per-IP limit (DEF-12), 2FA for hospital/admin (FR-09) |
| **Tampering** | Forged POSTs, IDOR | CSRF + ownership-scoped writes | + match-state FSM (DEF-06), enum validation (DEF-05) |
| **Repudiation** | "I didn't change that" | Partial audit | Audit **all** mutations (DEF-13, FR-46) |
| **Information disclosure** | Contact harvesting, health endpoint, error leakage | Gated contacts; `APP_DEBUG` gate | Lock `health.php` (DEF-15); encrypt PII at rest |
| **Denial of service** | SOS email flood, polling load | None on SOS | Throttle+CAPTCHA+queue (DEF-02/03) |
| **Elevation of privilege** | Role bypass | Folder + guard model | Centralize authz; deny-by-default middleware |

---

## 4. Graded findings & remediation (the security backlog)

### 🔴 Critical
- **DEF-02 — Unauthenticated, unthrottled Emergency SOS.** Anyone can create unlimited `critical`
  requests and trigger mass email. *Abuse, reputation, deliverability, and cost risk.*
  **Fix:** per-IP + per-phone rate limit; CAPTCHA; OTP-verify the requester's phone/email before any
  broadcast; cap recipients per SOS; monitor.
- **DEF-11 — `messages.is_edited` column missing** (also a correctness bug). Schema/code mismatch.
  **Fix:** consolidated schema (Doc 04 §6).

### 🟠 High
- **DEF-03 — Synchronous email fan-out** (SOS/registration). Blocks requests, partial failures, DoS
  amplifier. **Fix:** enqueue; idempotent worker; provider API with suppression lists.
- **DEF-04 — Stored XSS in messaging.** Content stored raw and injected into HTML (`nl2br`) and JS
  (`onclick` string-building). **Fix:** store raw, **encode on output** (`htmlspecialchars`), build
  DOM via jQuery `.text()`/data-attributes (never string-concatenated handlers), cap length, CSP.
- **DEF-07 — Email change without re-verification.** Account-takeover lever. **Fix:** send a
  confirm link to the new address; only swap login identity on confirm.
- **DEF-08 — Weak conversation authorization** in `get_messages`. **Fix:** verify membership.
- **DEF-10 — Upload validation by extension only.** **Fix:** verify MIME/`finfo`, re-encode images,
  randomized names (already), store outside web root or behind a sanitizing handler, `X-Content-Type-Options: nosniff`.
- **DEF-12 — Rate-limit is session-scoped** → trivially bypassed by dropping the cookie. **Fix:**
  Redis/edge keyed on IP + identifier.
- **DEF-13 — Admin edits not audited.** **Fix:** `auditLog()` on every mutation with before/after.

### 🟡 Medium
- **DEF-01 — Cool-off inconsistency** (90 vs 56 vs "3 months") — clinical-safety + trust issue.
- **DEF-05 / DEF-06 — Missing enum/state validation** on requests, blood type, match status.
- **DEF-14 — CSV formula injection** in admin export. **Fix:** prefix risky cells with `'`.
- **DEF-15 — Health endpoint info disclosure.** **Fix:** anon `healthz` = `{status}` only; detail behind admin.
- **DEF-16 — Persistent PDO** semantics at scale. **Fix:** pooler; reassess persistence.
- **Default admin credential** (`admin@bloodsystem.com` / `SecureAdmin2024!`) shipped in
  README/`setup_admin.php`. **Fix:** force rotation on first prod boot; never ship a known password.

### 🔵 Low / hardening
- Hash reset `token` at rest. Add security headers (CSP, HSTS, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`). Constant-time everywhere user-controlled. Bound geocode/SSRF egress.
  Subresource Integrity for any CDN-hosted jQuery (or self-host jQuery — preferred).

---

## 5. Defense-in-depth target architecture

```
Edge: WAF + TLS + rate limit + bot/CAPTCHA  ─►  App (deny-by-default authz middleware)
  ─► Secrets in vault/KMS (not .env on disk in prod)
  ─► PII encrypted at rest (app-level for hot columns; TDE for the volume)
  ─► Async queue for all outbound email (suppression + DKIM/SPF/DMARC)
  ─► Centralized structured audit (immutable, exportable)
  ─► Backups: encrypted, tested restores, PITR (NFR-06)
```

**Content-Security-Policy** (self-host jQuery to make this strict):
`default-src 'self'; img-src 'self' https://ui-avatars.com data:; script-src 'self'; style-src 'self';
frame-ancestors 'none'; base-uri 'self'`.

---

## 6. Privacy & compliance posture

The platform targets India first, with EU/global on the roadmap → design to the strictest applicable bar.

| Regime | Applicability | Key obligations & how we meet them |
|---|---|---|
| **India DPDP Act 2023** | Primary market | Lawful consent at registration; purpose limitation; data-principal rights (access/correction/erasure); breach notification; a Data Protection Officer; children's-data care (donors are 18+). |
| **GDPR** | EU users / future | Legal basis, DSAR tooling, right-to-erasure (needs soft-delete + purge job), data-portability export, records of processing, DPIA for matching. |
| **HIPAA-style safeguards** | Hospital/clinical partners (US/contractual) | Access controls, audit, encryption, BAAs with partners; minimum-necessary disclosure. |
| **CAN-SPAM / e-mail law** | All notification email | Unsubscribe in every donor email (link exists), honor suppression, identify sender. |

**Consent & preferences (build):** explicit consent capture at signup; a donor **notification
preferences + unsubscribe** center (FR-32); consent + version stored and auditable.

**Data lifecycle:** retention schedule per class; soft-delete + scheduled purge (FR-49); export &
erasure endpoints (Doc 06); de-identification for analytics (Doc 13).

---

## 7. Operational security

- **Secrets:** `.env` for dev only; production secrets in a manager; rotate DB/SMTP/admin creds.
- **Least privilege:** app DB user limited to required DML; no DDL in prod; separate migration creds.
- **Patching:** pinned PHP/MySQL/jQuery versions; dependency & CVE scanning in CI (Doc 11).
- **Logging:** never log secrets/PII payloads; log security events (authn, authz failures, SOS, admin actions).
- **Incident response:** runbook, on-call, breach-notification clock aligned to DPDP/GDPR timelines.

---

## 8. Security acceptance gate (pre-launch checklist)

- [ ] All 🔴 and 🟠 `DEF` items closed and regression-tested (Doc 11).
- [ ] External pen-test passed; OWASP Top-10 clean (NFR-07).
- [ ] Default admin credential rotated; secrets out of repo/README.
- [ ] CSP/HSTS/security headers live; jQuery self-hosted or SRI-pinned.
- [ ] PII encrypted at rest; backups restore-tested; PITR verified.
- [ ] Consent + unsubscribe + DSAR/erasure flows live.
- [ ] Audit covers 100% of mutations; logs immutable & exportable.
- [ ] Sign-off by Security officer **and** Clinical advisor (eligibility).

*Back to the [Documentation Index](00-Documentation-Index.md).*

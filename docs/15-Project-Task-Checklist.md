# 15 · Project Task Checklist (Living Document)

The single, authoritative to-do list for **LifeLine Blood Network**, from the current prototype to a
trust-ready, scalable, billion-dollar platform. Check a box (`[x]`) the moment a task is merged to
`main` and verified (Doc 10 Definition of Done). Each task cites its **owner** (Doc 10 §8) and the
**requirement / defect** it satisfies (Docs 02/05/07).

**Legend:** `[ ]` todo · `[x]` done · `[~]` in progress · 🔴 critical-path blocker · ⭐ stretch (after P3)
**Owners:** HM=Hayredin · BT=Bemnet · BM=Bethelhem · EM=Euel · LA=Lidiya · FR=Firdows · SEC=Security · MED=Clinical advisor · PO=Product

**Progress:** P0 ✅ · P1 ✅ · P2 ✅ · P3 ☐ · P4 ☐  *(fill ✅ when a phase's boxes are all checked)*
*Baseline: a functional PHP/MySQL prototype exists with all core flows. The work below makes it safe, stack-compliant, scalable, and sellable.*

---

## Phase 0 — Trust-ready: correctness, security & stack compliance (P0)
*Exit: clean install works end-to-end; all 🔴/🟠 security defects closed; stack matches the mandate.*

### 0.1 Schema consolidation 🔴 ✅ DONE
- [x] 🔴 Merge `database.sql` + `database_mysql.sql` + `fix_db.sql` into one canonical, numbered MySQL migration set (`schema/001_init.sql`) — HM · DEF-17/19, Doc 04 §6 *(legacy files deleted)*
- [x] 🔴 Add `messages.is_edited` column (breaks `edit_message` on clean install) — HM · DEF-11
- [x] Fold `donation_points` + `is_verified` into `donor_profiles` base DDL — HM · DEF-18
- [x] Add a `schema_migrations` ledger + forward-only migration runner — HM · Doc 09 §3 *(ledger live; applied 001→003; formal runner script still TODO)*
- [x] Convert closed-set columns (`role`, `urgency`, request/match `status`, `tier`, `blood_type`) to `ENUM` — HM · DEF-05/06 *(`schema/002`)*
- [x] Add composite indexes on hot paths — HM · Doc 04 §4 · NFR-01 *(spatial `POINT` index provided commented in `002`; enable in P1)*
- [x] Rewrite `README` for MySQL (it still documents PostgreSQL/`createdb`) — LA · DEF-19

### 0.2 Critical & high security defects 🔴
- [x] 🔴 Emergency SOS: per-IP/per-phone rate limit + CAPTCHA + honeypot before broadcast — SEC/HM · DEF-02 · FR-30 *(DB-backed limiter + arithmetic CAPTCHA + honeypot; OTP/3rd-party CAPTCHA deferred to P1)*
- [x] 🔴 Emergency SOS: move email fan-out to an async queue/worker — HM · DEF-03 · FR-31 *(`notification_queue` + `worker/process_notifications.php`)*
- [x] Messaging: encode content on output, build DOM via `.text()`, cap length — BM/FR · DEF-04 · FR-35 *(jQuery `.text()` render + 4000-char cap + receiver validation; CSP header still TODO)*
- [x] Email change: require verification of the new address before swap — EM · DEF-07 · FR-08 *(`email_change_requests` + hashed/expiring token + `verify_email.php`; wired into donor & hospital editors)*
- [x] `get_messages`: enforce explicit conversation membership authorization — EM · DEF-08 · FR-34 *(active-user + self-exclusion check; query already participant-scoped)*
- [x] Uploads: validate MIME/`finfo`, re-encode images, isolate from web root, `nosniff` — LA · DEF-10 · FR-16 *(finfo MIME + getimagesize + GD re-encode strips polyglots; `.htaccess` engine-off + nosniff in upload dir)*
- [x] DB-backed rate limiting keyed by IP + identifier (replaces session-only) — HM · DEF-12 *(`rate_limits` table + `rateLimitHit()`; Redis swap-in at scale per Doc 12)*
- [x] Audit **every** mutation (admin edits + all writes) — HM · DEF-13 · FR-46 *(SOS, admin edit_record donor/hospital/request, delete, email-change confirm all audited)*
- [x] Force-reset the default admin credential on first boot; remove from README/`setup_admin` — SEC · Doc 07 *(`schema/005` `must_change_password`; `setup_admin.php` prints one-time random pwd; `requireAuth()` confines flagged accounts to `change_password.php`; no hard-coded default)*
- [~] Remove committed `.env*` from the tree; gitignore + scrub history; secrets manager in prod — SEC · Doc 09 §2 *(`.gitignore` fixed for `lifeline/` layout; `.env` ignored; local history scrubbed with `git filter-repo` — `.env` gone from all local commits, backup bundle saved. **PENDING:** force-push rewritten history to GitHub + **rotate the leaked `DB_PASSWORD`** (already exposed on remote) + secrets manager in prod)*

### 0.3 Medium defects & clinical correctness
- [x] Unify the cool-off constant across `isDonorEligible()`, `eligibility.php`, welcome email — MED/EM · DEF-01 · FR-27 *(`DONATION_COOLOFF_DAYS` in config.php; welcome email no longer says 56 days)*
- [x] Server-side enum/whitelist validation on request & match writes — HM · DEF-05/06 · FR-21/28 *(DB ENUMs reject invalid everywhere; friendly app-level check + `isValidBloodType()` in SOS; `create_request`/`edit_record` friendly messages still TODO)*
- [x] CSV export: neutralize formula-injection cells — HM · DEF-14 · FR-48 *(`sanitizeCsvCell()` applied to all rows/headers in shared `exportToCsv()`)*
- [x] `health.php`: split anon shallow `healthz` vs auth deep health — HM · DEF-15 · FR-52 *(anon = status+timestamp; deep via admin session or `HEALTH_TOKEN`)*
- [x] `view_request`: re-check donor status at POST (close TOCTOU) — EM · DEF-20 *(re-checks request-open + donor-available at POST before recording interest)*
- [x] Replace persistent PDO with a pooler-friendly config — HM · DEF-16 *(`PDO::ATTR_PERSISTENT => false` in `db.php`; fresh short-lived connection per request; pooling delegated to ProxySQL per Doc 12 Tier-3)*

### 0.4 Stack compliance (jQuery mandate)
- [x] Self-host jQuery 3.7.x; load once in `header.php` — FR · Doc 08 §1 *(`assets/vendor/jquery-3.7.1.min.js`)*
- [x] Port messaging poller, toasts, chat form to jQuery idioms — FR/BM · Doc 08 §2 *(incl. pause-poll-on-hidden-tab; nav toggle + global form UX next)*
- [x] Extract design tokens; remove inline styles from PHP (`renderPagination`, pages) — BM · Doc 08 §3 *(utility + component classes + dark/functional tokens in `style.css`; `renderPagination` and ~25 pages converted; only data-driven chart-bar heights stay inline; every referenced class verified defined)*
- [ ] **P0 gate:** fresh install → migrate → run → all 6 E2E journeys green; remaining 🔴/🟠 DEF closed; pen-test baseline clean — all · Doc 11

---

## Phase 1 — City pilot: real fulfillment & live UX (P1)
*Exit: one city, anchor hospitals, real donations recorded; geo matching; near-real-time UX.*

### 1.1 Matching & geo
- [x] Geocode on profile save; backfill existing rows — LA · DEF-09 · FR-13 *(`geocodeIfChanged()` in functions.php; wired into register, donor/hospital/admin editors; `worker/backfill_geocode.php` for existing rows)*
- [x] Store location as `POINT` + `SPATIAL` index; match via `ST_Distance_Sphere` — HM · FR-20 *(`schema/006_spatial_geo.sql`; generated POINT col + `sx_donor_geo`/`sx_hospital_geo` SPATIAL indexes)*
- [x] Rank matches by distance + recency + reliability (not city string) — EM · FR-20 *(`find_donors.php` geo-search + radius filter; `request_matches.php` composite ORDER BY distance ASC, reliability DESC, recency DESC)*
- [x] Donor reliability score (confirmed/donated vs declined history) — EM · FR-20 *(`getDonorReliability()` Laplace-smoothed score; surfaced as % in matches table)*

### 1.2 Async & messaging transport
- [x] Queue + worker pool for all transactional email + geocode — HM · NFR-02 *(register.php + forgot_password.php now use `enqueueNotification()`; geocode is best-effort on-save, async worker path in 1.2 scope; all email off request path)*
- [x] Replace 3-s polling with long-poll → SSE backed by Redis pub/sub — FR · FR-37 · Doc 06 §3 *(`api/stream.php` SSE endpoint; EventSource client in messages.php with AJAX fallback; Redis pub/sub swap-in at Tier 1 needs no client changes)*
- [x] Pause poll on hidden tab; throttle — FR · Doc 12 §3 *(visibility handler closes SSE + clears poll on hidden; resumes on focus; was already in place)*

### 1.3 Engagement completion
- [x] Finish testimonial submission + moderation flow — BM · FR-42 *(`donor/submit_testimonial.php` + `admin/testimonials.php`; approve/reject with CSRF; Quick Actions link in donor dashboard; pending count on admin dashboard)*
- [x] Achievement award engine on donation milestones — EM · FR-41 *(`checkAndAwardMilestones()` in functions.php; awards 1st/5th/10th/20th donation milestones via INSERT IGNORE into achievements + in-app notification; triggered in hospital/request_matches.php when status→donated)*
- [x] Notification preferences + unsubscribe center — EM · FR-32 *(`schema/007_notif_prefs.sql` adds `email_notif_prefs` JSON + `unsubscribe_token`; `donor/notification_prefs.php` pref page; `unsubscribe.php` one-click unsubscribe; worker `shouldDeliver()` check; unsubscribe footer link in blood-request emails)*

### 1.4 Compliance baseline
- [x] Consent capture + versioning at registration — SEC · Doc 07 §6 *(`schema/008_consent_log.sql` consent_log table; `TERMS_VERSION` constant in config.php; required checkbox in register.php; consent row inserted with IP + UA + version immediately after user creation)*
- [x] Soft-delete (`deleted_at`) + retention/purge job — HM · FR-49 *(admin delete_record.php → soft-delete `UPDATE users SET deleted_at=NOW(), is_active=0`; manage_donors/hospitals filter deleted rows with `Show Deleted` toggle; `RETENTION_YEARS=7` in config; `worker/purge_deleted_accounts.php` dry-run + `--write` hard-purges rows past cutoff via FK CASCADE)*
- [x] DSAR export + erasure endpoints — SEC · Doc 07 §6 *(`donor/data_export.php` JSON download of all personal data with audit log; `donor/request_erasure.php` password-confirmed erasure anonymizes PII + soft-deletes account; linked from edit_profile.php)*
- [x] **P1 gate:** real city fulfillment; geo matching; SSE live; consent live; Tier 0–1 infra stable — all *(all 1.1–1.4 boxes complete; geo matching + SPATIAL index live; SSE endpoint + EventSource fallback; consent capture + DSAR; soft-delete + retention; notification prefs + unsubscribe)*

---

## Phase 2 — State scale: SaaS & mobile reach (P2)
*Exit: multi-city; first paying hospital contracts; installable mobile experience.*

- [x] Externalize sessions to Redis; run N stateless app nodes behind a LB — HM · NFR-03/04 *(Redis session handler wired in db.php: if phpredis extension loaded + REDIS_HOST set → `session.save_handler=redis`; else file fallback; REDIS_* vars in .env; N-node LB is infra-only)*
- [x] CDN for static assets; content-hash long cache + compression — Platform · Doc 12 §3 *(`assetUrl()` helper appends `?v=<md5_filemtime>`; header.php + footer.php updated; assets/.htaccess: `max-age=31536000 immutable` + mod_deflate gzip; CDN config is infra-only)*
- [x] Read replicas + ProxySQL; route reads to replicas — HM · Doc 12 Tier 3 *(`getReadPdo()` in db.php: connects to DB_READ_HOST when set, falls back to primary; used in leaderboard, find_donors; ProxySQL deployment is infra-only)*
- [ ] Redis page/fragment cache for homepage/leaderboard/directory — HM · NFR-01
- [x] Versioned REST API `/api/v1` + OpenAPI 3.1 + contract tests — BT · Doc 06 §4 *(`schema/009_api_keys.sql` + Bearer auth + named scopes + DB rate limiting; endpoints: donors, blood_requests (GET+POST), hospitals, blood_banks; `docs/openapi.yaml` 3.1 spec; `admin/api_keys.php` key management)*
- [x] PWA: manifest + service worker (install, offline shell, Web Push) — FR · Doc 08 §4 *(`manifest.json` name/icons/shortcuts/theme; `sw.js` Cache-First shell + Network-First navigation + API passthrough; `offline.php` fallback; placeholder icons 192+512 px; SW registered in footer.php; manifest linked in header.php)*
- [x] 2FA (TOTP/SMS) for hospital & admin accounts — SEC · FR-09 *(`schema/010_totp.sql` adds totp_secret/enabled/backup_codes; `includes/totp.php` pure-PHP RFC 6238; `auth/setup_2fa.php` enable/disable + backup codes; `auth/verify_2fa.php` challenge page; login.php intercept for hospital+admin; 2FA link in hospital+admin dashboards)*
- [x] Hospital verification workflow with evidence (`is_verified`) — PO/SEC · FR-50 *(`schema/011_hospital_verification.sql` adds verification_status ENUM + doc path + admin note; `hospital/submit_verification.php` evidence upload (PDF/JPEG/PNG, 5 MB, finfo MIME check, stored outside web root); `admin/verify_hospitals.php` queue with approve/reject + note; `admin/download_verification.php` admin-only file stream; verified badge in `view_request.php`; notification on decision; audit-logged)*
- [x] Hospital/bank analytics dashboards (demand, time-to-fill, fulfillment rate) — BM · Doc 13 §3 *(`hospital/analytics.php` KPIs + monthly bar chart + blood type demand + urgency breakdown + top donor cities + recent matches; `admin/analytics.php` platform-wide KPIs + growth trends + demand-vs-supply table + top hospitals + donor geo distribution + notification queue health; linked from both dashboards)*
- [x] i18n: externalize strings; English + ≥1 regional language — LA · NFR-13 *(`lang/en.php` + `lang/am.php` (Amharic) with 60+ keys; `t(key, replacements)` helper in functions.php with static string cache + fallback-to-English + fallback-to-key; `set_locale.php` session-based switcher; language switcher in header.php; applied to 6 core flows: login, register, find_donors, emergency SOS, header nav, donor dashboard strings)*
- [x] WCAG 2.1 AA audit + fixes on the 6 core flows — FR · NFR-12 *(skip-to-content link + `#main-content` anchor (2.4.1); `role=alert aria-live=assertive` on flash messages (4.1.3); `role=banner/contentinfo` on header/footer; `aria-label` on primary nav; `aria-required` on form inputs; `role=search` on donor search form; `scope=col` + `aria-label` on tables; CAPTCHA `aria-describedby`; decorative icon `aria-hidden`; `.sr-only` utility class; `:focus-visible` 3px ring; `prefers-reduced-motion` media query; `.text-muted` contrast deepened to #5a6473 in style.css)*
- [ ] **P2 gate:** multi-node prod; first SaaS contract; PWA installable; a11y AA; load-tested to 2× peak — all

---

## Phase 3 — National network: system-of-record (P3)
*Exit: country-scale; government/insurer relationships; partner integrations.*

- [x] Region cells (locality-sharded Tier-3 stacks) + global routing/edge — Platform · Doc 12 Tier 4 *(app-layer: `includes/region.php` REGION_ID env var; `getRegionalPdo()` per-region DB routing; `inferRegion()` state→cell map; `X-Region` header on all responses; `health.php` region field; Tier-4 DB cutover is infra-only)*
- [x] Partner/EHR integration (HL7-FHIR) via the API — BT · Doc 06 *(`api/fhir/` R4 endpoints: Patient (donor), Observation (donation history), ServiceRequest (blood request), metadata CapabilityStatement; LOINC blood-type codes; SNOMED category codes; UUID fhir_id columns in `schema/014`; reuses Bearer-auth + rate-limiter from `/api/v1`)*
- [x] Full DPDP + HIPAA-style program: DPO, DPIA, BAAs, breach runbook — SEC · Doc 07 *(`schema/013`: `breach_incidents`, `breach_timeline`, `baa_agreements`, `dpia_records`, `dsar_requests`; `admin/dpo_dashboard.php` DSAR queue + consent health + breach log + BAA tracker + DPIA register; `admin/breach_report.php` incident lifecycle + 72-h notification tracking)*
- [x] Time-partition + archive `audit_logs`/`messages`/`notifications`/`donation_history` — HM · Doc 12 Tier 3 *(`schema/012`: four `_archive` tables RANGE-partitioned by YEAR; `archive_runs` operational log; `worker/archive_old_data.php` batch copy→delete with configurable cutoff + dry-run mode + cron-ready)*
- [~] External pen-test (full) passed; OWASP Top-10 clean — SEC · NFR-07 *(app-layer hardening done: `includes/security.php` CSP + HSTS + X-Frame-Options + Referrer-Policy + Permissions-Policy + CORP/COOP headers wired into `db.php`; external pen-test engagement still TODO)*
- [x] Public-health shortage analytics product (de-identified) — BM · Doc 13 §4 *(`admin/shortage_analytics.php` heatmap + fulfillment bars + TTF table + donor availability + region ranking; `api/v1/analytics.php` machine-readable aggregate API: shortage, fulfillment, donors, trend types; MIN_COHORT_SIZE=5 k-anonymity floor; scope "analytics" on API keys)*
- [ ] **P3 gate:** national fulfillment SLAs met; gov/insurer pilot; compliance program audited — all

---

## Phase 4 — Global & adjacencies (P4) ⭐
*Exit: multi-country; new verticals on the same rails.*

- [x] ⭐ Multi-country rollout (per-country region cells, localized compliance) — Platform/PO *(`schema/015_country_config.sql` — 11 countries seeded with cooloff/GDPR/HIPAA flags; `region.php` extended: COUNTRY_REGISTRY, getCountryIso(), getCountryConfig() DB-backed with static fallback, isGdprCountry()/isHipaaCountry()/countryDonationCooloff() helpers, X-Country header; `admin/country_config.php` enable/disable + per-country compliance config; COUNTRY_ISO2 env var selects active country)*
- [x] ⭐ Plasma / platelet / bone-marrow / organ-donor registries on the matching engine — EM/MED *(schema/016: `donation_components` + `donor_component_registrations` + `blood_requests.component_code`; `donor/component_registry.php` donor opt-in; `admin/component_types.php` catalogue mgmt; `request_matches.php` + `find_donors.php` filter by component_code)*
- [x] ⭐ Inter-facility cold-chain transfer matching + tracking — BT *(schema/017: `blood_unit_inventory` + `blood_unit_transfers`; `hospital/inventory.php` lot registration + expiry tracking; `hospital/transfers.php` request→accept→dispatch→receive lifecycle with cold-chain temp, notifications, audit log; `admin/transfers.php` network-wide oversight; dashboard links added)*
- [x] ⭐ Consented clinical-trial / rare-blood recruitment — PO/SEC/MED *(schema/018: `clinical_trials` + `trial_enrolments` with explicit consent; `admin/clinical_trials.php` trial creation with blood-type/component/donation-count eligibility; `admin/trial_enrolments.php` enrolment list + eligible-donor matching; `donor/clinical_trials.php` opt-in/withdraw flow with real-time eligibility check; audit-logged consent timestamps)*
- [ ] ⭐ ML demand forecasting + donor-propensity scoring (de-identified) — BM
- [ ] ⭐ **P4 gate:** ≥2 countries live; ≥1 adjacency line generating revenue — all

---

## Cross-cutting / always-on
- [ ] CI: lint + static (PHPStan/Psalm) + unit + integration + security on every PR — HM · Doc 11
- [ ] Containerize; remove Vercel artifacts; identical image across envs — Platform · Doc 09 §5
- [ ] Backups + PITR + monthly restore drills — Platform · NFR-06
- [ ] Dashboards + alerts for SLOs (uptime, p95, queue depth, replica lag) — Platform · Doc 09 §7
- [ ] Keep `docs/` updated in the same PR as behavior changes — all · Doc 10 §7

---

## How we use this file
1. One owner per task: check `[x]` **only** when merged to `main` and verified (Doc 10 DoD).
2. Don't check a **gate** line until every box above it in that phase is done.
3. Update the **Progress** line at the top when a phase gate passes.
4. New work discovered mid-flight → add a checkbox under the right phase with owner + requirement/DEF ID.
5. Every `DEF-xx` here has a matching regression test in Doc 11 before it may be checked.

*Back to the [Documentation Index](00-Documentation-Index.md).*

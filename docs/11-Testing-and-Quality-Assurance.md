# 11 · Testing & Quality Assurance

**Status:** 🟡 In review · **Owner:** QA + Engineering · **Traces to:** Doc 02 (every FR/NFR), Doc 05 (DEF), Doc 07.

A platform that touches a patient's blood supply must be *proven*, not hoped. This is the test
strategy: what we test, how, and the acceptance gates a milestone must pass.

---

## 1. Test pyramid (on the PHP/MySQL/jQuery stack)

```
        ▲  E2E (browser)        ← few; the 6 critical user journeys
       ╱ ╲ Integration (HTTP+DB)← many; every page/endpoint contract
      ╱   ╲ Unit (pure PHP)     ← most; helpers & business rules
     ╱_____╲ Static + lint      ← gate before anything runs
```

| Layer | Tooling (stack-compatible) | Targets |
|---|---|---|
| Static | `php -l`, **PHPStan/Psalm**, PHPCS (PSR-12), ESLint, Stylelint | Type safety, style, dead code |
| Unit | **PHPUnit** | `functions.php` pure logic |
| Integration | PHPUnit + **Dockerized MySQL** + PHP built-in server | Page controllers, `api/*`, DB effects |
| E2E | **Playwright/Cypress** (headless Chromium) | Full journeys incl. jQuery behavior |
| Security | OWASP ZAP baseline, dependency/secret scanners | DEF regression, OWASP Top-10 |
| Performance | k6 / JMeter | NFR-01/02 load targets |

---

## 2. Unit tests — business rules (highest ROI)

These encode the rules that *matter clinically and commercially*:

| Suite | Asserts | Guards FR/DEF |
|---|---|---|
| Blood compatibility | `getCompatibleDonorBloodTypes()` & `getPatientBloodTypesForDonor()` are exhaustive, symmetric inverses, and medically correct for all 8 types | FR-17 |
| Eligibility & cool-off | `isDonorEligible()` boundary days; **one** canonical constant; matches questionnaire copy | FR-27, **DEF-01** |
| Donor status | `getDonorCurrentStatus()` → available/busy/cool_off/unavailable transitions | FR-18 |
| Password policy | `validatePassword()` accepts/rejects the full matrix | FR-03 |
| Rate limiting | lockout after N, reset, time-window | FR-04, DEF-12 |
| Tiers & points | bronze/silver/gold/platinum thresholds; +100/donation | FR-39 |
| Distance | `calculateDistance()` Haversine vs known city pairs | FR-14 |
| CSV | `exportToCsv()` neutralizes `=,+,-,@` leading cells | **DEF-14** |
| Sanitizers | escaping of `<,>,",'`, length caps | DEF-04 |

## 3. Integration tests — endpoint contracts

For **every** page/endpoint in Doc 05, assert the contract:

- **AuthZ matrix:** guest/donor/hospital/admin × each route → correct allow/redirect/403. Catches
  role-bypass and IDOR.
- **CSRF:** state-changing POST without/with bad token → rejected.
- **DB effects:** correct rows created/updated; transactions roll back on failure (donation flow).
- **Negative/security cases** (one per DEF):
  - SOS without throttle/CAPTCHA is rejected (DEF-02); SOS enqueues, doesn't block (DEF-03).
  - Message with `<script>` is stored raw and rendered escaped (DEF-04).
  - `edit_message` works because `is_edited` exists (DEF-11).
  - Non-party cannot read a conversation (DEF-08).
  - Bad `blood_type`/`status` rejected (DEF-05/06).
  - Email change requires confirmation (DEF-07).
  - Upload of a disguised non-image rejected (DEF-10).
  - Admin edit writes an `audit_logs` row (DEF-13).
  - `healthz` anon payload contains no version/env (DEF-15).

## 4. End-to-end journeys (the 6 that must never break)

1. **Donor onboarding** — register → verify → set availability → appear in search.
2. **Hospital request → fulfillment** — create request → see matches → contact → record donation →
   donor tier/points update → donor notified.
3. **Emergency SOS** — raise SOS → throttle/CAPTCHA → compatible donors notified (async) → donor opens request.
4. **Messaging** — two users exchange, edit, delete; unread badge; live update without reload.
5. **Eligibility self-check** — answers map to the correct, server-consistent verdict.
6. **Admin governance** — moderate testimonial, verify hospital, edit/delete record (audited), CSV export.

Run E2E on a seeded DB in CI (headless) and on staging (real browser) before release.

## 5. Non-functional testing

| NFR | Test |
|---|---|
| NFR-01 performance | k6 load to target RPS; assert p95 < 400 ms; profile slow queries (`EXPLAIN`) |
| NFR-02 SOS async | broadcast to 10k compatible donors enqueues < 200 ms, delivers via worker |
| NFR-03/04 scale | run 2+ app nodes with Redis sessions; verify no session affinity needed |
| NFR-05/06 resilience | kill a node mid-request; restore-from-backup drill; replica failover |
| NFR-07 security | external pen-test + ZAP baseline clean |
| NFR-12 a11y | axe/Lighthouse ≥ 90; manual keyboard + screen-reader pass on 6 flows |
| NFR-14 compat | matrix on Chrome/Firefox/Safari/Edge + Android/iOS; JS-off graceful degradation |

## 6. Test data & environments

- Dockerized MySQL per CI run; migrations applied fresh; deterministic seed (`blood_banks`,
  fixture donors/hospitals/requests). Time-dependent tests inject a clock (cool-off boundaries).
- Staging uses **anonymized** prod-like data — never raw PII (Doc 07).

## 7. Acceptance gate (per milestone, mirrors Doc 15)

A milestone passes only when:

- [ ] All in-scope **FRs** have green unit + integration tests.
- [ ] The **AuthZ matrix** is fully covered and green.
- [ ] Every closed **DEF** has a regression test.
- [ ] The relevant **E2E journeys** pass headless + on staging.
- [ ] NFR tests for the milestone meet target.
- [ ] Coverage ≥ **70%** on `functions.php` and `api/*`; no drop vs previous release.
- [ ] Security scan clean; Clinical advisor signs off eligibility/matching changes.

## 8. QA results ledger

Track each release's run in an appendix table (date, build, suite, pass/fail, environment-blocked,
notes) so "what passed and what is environment-blocked" is always visible — the QA equivalent of the
audit log.

*Back to the [Documentation Index](00-Documentation-Index.md).*

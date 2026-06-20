# 14 · Risk Register & Glossary

**Status:** 🟡 In review · **Owner:** Product + Engineering + Security · **Traces to:** all docs.

---

## 1. Risk register

Scored **Likelihood (L)** × **Impact (I)** on 1–5; **Score = L×I**. 🔴 ≥15 · 🟠 9–14 · 🟡 4–8 · 🔵 ≤3.
Each risk names its owner and the mitigation (cross-referenced to the doc that implements it).

| ID | Risk | L | I | Score | Owner | Mitigation |
|---|---|---|---|---|---|---|
| R-1 | **Clinical-safety incident** (ineligible donor matched, wrong cool-off) damages trust/health | 2 | 5 | 🟠10 | MED | Single canonical eligibility constant (DEF-01); medical-advisor sign-off; app never asserts clearance (Doc 01/02) |
| R-2 | **Privacy/data breach** of donor PII/health data | 2 | 5 | 🟠10 | SEC | Encrypt PII at rest, need-to-know exposure, pen-test, DPDP/GDPR program (Doc 07) |
| R-3 | **Emergency-SOS abuse** (spam, mass-email, reputation/cost) | 4 | 4 | 🔴16 | SEC | Throttle+CAPTCHA+OTP, async queue, recipient caps (DEF-02/03; FR-30/31) |
| R-4 | **Stored XSS / account takeover** via messaging or email-change | 3 | 4 | 🟠12 | SEC | Output-encoding, length caps, CSP, verified email change (DEF-04/07) |
| R-5 | **Schema/code mismatch breaks prod** (e.g. `is_edited`) | 3 | 4 | 🟠12 | HM | Consolidated migrations + CI integration tests (DEF-11/17/18; Doc 04/11) |
| R-6 | **Cannot scale past one node** (file sessions, sync email) | 3 | 4 | 🟠12 | Platform | Redis sessions, async workers, stateless tier (NFR-03/04; Doc 12) |
| R-7 | **Stack drift** away from mandate (framework/SPA creep, CDN jQuery) | 2 | 3 | 🟡6 | HM | Constraint C1 enforced in review; self-host jQuery (Doc 00/08/10) |
| R-8 | **Cold-start density** (too few donors per region to fulfill) | 4 | 3 | 🟠12 | PO | City-by-city, demand-anchored rollout; drives & referrals (Doc 13) |
| R-9 | **Regulatory change** in health-data law | 3 | 4 | 🟠12 | SEC/PO | Compliance-by-design; DPO; adaptable consent model (Doc 07) |
| R-10 | **Third-party dependency** (Nominatim/SMTP) latency or limits | 3 | 3 | 🟡9 | Platform | Timeouts, circuit breakers, caching, fallbacks (Doc 12 §4) |
| R-11 | **Default/committed secrets** (admin creds, `.env` in tree) leak | 3 | 4 | 🟠12 | SEC | Rotate on first boot, gitignore + scrub history, secrets manager (Doc 07/09) |
| R-12 | **Audit gaps** undermine governance/forensics | 3 | 3 | 🟡9 | HM | Audit all mutations (DEF-13; FR-46) |
| R-13 | **Mission/monetization tension** erodes brand | 2 | 4 | 🟡8 | PO | Free-for-patients guardrail; institution-paid model (Doc 13 §3) |
| R-14 | **CSV/export injection** at admin endpoints | 2 | 3 | 🟡6 | HM | Neutralize formula cells (DEF-14) |
| R-15 | **Accessibility/i18n gaps** block public-sector adoption | 2 | 3 | 🟡6 | Frontend | WCAG 2.1 AA, externalized strings (Doc 08; NFR-12/13) |
| R-16 | **Vendor lock / deploy mismatch** (Vercel artifacts vs stateful PHP) | 2 | 2 | 🔵4 | Platform | Containerize; remove Vercel config (Doc 09 §5) |

**Top risks to retire first:** R-3, then R-4/R-5/R-6/R-11 — they gate the P0 "trust-ready" milestone.

---

## 2. Glossary

| Term | Definition |
|---|---|
| **ABO/Rh compatibility** | The rules governing which donor blood types may be transfused to which patient types; encoded in `getCompatibleDonorBloodTypes()` / `getPatientBloodTypesForDonor()`. |
| **Availability** | A donor's self-declared willingness to be contacted (`donor_profiles.is_available`), distinct from eligibility. |
| **Audit log** | Immutable record of security-relevant actions (`audit_logs`): actor, action, entity, before/after, IP, UA. |
| **Cool-off period** | Minimum days after a donation before a donor is eligible again. Canonical constant (see DEF-01). |
| **CSRF** | Cross-Site Request Forgery; mitigated by per-session tokens validated with `hash_equals()`. |
| **DEF-xx** | An identified defect/gap in the current build; tracked to closure in Doc 15. |
| **Donor match** | A link between a donor and a request (`donor_matches`) with a status lifecycle. |
| **DPDP Act 2023** | India's Digital Personal Data Protection Act; primary privacy regime (Doc 07). |
| **Eligibility** | Whether a donor may currently donate (cool-off + health self-check), distinct from availability. |
| **Emergency SOS** | A public, unauthenticated critical blood request that broadcasts to compatible donors (`emergency.php`). |
| **FR / NFR** | Functional / Non-Functional Requirement (Doc 02). |
| **Haversine** | Great-circle distance formula (`calculateDistance()`); to be superseded by spatial indexing. |
| **Idempotency key** | A client-supplied key that makes a retried POST safe to process once (Doc 06). |
| **Kernel (includes/)** | Shared bootstrap: config → db → functions → header/footer (Doc 03 §3). |
| **LAMP** | Linux/Apache/MySQL/PHP — the deployment stack. |
| **MPA** | Multi-Page Application — server-rendered pages, the app's architecture. |
| **PITR** | Point-In-Time Recovery via DB binlogs (NFR-06). |
| **PRG** | Post/Redirect/Get — write → redirect → read, used throughout. |
| **PWA** | Progressive Web App — installable web app with offline shell + push (Doc 08/12). |
| **Reliability score** | (Target) donor dependability metric to improve match ranking (FR-20). |
| **SSE** | Server-Sent Events — one-way server push replacing polling (Doc 06). |
| **Tier** | Donor gamification level (bronze/silver/gold/platinum) by `total_donations`. |
| **TOCTOU** | Time-Of-Check-To-Time-Of-Use race (e.g. DEF-20, donor status). |
| **utf8mb4** | Full Unicode MySQL charset used end-to-end (NFR-13). |

*Back to the [Documentation Index](00-Documentation-Index.md).*

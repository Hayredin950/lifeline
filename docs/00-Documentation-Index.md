# 00 · Documentation Index

This is the entry point for the **LifeLine Blood Network** engineering blueprint — the complete,
reviewed specification for taking an India-focused blood-donor marketplace from a working LAMP
prototype to a globally-scalable, audit-grade, **billion-dollar** public-health platform.

The blueprint documents two things at once:

1. **As-built** — exactly what the current codebase does, function by function and table by table.
2. **As-target** — the requirements, modifications, upgrades and "boosts" needed to make the system
   safe, compliant, and scalable to national / global volume.

Every claim about the current system in these docs is grounded in the actual source under
`app/lifeline/`. Every forward-looking requirement is tagged so you can tell *spec* from *status*.

## Hard technology constraint

The product **MUST** be built only from this stack. No server frameworks, no SPA frameworks, no
build step beyond static asset bundling:

| Layer | Technology | Notes |
|---|---|---|
| Markup | **HTML5** | Server-rendered by PHP |
| Styling | **CSS3** | Custom design system, CSS variables, dark theme |
| Interactivity | **JavaScript (ES5/ES6) + jQuery 3.x** | jQuery is the mandated DOM/AJAX layer |
| Server | **PHP 8.1+** | Vanilla, PDO, no framework |
| Database | **MySQL 8.0+ (InnoDB, utf8mb4)** | PDO driver |

> The current code ships **vanilla JS** (`assets/js/app.js`) and a stale PostgreSQL `README`. Both
> are reconciled to the mandated stack in this blueprint — see Docs 04 and 08.

## Reading order

1. **[01 · Vision & Product Charter](01-Vision-and-Product-Charter.md)** — the *why* and the boundaries.
2. **[02 · Software Requirements Specification](02-Software-Requirements-Specification.md)** — the *what*, as numbered, testable requirements (FR/NFR), current-vs-target.
3. **[03 · System Architecture](03-System-Architecture.md)** — the *how*, at the structural level: layers, request lifecycle, sessions.
4. **[04 · Data Model & Database Schema](04-Data-Model-and-Database-Schema.md)** — every table, the schema-fragmentation defect, the canonical MySQL DDL, indexes & ERD.
5. **[05 · Module & Page Specifications](05-Module-and-Page-Specifications.md)** — every page and endpoint as an input/output/authz contract.
6. **[06 · API Specification](06-API-Specification.md)** — the messaging AJAX API and the target public REST API.
7. **[07 · Security, Privacy & Compliance](07-Security-Privacy-and-Compliance.md)** — threat model, severity-graded findings, HIPAA / GDPR / India DPDP posture.
8. **[08 · Frontend Architecture & Design System](08-Frontend-Architecture-and-Design-System.md)** — the jQuery adoption plan, design tokens, accessibility.
9. **[09 · Infrastructure, Deployment & DevOps](09-Infrastructure-Deployment-and-DevOps.md)** — topology, environments, CI/CD, observability.
10. **[10 · Coding Standards & Git Workflow](10-Coding-Standards-and-Git-Workflow.md)** — how we write and merge code.
11. **[11 · Testing & Quality Assurance](11-Testing-and-Quality-Assurance.md)** — how we prove it works.
12. **[12 · Scalability, Reliability & Performance](12-Scalability-Reliability-and-Performance.md)** — scaling LAMP + jQuery to nation/global volume.
13. **[13 · Business Model & Path-to-Billions Roadmap](13-Business-Model-and-Path-to-Billions.md)** — market, monetization, the valuation thesis.
14. **[14 · Risk Register & Glossary](14-Risk-Register-and-Glossary.md)** — what could go wrong, and definitions.
15. **[15 · Project Task Checklist](15-Project-Task-Checklist.md)** — the living, phased to-do list with owners and requirement IDs. Tick boxes as we build.
16. **[16 · User Guide](16-User-Guide.md)** — the end-user manual for donors, hospitals, and admins.

## Document conventions

- **Requirement IDs** — `FR-xx` (functional), `NFR-xx` (non-functional). Defined in Doc 02; referenced everywhere else.
- **Defect IDs** — `DEF-xx` (existing defects/gaps in the current build). Defined in Docs 04/05/07; tracked to closure in Doc 15.
- **Traceability** — every requirement traces forward to a module in Doc 05, a table in Doc 04, and a test in Doc 11.
- **MUST / SHOULD / MAY** — used per [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) to grade obligation.
- **Code identifiers** — `functions.php::getCompatibleDonorBloodTypes()` style, matching the real source.
- **Severity** — 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low, used for defects and risks.
- **Status tags** — 🔵 Draft · 🟡 In review · 🟢 Approved. This blueprint is 🟡 until product/clinical/security sign-off.

## Traceability map (feature → where it's documented)

| Capability | Source of truth | Spec'd in |
|---|---|---|
| Identity & roles (donor/hospital/admin) | `users`, `*_profiles`, `functions.php` guards | Doc 02 §FR-Auth; Doc 03 §4; Doc 07 |
| Blood-type compatibility matching | `getCompatibleDonorBloodTypes()` / `getPatientBloodTypesForDonor()` | Doc 02 §FR-Match; Doc 05 |
| Donation lifecycle & gamification | `donor_matches`, `donation_history`, tiers/points | Doc 02 §FR-Donation; Doc 04 |
| Emergency SOS broadcast | `emergency.php`, `EmailService` | Doc 02 §FR-Emergency; Doc 05; Doc 07 (DEF) |
| Donor↔hospital messaging | `messages.php`, `api/*`, `messages` table | Doc 02 §FR-Msg; Doc 06 |
| Geo discovery (donors, banks) | Nominatim geocode + Haversine | Doc 02 §FR-Geo; Doc 12 |
| Admin console & audit | `admin/*`, `audit_logs` | Doc 02 §FR-Admin; Doc 07 |
| Leaderboard & testimonials | `leaderboard.php`, `testimonials` | Doc 02 §FR-Engage |

## Sign-off

| Reviewer | Role | Status | Date |
|---|---|---|---|
| Engineering | Authors | 🟡 In review | — |
| Clinical / Medical advisor | Eligibility & safety | ☐ Pending | — |
| Security & Privacy officer | Doc 07 sign-off | ☐ Pending | — |
| Product owner | Scope & roadmap | ☐ Pending | — |

No production deploy of a new milestone ships until the relevant row reads 🟢.

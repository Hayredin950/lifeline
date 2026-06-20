# 01 · Vision & Product Charter

**Status:** 🟡 In review · **Owner:** Product · **Traces to:** Doc 02 (requirements), Doc 13 (business model)

---

## 1. The problem

Blood does not store, does not synthesize, and does not wait. Every two seconds someone needs
blood; a single road-accident trauma case can consume 10+ units of a specific type within an hour.
Yet the supply chain that connects a *willing donor* to a *bleeding patient* is, in most of the
world, a chain of phone calls, WhatsApp groups, and luck. Hospitals keep paper logbooks of
"frequent donors." Families post desperate requests to social media. Blood banks cannot see live
demand outside their own walls.

The result is a market failure with a body count: shortages of rare types (O−, AB−), wasted units
that expire un-matched, and a donor base that is willing but **un-addressable** at the moment of need.

## 2. The product

**LifeLine Blood Network** is a real-time, geo-aware marketplace that makes the donor base
addressable. It connects three parties:

- **Donors** register once, declare blood type and location, and become reachable — but only when
  *eligible* (post-cool-off) and *available*. They are notified when a compatible, nearby need arises.
- **Hospitals** post blood requests (type, units, urgency, deadline, location) and instantly see a
  ranked list of compatible, available donors, then coordinate directly.
- **The public** can fire an **Emergency SOS** for a patient and reach every compatible donor in the
  area at once, and can browse a directory of blood banks.

Layered on top is a **gamification and trust system** (donation tiers, points, leaderboards, verified
badges, testimonials) that turns one-time donors into repeat lifesavers, and an **admin/audit plane**
for governance, moderation, and compliance.

The current build is a functional prototype covering all of the above on a pure **PHP + MySQL +
HTML/CSS/JS** stack, seeded with Indian blood banks and locales.

## 3. Vision statement

> **Make every willing drop of blood reachable by every patient who needs it — within minutes,
> anywhere on Earth — on infrastructure simple enough to run in a district hospital and robust
> enough to run a continent.**

## 4. Why this can be worth billions

Value is not a feature; it is the product of *reach × trust × frequency × adjacency*. The charter
commits to all four (the financial model is detailed in Doc 13):

| Lever | What it means here |
|---|---|
| **Reach** | The donor graph is a network-effect asset: each new donor/hospital makes every request more fillable. National rollouts compound. |
| **Trust** | Verified hospitals, audited actions, clinical-grade eligibility, and privacy-by-design make the data defensible and the brand institutional. |
| **Frequency** | Gamification + reminders convert episodic donors into a recurring, predictable supply — the difference between a charity and a utility. |
| **Adjacency** | The same rails extend to plasma, platelets, organ-donor registries, cold-chain logistics, clinical-trial recruitment, and public-health analytics. |

A platform that becomes the *system of record* for a region's blood supply is infrastructure — and
infrastructure is what gets valued in the tens of billions.

## 5. Goals & success criteria

| # | Goal | Success metric (target) |
|---|---|---|
| G1 | Fill requests fast | Median time-to-first-confirmed-donor **< 30 min** for non-rare types |
| G2 | Grow an addressable donor graph | **70%+** of registered donors reachable & eligible at any time |
| G3 | Be clinically trustworthy | Eligibility logic reviewed & signed off by a medical advisor; zero ineligible-donor incidents traced to the app |
| G4 | Be private & compliant by design | Pass an external security review; DPDP/GDPR/HIPAA-aligned data handling (Doc 07) |
| G5 | Be operable anywhere | Two-command install; runs on a single commodity VM; horizontally scalable without re-platforming |
| G6 | Be measurably reliable | **99.9%** uptime; p95 page render **< 400 ms**; zero data-loss incidents |

## 6. Stakeholders

| Stakeholder | Primary interest |
|---|---|
| **Donors** | Easy, safe, recognized giving; privacy; not being spammed |
| **Hospitals / blood banks** | Fast, reliable fulfillment; verified counterparties; audit trail |
| **Patients & families** | A working Emergency SOS when it matters most |
| **Regulators / health ministries** | Safety, traceability, data protection, public-health reporting |
| **Operators / admins** | Moderation, fraud prevention, governance |
| **Investors / partners** | Defensible network, compliant data asset, durable monetization |

## 7. In scope (now)

- Three roles (donor, hospital, admin) with session auth, CSRF, rate-limited login.
- Donor & hospital profiles with geolocation (city/state → lat/lng via OpenStreetMap Nominatim).
- Blood-type compatibility matching (both directions) and donor discovery.
- Blood requests, donor matches, donation history, tiers/points/achievements, leaderboard.
- Emergency SOS with email broadcast to compatible donors.
- Donor↔hospital direct messaging (polling-based) + in-app notifications.
- Blood-bank directory, eligibility self-check, testimonials.
- Admin CRUD over donors/hospitals/requests, audit log, CSV export.

## 8. Out of scope (now) → future roadmap

- Native mobile apps (the web app is mobile-responsive; PWA is the bridge — Doc 12).
- Real-time push (WebSockets/SSE) — polling today; see Doc 06 evolution.
- Payments / incentives, insurance integration, EHR/HL7-FHIR integration.
- Plasma/platelet/organ registries, cold-chain logistics, analytics products (Doc 13 §Adjacencies).
- ML-based demand forecasting and donor-propensity scoring.

## 9. Guiding principles

1. **Clinical safety outranks growth.** Eligibility and matching rules are reviewed by medical staff; the app never asserts medical clearance.
2. **Privacy is the product.** Contact details are exposed on a strict need-to-know basis (Doc 07).
3. **Stay on the stack.** HTML/CSS/jQuery/JS/PHP/MySQL — scale *within* it before re-platforming (Doc 12). Simplicity is an operational feature in low-resource settings.
4. **Everything an admin or system does to a record is auditable.** (`audit_logs`.)
5. **Degrade, don't fail.** Missing email config logs instead of erroring; missing geocode falls back to text search.

## 10. Non-goals

LifeLine is **not** an EHR, **not** a blood-bank inventory ERP, and **not** a medical-advice engine.
It is the *connective tissue* between supply and demand. It integrates with those systems; it does
not replace them.

*Back to the [Documentation Index](00-Documentation-Index.md).*

# 10 · Coding Standards & Git Workflow

**Status:** 🟡 In review · **Owner:** Engineering · **Traces to:** NFR-11, Doc 11.

Consistency is how a codebase survives a team and a decade. These are the rules for writing and
merging code on the LifeLine stack.

---

## 1. PHP standards

- **PSR-12** formatting; **PSR-4**-style autoloading if/when classes grow (today: `require_once`
  kernel — keep includes ordered: config → db → functions).
- `declare(strict_types=1);` at the top of every new PHP file. Type-hint all params and returns
  (the codebase already does this well — keep it).
- **Always** use PDO prepared statements with bound params. Never interpolate user input into SQL.
  (This is non-negotiable and already the norm.)
- **Escape on output**, store raw: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` for all
  user-controlled values rendered into HTML; never echo raw request data.
- One responsibility per function; keep page controllers thin — push logic into `functions.php`
  helpers or new domain classes. Avoid business logic in views.
- **No secrets in code.** Read everything through `Config::`.
- Errors: throw/catch around I/O; respect `APP_DEBUG` for verbosity; user-facing pages show generic
  messages and log details (`error_log`).
- Naming: `camelCase` functions/vars, `PascalCase` classes, `UPPER_SNAKE` consts, table/column
  `snake_case`.

## 2. SQL standards

- Lowercase reserved words optional but consistent; explicit column lists (avoid `SELECT *` in new
  hot paths — the prototype uses it; prefer named columns going forward).
- Every new query that filters/sorts must have a supporting index (Doc 04 §4).
- Enums for closed sets (`role`, `urgency`, `status`, `tier`, `blood_type`).
- Migrations are **forward-only**, numbered (`NNN_description.sql`), idempotent where possible, and
  reviewed. Never edit a migration that has shipped.

## 3. JavaScript / jQuery standards

- **jQuery 3.x** is the interactivity layer (Doc 08). Self-hosted, not CDN.
- `'use strict';`; IIFE modules under a single `window.LifeLine` namespace; one file per surface.
- **Render user content safely:** build nodes with `$('<el>').text(value)`; **never** concatenate
  user data into `.html()` or inline `on*=` handlers (DEF-04).
- AJAX via `$.ajax` with a shared error handler and the CSRF token attached; JSON in/out.
- Use event delegation for dynamic content; throttle/debounce pollers; pause work on hidden tabs.
- Keep raw Canvas for the particle background; jQuery elsewhere.

## 4. CSS standards

- Design **tokens** (CSS variables) for all color/space/type/radius/motion (Doc 08).
- Component classes (`.btn`, `.card`, `.alert-*`, `.badge-*`); **no inline styles** in PHP (migrate
  the inline CSS in `renderPagination()` and pages into classes).
- Mobile-first; documented breakpoints; respect `prefers-reduced-motion`.
- BEM-ish, predictable class names; avoid deep selector nesting.

## 5. Documentation in code

- Docblocks on every public function/class (the kernel already does this — maintain it).
- A short header comment per page stating: purpose, actor/guard, methods, tables touched.
- Update the relevant `docs/` file in the **same PR** as a behavior change. Docs are part of "done."

---

## 6. Git workflow

**Branching (trunk-based with short-lived branches):**

```
main (always deployable, protected)
 └─ feat/<ticket>-short-desc
 └─ fix/<ticket>-short-desc
 └─ chore/…  docs/…  sec/…
```

- Branch from `main`; keep branches < a few days; rebase on `main` before opening a PR.
- **Conventional Commits**: `feat:`, `fix:`, `sec:`, `docs:`, `refactor:`, `test:`, `chore:` — drives
  changelogs and signals release impact.
- One logical change per PR; small PRs review faster and break less.

**Pull requests:**
- Template: *what / why / how to test / requirement & DEF IDs / screenshots / migration notes*.
- CI must be green (lint, static, tests, security — Doc 09 §5). Red blocks merge.
- ≥1 reviewer (≥2 + Security sign-off for anything touching auth, PII, SQL, uploads, or `DEF` items).
- Squash-merge to keep `main` linear and readable.

**Releases:** semantic version tags (`vMAJOR.MINOR.PATCH`); release notes from Conventional Commits;
prod deploys only from tags.

---

## 7. Definition of Done

A change is done when:

1. Meets its requirement(s); enums/validation/escaping applied; no new SQLi/XSS surface.
2. Tests added/updated and green (unit + integration; e2e for user-facing flows) — Doc 11.
3. Migrations numbered, reviewed, and applied on staging via expand/contract.
4. Audit logging added for any new mutation (FR-46).
5. Docs updated in the same PR; `DEF`/task checkboxes in Doc 15 ticked.
6. CI green; reviewed; security sign-off where required.

---

## 8. Owners legend (used across the docs & Doc 15)

`HM`=Hayredin · `BT`=Bemnet · `BM`=Bethelhem · `EM`=Euel · `LA`=Lidiya · `FR`=Firdows ·
`SEC`=Security officer · `MED`=Clinical advisor · `PO`=Product owner.

*Back to the [Documentation Index](00-Documentation-Index.md).*

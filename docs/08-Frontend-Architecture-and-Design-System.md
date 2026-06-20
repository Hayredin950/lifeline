# 08 · Frontend Architecture & Design System

**Status:** 🟡 In review · **Owner:** Frontend · **Traces to:** Doc 00 (stack), NFR-12/14, FR-Msg.

The frontend is **server-rendered HTML enhanced with CSS and jQuery**. There is no SPA framework and
no bundler-driven component model — by mandate. This document defines the jQuery adoption plan, the
design system, and the accessibility/performance bar.

---

## 1. The jQuery mandate (and the current gap)

**Mandated interactivity layer: jQuery 3.x.** The current build ships **vanilla JS**
(`assets/js/app.js`, 558 lines: particle canvas, toasts, charts, scroll/nav interactions) and
hand-rolled `fetch`/DOM code in `messages.php`. There is **no jQuery in the project today** — this is
a stack-compliance gap to close.

**Adoption strategy (incremental, low-risk):**

1. **Vendor jQuery** — self-host `assets/vendor/jquery-3.7.x.min.js` (do **not** hot-link a CDN; it
   complicates CSP and adds a third-party dependency — Doc 07). Load once in `header.php` before `app.js`.
2. **Wrap, don't rewrite** — convert the AJAX/DOM-heavy surfaces first (messaging polling, toasts,
   form submits, nav toggle) to jQuery (`$.ajax`, `$(…).on()`, `.text()/.html()`). Keep the
   `<canvas>` particle animation in raw Canvas API (jQuery adds nothing there).
3. **Standard patterns** — establish house idioms:
   - AJAX: `$.ajax({url, method, data, dataType:'json'})` with a shared error handler + CSRF header.
   - DOM-safe rendering: build message nodes with `$('<div>').text(content)` — **never** string-concatenate
     user content into `.html()` or inline handlers (kills DEF-04).
   - Event delegation: `$(document).on('click', '.js-action', …)` for dynamically-added items.
4. **Progressive enhancement** — every form still works without JS (server handles the POST); jQuery
   only adds optimistic UI, toasts, and live polling.

---

## 2. Module map (`assets/js/app.js` → jQuery modules)

| Concern | Today | Target (jQuery) |
|---|---|---|
| Particle background | `initParticles()` raw canvas | unchanged (canvas) |
| Toasts | custom DOM | `LifeLine.toast()` jQuery util |
| Charts (blood-type dist.) | custom | small jQuery render helper / lightweight chart |
| Nav / mobile menu | `getElementById` + listeners | `$('#mobileMenuToggle').on('click', …)` |
| Messaging polling | `fetch` every 3 s in `messages.php` | `$.ajax` poller in `messages.js`, fingerprint de-dupe |
| Form UX (loading, inline errors) | inline | shared `LifeLine.forms` jQuery module |

Organize as small IIFE modules under a single `window.LifeLine` namespace; one file per surface
(`messages.js`, `forms.js`, `ui.js`) concatenated/minified at deploy (no framework, just a copy/min step).

---

## 3. Design system

The product already has a coherent **dark, crimson-accented medical-urgency** aesthetic
(`assets/css/style.css`, ~2 500 lines, CSS variables). Formalize it as tokens:

| Token group | Examples |
|---|---|
| Color | `--crimson:#b91c1c`, `--crimson-light`, urgency colors (critical `#991b1b`, urgent `#92400e`, normal `#1e40af`), surfaces, text, success/danger |
| Type | system font stack; scale (h1→caption); weights |
| Space | 4-pt spacing scale; container max-width |
| Radius / elevation | card radius, shadow levels |
| Motion | durations/easings for toasts, hovers, scroll |
| Components | buttons (`.btn`, `.btn-small`, `.btn-secondary`), cards, alerts (`.alert-*`), badges (`.badge-unread`), tables, forms, podium, nav |

**Rules:** all colors/spacing reference variables (no magic hex in components); urgency colors are
shared with the email templates (`email_service.php`) and the request UI for consistency. Remove the
significant **inline styles** scattered through PHP (e.g. `renderPagination()` inline CSS) into
component classes.

---

## 4. Responsive & mobile

- Mobile-first; the app is already responsive (mobile menu toggle, responsive grids, messaging
  sidebar collapse). Formalize breakpoints (e.g. 480/768/1024).
- Touch targets ≥44 px; the eligibility and SOS forms are the critical mobile flows — optimize first.
- **PWA bridge** (Doc 12): manifest + service worker for installability, offline shell, and Web Push
  notifications — the path to "mobile" without leaving the stack.

---

## 5. Accessibility (WCAG 2.1 AA — NFR-12)

| Area | Requirement |
|---|---|
| Semantics | Landmarks (`header/nav/main/footer` — present), headings in order, `<label>` for every field |
| Keyboard | All interactive elements reachable & operable; visible focus rings; no keyboard traps in messaging |
| Contrast | Dark theme must meet 4.5:1 for text; verify crimson-on-dark and badge colors |
| ARIA | `aria-expanded` on the menu toggle (present); live-region for toasts & new messages; `aria-current` on active nav |
| Forms | Inline errors announced; required/invalid state programmatic, not color-only |
| Media | Avatar/img `alt`; decorative canvas `aria-hidden` |
| Motion | Respect `prefers-reduced-motion` (pause particles/animations) |

Accessibility is also a **growth** lever: public-health platforms are procured against accessibility
standards.

---

## 6. Frontend performance (NFR-01/14)

- Serve CSS/JS/images from a **CDN**; long-cache with content hashes.
- Self-hosted, minified jQuery (one file) + concatenated app modules; defer non-critical JS.
- Lazy-load images; size and compress the hero/avatar assets (the repo ships several large JPGs).
- Throttle the messaging poll and **pause it when the tab is hidden** (`visibilitychange`) — major
  load reduction at scale.
- Inline critical CSS for first paint; the rest async.

---

## 7. Internationalization (NFR-13)

- Storage is already `utf8mb4` end-to-end. Externalize UI strings into a PHP message catalog
  (`lang/en.php`, `lang/hi.php`, …) resolved by a `t('key')` helper; never hard-code user-facing copy.
- Locale-aware dates/numbers; design components to tolerate text expansion and RTL.
- India-first: English + major regional languages are a real adoption driver.

---

## 8. Frontend acceptance gate

- [ ] jQuery self-hosted and loaded; messaging/toasts/forms/nav ported to jQuery idioms.
- [ ] No user content reaches `.html()`/inline handlers unescaped (DEF-04 closed on the client side).
- [ ] Design tokens extracted; inline styles removed from PHP.
- [ ] WCAG 2.1 AA audit passed on the 6 core flows.
- [ ] Lighthouse: Performance & Accessibility ≥ 90 on mobile.
- [ ] PWA manifest + service worker (install + offline shell).

*Back to the [Documentation Index](00-Documentation-Index.md).*

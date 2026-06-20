# 06 · API Specification

**Status:** 🟡 In review · **Owner:** Engineering · **Traces to:** FR-33…FR-38, Doc 03 §6.

This covers (a) the **as-built messaging AJAX API** and (b) the **target public/internal REST API**
that the platform needs for mobile/PWA clients, partner integrations, and async processing — all
implemented in vanilla PHP, consumed from the browser via jQuery `$.ajax`.

---

## 1. Conventions

- Transport: HTTPS only. Browser calls use **jQuery AJAX**; server returns `application/json`.
- Auth: session cookie (`HttpOnly`, `SameSite=Strict`). State-changing calls require the CSRF token
  (form field today; `X-CSRF-Token` header for the target API).
- Errors: `{ "success": false, "error": "<message>", "code": "<machine_code>" }` + correct HTTP status.
- Success: `{ "success": true, "data": … }`.
- All inputs validated server-side; all output values are pre-escaped or explicitly raw-by-contract.

---

## 2. As-built: Messaging API (`app/lifeline/api/`)

### `GET /api/get_messages.php?conversation={userId}`
- **Auth:** `requireAuth()`. **Returns:** last 100 messages between the caller and `{userId}`,
  ascending; marks inbound messages read.
- **Response:** `{ success, messages:[{id, sender_id, receiver_id, content, is_read, is_edited, created_at}], current_user_id }`
- **Hardening (FR-34/DEF-08):** assert `{userId}` has at least one message with the caller or an
  established relationship; paginate with `?before_id=` beyond 100.

### `POST /api/send_message.php`
- **Body:** `receiver_id`, `content`, `csrf_token`. **Effect:** INSERT `messages`; INSERT
  `notifications` for the receiver linking back to the thread.
- **Hardening:** validate `receiver_id` exists and is a permitted counterpart; cap `content` length
  (e.g. 4 000 chars); store raw, escape on output (DEF-04).

### `POST /api/edit_message.php`
- **Body:** `message_id`, `content`, `csrf_token`. **Effect:** `UPDATE … SET content, is_edited=1
  WHERE id=? AND sender_id=?`. **Blocked by DEF-11** until `is_edited` exists in the schema.

### `POST /api/delete_message.php`
- **Body:** `message_id`, `csrf_token`. **Effect:** hard `DELETE … WHERE id=? AND sender_id=?`.
  **Target:** soft-delete (`deleted_at`) so the counterpart's thread stays coherent.

### Polling contract
Client polls `get_messages` every 3 s; de-dupes via `(id-content-is_edited)` fingerprints; appends
optimistically on send; auto-scrolls only when the message count grows.

---

## 3. Target transport evolution (FR-37)

Polling is fine at pilot scale but is O(users × 1/3s) wasted queries at national scale. Evolution
path, staying on the stack:

| Stage | Mechanism | Notes |
|---|---|---|
| Now | jQuery short-poll (3 s) | Simple, works everywhere. |
| Next | **Long-poll** endpoint (`/api/poll.php` holds open ~25 s) | Cuts idle queries; still plain PHP. |
| Scale | **Server-Sent Events** (`text/event-stream` from PHP) | One-way push for messages/notifications; jQuery/`EventSource` client. |
| Peak | Dedicated **WebSocket** sidecar (e.g. a small PHP/Ratchet or edge service) | Only if SSE proves insufficient; app data stays in MySQL. |

A Redis pub/sub channel per user bridges worker events → SSE without polling MySQL (Doc 12).

---

## 4. Target REST API (versioned, `/api/v1`)

Needed for PWA/mobile, partner hospitals, and EHR integration. JSON, token-auth (short-lived bearer
issued from the session, or API keys for server-to-server partners), strict rate limits, OpenAPI 3.1
spec published.

### Auth & accounts
| Method · Path | Purpose |
|---|---|
| `POST /api/v1/auth/login` | Exchange credentials → session/bearer |
| `POST /api/v1/auth/logout` | Invalidate |
| `POST /api/v1/auth/register` | Create donor/hospital |
| `POST /api/v1/auth/password/forgot` · `/reset` | Reset flow |
| `POST /api/v1/auth/2fa/verify` | TOTP/SMS (FR-09) |

### Donors & matching
| Method · Path | Purpose |
|---|---|
| `GET /api/v1/donors?blood_type=&lat=&lng=&radius_km=&available=` | Geo donor search (ranked) |
| `GET /api/v1/donors/{id}` | Profile (contact gated by authz) |
| `PATCH /api/v1/donors/me` | Update own profile / availability |
| `GET /api/v1/me/eligibility` | Server-computed eligibility & next-eligible date |

### Requests & donations
| Method · Path | Purpose |
|---|---|
| `POST /api/v1/requests` | Hospital creates request |
| `GET /api/v1/requests/{id}/matches` | Ranked compatible donors |
| `POST /api/v1/requests/{id}/interest` | Donor expresses interest |
| `PATCH /api/v1/matches/{id}` | Transition match state (validated FSM, FR-28) |
| `POST /api/v1/donations` | Record a completed donation (transactional) |

### Emergency
| Method · Path | Purpose |
|---|---|
| `POST /api/v1/sos` | Create SOS → **enqueue** broadcast (FR-31); requires CAPTCHA/OTP (FR-30) |

### Messaging & notifications
| Method · Path | Purpose |
|---|---|
| `GET /api/v1/conversations` · `/{id}/messages` | Threads & messages (paginated) |
| `POST /api/v1/conversations/{id}/messages` | Send |
| `GET /api/v1/notifications` · `POST …/read` | Bell list & mark-read |
| `GET /api/v1/stream` (SSE) | Live messages + notifications |

### Admin & integration
| Method · Path | Purpose |
|---|---|
| `GET /api/v1/admin/audit` | Audit query/export (auth: admin) |
| `POST /api/v1/webhooks` | Partner webhook registration |
| `GET /api/v1/health` (deep) · `/healthz` (shallow, anon) | Split health: anon shallow vs auth deep (DEF-15) |

---

## 5. Cross-cutting API requirements

| Area | Requirement |
|---|---|
| Versioning | URL-versioned `/api/v1`; additive changes only within a version. |
| Rate limiting | Per-token + per-IP token bucket at the edge/Redis (DEF-12); SOS extra-strict. |
| Validation | Centralized request validators; reject unknown fields; enum-checked (DEF-05/06). |
| Idempotency | `Idempotency-Key` on `POST /sos`, `/donations`, `/messages` to dedupe retries. |
| Pagination | Cursor-based (`?before_id=`/`?cursor=`), default 25, max 100. |
| Observability | Request ID per call; structured access logs; latency histograms (NFR-01). |
| Docs | Machine-readable OpenAPI 3.1 + generated reference; contract tests in CI (Doc 11). |
| Errors | Stable machine `code`s; never leak stack traces (gate on `APP_DEBUG`). |

*Back to the [Documentation Index](00-Documentation-Index.md).*

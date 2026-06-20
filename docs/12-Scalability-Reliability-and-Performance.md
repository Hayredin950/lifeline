# 12 · Scalability, Reliability & Performance

**Status:** 🟡 In review · **Owner:** Platform/Engineering · **Traces to:** NFR-01…06, Doc 03 §8, Doc 09.

The thesis: **the same PHP/MySQL/jQuery code scales from one district to a continent** by changing
configuration, not architecture. This document is the engineering of that claim — how to take a
single-VM prototype to billions of requests without abandoning the mandated stack.

---

## 1. Where the prototype breaks under load (honest baseline)

| Bottleneck | Cause in current code | Symptom at scale |
|---|---|---|
| **Local file sessions** | default PHP session handler | Can't add a 2nd app node (no shared state) — NFR-04 |
| **Synchronous SOS email loop** | `emergency.php` loops + sends inline | Request timeouts; partial sends — DEF-03 |
| **3-second polling** | `messages.php` per client | Query storm: users × 0.33 qps wasted |
| **Full-scan matching** | `city LIKE` + no geo index | Slow donor search as donor table grows — FR-20 |
| **`SELECT *` + no composite indexes** | prototype convenience | Table scans on hot paths — Doc 04 §4 |
| **Persistent PDO** | `ATTR_PERSISTENT=true` | Connection exhaustion across many FPM workers — DEF-16 |
| **In-process geocode** | Nominatim call on save | Third-party latency/limits block the request |

None require leaving the stack. Each has a stack-native fix below.

---

## 2. The scaling ladder

### Tier 0 — Single VM (0–50k users / district pilot)
Apache/Nginx + PHP-FPM + MySQL + Redis on one box. Add the **indexes** (Doc 04 §4), turn on
**OPcache**, and you comfortably serve a city. Cost: one small VM.

### Tier 1 — Stateless horizontal app tier (state/region)
1. **Externalize sessions to Redis** (`SESSION_DRIVER=redis`) → app tier becomes stateless.
2. Put **N PHP-FPM nodes** behind a load balancer (identical container image).
3. **CDN** for `assets/*` (CSS/JS/images) — offloads the majority of bytes.
4. Result: scale app throughput linearly by adding nodes. No code change.

### Tier 2 — Async everything (national)
1. **Queue** (Redis/Beanstalk) + **worker pool**: SOS broadcast, all transactional email, geocoding,
   achievement computation move off the request path (NFR-02). SOS becomes *enqueue < 200 ms*.
2. **Cache** read-heavy public pages (homepage stats, leaderboard, blood-bank directory) in Redis
   with short TTLs + event-based invalidation.
3. **Replace polling** with **SSE** backed by Redis pub/sub (Doc 06) — one push channel per user
   instead of N pollers hammering MySQL.

### Tier 3 — Data tier scale-out (multi-state / multi-country)
1. **Read replicas**: route reads (search, leaderboard, dashboards) to replicas; writes to primary.
   Front with **ProxySQL** (also fixes the persistent-connection problem — DEF-16).
2. **Geo/spatial**: store donor/bank location as `POINT` + `SPATIAL` index; match with
   `ST_Distance_Sphere` and a bounding box — O(log N) range instead of O(N) scan (FR-20).
3. **Partition hot tables**: `audit_logs`, `messages`, `notifications`, `donation_history` by time
   range; archive cold partitions to cheap storage.
4. **Search**: if free-text donor/bank search grows, add an index engine (OpenSearch) fed by the DB.

### Tier 4 — Geo-distribution (global / "billions")
1. **Region-sharded by geography** — blood is *local*; a request in Delhi never needs a donor in
   Lagos. Shard the donor/request graph by region; each region is a self-contained Tier-3 stack.
2. **Global routing + edge** (Anycast/CDN/edge cache) sends users to their nearest region.
3. **Cross-region** only for global concerns (identity federation, analytics warehouse, admin).
4. This sharding is *natural* to the domain — locality is a feature, not a constraint — which is why
   the stack scales to global volume without a rewrite.

---

## 3. Performance engineering checklist

| Lever | Action | Target |
|---|---|---|
| OPcache | Enable + tune; preload kernel | −CPU, +RPS |
| Indexes | Composite + spatial (Doc 04 §4) | p95 query < 20 ms |
| Query hygiene | Named columns, `EXPLAIN` every hot query, kill N+1s | No scans on hot paths |
| Caching | Redis page/fragment cache for public reads | Homepage/leaderboard < 50 ms |
| Static | CDN + content-hash long cache + compression | <1 origin hit per asset/version |
| Client | Self-host min jQuery, defer JS, lazy images, **pause poll on hidden tab** | Faster TTI, less load |
| DB pool | ProxySQL; drop ad-hoc persistent conns | Stable under FPM fan-out |
| Connection | Keep-alive, HTTP/2 | Fewer RTTs |

p95 server render **< 400 ms** at 1k RPS (NFR-01); SOS enqueue **< 200 ms** (NFR-02).

---

## 4. Reliability & resilience (NFR-05/06)

- **No SPOF:** ≥2 of everything in prod (app nodes, replicas, queue, Redis with failover).
- **Health-gated LB** via `healthz`; unhealthy nodes drained automatically.
- **Graceful degradation** (already a design value): email unconfigured → log; geocode fails → text
  search; replica lag → read-from-primary fallback for critical reads.
- **Backups + PITR**, monthly restore drills; replica failover runbook (RPO ≤ 5 min, RTO ≤ 1 h).
- **Circuit breakers / timeouts** on every external call (SMTP, geocode); retries with backoff +
  idempotency keys (Doc 06).
- **Capacity planning:** load-test to 2× projected peak each major release; autoscale on CPU/queue depth.

---

## 5. Capacity model (illustrative)

| Scale | Donors | Peak RPS | Topology | Rough infra |
|---|---|---|---|---|
| Pilot | 50k | 50 | Tier 0 | 1 VM |
| State | 2M | 1k | Tier 1–2 | LB + 4 app + Redis + CDN |
| National | 30M | 15k | Tier 3 | + replicas + ProxySQL + workers + spatial |
| Global | 300M+ | 100k+ | Tier 4 | region-sharded Tier-3 cells + edge |

The point is not the exact numbers — it's that each row is the **previous row plus configuration and
managed services**, never a re-platform off PHP/MySQL.

## 6. Cost discipline

Locality-sharding means cost scales with *active regional demand*, not global headcount. A dormant
donor costs a row; an active region costs a cell. This keeps unit economics healthy as the network
grows — the financial counterpart in Doc 13.

*Back to the [Documentation Index](00-Documentation-Index.md).*

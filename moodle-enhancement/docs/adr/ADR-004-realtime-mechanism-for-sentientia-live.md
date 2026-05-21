# ADR-004 — Real-time mechanism for `local_sentientia_live` (Mentimeter clone)

- **Status:** Accepted
- **Date:** 2026-05-21
- **Decider:** Nitin Rajput (deferred to Claude under continuous-build mandate)
- **Stream:** E — Live engagement (Tier 1 #3)
- **Phase:** E.0 — foundation

---

## Context

`local_sentientia_live` is the Mentimeter clone for Sentientia LMS — trainers run
live polls/quizzes/Q&A; the audience joins via a code, answers in real time,
and watches results stream back. The fundamental design question is **how the
audience and trainer screens stay in sync without each user holding a noisy
polling loop open**.

The plugin will run on the same XAMPP / AWS RDS stack as the rest of
Sentientia LMS, so the choice is constrained by what works inside Apache + PHP
without adding new infrastructure for the Airpay deployment.

---

## Options considered

### A — Long polling

Trainer's "next slide" UI: long POST that the server holds until the audience
event fires. Audience: same — long GET that holds until the trainer pushes the
next slide.

**Pros:** No new tech; works through any reverse proxy; pure HTTP.

**Cons:** Each open connection holds an Apache worker. With Apache MPM Prefork
(typical XAMPP default) and ~150 workers, ~150 simultaneous audience members
exhausts the pool. Scales badly past a typical training room.

### B — Short polling (every 2–3 s)

Audience polls `GET /current_slide` every 2-3 seconds. Trainer polls
`GET /response_count` similarly.

**Pros:** Trivially compatible with every host on earth; no Apache worker
held open.

**Cons:** O(N) requests per second for N audience members. 100 attendees = 30-50
hits/second. Eats DB connections, adds 2-3 s delay to "next question" UX
(perceptible — Mentimeter feels instant). Hard cap on session size.

### C — Server-Sent Events (SSE)

`text/event-stream` response held open by the server, pushed-to via line writes.
Browser auto-reconnects on disconnect. One-way (server → client) only —
client-to-server still uses regular POST.

**Pros:**
- Native PHP support (`while (true) { echo "data: ...\n\n"; flush(); }`)
- One worker per connected client (same cost as long polling, but Apache MPM
  workers can be tuned higher because the connection is genuinely idle except
  during event writes — and PHP-FPM scales better than mod_php for this)
- Built-in auto-reconnect via `EventSource(...)` in the browser
- Works through all major reverse proxies that don't enforce response buffering
  (need `X-Accel-Buffering: no` for nginx; Apache works out of the box)
- The trainer-side "audience joined / response received" updates and the
  audience-side "next slide / show results" updates are both ONE-WAY pushes —
  exactly what SSE optimises for.

**Cons:**
- One Apache worker per active SSE connection — same scaling cliff as long
  polling. Mitigated by:
  - Stream B PWA + push notifications already lets us "wake" devices remotely,
    so we don't need an always-connected SSE for low-priority traffic.
  - Apache event MPM (XAMPP supports it; flip in httpd.conf) frees the worker
    during the idle gap, raising the cap to ~1000 connections per server.
  - For sessions > 500 attendees we'd switch to NGINX + PHP-FPM (Airpay is
    already on AWS, can swap).
- Audience client-to-server (response submission) still needs a regular POST
  — not a true bidirectional protocol.

### D — WebSockets

Bidirectional protocol via `ws://` upgrade. Persistent, low-overhead, used by
the actual Mentimeter.

**Pros:** Best-in-class for high-frequency bidirectional updates.

**Cons:**
- Apache + PHP can do WebSockets but PHP isn't designed for long-lived
  processes — you'd run a separate `ratchet/pawl` or `swoole` daemon.
- Adds a second process to deploy, monitor, restart, scale.
- Reverse proxy / load-balancer configuration becomes non-trivial.
- Airpay's IT team would need to support a new long-lived service alongside
  Apache — a deployment burden we shouldn't impose until the user count
  justifies it.

### E — Third-party (Pusher / Ably / Pubnub / Supabase realtime)

Outsource the websocket plumbing to a SaaS.

**Pros:** Zero infra burden; high scale; battle-tested.

**Cons:**
- Recurring per-message / per-connection cost.
- New vendor dependency for a feature that's not core to Sentientia LMS's
  value proposition.
- Sentientia LMS positioning as enterprise SaaS — pushing all live-engagement
  traffic to a third-party SaaS muddies that story.
- Compliance: messages flow through an external provider. Airpay's auditors
  will want to know what data leaks (per Nitin: "Airpay has auditors, not our
  bottleneck" — but the answer "we don't share data with third parties" is
  still preferable when achievable).

---

## Decision

**SSE (Option C) for the server → client direction, regular POST for client →
server.** Audience receives slide-transition + result-update events via SSE;
audience POSTs responses via a standard `webservice/rest` call.

Worker-cost concern mitigated by:
1. The Airpay deployment is currently sized for ~3,500 users TOTAL. A typical
   live session has 20-100 active participants. Apache's default ~150 workers
   absorb that comfortably.
2. We add a feature flag `live.realtime.enabled` (default ON) that downgrades
   to **Option B short polling** when admin needs to constrain worker pool
   usage. Both code paths ship.
3. Hard cap of 500 concurrent SSE connections per session — admin setting
   `live_max_concurrent`. Sessions advertising > 500 fall back to short
   polling automatically.

If Sentientia LMS ever lands a customer running 1000-attendee all-hands
sessions, we revisit and move to Option D (WebSocket daemon).

---

## Consequences

### Positive

- No new infrastructure for Airpay deployment.
- Native PHP — works on every Sentientia LMS deployment without coordination.
- Auto-reconnect comes free from the browser.
- Compliance story stays clean ("no third-party realtime provider").

### Negative

- Caps at ~500 concurrent attendees per session on default Apache config.
  Mentimeter handles ~50,000.
- One-way only — client-to-server still uses normal POST. Slightly higher
  latency for response submission than WebSocket (but Mentimeter perceives
  the same way because responses are async anyway).
- Apache workers are tied up during SSE connections; need to monitor pool
  saturation in production.

### Reversibility

**High.** The SSE entry point is a single PHP file
(`/local/sentientia_live/stream.php`). If we need to swap for WebSockets later,
the JS `EventSource(stream.php)` becomes `new WebSocket(...)` and the PHP
becomes a Ratchet handler. The DB schema and the response-POST endpoint stay
unchanged. Estimated swap effort: 2 sessions.

---

## Implementation notes

### Server side (`/local/sentientia_live/stream.php`)

```php
<?php
require_once(__DIR__ . '/../../config.php');
require_login();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Accel-Buffering: no');   // disable nginx buffering
ob_implicit_flush(true);
while (ob_get_level()) {
    ob_end_flush();
}

// Tear down expensive resources before the long loop.
\core\session\manager::write_close();

$sessionid = required_param('sessionid', PARAM_INT);
$last_event_id = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);

while (true) {
    // Poll DB for events newer than $last_event_id.
    // Yield each as: id: N\ndata: {...}\n\n
    // Sleep 1 second between checks.
    if (connection_aborted()) {
        break;
    }
    sleep(1);
}
```

### Client side

```javascript
const stream = new EventSource('/local/sentientia_live/stream.php?sessionid=42');
stream.addEventListener('slide_changed', (e) => {
    const data = JSON.parse(e.data);
    renderSlide(data.slide);
});
stream.addEventListener('response_added', (e) => {
    const data = JSON.parse(e.data);
    updateResultsChart(data);
});
```

### DB-side event journal

A `local_sentientia_live_events` table holds the event journal:
- `id` (auto-incrementing serial — also serves as SSE `id:`)
- `sessionid`
- `type` (slide_changed, response_added, session_ended)
- `payload_json`
- `created_at`

The stream loop just polls `SELECT ... WHERE sessionid = ? AND id > ?`. Cleanup
cron purges events older than 24h (session lifetime cap).

---

## References

- [MDN: Using server-sent events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events/Using_server-sent_events)
- [HTML Living Standard: EventSource](https://html.spec.whatwg.org/multipage/server-sent-events.html)
- Phase B PWA work (`docs/adr/ADR-003-hand-rolled-web-push-crypto.md`) for the
  push-notification "wake the device" complement.

---

## Audit Trail

- 2026-05-21 — Drafted by Claude during Phase E.0 (Mentimeter clone foundation).
- _Pending_: Apache worker-pool sizing review when first 100-attendee session
  is scheduled.
- _Pending_: Revisit decision when Sentientia LMS lands first customer with
  > 500 concurrent attendee sessions.

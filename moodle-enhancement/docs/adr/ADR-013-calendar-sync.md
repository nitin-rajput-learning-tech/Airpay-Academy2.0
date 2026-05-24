# ADR-013 — Calendar sync: token-URL ICS feed vs OAuth bi-directional

**Status:** Accepted
**Date:** 2026-05-24
**Deciders:** Nitin Rajput, Claude
**Workstream:** Tier 2 #6 (Sentientia LMS roadmap)
**Plugin:** `local_sentientia_calendar`

---

## Context

Tier 2 of the Sentientia LMS roadmap calls for calendar integration so
learners can see their course deadlines, classroom (ILT) sessions, and
exam close-dates inside their existing calendar app (Outlook on the web
+ desktop, Google Calendar, Apple Calendar on macOS / iOS) — not just
inside Sentientia LMS.

Two architecturally distinct approaches were considered:

### Path A — Outbound-only ICS subscription URL (token-authenticated)

Each user gets a personal subscription URL of the shape:

```
https://www.airpay.academy/local/sentientia_calendar/ics.php?token=<64-char>
```

Calendar clients periodically (Outlook: hourly; Google: every 8-24h;
Apple: every 1-24h depending on user setting) fetch this URL. The
server returns a `text/calendar` body (RFC 5545) containing one VEVENT
per upcoming deadline.

**Authentication:** the token in the URL. No OAuth. No third-party API
keys. The token is a 64-char URL-safe random string (~381 bits of
entropy) generated via `random_bytes()`. One active token per user;
regenerate revokes the old one.

**Direction:** outbound only — read-only into the user's calendar.
The user cannot edit or delete events from their calendar app and have
the change reflected in Sentientia LMS.

### Path B — OAuth-mediated bi-directional sync

Sentientia LMS authenticates against Microsoft Graph (for Outlook /
Office 365) and Google Calendar API per-user via OAuth 2.0. Each
deadline becomes an event in the user's primary calendar via the
provider's REST API; deletions in the calendar app push back to
Sentientia LMS.

**Authentication:** OAuth 2.0 with per-user refresh tokens stored in
Sentientia LMS, an admin-configured app registration in Microsoft
Azure AD + Google Cloud Console, and Microsoft Graph API + Google
Calendar API consent screens that need privacy + security review at
each provider.

**Direction:** bi-directional — events flow into and out of the user's
calendar.

---

## Decision

**Adopt Path A (token-URL ICS feed) for Tier 2.6 Phase 1.**
Defer Path B (OAuth bi-directional) until Phase 2 if/when bi-directional
demand becomes clear.

Specifically:
1. Ship `local_sentientia_calendar` with one user-facing page,
   one feed endpoint, one regenerate endpoint, one DB table.
2. Master feature flag `sentientia.calendar_sync.enabled` — default OFF.
3. Three sub-flags scoping which event types appear in the feed:
   - `sentientia.calendar_sync.events.courses` (default ON)
   - `sentientia.calendar_sync.events.classroom` (default ON)
   - `sentientia.calendar_sync.events.exams` (default ON)
4. Apple/iCloud, Outlook, Google all accept the `webcal://` and `https://`
   ICS subscription URL shapes; the page surfaces both so the user can
   click through on mobile.

---

## Rationale

### Why Path A wins for v1

| Dimension                              | Path A (ICS URL)         | Path B (OAuth)                       |
|----------------------------------------|--------------------------|--------------------------------------|
| Provider API keys required             | None                     | Microsoft + Google + per-customer    |
| Per-customer setup before rollout      | None — pure server-side  | App registrations × 2 providers × N customers |
| Time-to-MVP                            | One session              | 3-4 sessions per provider            |
| Maintenance burden                     | Zero outside Moodle      | Refresh-token rotation, scope drift, consent UI churn |
| Vendor lock-in                         | None — pure RFC 5545     | Two — Microsoft Graph + Google APIs  |
| Phase 2 customer onboarding cost       | Zero                     | Bespoke admin setup per new customer |
| Privacy review surface                 | Token + IP audit only    | Provider data-processing addendums   |
| Coverage of mobile clients             | All — every iOS/Android client speaks RFC 5545 | Limited — Apple Calendar's native Microsoft sync is restricted |
| Bi-directional sync                    | No                       | Yes                                  |
| Real-time freshness                    | 1-24h (provider-dependent) | Minutes                              |

The bi-directional column is the only one Path B wins on. For the
specific use case — "let me see my Sentientia deadlines in the calendar
I already use" — bi-directionality is not what learners are asking for.
They want VISIBILITY of deadlines; they do not want to MODIFY deadlines
from their phone calendar app. Modifying deadlines is a manager's
authority and a managed UI in Sentientia LMS, not a sync conflict to
resolve.

### Why we keep Path B as a future option

Path B becomes correct if:
- A customer demands two-way sync as a procurement requirement
- We add user-created events (1-on-1 mentoring sessions in Phase 3+)
  that the user might naturally accept/decline in their calendar app
- Provider-side notification quality becomes worse than ours (push
  notifications via PWA / WhatsApp are already in flight in Phase B,
  reducing this risk)

Phase 2 retrofit: Path A's database table (one row per user with token)
is orthogonal to a Phase 2 OAuth table; both can coexist. Users on a
Path A subscription can be opt-up-migrated to Path B without losing
their existing deadlines (they're regenerated on each fetch anyway).

---

## Security model

### Threat: token leak

A 64-char URL-safe random token has ~381 bits of entropy. Brute-force
discovery is infeasible. The risk is leakage via:

- The user pasting the URL into a public chat / bug report / git commit
- The URL appearing in browser history of a shared device
- The URL being intercepted in transit (mitigated by mandatory HTTPS in
  production)

**Mitigations:**
1. The subscription page warns the user (translated EN + HI) to treat
   the URL like a password.
2. A "Regenerate URL" button revokes the leaked token immediately. Old
   tokens are kept (revoked=1) for 90 days so an audit can answer "who
   used this leaked token, when?", then purged by the daily cron.
3. `ics.php` returns 404 (not 401/403) for unknown/revoked/wrong-shape
   tokens. An attacker cannot probe the existence of a tenant or user.
4. `ics.php` defines `NO_MOODLE_COOKIES` so the fetch does NOT create a
   Moodle session for the token bearer.
5. Token lookups short-circuit on syntactic gates (length, charset)
   before any DB hit — denies easy timing oracles.
6. Audit fields (`last_used_at`, `last_used_ip`, `use_count`) provide
   forensic context for abuse reports.

### Threat: enumeration

The token is the only authentication. There is no username + password
combination to enumerate. A malicious fetcher gets the same 404 for
"token doesn't exist", "token revoked", "feature flag off", and "user
suspended" — denying any oracle.

### Threat: feed-content leak

The feed body contains the user's course names, classroom-session
titles, exam names, and Sentientia LMS deep-links. None of this is
personally identifiable beyond the user's own learning record. The
feed does NOT include:
- Other users' names, emails, or IDs
- Salary or HR data
- Course content (only deadlines + titles)
- API tokens or session keys

### Threat: feature-flag bypass

The master feature flag `sentientia.calendar_sync.enabled` is enforced
in **three** places: the UI page, the regenerate endpoint, and the ICS
fetch endpoint. Disabling the flag immediately disables every surface.

---

## Implementation notes

### Token format

- 64 characters, alphabet `[A-Za-z0-9]` excluding confusable pairs
  (0/O, 1/l/I) → 56-character alphabet
- ~381 bits of entropy
- Generated via `random_bytes()` — cryptographically secure

### Timezone

Every VEVENT uses `TZID=Asia/Kolkata` (IST, UTC+5:30, no DST). This is
hardcoded for the Airpay customer-zero deployment. Phase 1.x will move
this to a per-customer setting once `local_airpay_core::get_customer_branding()`
grows a `timezone` field. Until then, customers in different timezones
will see their events shifted — an acceptable Phase 1 limitation.

### Feed contents

Per `ics_builder::build_for_user($userid)`:

| Source                              | Window                | VEVENT shape          |
|-------------------------------------|-----------------------|-----------------------|
| Course completion deadline          | -180 to +∞ days       | All-day VEVENT        |
| Classroom session                   | All (past + future)   | Timed VEVENT          |
| Exam close (`quiz.timeclose`)       | now to +90 days       | Timed 30-minute event |

User scoping: all queries scope by `userid` (the user authenticated via
the token). No cross-tenant data can leak because the queries only
project the authenticated user's enrolments / classroom roster / quiz
attempts.

### Cache headers

`Cache-Control: no-store, no-cache, must-revalidate, private` — calendar
clients re-fetch each time their refresh interval ticks. Cached
intermediaries (proxies, CDNs) MUST NOT cache the body because the body
varies per-token. The `private` directive prevents shared caches from
storing it.

---

## Consequences

### Positive
- Ships in one session vs 6-12 sessions for Path B
- Zero ongoing third-party API maintenance
- Works on every mobile client, including ones without OAuth-mediated
  Microsoft / Google sync (e.g. macOS desktop Outlook, Apple Calendar
  on iOS not signed into Microsoft 365)
- Per-customer onboarding requires zero setup outside Sentientia LMS
- Privacy review surface is small — one secret token per user

### Negative
- One-way sync only — events deleted in the calendar app reappear on
  next fetch
- Token is a credential; users must be careful with it (mitigated by
  documentation + regenerate UX)
- Refresh latency 1-24h depending on the client's poll interval
- No "this event has been moved" notification — when a deadline shifts,
  the user sees the new event on next fetch, no in-calendar reminder

### Neutral
- Customers asking for true bi-directional sync will be told "Phase 2"
- The audit-trail rows (last_used_ip etc) persist for 90 days after
  revocation — long enough for forensics, short enough for GDPR retention

---

## References

- RFC 5545 — Internet Calendaring and Scheduling Core Object Specification (iCalendar)
- `local_airpay_classroom\ics_builder` — existing single-session ICS download (model for the build code)
- `local_airpay_courses\task\course_reminder` — deadline source query
- `local_airpay_exams\task\exam_reminder` — exam-close source query
- ADR-002 — Customer-Level Feature Flags (used here for the master flag)
- ADR-005 — PWA install + native-wrapper decision (sibling Path A vs Path C reasoning)

# State Card — `local_sentientia_calendar` (Sentientia LMS Calendar Sync)

**Current phase:** 1 — Outbound ICS Feed (MVP)
**Version:** 1.0.0-beta (2026052400)
**Status:** First shippable build; feature flag default OFF
**Owner:** Nitin Rajput (PM) + Claude (engineering)
**Last updated:** 2026-05-24

---

## Mission

Tier 2 #6 on the Sentientia LMS roadmap. Surface each learner's course
deadlines, classroom (ILT) sessions, and exam dates inside whichever
calendar app they already use — Outlook (web + desktop), Google
Calendar, Apple Calendar (macOS + iOS) — without requiring them to
keep Sentientia LMS open in a browser tab.

## Architecture decision

- **Sync direction (Phase 1):** OUTBOUND only — Sentientia LMS pushes
  events into the user's calendar via a periodically-fetched ICS feed.
- **Authentication:** 64-char random token in the URL (no OAuth, no
  cookies, no provider API keys). See ADR-013.
- **Sync direction (Phase 2):** Optional OAuth-mediated bi-directional
  sync. Deferred pending customer demand signal.

## Database schema (1 table — locked in Phase 1)

| Table | Rows per | Purpose |
|-------|----------|---------|
| `local_sentientia_calendar_token` | one ACTIVE per user (revoked rows kept 90 days) | The credential that authenticates ICS-feed fetches. Includes audit fields (last_used_at, last_used_ip, use_count) for abuse forensics |

## Capability matrix

| Capability | Role allowed | Notes |
|------------|--------------|-------|
| `local/sentientia_calendar:subscribe` | user+ | Manage own subscription URL |
| `local/sentientia_calendar:manage_all` | manager | Admin override; planned for Phase 1.1 (no UI yet) |

## Feature flags

| Flag | Default | Purpose |
|------|---------|---------|
| `sentientia.calendar_sync.enabled` | OFF | Master kill switch. When OFF every surface is 403/404. |
| `sentientia.calendar_sync.events.courses` | ON | Include course completion deadlines in the feed |
| `sentientia.calendar_sync.events.classroom` | ON | Include classroom (ILT) sessions |
| `sentientia.calendar_sync.events.exams` | ON | Include exam close-dates (quiz.timeclose) |

## Public surfaces

| URL | Auth | Purpose |
|-----|------|---------|
| `/local/sentientia_calendar/index.php` | session login | User-facing page — shows subscription URL + how-to + Regenerate button |
| `/local/sentientia_calendar/regenerate.php` | session login + sesskey | Revokes old token, issues new one, redirects back |
| `/local/sentientia_calendar/ics.php?token=…` | token in URL | ICS feed endpoint fetched by calendar clients (no Moodle session) |

## Event sources (V1 — 3 categories)

| Category | SQL shape | Source plugin | Window |
|----------|-----------|---------------|--------|
| Course completion deadlines | `mdl_user_enrolments.timestart + mdl_course.open_coursecompletiondays * 86400` for incomplete courses | `local_airpay_courses` | -180 days → ∞ |
| Classroom sessions | `local_airpay_classroom_sessions` joined to `local_airpay_classroom_users` (roster) | `local_airpay_classroom` | all (past + future) |
| Exam closes | `quiz.timeclose` for quiz.id in `local_airpay_exams`, scoped to enrolled courses | `local_airpay_exams` | now → +90 days |

## Scheduled tasks

| Task | Schedule | Purpose |
|------|----------|---------|
| `purge_old_tokens` | 03:17 daily | Delete revoked rows older than 90 days |

## Key files

```
local/sentientia_calendar/
├── version.php
├── lib.php                                      Nav callback
├── index.php                                    User-facing page
├── regenerate.php                               Token regenerate endpoint
├── ics.php                                      ICS feed endpoint (no cookies)
├── classes/
│   ├── token_manager.php                        Token lifecycle
│   ├── ics_builder.php                          RFC 5545 generator
│   ├── task/purge_old_tokens.php                Daily cleanup
│   └── privacy/provider.php                     GDPR / DPDP
├── db/
│   ├── install.xml                              1 table
│   ├── upgrade.php                              Phase 1: no migrations yet
│   ├── access.php                               Capabilities
│   ├── tasks.php                                Cron registration
│   └── feature_flags.php                        4 flags
├── lang/
│   ├── en/local_sentientia_calendar.php         28 strings
│   └── hi/local_sentientia_calendar.php         28 strings (100% parity)
├── templates/
│   └── subscription_page.mustache               Copy-button + how-to
└── tests/
    ├── token_manager_test.php                   17 tests
    └── ics_builder_test.php                     11 tests
```

## Roadmap

| Phase | Status | Scope |
|-------|--------|-------|
| 1.0 — Outbound ICS feed | ✅ this version | Courses + classrooms + exams as outbound VEVENTs, token URL UI |
| 1.1 — Dashboard block | ⏳ next | Block plugin with the "Subscribe" CTA for dashboard placement |
| 1.2 — Per-category filters | ⏳ | User toggle: "classrooms only" / "deadlines only" etc. |
| 1.3 — Customer timezone | ⏳ | Move hardcoded `Asia/Kolkata` to per-customer setting |
| 2.0 — OAuth bi-directional | ⏳ pending demand | Microsoft Graph + Google Calendar APIs, refresh-token storage |

## Open questions

- Should the feed include MOODLE-native calendar events (`mdl_event`)
  too? Currently we only surface Airpay plugin events. (Decision:
  defer to Phase 1.4 — likely yes, behind a sub-flag.)
- Should we expose a per-user "this token has been compromised" tile
  in the audit log? Currently the user only sees the URL, not its
  fetch history. (Decision: Phase 1.1 with a 30-day fetch summary table.)

## Tests

```
tests/token_manager_test.php   17 assertions across token lifecycle + isolation
tests/ics_builder_test.php     11 assertions: RFC 5545 conformance, user isolation, feature flags
```

Run with:
```
cd C:\xampp\htdocs\moodle5\public
php admin/tool/phpunit/cli/init.php
vendor\bin\phpunit local/sentientia_calendar
```

## Visual evidence

`docs/visual-evidence/2026-05-24/`:
- `desktop-subscription-page.png` — `/local/sentientia_calendar/index.php` as a learner
- `mobile-subscription-page.png` — same surface at 390px
- `outlook-subscription-success.png` — Outlook on the web showing the
  subscribed Sentientia calendar with sample VEVENTs

## Dependencies

```
local_airpay_core  2026051401+   feature_flags resolver, tenant + customer helpers
```

Optional (graceful degradation if missing):
```
local_airpay_courses   for COURSE-DEADLINE events
local_airpay_classroom for CLASSROOM-SESSION events
local_airpay_exams     for EXAM-CLOSE events
```

Each event source checks `$DB->get_manager()->table_exists()` before
querying — installing the calendar plugin without one of the source
plugins simply omits that event category.

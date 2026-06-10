# State Card — `local_airpay_whatsapp`

**Component:** `local_airpay_whatsapp`
**Version:** `2026052501` / `0.4.0-alpha`  — Stream F / Wave E2 P4 (content notifications)
**Maturity:** `MATURITY_ALPHA`  — mock-mode only; `[CONFIRM]` required before live
**Status:** Mock-mode shipped end-to-end. Live API gated behind core feature flag.
**Last refreshed:** 2026-05-25 (Stream F — content-event triggers)

---

## Mission

WhatsApp Business API (+ SMS fallback via DLT-template registry) as a
notification channel for Sentientia LMS. Acts as a parallel channel
alongside the existing email pipeline — `notification_bridge` plugs
into the same triggers as `local_airpay_emails`, so course completions,
overdue reminders, classroom joins, etc. can fire over WhatsApp / SMS
when the relevant sub-flag is ON.

Mock-mode runs the entire pipeline (preference lookup → DLT-template
match → render → "send" → log) without hitting WhatsApp Business API.
Live mode requires `[CONFIRM]` per CLAUDE.md §10.

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_airpay_user_channel_prefs` | Per-user channel opt-in (whatsapp / sms / email; per rule type) |
| `local_airpay_user_channel_audit` | Append-only audit of preference changes |
| `local_airpay_dlt_templates` | DLT-approved template registry (India regulatory requirement for SMS) |
| `local_airpay_send_log` | Per-send audit (channel, template, recipient, status, vendor message-id) |

## Capabilities

None declared explicitly in `db/access.php` (the plugin is read-only
from a per-user perspective; admin surfaces gate on
`moodle/site:config`).

## Feature flags

Consumed (registered in `local_airpay_core`):
- `engagement.whatsapp.enabled` (master switch — default OFF)
- `engagement.sms.enabled` (SMS fallback — default OFF)
- `engagement.whatsapp.reminders` (sub-channel: incomplete-course reminders — Phase C.1)
- `engagement.whatsapp.overdue` (sub-channel: manager overdue alerts — Phase C.1)

Registered + owned by this plugin (`db/feature_flags.php`, Stream F):
- `airpay_whatsapp_content_notifications` (master switch for the 4 content-event
  triggers — **default OFF**, per-customer override via 5-level resolver / ADR-002).
  Each `send_*` content method short-circuits to `flag_off` when this is OFF.

## Content-event triggers (Stream F / Wave E2 P4 — 2026-05-25)

`notification_bridge` gained four content-notification methods, each
gated on `airpay_whatsapp_content_notifications` + a 6h per-(user,
template, context) throttle, all routed through the existing mock-mode
`whatsapp_client`:

| Method | Fires from | Template key | Throttle context |
|--------|-----------|--------------|------------------|
| `send_new_course_notification($userid, $courseid)` | `observer::course_updated` (visibility 0→1, announce-once via per-course config marker) | `content_new_course` | `course:<id>` |
| `send_course_due_soon($userid, $courseid, $hours_remaining)` | `local_airpay_courses\task\course_reminder` (inline, <48h surface) | `content_course_due_soon` | `course:<id>` |
| `send_certificate_ready($userid, $certificateid)` | `observer::certificate_issued` (`\tool_certificate\event\certificate_issued`) | `content_certificate_ready` | `cert:<id>` |
| `send_path_milestone($userid, $pathid, $milestone_label)` | `observer::course_completed` (recompute path %, fire on 25/50/75/100% crossing) | `content_path_milestone` | `path:<id>:<milestone>` |

Return vocabulary: `sent` / `mocked` / `opted_out` / `no_template` /
`no_mobile` / `failed` / `throttled` / `flag_off` / `no_user` /
`no_record`.

Throttle store: the per-event context marker `[ctx=<context>]` is
stamped into `local_airpay_send_log.failure_reason`; the next attempt's
throttle check matches it with an escaped LIKE (the literal `%` in a
"50%" milestone is escaped, not treated as a wildcard). Only SENT /
MOCKED / DELIVERED rows count, so opted-out / failed attempts don't
suppress a legitimate retry.

Observers registered in `db/events.php`:
- `\core\event\course_updated`  → `observer::course_updated`
- `\tool_certificate\event\certificate_issued` → `observer::certificate_issued`
- `\core\event\course_completed` → `observer::course_completed`

## Key files

```
local/airpay_whatsapp/
├── version.php                                   2026052501 / 0.4.0-alpha
├── lib.php
├── settings.php                                   Admin API key + DLT config
├── preferences.php                                Per-user channel opt-in UI
├── styles.css
├── admin/                                         Admin operations surfaces
├── cli/                                           Diagnostics + mock-send smoke
├── classes/
│   ├── notification_bridge.php                    also_send() + 4 Stream F content methods + 6h throttle
│   ├── observer.php                               Stream F — course_updated / certificate_issued / course_completed
│   ├── channel_router.php                         Pick channel based on prefs + flags + template availability
│   ├── whatsapp_client.php                        WhatsApp Business API client (mock + live)
│   ├── sms_client.php                             SMS provider client (mock + live)
│   ├── dlt_template_registry.php                  DLT-approved template lookup + render
│   ├── preference_manager.php                     User pref CRUD
│   ├── send_log.php                               Audit-log writer
│   ├── analytics.php                              Delivery rate + bounce summary
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                4 tables
│   ├── install.php                                Post-install seed (9 + 4 Stream F DLT templates)
│   ├── upgrade.php                                Seeds Stream F templates on upgrade (idempotent)
│   ├── events.php                                 Stream F — 3 observer registrations
│   └── feature_flags.php                          Stream F — airpay_whatsapp_content_notifications (default OFF)
├── templates/
├── lang/
│   ├── en/local_airpay_whatsapp.php
│   └── hi/local_airpay_whatsapp.php
└── tests/
    ├── dlt_template_registry_test.php             9 methods
    ├── preference_manager_test.php                13 methods
    ├── channel_router_test.php                    6 methods (28 original total)
    ├── notification_bridge_content_test.php       Stream F — 16 methods
    └── observer_test.php                          Stream F — 6 methods
```

## Tests

5 PHPUnit classes. The 3 original (`dlt_template_registry_test`,
`preference_manager_test`, `channel_router_test` — 28 methods) plus
2 added in Stream F:
- `notification_bridge_content_test` — 16 methods: each content method's
  template substitution, content-flag gating (default OFF → `flag_off`),
  6h throttle (suppress duplicate within window; allow different
  milestone on same path), certificate userid sanity, missing-record
  paths.
- `observer_test` — 6 methods: course-publish announce-once semantics
  (publish → 1 send; re-edit → no re-send; hidden → none; re-publish
  after hide → announce again), content-flag OFF suppression, and the
  course_completed → 50% path-milestone crossing.

All run against the mock clients — no live API calls.

## Open items / next phase

- [ ] Phase C.2 — SSO + WhatsApp OTP for passwordless login
- [ ] Live API flip — requires `[CONFIRM]` + DLT-approved template
      submission to Reliance Jio / Vodafone-Idea TRAI registry
- [ ] Inbound message handling (reply-to-confirm flows)
- [ ] Per-tenant DLT template override
- [ ] WhatsApp media template support (currently text only)
- [ ] Capability decoupling: `:manage_dlt_templates` (compliance) vs
      `:view_send_log` (HR / audit)

## State card created — 2026-05-24

Initial state card. Plugin is in Phase C.1 — mock-mode complete + cron
hooks live; live API still default OFF behind two feature flags +
admin API key requirement.

## Updated — 2026-05-25 (Stream F / Wave E2 P4)

Deepened for course-content events. Added 4 content-notification
triggers (`send_new_course_notification`, `send_course_due_soon`,
`send_certificate_ready`, `send_path_milestone`) on `notification_bridge`,
wired via `classes/observer.php` + `db/events.php` (course_updated /
certificate_issued / course_completed) plus an inline call from
`local_airpay_courses\task\course_reminder` for the <48h surface. New
plugin-owned master flag `airpay_whatsapp_content_notifications`
(default OFF, per-customer override). 6h per-(user, template, context)
throttle. 4 new DLT templates seeded (install + idempotent upgrade).
22 new PHPUnit methods across 2 new test classes, all mock-mode. Hindi
+ English lang parity preserved. Version → 2026052501 / 0.4.0-alpha.
Live WhatsApp Business API send remains `[CONFIRM]`-gated — nothing in
this stream POSTs externally.

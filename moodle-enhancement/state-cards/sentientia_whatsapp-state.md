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


## 2026-09-22 - The E2E harness called a namespace that does not exist (W1-11)

`cli/run_whatsapp_e2e.php` used `\local_airpay_core\feature_flags`. That namespace was renamed by
ADR-022/025 and is **declared nowhere in the repo**, so the harness fatal'd on its first flag line.
The real class is `\local_sentientia_platform\feature_flags`, and its signature already matched
every call site - this was a pure rename miss, not a design change.

**Root cause: `CLAUDE.md` section 5 documented the dead namespace as the canonical feature-flag
pattern.** Anything written from the project instructions inherited the bug. Both the harnesses and
the doc are corrected in the same change, or it recurs on the next plugin.

A second defect in the same file: the prior-state backup read

```php
$prior_flag_master = get_config(null, 'local_airpay_core_flag_engagement_whatsapp_enabled');
```

Flags live in `{local_sentientia_feature_flags}`, not in `{config}`, so that read returned false
every time and the restore step at the end unset the flag regardless of what it had been. Replaced
with `feature_flags::is_enabled()`, matching the pattern the PWA harness already used.

Worth noting how the two trees had diverged here. The top-level `local/` copy had already had the
namespace search-and-replaced to `local_sentientia_platform` - but the replace was blind, so it
produced `get_config(null, 'local_sentientia_platform_flag_...')`, still reading a place the value
is not kept. The ME copy was untouched. Neither tree was correct.

Found by the 2026-09-22 confidence audit. Both trees now agree on this file; the reconciliation is
recorded as two entries drained from `tools/tree-drift-baseline.txt`.


## 2026-09-22 - Privacy provider did not declare every table it owns

`privacy_coverage_test` (new, in `local_sentientia_platform`) walks every Sentientia plugin's
`install.xml` and fails the build when a plugin holding a user-identifying column does not declare
it. It found eleven such tables across six plugins on its first run. This plugin held two:

- `local_sentientia_user_channel_audit` (`userid`, `changed_by`) - every change to a user's messaging preferences
- `local_sentientia_send_log` (`userid`) - every message sent, including `recipient`, which is the employee's **mobile number**

This is the harder version of the `null_provider` bug. A provider that declares *some* of its
tables makes the Privacy registry page read as complete, so nobody looks again. A subject-access
request returned a partial answer and an erasure request left rows behind, in both cases reporting
success.

The audit rows were already being deleted on erasure by `preference_manager::delete_user_data()`; the registry simply never said they existed. `send_log` was neither declared **nor** deleted, so a right-to-erasure request left behind a row-per-message carrying the mobile number the reminder went to. That is the one that mattered.

Version bumped to 2026092202 so the cached privacy registry picks up the new declarations. en + hi
strings added at parity.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider under-reported users (no version bump; class change only)

`get_contexts_for_userid()` reported the system context only for users with a saved channel
preference. `get_users_in_context()` counted send-log and channel-audit users too. The two
disagreed, so a user with send-log rows but no preference was told they held no WhatsApp data.
No erasure, core's or Sentientia's, ever asked this provider to delete those rows, each of which
holds a mobile number. It now checks all four sources. Found while wiring the right-to-erasure
flow to every provider. Covered by `local_sentientia_privacy\privacy_manager_test`, which seeds a
send-log row with no preference.

## 2026-09-24 - Privacy provider fix (erasure audit)

`preference_manager::delete_user_data()` deleted every channel-audit row where the subject was `changed_by`. Those are OTHER employees' consent-provenance records, which DPDP requires us to keep (an admin editing someone else's opt-in). The actor is now anonymised (`changed_by` and `ip_address` set to NULL) and those rows survive.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-25 - Two pre-existing PHPUnit failures fixed (one was a real analytics defect)

The full suite run on 2026-09-24 against the deployed ME tree failed two tests. Class and test
change only: no version bump, no schema change, no lang change.

- `channel_router_test::test_analytics_channel_mix_aggregates` (100.0 vs 100). **The code was
  wrong.** `analytics::channel_mix()` built `mocked_pct` and `success_pct` with a bare `round()`,
  which returns a float in PHP 8. `admin/analytics.php` colours the "Mocked" tile with
  `mocked_pct === 100 ? 'info' : 'warning'`. That comparison was never true, so a fully-mocked
  install always showed the tile as a warning. Both percentages are now `(int) round(...)`, which
  also matches the method's docblock (`'mocked_pct' => 87`). The test's assertion is unchanged.
- **A second defect, found while fixing the first.** `channel_mix()` read its
  `GROUP BY channel, status` through `get_records_sql()`, which keys rows by the first column.
  That column was `channel`, which repeats once per status. A channel with two statuses in the
  window (for example whatsapp mocked plus opted_out) kept only the last group. The dashboard
  under-counted the per-channel figures and the totals, and DEBUG_DEVELOPER raised "Duplicate
  value". The method now uses `get_recordset_sql()`. New regression test:
  `test_analytics_channel_mix_counts_every_status_of_a_channel`.
- `preference_manager_test::test_get_returns_defaults_when_user_has_no_row` (132000 vs '132000').
  **The test was wrong.** `create_user()` re-reads the row from `{user}`, so `$user->id` is the
  driver's string. The expected side is now cast to int. The assertion still proves the no-row
  default carries the requested user's id.

Both trees were changed identically. PHPUnit was not run here because it is on hold while the
shared test DB is rebuilt. The next suite run will confirm these fixes.

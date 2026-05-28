# airpay_emails — STATE CARD

**Component:** `local_airpay_emails`
**Current version:** `2026052001`  (release `1.1.2`, post-Sprint B + Hindi top-ups)
**Maturity:** STABLE (production)
**Last touched:** 2026-05-20 (P1 #49 — Hindi top-up)
**Last refreshed:** 2026-05-28 (F-039 closeout — runtime DB probe + table inventory)
**Owner:** Head of L&D

## 2026-05-28 runtime snapshot (F-039 closeout)

`tools/audit_table_inventory.php` against the local Moodle DB:

| Table | Row count | Interpretation |
|-------|-----------|----------------|
| `local_airpay_email_rules` | 12 | Phase 1–4 rule registry populated; all 11 rule types live on local |
| `local_airpay_email_overrides` | 0 | Tenant-override admin UI present but unused on local (Airpay tenant uses default templates only) |
| `local_airpay_email_log` | 0 | Outbound log writer is conditional on the admin opting in; default OFF on local. Production has rows (target = SOC2 audit retention 7y) |
| `local_airpay_email_prefs` | 0 | Per-rule channel opt-out — populated when consumer learners run onboarding; no consumer signup yet on this DB |

**Verdict** — no outstanding "Phase 5" work. The Sprint B observer +
ramping reminders + certificate-PDF attach all shipped and verified
under the 2026-05-13 work. Audit's F-039 was a stale "what's left"
question already answered by the existing state-card content. Closed.

---

## What it does

Replacement for BizLMS `local_notifications` plus the bolt-on
"better completion email + ramping reminders + audit trail"
functionality the LMS Admin requested on 2026-05-13.

Owns:
- Email TEMPLATES (Mustache, branded, per-tenant overrideable)
- Email RULES (when to fire, audience, channel, cadence)
- Delivery LOG (sent / failed / suppressed / suppressed_completion)
- User PREFERENCES (per-rule channel opt-out)
- A scheduled task that walks the rules every hour
- An event observer that fires on course-completion (Sprint B)

## DB tables

| Table | Purpose |
|-------|---------|
| `local_airpay_email_overrides` | Per-tenant template overrides (subject/body) |
| `local_airpay_email_rules`     | Rule registry — when + how to fire |
| `local_airpay_email_log`       | Unified delivery log incl. attachment + cert FK |
| `local_airpay_email_prefs`     | Per-user channel preferences (opt-out) |

## Sprint B schema additions (2026-05-13)

**`local_airpay_email_rules`** — three new columns:
- `cadence_days_json` (varchar 255 nullable) — JSON array of day offsets for ramping reminders, e.g. `[1,3,7,14,21]`
- `max_reminders_per_user` (int default 0) — cap per (user × course); 0 = unlimited
- `auto_stop_on_completion` (int default 1) — suppress reminders once user completes

**`local_airpay_email_log`** — two new columns:
- `attachment_filename` (varchar 255 nullable) — e.g. `Airpay-certificate-ABC123.pdf`
- `certificate_issue_id` (int 10 nullable) — FK to `tool_certificate_issues.id`

A new `status` value is also used: `suppressed_completion` (= a ramping
reminder that was on its way out, but the user completed the course
in the meantime so we're stamping the existing log rows to mark them
"no longer relevant" for downstream analytics).

## Rule types

| Rule type | Driver | Notes |
|-----------|--------|-------|
| `course_not_started`     | cron, hourly | Original. 7-day dedup window. |
| `deadline_approaching`   | cron, hourly | Original. Window-around-deadline. |
| `streak_broken`          | cron, hourly | Stub. |
| `manager_nudge`          | cron, hourly | Stub. |
| `compliance_enrolled`    | cron, hourly | Welcome email. |
| `compliance_reminder`    | cron, hourly | Mid-cycle reminders. |
| `compliance_overdue`     | cron, hourly | Past-due nag. |
| `weekly_escalation`      | cron, hourly | Manager escalation. |
| `new_course`             | event       | `\core\event\course_created` |
| **`course_completed`**   | **event**  | **Sprint B — fires `\core\event\course_completed`. Sends congrats + cert PDF attached.** |
| **`course_incomplete`**  | **cron, hourly** | **Sprint B — ramping cadence; honours cap + auto_stop_on_completion** |

## Sprint B flow — course completion email + certificate PDF

```
                          [Moodle core fires]
                                  │
              \core\event\course_completed
                                  │
                                  ▼
            db/events.php registers observer at priority 100
                                  │
                                  ▼
       \local_airpay_emails\observer::course_completed()
                                  │
              ┌───────────────────┴───────────────────┐
              ▼                                       ▼
   stamp existing reminders            send congratulations email
   for (user, course) as               + tool_certificate PDF
   status='suppressed_completion'      attached (if issued)
              │                                       │
              ▼                                       ▼
   delivery_log::                       notification_sender::send()
   mark_reminders_                      with ['certificate_issue' => $issue]
   suppressed_on_                                     │
   completion()                                       ▼
                                   certificate_helper::materialise_pdf()
                                   → temp file under $CFG->tempdir/airpay_emails/
                                                      │
                                                      ▼
                                   email_to_user($user, $from, $subject,
                                                  $text, $html,
                                                  $attachment_path, $attachname)
                                                      │
                                                      ▼
                                   delivery_log row with:
                                     status='sent'
                                     attachment_filename='Airpay-cert-X.pdf'
                                     certificate_issue_id=<int>
                                                      │
                                                      ▼
                                   certificate_helper::cleanup_materialised()
                                   → unlinks the temp PDF
```

## Sprint B flow — ramping daily reminders

```
                  [cron, hourly at :15 past every hour]
                                  │
                                  ▼
              process_rules::execute()
                                  │
                                  ▼
        for each enabled rule with rule_type='course_incomplete':
                                  │
                                  ▼
              process_course_incomplete($rule)
                                  │
              parse cadence_days_json → e.g. [1, 3, 7, 14, 21]
                                  │
                                  ▼
              SQL: enrolments NEWER than max(cadence) days ago,
                   NOT completed (if auto_stop_on_completion = 1)
                                  │
                                  ▼
              for each (user × course) candidate:
                   days_since = floor((today − enrolled) / 86400)
                                  │
                   if days_since IN cadence: continue
                   else: skip
                                  │
                   if log_count for (user × course × template, status='sent'
                                     OR 'suppressed_completion')
                       >= max_reminders_per_user: skip
                                  │
                   if log_row exists for today × (user × course × template):
                       skip  (idempotent within calendar day)
                                  │
                                  ▼
              notification_sender::send($rule, $cand, $context, $courseid)
```

## CLI tools (`cli/`)

| CLI | Purpose |
|-----|---------|
| `cli/cert_emails_report.php` | **Sprint B** — list cert emails by tenant/status, with `--since` / `--tenant` / `--status` / `--detail` / `--csv` flags |

## Files map (Sprint B additions only)

| File | Purpose |
|------|---------|
| `db/events.php`                 | Registers the course_completed observer |
| `classes/observer.php`          | Observer handler |
| `classes/certificate_helper.php` | Wraps `tool_certificate\template::get_issue_file()` + temp materialisation |
| `cli/cert_emails_report.php`    | Audit report CLI |
| `tests/observer_test.php`       | PHPUnit: mark_reminders_suppressed |
| `tests/cadence_test.php`        | PHPUnit: cadence parsing + day-offset math |
| `tests/certificate_helper_test.php` | PHPUnit: null safety + temp cleanup |

## Decisions / non-obvious bits

- **Why two channels for course_completed have different code paths.**
  `message_send()` (Moodle's unified channel pipeline) doesn't support
  file attachments. To carry the certificate PDF we must call
  `email_to_user()` directly. For the email channel we route through
  the attachment path; for popup we still use `message_send`. Two
  delivery_log rows result, one per channel.

- **Why we use `$CFG->tempdir` not `$CFG->dataroot/files`.**
  Certificates are short-lived during the send (microseconds). Putting
  them under `$CFG->dataroot/files` would pollute the canonical Moodle
  filedir and require explicit garbage collection. `$CFG->tempdir` is
  already swept periodically by Moodle's own cron.

- **Why the observer can't throw.**
  Course-completion is a Moodle-core operation. If our observer raises
  an exception, the user's completion record won't write and they'll
  re-complete on the next interaction — bad UX. Every external call
  inside `observer::course_completed()` is wrapped in try/catch.

- **Why dedup is "no two emails on the same calendar day"**
  rather than "no two emails within X hours". Calendar boundary maps
  cleanly to the cadence array (which is in day-offsets); makes the
  audit trail readable; and lets ops manually fire cron multiple times
  per day during incident response without triggering duplicate emails.

## Open / next-up

- ~~Hindi / Kannada / Marathi / Swahili lang-string copies of the
  Sprint B strings.~~ ✅ shipped (P1 #49, version 1.1.2)
- Settings-page (`settings.php`) UI controls for the cadence default
  and max-cap (admin can already edit via the rule editor).
- Manager-facing "X learners on your team are 14 days into a course"
  digest — natural follow-on to course_incomplete.

---

## Capabilities (6 in db/access.php)

`local/airpay_emails:` `preview`, `manage`, `manage_templates`,
`manage_rules`, `view_logs`, `manage_settings`. Splitting `manage_*`
into separate caps lets compliance auditors hold `:view_logs` without
edit rights.

## PHPUnit (4 classes, 26 methods)

- `cadence_test.php` — 9 methods (Sprint B ramping cadence math)
- `setting_cadence_json_test.php` — 10 methods (per-rule cadence array)
- `certificate_helper_test.php` — 4 methods (Sprint B PDF helper)
- `observer_test.php` — 3 methods (course_completed observer)

## Top-level files

- `version.php`, `README.md`, `lib.php`, `settings.php`, `styles.css`
- Surfaces: `manage.php`, `editor.php`, `preview.php`,
  `preview_ajax.php`
- `cli/` — production audit + repair scripts
- `amd/`, `templates/`, `db/`, `lang/`

## classes/

`admin/`, `external/`, `task/`, `privacy/`, plus:
`rule_manager.php`, `template_manager.php`, `tenant_config.php`,
`email_renderer.php`, `email_context.php`, `notification_sender.php`,
`delivery_log.php`, `certificate_helper.php` (Sprint B),
`observer.php` (Sprint B), `legacy_bridge.php`, `manage_controller.php`.

## Feature flags

None registered directly. Rule-by-rule "enabled" state lives on each
row of `local_airpay_email_rules` (the rule editor toggle), not in
the central feature-flag registry.

## State card refresh — 2026-05-24

P1 state-card pass: bumped Current version `1.1` → `1.1.2`
(`2026051301` → `2026052001`) after the Hindi top-up (P1 #49) landed.
No DB schema, capability, or feature-flag drift. Added explicit
inventories of capabilities, PHPUnit classes, top-level files, and
the `classes/` tree (previously implicit in the Sprint B section).

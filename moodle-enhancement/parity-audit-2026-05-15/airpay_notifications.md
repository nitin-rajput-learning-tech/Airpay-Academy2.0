# airpay_notifications vs BizLMS local_notifications — Parity Audit
Generated: 2026-05-15 | Auditor: feature-parity cluster 4 | Stakes: HIGH

## Source paths + size

| | BizLMS `local_notifications` | Airpay `local_airpay_notifications` |
|---|---|---|
| Path | `C:\xampp\htdocs\moodle5\bizlms_disabled\notifications\` | `C:\xampp\htdocs\moodle5\public\local\airpay_notifications\` |
| PHP files | 35 | 24 (incl. 3 unit tests) |
| Total LOC (PHP+mustache+js) | **20,576** | **4,587** |
| Tables (DB) | `local_notification_info`, `local_notification_type`, `local_emaillogs`, `local_email_logs`, `local_notification_strings` | `local_airpay_notif_rules`, `local_airpay_notif_log`, `local_airpay_notif_prefs` |
| Capabilities | `manage`, `view`, `create`, `delete`, `update`, `visible` (6) | `manage`, `viewlogs` (2) |
| Cron tasks | `send_email_notifications` (hourly) | `process_rules`, `daily_digest` |
| Lang | en + es (2) | en, hi, kn, mr, sw (5) |

Note: BizLMS retains 7 large legacy "reminder" files (`certification_reminder.php`, `ilt_reminder.php`, `program_reminder.php`, `session_reminder.php`, etc., total ~1,800 LOC) — but every one of them is **fully commented-out** and deprecated. Real work is in `lib.php` (notifications class, 1,400+ LOC), `notification.php` (notification_triger), and the scheduled task `classes/task/send_email_notifications.php`. So the like-for-like comparison is closer to 14,000 active LOC vs 4,587 active LOC.

---

## Architecture summary

**BizLMS model — admin-authored templates per event:**
1. Admin opens `/local/notifications/index.php` and creates rows in `local_notification_info` — each row is one template (subject + HTML body) keyed by a `local_notification_type` shortname (e.g. `course_enrol`, `classroom_reminder`, `program_completion`, `request_add`).
2. **76+ notification types** are wired into other plugins (course, classroom, program, ILT, certification, online-test, feedback, forum, request, LEP, learningplan). Each type has a token vocabulary like `[course_title]`, `[classroom_enroluseremail]`, `[req_component]` resolved via `local_notification_strings` lookup.
3. When the source plugin triggers an event (enrol, complete, etc.), it calls `notifications::send_email_notification($emailtype, $dataobj, $touserid, $fromuserid)` which **writes a row to `local_emaillogs`** with token-substituted subject+body.
4. The hourly scheduled task `send_email_notifications` reads 50 unsent rows from `local_emaillogs`, sends them via `message_send()`, marks status=1.
5. Per-tenant scoping is via `costcenterid` column. Per-module scoping via `moduleid` (CSV of course IDs / classroom IDs). Manager CC via `enable_cc` flag → resolves `open_supervisorid`. Certificate attachment supported via `attach_certificate` flag (PDF generation at send time).
6. **Per-user preferences: NONE** — users cannot opt out. Admin templates apply to everyone in tenant.
7. **Channels: email only** (delivered through Moodle messaging which can fan-out to email + popup but template targets email).
8. **Email status page** `/local/notifications/email_status.php` shows per-user, per-template send status with filter form.

**Airpay model — rule-engine with per-user prefs:**
1. Admin opens `/local/airpay_notifications/index.php` and creates rules in `local_airpay_notif_rules` — each rule has `rule_type` (16 fixed types), `trigger_days`, `audience`, `channel`, `template` (free-text), `enabled` flag.
2. **Rule types are hardcoded in `classes/rule_engine.php`** — exactly 16: `deadline_approaching`, `course_not_started`, `streak_broken`, `manager_nudge`, `new_course`, `compliance_overdue`, `certificate_expiring`, `ilt_feedback_pending`, `learning_path_stalled`, `enrolment_anniversary`, `inactive_user`, `quiz_low_score`, `monthly_summary`, `cert_expired`, `training_overdue`, `manager_summary_weekly`, `peer_completion_celebration`.
3. Cron-driven: `process_rules` task runs all enabled rules, computes recipients via SQL, sends via `message_send()`, logs to `local_airpay_notif_log`.
4. **No event-driven triggers** — everything is polled hourly. Enrol/complete/cancel events do NOT push notifications immediately; user waits up to one cron cycle.
5. Per-user preferences via `prefs.php`: channel on/off (inapp/email/push), per rule-type opt-out, quiet hours, digest frequency.
6. Channels declared: `inapp`, `email`, `push`, `whatsapp` (only `inapp`+`email` actually implemented in `rule_engine::send()` line 1003).
7. Audience hardcoded to 4 values: `learner`, `manager`, `admin`, `all` — narrower than BizLMS's free-form `costcenterid`+`moduleid` combination.
8. Manual nudge available via `/local/airpay_notifications/nudge.php` for managers to ping team members.
9. Templates: `template` column is just a plain-text template string with `{{firstname}}` Mustache placeholders. **No Mustache resolution code exists in rule_engine — the `send()` method ignores the template column entirely and uses hardcoded subject/body strings written inside each rule_* handler.** Templates only resolve if `local_airpay_emails::email_renderer` is present (HTML wrapper).

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| **EVENT TRIGGERS** | | | | | |
| 1 | Course enrolment notification | yes — `course_enrol` template fired on enrol event | NO — must wait for cron poll; no event hook | **LOST** | **P0** |
| 2 | Course unenrolment notification | yes — `course_unenroll` template | NO | **LOST** | **P1** |
| 3 | Course completion notification | yes — `course_complete` (with cert attach) | NO event-trigger; cert attachment fully absent | **LOST** | **P0** |
| 4 | Course reminder (N-days-before-end) | yes — `course_reminder` with reminderdays | partial — `deadline_approaching` via cron | partial parity | P1 |
| 5 | Classroom (ILT) enrol | yes — `classroom_enrol` template | NO | **LOST** | **P0** |
| 6 | Classroom waiting-list notification | yes — `classroom_enrolwaiting` with order# | NO | **LOST** | **P1** |
| 7 | Classroom cancel | yes — `classroom_cancel` | NO | **LOST** | **P1** |
| 8 | Classroom hold | yes — `classroom_hold` | NO | **LOST** | P2 |
| 9 | Classroom reminder | yes — `classroom_reminder` configurable days | NO direct equivalent (ILT feedback only fires after end) | **LOST** | **P0** |
| 10 | Classroom completion | yes — `classroom_complete` | NO | **LOST** | P1 |
| 11 | Classroom invitation | yes — `classroom_invitation` | NO | **LOST** | P1 |
| 12 | Classroom nomination | yes — `classroom_nomination` | NO | **LOST** | P1 |
| 13 | ILT opting / opt-out | yes — `ilt_opting`, `ilt_optclassroom_cancel`, `ilt_optrequest_cancel`, `ilt_reason` | NO | **LOST** | P1 |
| 14 | ILT feedback request | yes — `ilt_feedback` template | partial — `ilt_feedback_pending` polls cutoff | weaker | P2 |
| 15 | Program enrol/unenrol | yes — `program_enrol`, `program_unenroll`, `program_completion`, `program_level_completion`, `program_course_completion` | NO | **LOST** | **P0** |
| 16 | Program session events (enrol/reschedule/attendance/reminder/completion/cancel) | yes — 7 distinct templates | NO | **LOST** | **P0** |
| 17 | Online test enrol/unenrol/due/completed | yes — 4 templates | NO | **LOST** | **P1** |
| 18 | Feedback enrol/unenrol/due/completed | yes — 4 templates | NO | **LOST** | P1 |
| 19 | Forum subscription/post/reply | yes — 4 templates | NO | **LOST** | P2 |
| 20 | Request add/approve/deny | yes — 3 templates | NO | **LOST** (see airpay_request.md) | **P0** |
| 21 | Certification reminder/complete/cancel | yes — full set | partial — uses `tool_certificate_issues`, only expire-warn + cert-expired | weaker | P1 |
| 22 | Learning-plan enrol/unenrol/complete/approval/rejected/reminder/nomination | yes — 7 templates | NO direct (LP is replaced by airpay_learningpath) | **LOST** | P1 |
| 23 | Online exam enrol/complete | yes — 2 templates | NO | **LOST** | P1 |
| 24 | New course / new ILT added notification | yes — `new_course`, `new_ilt_added` | partial — `new_course` polls past 24h | weaker (24h race) | P1 |
| 25 | Onlinetest due reminder | yes — `onlinetest_due` | NO | **LOST** | P1 |
| **EVENT-AGNOSTIC SCHEDULED RULES** | | | | | |
| 26 | Streak broken | NO | yes | **NEW** | — |
| 27 | Manager nudge digest | NO | yes (cron + manual) | **NEW** | — |
| 28 | Inactive user "we miss you" | NO | yes | **NEW** | — |
| 29 | Quiz low score retry suggestion | NO | yes | **NEW** | — |
| 30 | Peer completion celebration | NO | yes | **NEW** | — |
| 31 | Manager weekly summary | NO | yes | **NEW** | — |
| 32 | Enrolment anniversary | NO | yes | **NEW** | — |
| **DELIVERY / TEMPLATING** | | | | | |
| 33 | Token placeholders ([course_title], [classroom_enroluseremail] etc.) | yes — 200+ tokens across 30+ event types, resolved per-tenant | **NO** — Airpay's `template` column ignored at send time; rule handlers hardcode subject/body | **LOST** | **P0** |
| 34 | Mustache `{{firstname}}` resolution | not used | declared in `template` column but **not parsed** in rule_engine::send (line 974-1000 only triggers if local_airpay_emails plugin loaded) | partial | **P1** |
| 35 | HTML email body via WYSIWYG editor (atto) | yes — `editor` element in `notification_form.php` line 169 | NO — plain `textarea` | **LOST** | P1 |
| 36 | Certificate PDF attachment on completion email | yes — `attach_certificate` flag wires `local_certification\template::generate_pdf()` | NO | **LOST** | **P0** for compliance |
| 37 | Manager CC (auto-CC employee's `open_supervisorid`) | yes — `enable_cc` flag | NO | **LOST** | **P1** |
| 38 | Per-tenant template scoping (costcenterid/open_path) | yes — `local_notification_info.open_path` filtered | NO — rules are global, no per-tenant template | **LOST** | **P1** (tenant branding lost) |
| 39 | Per-module template scoping (CSV moduleid) | yes — `moduleid` CSV picks specific courses/classrooms | NO — rules apply to ALL items of matching type | **LOST** | **P1** |
| 40 | Email status report page (per-user delivery audit) | yes — `email_status.php` with filter form, per-user breakdown | yes — `logs.php` per-row, with status/channel/ruletype filters | **PARITY** | — |
| 41 | Resend / retry failed emails | possible via `status=0` queue rows reprocessed | NO retry — failed rows stay `failed` forever | **LOST** | **P1** |
| 42 | Master-info dashboard tile (notification count) | yes — `local_notifications_masterinfo()` exports tile to `block_masterinfo` | NO | **LOST** | P2 |
| **PER-USER PREFERENCES** | | | | | |
| 43 | Per-user opt-out of channel | NO | yes (inapp/email/push toggles) | **NEW** | — |
| 44 | Per-user opt-out of rule type | NO | yes | **NEW** | — |
| 45 | Quiet hours (DND window) | NO | yes (cron-aware) | **NEW** | — |
| 46 | Digest frequency | NO | yes (none/daily/weekly column — but **digest_frequency is stored, not implemented** — `daily_digest` task exists but no code aggregates by user prefs) | **partial-NEW** | P1 |
| **MULTI-CHANNEL** | | | | | |
| 47 | SMS channel | NO | declared in `prefs` table but no sender | partial | P2 |
| 48 | WhatsApp channel | NO | declared in `rule_manager::CHANNELS` but `rule_engine::send()` lines 1003 only handles inapp+email | **declared-but-not-implemented** | P1 |
| 49 | Push notification | NO | declared in channel list, no service worker / FCM wiring | **declared-but-not-implemented** | P1 |
| 50 | MS Teams notification | NO | NO | — | — |
| **ADMINISTRATION** | | | | | |
| 51 | Rule list page with filters | yes — `index.php` with `filters_form` autocomplete by moduletype | yes — datatable with filters via `list_rules` external | **PARITY** | — |
| 52 | Create/edit rule form (dynamic form) | yes — fragment-loaded modal | yes — dynamic form via external `save_rule` | **PARITY** | — |
| 53 | Delete rule with sesskey confirm | yes | yes (`delete_rule` external) | **PARITY** | — |
| 54 | Preview before save | NO | yes — `preview_rule` external | **NEW** | — |
| 55 | Test-send to admin | NO | yes — `test_send` external | **NEW** | — |
| 56 | Toggle enabled/disabled (active flag) | yes — visible flag on rows | yes — `toggle_rule` external | **PARITY** | — |
| 57 | RIGHT-MANAGER capability scoping (CONTEXT_COURSECAT) | yes — capability defined at course-category level (delegate to L&D admins) | NO — only CONTEXT_SYSTEM `manage` | **LOST** | **P1** for delegation |
| 58 | Help text + token reference shown in form | yes — `string_identifiers` static element shows valid tokens per type | NO | **LOST** | P2 |
| 59 | Notification type categorisation in dropdown (parent_module → children) | yes — `selectgroups` element groups by `local_notification_type.parent_module` | NO — flat select list | **LOST** | P2 |
| **EXTERNAL API / SERVICES** | | | | | |
| 60 | Mobile-service support (MOODLE_OFFICIAL_MOBILE_SERVICE) | yes — `managenotificationsview` exposed | partial — externals exist but not registered to MOODLE_OFFICIAL_MOBILE_SERVICE | partial | P2 |
| 61 | Datatable pagination/search external | yes — `managenotificationsview` with offset/limit/filters | yes — `list_rules` with same shape | **PARITY** | — |
| **NUDGE / MANUAL ALERT** | | | | | |
| 62 | Manual nudge from manager dashboard | NO (BizLMS had `enable_cc` but no UI) | yes — `nudge.php` with 4 templates and message customisation | **NEW** | — |
| **TESTING / RELIABILITY** | | | | | |
| 63 | Unit tests | NO | yes — `tests/crud_test.php`, `tests/external/list_rules_test.php`, `tests/rule_engine_phase_c_test.php` | **NEW** | — |
| 64 | Race-safe duplicate prevention | weak — `local_emaillogs` dedupe via existence query (race possible) | strong — `start_delegated_transaction()` + insert-claim pattern at `rule_engine.php:900-924` | **BETTER** | — |
| 65 | i18n | en, es | en, hi, kn, mr, sw | **MORE COVERAGE** | — |
| 66 | Privacy provider | NO | yes — `classes/privacy/provider.php` | **NEW** | — |
| 67 | CLI smoke tests | NO | yes — `cli/smoke_prefs.php`, `cli/smoke_preview_send.php` | **NEW** | — |

---

## User flows (multi-step tasks)

### Flow 1: Admin creates a course-enrol email with brand-specific subject for Airpay tenant
**BizLMS path** (works):
1. Go to `/local/notifications/index.php`
2. Click bell-create icon (top-right) → modal opens
3. Pick costcenter (e.g. id=1 Airpay) — auto-populates available courses below
4. Pick notification type from grouped dropdown ("Course → Course Enrol")
5. Pick specific courses (multi-select autocomplete) OR leave empty for "all"
6. Type subject, fill rich HTML body using atto editor, drag in tokens shown in `[course_title]` reference
7. Optional: tick "attach certificate" (visible only for completion events)
8. Optional: tick "enable CC" to copy manager
9. Submit → row saved to `local_notification_info`
10. From the moment a user enrols, the source plugin emits an enrol event → handler writes a row to `local_emaillogs` with token-substituted body keyed by this notification_info row → cron picks up within 1 hour → mail goes out.

**Airpay path** (broken):
1. Go to `/local/airpay_notifications/index.php`
2. Click create — `edit_rule` dynamic form modal opens
3. Set name; pick rule_type from 6-entry dropdown (no course_enrol type exists!)
4. Set trigger_days, audience (learner/manager/admin/all), channel (inapp/email/push/whatsapp)
5. Paste a Mustache template `Hi {{firstname}}`
6. Save.
7. **NO event-driven send happens on enrol.** Cron runs `process_all()` hourly which iterates rules, but no rule_type in the switch (line 44 of rule_engine.php) handles `course_enrol`. Even if the admin creates it, **the rule will be silently dropped** (default case `: break` in switch yields zero recipients).
8. The Mustache `template` column is never parsed — `rule_engine::send()` line 974-1000 only renders via `local_airpay_emails::email_renderer` if that plugin is installed; otherwise body is whatever the hardcoded handler wrote.

**Result**: Admin can create a "course enrol email" in the UI but it will never send.

### Flow 2: Learner enrols in mandatory compliance course — should receive immediate email
**BizLMS path** (works):
1. Enrol event fires → `local_notifications::send_email_notification('course_enrol', $dataobj, $userid, $fromuserid)` runs in 1 line of glue from enrol plugin
2. Writes `local_emaillogs` row with subject/body tokens resolved
3. Cron picks up within ≤60 minutes
4. Email arrives.

**Airpay path** (BROKEN):
1. Enrol event fires → nothing happens. No event observer in `db/events.php` (file doesn't exist), no `db/messages.php` mapping for `course_enrol`, no hook in airpay_courses to push a notification.
2. Up to 60 minutes later, `process_rules` runs. It executes `deadline_approaching` (no enroll event), `course_not_started` (only fires if user enrolled X+ days ago AND hasn't logged into the course), `new_course` (only fires for courses created in last 24h, not for users enrolling).
3. **The user never gets a "welcome to course X" email.**

### Flow 3: Manager sees team member's overdue compliance, sends manual nudge
**BizLMS path** (impossible — only auto-cc via enable_cc on admin templates).
**Airpay path** (works):
1. Manager goes to team page; clicks "nudge" icon next to overdue user.
2. Lands on `/local/airpay_notifications/nudge.php?userid=X&type=compliance`.
3. Picks a quick template, customises text, clicks Send.
4. `message_send()` delivers + row written to `local_airpay_notif_log` with ruleid=0 (manual).
This is a NEW capability.

### Flow 4: User opens preferences and disables "we miss you" emails + sets quiet hours 22:00-07:00
**BizLMS path** (impossible — no per-user prefs).
**Airpay path** (works):
1. Go to `/local/airpay_notifications/prefs.php`.
2. Toggle "Inactive user" rule off; set start=22, end=7.
3. Submit → row in `local_airpay_notif_prefs`.
4. At next cron, `rule_engine::send()` line 940-959 honours both opts.

### Flow 5: Compliance officer wants to attach a fresh PDF certificate to course-complete email
**BizLMS path** (works) — see Flow 1 step 7; PDF generated at queue-time via `local_certification\template::generate_pdf`.
**Airpay path** (BROKEN) — no certificate attachment hook in `rule_engine::send()`. Compliance pipeline that auto-emails the PDF on completion is **gone**.

### Flow 6: Admin investigates "Did Ravi get his classroom reminder?"
**BizLMS path** (works):
1. `/local/notifications/email_status.php` → filter by user, by notification type, by date range
2. See per-user, per-template row with sent_date and status; clickable for details

**Airpay path** (works, similar):
1. `/local/airpay_notifications/logs.php` → filter by status, channel, rule_type, date range, user email search
2. See per-row data with subject + status badge; click for log_detail page.
**PARITY for audit logging**, but limited because most BizLMS event types simply don't fire in Airpay.

---

## Severity legend
- **P0** = blocks enterprise use (compliance email pipeline, cert attachment, immediate enrol/complete confirmations)
- **P1** = important workflow degraded (no manager CC, no per-tenant templates, declared-but-not-implemented channels)
- **P2** = polish

---

## Recommended fixes (prioritised)

### P0 — Restore event-driven notifications (HIGHEST)

1. **Create event observers** for the core lifecycle events. Add file `public/local/airpay_notifications/db/events.php`:
   ```php
   $observers = [
       ['eventname' => '\core\event\user_enrolment_created',
        'callback'  => 'local_airpay_notifications\observer\enrolment::created'],
       ['eventname' => '\core\event\course_completed',
        'callback'  => 'local_airpay_notifications\observer\enrolment::completed'],
   ];
   ```
   Stub these observers to fire a configurable rule_type. **Start at:** `classes/observer/enrolment.php` (NEW).

2. **Add `course_enrol`, `course_complete`, `course_unenrol` rule types** to `rule_manager::RULE_TYPES` const at `classes/rule_manager.php:33-40` and add matching handlers in `classes/rule_engine.php` switch at line 44.

3. **Wire certificate PDF attachment.** At `classes/rule_engine.php:893` (the `send()` method), accept an optional `$attachfile` arg; in the rule_course_complete handler, generate the cert PDF via the airpay_compliance plugin (or `tool_certificate_issues`) and pass file content; in `send()` use `email_to_user()` instead of `message_send()` when an attachment is present.
   **Start at:** `classes/rule_engine.php:1003` — add a branch for `if ($channel === 'email' && $attachfile)`.

4. **Honour the `template` column.** At `classes/rule_engine.php:974`, when `$rule->template` is non-empty, run a simple `{{var}}` substitution against `$tplcontext` BEFORE checking `local_airpay_emails`. This restores admin-author HTML body for ANY rule type.
   ```php
   // Inside send() before line 974
   if (!empty($rule->template)) {
       $message = preg_replace_callback('/{{(\w+)}}/',
           fn($m) => $tplcontext[$m[1]] ?? $m[0], $rule->template);
   }
   ```

### P1 — Restore tenant + module scoping

5. **Add `costcenterid` (or `open_path`) column to `local_airpay_notif_rules`.** Edit `db/install.xml` and add upgrade step. Filter `rule_engine::process_all()` line 25 by tenant.
   Otherwise tenant 1 (Airpay) cannot send a different welcome email than tenant 77 (Public).

6. **Add `moduleids` CSV column to rules.** A single rule should be able to target "only courses 42, 43, 44" not "all courses".

7. **Add manager CC.** New `enable_cc` boolean column on rules; in `rule_engine::send()` line 1003, after sending the primary message, look up `$DB->get_field('user', 'open_supervisorid', ['id' => $userid])` and `message_send` a copy if not null.

8. **Implement digest_frequency.** The column exists in `local_airpay_notif_prefs` and the task class `daily_digest` is registered in `db/tasks.php`, but `classes/task/daily_digest.php` body never aggregates by user prefs. Required: collect all `sent` log entries since last digest per user, render a single digest mail. **Start at:** `classes/task/daily_digest.php:execute()`.

9. **Implement retry queue.** Add a state `failed_retry_count` to `local_airpay_notif_log`; in `process_rules` task, before processing new rules, re-attempt `failed` rows up to 3 times. **Start at:** new method `classes/rule_engine.php:retry_failed()`.

10. **Implement WhatsApp + push channels** declared in `rule_manager::CHANNELS` line 43 or remove them from the dropdown. Currently the admin sees them but `send()` silently does nothing for those channels — confusing.

### P1 — Restore template authoring UX

11. **Replace plain `textarea` with HTML editor + token reference panel.** In `classes/form/edit_rule.php:62`, swap `addElement('textarea', ...)` for `addElement('editor', 'template', ...)` and add a `static` element listing the available `{{firstname}}`, `{{course_name}}`, `{{course_url}}` etc. for the selected rule_type. Mirrors BizLMS notification_form.php:164.

### P2 — Polish

12. **Categorise rule_type dropdown by parent module** (Course/Classroom/Program/Compliance/Engagement) using `selectgroups` element. Mirrors BizLMS `notification_form.php:70`.

13. **Restore master-info tile** in `block_masterinfo` so admins see total active rules at-a-glance. Add `local_airpay_notifications_masterinfo()` to `lib.php`.

14. **Add help button + tokenlist hint to rule_type select**, mirroring BizLMS form.

15. **Register externals to MOODLE_OFFICIAL_MOBILE_SERVICE** in `db/services.php` so mobile app can list/save rules.

---

## Summary verdict for stakeholder

**Status: PARTIAL PARITY with NET LOSS for enterprise.**

What Airpay GAINED: per-user prefs, quiet hours, manual nudge UI, race-safe dedupe, 5 new poll-driven engagement rules (streak, inactive, peer, anniversary, weekly mgr summary), better i18n (5 langs), unit tests, privacy provider.

What Airpay LOST (blocking real-world LMS use):
- **Zero event-driven email sends.** Every enrol-confirmation, complete-confirmation, ILT-reminder, program-completion email from BizLMS is gone. No customer received any email after this swap unless they happened to match one of the 16 polled rule types.
- **Zero PDF certificate attachments** on completion mail — entire compliance evidence pipeline broken.
- **Zero per-tenant template variants** — Airpay & Public tenants share identical wording.
- **Zero per-course / per-classroom template override** — can't have different reminder text for "Mandatory POSH" vs "Voluntary Sales Skill".
- **Zero manager CC on learner emails** — supervisors blind to direct-reports' enrolments.
- **WhatsApp/push are visual stubs only** — admin sees them in dropdown, they do nothing.

Before production, fixes 1-4 are mandatory. Fixes 5-10 should be in the first month. Without them, Airpay Academy will revert to manual emailing for every enrol/complete event.

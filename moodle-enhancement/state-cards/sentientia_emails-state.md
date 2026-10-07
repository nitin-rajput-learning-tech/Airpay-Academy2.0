# airpay_emails — STATE CARD

**Component:** `local_sentientia_emails` (was `local_airpay_emails` until ADR-022/025; the body of this card below the 2026-09-22 sections still uses the old table and plugin names where it was written before the rename)
**Current version:** `2026100701`  (release `1.4.0`: 2026-10-07 owner decisions, see the last section; before that `2026093001` / `1.3.0`, ADR-032 importer)
**Maturity:** STABLE (production)
**Last touched:** 2026-10-07 (COMMS owner decisions: redaction, manager copies, course link, three flagged senders)
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

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## ADR-021 Wave 4 — preview.php allow-list → tenant_registry (2026-06-01)
`preview.php` tenant allow-list migrated off the hardcoded `[1,77,177]` onto
`tenant_registry::valid_roots/is_valid`. Behaviour-identical while legacy ON.
v1.1.3→1.1.4 (2026060100). See ADR-021.

## 2026-09-22 — Cadence setting never persisted (1.1.3 / 2026092200)

Every UAT upgrade run printed `New setting: local_sentientia_emails/default_cadence_days_json` and
`cfg.php` reported "No such configuration variable" — the install default (and any admin edit) was never
written. Cause: `admin\setting_cadence_json::validate()` returned `''` on success, but the parent contract
is `true`; `admin_setting_configtext::write_setting()` only calls `config_write()` when
`validate() === true`, so the save returned `''` (read as "no error") without storing anything. Runtime
was unaffected (process_rules falls back to the baked-in `[1,3,7,14,21]`), but the admin UI silently
discarded every change. Both trees patched identically (`return true` on both success paths; docblock);
PHPUnit `setting_cadence_json_test` updated to the `true` contract. Deployed to UAT 2026-09-22 (see
PROJECT-STATE).


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031 tenant scope (1.2.0, 2026092500)

Sweep hits 22, 23 and 35 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25). `:manage`, `:manage_templates`
and `:manage_rules` keep their manager default: managing your own tenant's notifications is the
feature. What changed is WHERE, now decided only by `tenant::is_cross_tenant()` through the new
`classes/tenant_scope.php`:

- `manage.php` and `editor.php` pin a scoped caller to their own tenant root whatever `?tenant=`
  says, and refuse a caller whose tenant does not resolve. `?tenant=77`, `?tenant=-1` and an empty
  open_path used to mean another tenant or "All Tenants".
- The delivery log, its CSV export and the dashboard counts are forced to the reader's tenant
  inside `delivery_log` itself; a reader with no tenant gets nothing.
- Rule toggle, save, edit and delete, and the `toggle_rule` web service, check the rule. Global rules
  (tenant 0) and other tenants' rules are cross-tenant writes; a global rule stays readable. A scoped
  save lands on the caller's tenant (the form's default "All Tenants" becomes their tenant); naming
  another tenant is refused.
- `save_template` / `revert_template` / `editor.php` refuse the global override (tenant 0) and
  other tenants for scoped callers. `get_template` had no capability check; it now needs
  `:manage_templates` and reads the caller's own tenant. `preview_template` needs `:preview`.
- `email_renderer::render()` now resolves the RECIPIENT's tenant. It never did (tenant_config had
  no open_path key), so only the global override was ever delivered, which is why tenant admins
  edited the global one. Tenant overrides now reach their tenant.
- `process_rules` confines a tenant rule's recipients to that tenant. A rule marked "Airpay Only"
  used to email every tenant's learners. Global rules are unchanged.

Not changed: the BizLMS legacy email counts on the dashboard stay site-wide (aggregate counts, no
tenant column); the tab templates still show the scope selector and toggle/delete buttons to scoped
callers, but the server refuses them (no visual evidence could be captured in this session).
Data review for Nitin: existing `local_sentientia_email_overrides` rows with `tenant_id = 0` written
by non-admins are live cross-tenant bodies; rows with `tenant_id > 0` start delivering now.
Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 follow-up (1.2.1, 2026092501)

Reviewer items on wave 1 (branch `claude/adr031-comms-ff`).

- **The deploy gate is now enforced in code, so tenant overrides go live safely.** Wave 1 made
  `email_renderer` deliver the recipient's TENANT override. Before that, only the global override
  was ever sent, so every `tenant_id > 0` row sat unused. Any manager-archetype tenant admin could
  also write those rows for any tenant. Upgrade step 2026092501 (`db/upgradelib.php`,
  `local_sentientia_emails_deactivate_unentitled_overrides()`) sets `is_active = 0` on an active
  tenant override when both of these hold:
  - its `usermodified` is not cross-tenant (a site admin or a `:crosstenant` holder);
  - that author's tenant root differs from the row's `tenant_id`.

  Rows with no attributable author are switched off as well. Each switched-off row is `mtrace()`d
  as `DEACTIVATED tenant override id=.. template_key=.. tenant_id=.. usermodified=..` for Nitin's
  review. Rows are switched off, not deleted, so an entitled editor can re-save them. Global rows
  are **left active**: they were already being delivered before ADR-031, and switching them off
  would change the mail learners get. Each global row written by a non-cross-tenant author is
  traced as `REVIEW (left active) global override ...`. The query below is a read-only preview of
  what the step will touch. Site admins also appear in its results, but the step exempts them:

  ```sql
  SELECT o.id, o.template_key, o.tenant_id, o.usermodified, u.username, u.open_path
    FROM mdl_local_sentientia_email_overrides o
    LEFT JOIN mdl_user u ON u.id = o.usermodified
   WHERE o.is_active = 1 AND o.tenant_id > 0
     AND (u.id IS NULL OR u.open_path IS NULL
          OR (u.open_path <> CONCAT('/', o.tenant_id)
              AND u.open_path NOT LIKE CONCAT('/', o.tenant_id, '/%')));
  ```
- `legacy_bridge::get_bizlms_templates()` and `get_bizlms_template()`: the templates tab showed
  every tenant admin the BizLMS legacy subjects and body previews of every costcenter. Both now
  apply `tenant::sql_filter('ni')`. That means own costcenter only, nothing when the caller has no
  tenant, and everything for a cross-tenant caller. `get_bizlms_template()` also returned `false`
  through a `?object` return type, which is a TypeError; that is fixed.
- Rules tab UI (hits 22/35, the UI half):
  - A scoped admin viewing a rule they may not change (a global rule) now sees a disabled toggle
    and a lock in place of edit and delete.
  - The Tenant Scope select comes from `manage_controller::rule_scope_options()`. It offers a
    scoped caller their own tenant only, and it pre-selects the scope of the rule being edited. The
    old form pre-selected nothing, so re-saving a tenant rule turned it into a global one.
  - Viewing a global rule is read-only (no Save button).
  - manage.php no longer quietly rewrites a posted `rule_tenant=0` to the caller's own tenant
    (deviation 4). It now refuses it.
  - The page's tenant selector is shown to every cross-tenant caller (`is_crosstenant`), not only
    to site admins.
- `:manage_templates` now carries `RISK_XSS | RISK_SPAM` (hardening for hit 23).
- Verified that wave 1's `:preview` requirement does not break the editor:
  - The Design Studio's live preview posts to `preview_ajax.php`, which needs `:manage_templates`,
    not `:preview`.
  - The `template_editor` AMD module, which calls `preview_template`, is loaded nowhere.
  - `:preview` defaults to the manager archetype, and a test pins that a tenant admin keeps
    `preview_template`.

  Still to check on UAT: that role 9 holds `:preview`, unless someone has removed it there.

Not changed:
- `legacy_bridge::get_email_stats()` dashboard counts stay site-wide. They are aggregates only,
  and `local_emaillogs` has no tenant column that this code owns.
- `email_renderer::render()` still reads the recipient's `open_path` twice per email. This costs
  efficiency only.
- Editing a rule does not pre-select its type, channel or audience. This bug predates this change:
  saving an edit resets those three fields to their first option.
- No screenshots could be captured in this session because there was no local Moodle access.
  Before merge, visual evidence is still owed for the `manage.php` rules tab and for `editor.php`,
  each as a scoped admin and as a site admin, on desktop and mobile.

Tests: `tests/override_audit_test.php` (new) and `tests/tenant_scope_test.php` (legacy templates,
rule UI, preview). Both are in `@group tenant_isolation`.

## 2026-09-25 - ADR-031 follow-up 2 (still 1.2.1, 2026092501)

Adversarial-review items on `claude/adr031-comms-ff` (branch `claude/adr031-comms-ff2`). No upgrade
step was added and access, install.xml, services and caches are unchanged, so the version stands.

- **The templates tab now matches delivery.** `template_manager::get_templates_with_status()` let an
  INACTIVE tenant row overwrite the global row in its override map. Once 2026092501 switched a
  tenant override off, the tab showed "file / no override" while that tenant's learners were
  receiving the active global override. Inactive tenant rows are now skipped, as
  `get_override()` skips them. Side effect: with tenant 0 selected (the global scope), global rows
  are no longer relabelled "tenant".
- **The 2026092501 audit is persisted, not only printed.** The DEACTIVATED and REVIEW lines used to
  exist only in the upgrade output. The step now runs `local_sentientia_emails_run_override_audit()`
  (`db/upgradelib.php`). It prints the same lines and also stores them in two places, so Nitin can
  review them after a web or CLI upgrade:
  - **Config changes report:** Site administration > Reports > Config changes
    (`/report/configlog/index.php`), plugin `local_sentientia_emails`, filtered by setting name.
    - `adr031_override_deactivated`: one entry per switched-off row. The original value is
      `is_active=1`, and the new value names the id, template key, tenant and the author's tenant
      root.
    - `adr031_override_review`: one entry per global override left active for review.
    - `adr031_override_audit`: one summary entry with both id lists. It is written even when
      nothing is found, so "found nothing" can be told apart from "never ran".
  - **Plugin setting `local_sentientia_emails/adr031_override_audit`:** a JSON document
    `{"recorded", "deactivated": [...], "review": [...]}`. Each entry holds `id`, `template_key`,
    `tenant_id` and `author_root`. Read it with
    `php admin/cli/cfg.php --component=local_sentientia_emails --name=adr031_override_audit`.
  - **The author's user id is not stored.** No privacy provider covers a config value or
    config-log text. The override row keeps `usermodified`, and the step changes only `is_active`
    and `timemodified`, so the author can still be looked up by override id. The console lines
    still print `usermodified`, as before.
  - **Caveat:** the step body changed but its version did not. A database that ran 2026092501 on
    the earlier code has only that run's console output. None is known: nothing from ADR-031 has
    been deployed to UAT or production (PROJECT-STATE, 2026-09-25). On such a database, the
    read-only query above with `o.is_active = 0` lists the candidates. It also lists any override
    that was already switched off by hand.
- **Tenant labels are localised and white-label.** `manage_controller::rule_scope_options()` and
  `tenant_selector_options()` hardcoded English ("All Tenants (Global)", "Airpay Only",
  "Tenant N"). "Airpay Only" also put a customer's name on every white-label deployment. The labels
  now come from these strings, in en and hi:
  - `rule_scope_global` (reused);
  - `tenant_all`;
  - `rule_scope_tenant` ("{$a} only");
  - `tenant_n`.

  The tenant name is the org registry's `fullname` for path `/N` (`local_sentientia_org`, falling
  back to the legacy costcenter). When the registry has no name, "Tenant N" is used. The tenant
  list comes from `tenant_registry::valid_roots()` instead of a hardcoded `[1, 77, 177]`, and in
  legacy mode it is still those three.
- **The read-only global-rule form (UI):**
  - When `editrule_readonly` is set, every field sits inside `<fieldset disabled>`.
  - The form no longer carries the `saverule` action, `sesskey` or `ruleid`. The server still
    refuses the save regardless.
  - Both lock icons have `aria-hidden="true"`.
  - The disabled toggle has an `aria-label`, not only a `title`.
  - The actions-column lock has screen-reader text (`sr-only visually-hidden`).
- **Behaviour change to note:** a cross-tenant caller on `manage.php?tenant=N` now gets tenant N
  pre-selected for a NEW rule. Before the first follow-up the form pre-selected nothing, so a new
  rule defaulted to Global. A cross-tenant admin who wants a global rule from a tenant-filtered
  page must now choose "All Tenants (Global)" explicitly. This is pinned in
  `test_rule_ui_for_site_admin_keeps_every_scope_and_preselects_the_rule`.
- **Deliberately NOT fixed (Nitin decides):** the `local_sentientia_notifications` `rule_engine`
  precedence bug. `rule_course_not_started`, `rule_streak_broken` and `rule_new_course` build
  `"... LIMIT " . (int) get_config(..., 'batch_limit') ?: 500`, which evaluates to `LIMIT 0`, so
  those three seeded, enabled rules have never sent anything. Fixing the precedence would start
  real sends to UAT's imported production users. The bug and the options (disable the seeded rules
  first, or put the fix behind a flag) are written up in `sentientia_notifications-state.md`, in
  the 2026-09-25 follow-up. The code is unchanged.

Still open:
- Visual evidence is still owed for the rules tab: a read-only global rule as a scoped admin and an
  editable rule as a site admin, on desktop and mobile. No local Moodle was used in this session.
- `editor.php` and `preview.php` still hardcode their tenant lists ("Global (all tenants)",
  "Airpay", "Public", "ZEEA"), and the rules table still shows "Tenant {id}" in English. These were
  not part of these items.

Tests:
- `tests/tenant_scope_test.php`: new tests
  - `test_templates_tab_reports_the_override_the_tenant_actually_receives`
  - `test_tenant_labels_come_from_the_org_registry_not_a_hardcoded_customer`
  - `test_readonly_global_rule_form_cannot_be_submitted`
- `tests/tenant_scope_test.php`: the scoped-admin scope assertion now expects the neutral
  localised label instead of "Airpay Only".
- `tests/override_audit_test.php`: new tests
  - `test_the_audit_outlives_the_upgrade_output`
  - `test_an_audit_that_finds_nothing_still_records_that_it_ran`
- Both classes are `@group tenant_isolation`. PHPUnit was not run in this session.

### 2026-09-29 - legacy-queue totals shown to cross-tenant callers only

The Playwright visual pass found the manage.php dashboard's "Legacy queue" card (BizLMS
`local_emaillogs` totals) identical for the Airpay and the ZEEA tenant admin: that table has no tenant
column, so the numbers were site-wide. `manage_controller` now fetches them only when
`tenant::is_cross_tenant()` and passes `show_legacy`; `tab_dashboard.mustache` hides the card
otherwise. Aggregate counts only (no PII). Evidence: docs/visual-evidence/2026-09-29/recheck-emails/.

### 2026-09-30 - ADR-032 notifications importer (BizLMS e-mail history into the delivery log)

Version `2026093001`, release `1.3.0`. Branch `claude/bizlms-import-notifications`. Mapping doc section 11.
PHPUnit was NOT run in this session (the lead re-inits once for every version bump, then runs the group).
The ADR-032 gating items on MySQL 8.4 and MariaDB 10.11 still apply to this importer like every other.

What the import does (`classes/bizlms/`, registered in `db/bizlms_import.php`, feature key `notifications`):
- `local_emaillogs` and, only if the table exists, `local_email_logs` become rows of
  `local_sentientia_email_log`: one map row per source row, MAP ids, no PRESERVE step, `depends()` empty,
  batch mode (`atomic()` false). `local_notification_info/_type/_strings` are declined (read in place).
- **Nothing is ever sent.** The steps return outcomes; the framework writer inserts. No message, e-mail,
  event, `notification_sender`, or `delivery_log::log()` (which under `noemailever` would rewrite the status
  to `suppressed`). The static scan of `classes/bizlms/` is clean.
- Status: BizLMS `1` -> `sent`; `0`, NULL and anything else -> `not_sent` with a note (new status value,
  documented in the install.xml COMMENT; never `failed` or `suppressed`, which drive the dashboard tiles).
  A row BizLMS marked sent for a recipient who was ALREADY deleted when the send ran stays `sent` with a note
  (decision `notifications.deleted_recipient_sent`; `suppressed` is the other accepted value). A recipient
  deleted AFTER the send was delivered to, and that row is plain `sent` (review round 1, below).
- `template_key` and `rule_id` stay NULL on every imported row, so the reminder dedupe, cap and
  completion-stamp queries can never count an imported row. `legacy_source = 'bizlms'` marks the row.
- Tenant: the root of the RECIPIENT's current `open_path`, validated by `tenant::assert_valid()`. The
  template's path is used only when the recipient has NO path at all (none stored, or an empty one); a
  recipient whose path is there but does not parse, or names an unregistered root, is NOT filed under the
  template's tenant (tenant 0, cross-tenant callers only).
  Decision `tenant.unresolved.notifications` = `pathless` (the alternative, `skip`, archives such rows).
- Credentials (`redactor`): a users-module type, a template that uses `[employee_password]`, a row whose own
  subject or body still carries the placeholder, a row whose own module type says users, or an unresolvable
  template whose subject reads like an account message is
  imported with the subject masked (`[withheld: account credentials]`) and the body NULL. Every other
  subject and body is scrubbed (password/OTP/PIN/token/API key values, secret link parameters, bearer
  tokens); if the scrub cannot run the text is withheld. Status 0 rows too.
- Source timestamps kept: a delivered `local_emaillogs` row is created when it was sent (BizLMS stamped the
  sender task's time); otherwise the first non-zero of `timecreated`, `time_created`, `timemodified`.
  `sent_date` becomes `timesent`. Nothing is stamped with the import time.
- Skips: a recipient with no user row (`orphan_user`, needs-owner, so parity exits 2 until the owner adds
  `notifications:orphan_user` to the top-level `accepted_reasons` list of the decisions file, with its Stage B
  count, after the rehearsal; nothing is pre-accepted, COMMS-C1, F-82) and, only under the `skip` decision,
  `tenant_unresolved`.
  Deleted users' rows are imported (their user row exists).

Schema (`db/install.xml` + guarded `db/upgrade.php` step): `local_sentientia_email_log` gains
`legacy_source` CHAR(40), `sender_userid` INT, `timesent` INT, `body_html` TEXT (all nullable) and
`idx_legacy_source`. `tenant_columns()` is deliberately EMPTY: `tenant_id` is an INT root, and the generic
tenant verify only accepts normalised paths (`/77`, not `77`); `importer::verify()` checks the roots itself.

Reader code fixes (mapping doc, code fixes 1-7), behind two default-OFF flags in `db/feature_flags.php`:
- `sentientia.emails.imported_history.enabled`: the Logs tab, its export and the dashboard tiles include
  imported rows, with a BizLMS badge, the BizLMS type when there is no template, a badge for every status
  (`not_sent` included) and Sent from / Sent on columns. OFF: every reader leaves imported rows out, so
  the page, the numbers and the export are what they were before the import.
- `sentientia.emails.imported_body_detail.enabled` (needs the first): `email_detail.php`, a cleaned
  (`format_text`, no filters) view of one imported message for holders of `:manage` within their tenant. With
  either flag OFF, for a native row, and for another tenant's row it answers as for a row that does not exist.
- The four reminder dedupe/cap queries and `mark_reminders_suppressed_on_completion` carry
  `legacy_source IS NULL`. The list and `export_csv` select explicit columns (no `body_html`); the Export CSV
  button streams the whole log a page at a time instead of the first 10 000 rows. The dashboard's BizLMS
  "Legacy queue" card is hidden once imported rows are shown (no double count). `legacy_bridge` matches
  templates on `open_path` (`/N` or `/N/...`, whole segment) OR the old `costcenterid`; the importer preflight
  counts templates whose `open_path` has no leading slash (`template_open_path_without_leading_slash`), which
  the path filter cannot match.
- Nobody turns these flags ON but Nitin, after he has reviewed the visual evidence
  (`framework.reader_flags_airpay_at_cutover`). **Visual evidence is still owed** for the Logs tab (desktop
  and mobile, flag ON) and the detail page: no local Moodle was used in this session.

Privacy (`classes/privacy/provider.php`): `sender_userid`, `body_html` and `timesent` are declared (en + hi).
The recipient's export includes their rows whole; a sender's export lists the rows that name them (id, type,
status, times; never the recipient or the body). Erasing a recipient deletes their rows as before and sets
`sender_userid` to 0 on the rows they queued; erasing a sender keeps the recipients' history with
`sender_userid` 0. `sender_userid` was already in the platform's `USER_COLUMNS` guard.

Tests (`tests/`): `bizlms_import_test.php` (importer contract traits plus the 17+4 row fixture of the
mapping doc), `bizlms_redactor_test.php` (pure), `imported_history_reader_test.php` (flags, tenant isolation,
streamed export, detail view, dashboard, template filter), `privacy_imported_history_test.php`. Fixture
`tests/fixtures/bizlms/notifications.install.xml` is BUILT from `classroom/db/install.php` (BizLMS declares no
install.xml for these tables), with the production-only columns and both timestamp dialects.

Open for Nitin / the lead:
- After the Stage B rehearsal: the owner decides whether to add `notifications:orphan_user` to `accepted_reasons`
  (a count of rows; 0 on April). The older name `accept_needsowner.notifications.orphan_user` never existed in the loader.
- Which BizLMS notification types have no Sentientia rule once BizLMS stops sending (mapping doc, open
  question 5) is a parity question, not something this importer answers.
- Retention of imported rows is "keep, no purge" (`notifications.retention`); a retention period comes with
  the later legacy-table privacy ADR.

#### Review round 1 (2026-10-01): fixes

Same branch, no schema change, plugin version stays `2026093001`. PHPUnit was NOT run (the lead re-inits and runs
the group). Checked read-only against the April 2026 rehearsal copy (schema `bizlms_april`).

- **Deleted-recipient note is now true.** `log_step::delivered_to_deleted_recipient()` compares the recipient's
  user row with the row's sent date: the note (or `suppressed`) is applied only when `deleted = 1` AND
  `timemodified <= sent_date` AND `lastaccess <= sent_date`, i.e. the user was already deleted when BizLMS ran
  the send (BZ `notification.php:85-88` marks such a row sent without sending). A recipient deleted after a real
  delivery imports as plain `sent`, `error_message` NULL. If the sent date or the deletion stamp is missing the
  two cannot be compared: the note is kept and the report carries `deleted_recipient_time_unknown`. On April
  there are 342 status-1 rows to 108 now-deleted users; the new rule notes 1 of them and finds 0 undecidable
  (measured read-only), so the old rule would have written 341 false "not delivered" notes. The preflight warning
  `sent_to_deleted_recipient` is narrowed the same way, and `sent_to_deleted_recipient_time_unknown` counts the
  undecidable ones. The wording of ADR-032 and of the decision `notifications.deleted_recipient_sent` should say
  "deleted when BizLMS ran the send" (lead: both are outside this branch's remit).
- **Template filter on the production shape.** `local_notification_info` in the April copy has `open_path` and NO
  `costcenterid`. `legacy_bridge` now builds its select, order and tenant filter from the columns the table has
  (`open_path` only, `costcenterid` only, or both); before, the SQL named `ni.costcenterid`, threw, the catch
  swallowed it, and the Templates tab and the preview were empty for everybody. Verified against the April
  copy: 15 templates for a cross-tenant caller, 10 for a /1 caller, 4 for a /77 caller. The label's "(Tenant N)"
  comes from the root of `open_path` when there is no `costcenterid`. The fixture now has the April shape; the
  reader test covers it, and a pure test covers all three shapes. **This filter change has no flag**: scoped
  admins see `open_path` templates as soon as it deploys. It reads as a bug fix; the lead should accept it as one.
- **Tenant fallback tightened.** A recipient path that is present but does not parse is tenant 0, never the
  template's root (the template path can name another tenant: BizLMS matched templates with an unbounded LIKE).
  0 April rows are affected (all 14,202 recipient paths are exact).
- **Redaction.** A subject that cannot be scrubbed is masked AND its body is withheld (verify() would otherwise
  fail a non-atomic run after the rows were committed). A row whose own subject or body still holds
  `[employee_password]` is a credential row (`credentials_withheld:row_placeholder`), whatever its template says.
- **Display.** `template_label`, `error`, `sender_name` and `subject` are passed to the Logs template raw; the
  template escapes once (they were escaped twice, so "&" showed as "&amp;").
- **Preflight** guards every legacy column it queries (`to_userid`, `sent_date`, `notification_infoid`, ...), so a
  malformed table reports the runner's `missing_column` block instead of throwing.

Known, not changed here (see the build report): while `imported_history.enabled` is OFF (the default), the
dashboard's BizLMS "Legacy queue" card still counts `local_emaillogs` site-wide; the card is only hidden once the
imported rows are shown, so nothing is counted twice. Manager copies (`teammemberid` > 0) are imported with a body
that names the team member, and `teammemberid` is not carried, so erasing the team member does not reach that
body (spec gap, needs a decision). 78% of April rows were queued by the support pseudo-user (`from_userid` -20),
which maps to a NULL sender, so the Sent from column is blank on them. `mask_pii_for_dev.php` (platform) does not
mask the subject or body of imported rows. Visual evidence for the flagged UI is still owed.

### 2026-10-07 - owner decisions of the comms cluster (version `2026100701`, release `1.4.0`)

Branch `claude/owner-decisions-x`. Decided by Nitin's delegation of 2026-10-07 ("self review and decide
recommended option") on top of the signed basis "do everything as recommended". Full list:
`docs/cutover/OWNER-DECISIONS-2026-10-07.md`. **PHPUnit was NOT run in this session** (the lead re-inits for the
version bump and runs the group). Nothing was copied to XAMPP and no flag was flipped.

Importer (`classes/bizlms/`), the three keys are in the signed decisions file and declared by `importer::decisions()`:
- **COMMS-N1, credentials.** A row whose template (`local_notification_info`) or notification type cannot be resolved
  (gone, or no template reference at all: `notification_infoid` 0 or NULL) has its BODY withheld whatever it says
  (`credentials_withheld:unresolved_template`). Its subject is masked only when `redactor::subject_suggests_credentials()`
  or the new `redactor::text_mentions_secret()` matches, otherwise scrubbed. (The first build kept the body of a row that
  never pointed at a template, behind a `notification_infoid > 0` condition; the decision has no such exception, and both
  reviews called it out, so a custom mail, an ILT reminder and every `local_email_logs` row lose their body too: fix round 1.)
  The row's own `moduletype` signal never fires on production (`''` on all 14,202 April rows, because the users
  writer never sets it); the template and its type are what carry the answer. `scrub()` now also catches a secret in
  the next table cell (`<td>Password</td><td>X</td>`), behind a line break or a newline, and a value that holds
  `,` `;` or `&`; the value runs to whitespace, `<` or a quote (an `&` that opens the next link parameter still ends
  it). It is idempotent, and `importer::verify()` relies on that: `imported_text_with_unredacted_secret` fails the
  run when scrub would still change an imported subject or body (a subject of exactly 255 characters is not checked:
  the column limit may have cut a mask). Preflight counts the unresolved-template rows that name a secret word, in both
  tables (`unresolved_template_rows_naming_a_secret_word:<table>`, an upper bound: LIKE also matches "spin"; a row with
  no template reference counts). F-72: scrub also blanks
  the value after the bare words "pass" and "pin" and the word behind a tag or line break after any secret word; it
  changes 0 of the 13,363 kept April rows, so that over-redaction costs nothing and is accepted (documented in the
  redactor docblock). The optional "treat a '/' path as empty" tweak was NOT made (0 April rows).
- **COMMS-N2, manager copies.** `notifications.team_member_copy_body = withhold`: a row whose source has
  `teammemberid > 0` imports without its body, and the member's whole-word first and last name (and each word of
  them of two letters or more) becomes `[team member]` in the subject (one bulk fetch of names per batch). Warning
  `team_member_copy_body_withheld`. `verify()` joins the legacy map to `local_emaillogs` and fails
  `manager_copy_imported_with_a_body_although_the_decision_says_to_withhold`. A later `subject_userid` column
  could backfill the bodies from the legacy table. On April (counts only, read-only) 4 of the 23 distinct
  manager-copy subjects contain the member's first name as a substring and none contains it as a WHOLE word, so the
  subject scrub changes nothing there (the 4 are the first name inside a longer word, which is correctly left alone);
  the bodies are where the name is, and they are withheld.
- **COMMS-N3, course link.** `notifications.course_link = moduleid_for_course_templates`: `courseid` is the column
  when it is above zero and the course exists; else, for a template whose `moduletype` is `course`, the row's
  `moduleid` (a TEXT column, read as an id only when it is one plain number) above 1 whose course exists
  (warning `course_from_moduleid`); else NULL. The production table has no `courseid` column.
- **F-63** `timesent` is set only for a delivered row. **F-67** preflight warns
  `many_deleted_recipients_share_one_timemodified` and `deleted_recipients_modified_after_the_newest_send`: the
  deleted-at-send rule reads the user's `timemodified`, so run this feature BEFORE any step that rewrites deleted
  users' rows (the DPDP anonymiser, an HRMS re-sync, clean-ups). Preflight also reports `manager_copies`.
- **Measured read-only on the April copy** (local XAMPP database, the worktree code, no write): 14,202 rows,
  14,197 `sent`, 5 `not_sent`, 839 subjects masked with 0 bodies beside them, 2,760 bodies withheld (839 credential
  + 1,921 manager copies), 9,409 rows with a course from `moduleid`, 0 rows needing `credential_text_scrubbed`, scrub
  idempotent on every written subject and body. These are the expected Stage B figures.

New senders (COMMS-N7, `gaps.notification_sender_parity = build_flagged_off`), `classes/parity_senders.php`:
BizLMS sends `course_enrol`, `learningplan_enrol` and a manager copy of `course_complete`, and Sentientia had no
sender for them (it has the learner's completion e-mail and, in `local_sentientia_users`, the welcome e-mail).

| Flag (all default OFF, read for the RECIPIENT's customer and tenant) | Trigger | Template |
|---|---|---|
| `sentientia.emails.send_course_enrolment.enabled` | `\core\event\user_enrolment_created` (`observer::user_enrolment_created`) | `enrollment/course_enrolled` |
| `sentientia.emails.send_learning_path_enrolment.enabled` | scheduled task `send_path_enrolments`, every 5 minutes | `enrollment/learning_path_enrolled` |
| `sentientia.emails.send_manager_completion_copy.enabled` | `observer::course_completed` (the learner's live supervisor, same tenant) | `enrollment/manager_course_completed` (new) |

- The learning-path plugin fires no event when it enrols a learner, so the task polls
  `local_sentientia_learningpath_users` above a saved id (`path_enrolment_watermark`): the first run only records the
  end of the table, the id moves past every row it looked at (flag OFF, rule off, user gone), so switching the flag ON
  never e-mails an old enrolment; a row older than two days, a row the BizLMS import wrote (it is in the legacy
  map) and an archived path are never e-mailed. If the learning-path plugin ever raises an event, call
  `parity_senders::learning_path_enrolled()` from it and drop the task.
- All three go through `notification_sender`: `$CFG->noemailever` logs the row `suppressed` (every non-production
  copy), the recipient's channel preference applies, a delivery-log row with its `template_key` is written, no
  password is ever sent. E-mail leaves through the same path as every other rule (`message_send`, honouring the
  user's message preferences; to force it, set the `notification_alert` provider's e-mail default to forced under
  Messaging).
- A rule row of the type (`course_enrolled`, `learning_path_enrolled`, `manager_course_completed`) sets the channel and
  the template and can switch the e-mail off for a tenant. With NO rule row of the type a built-in default is used,
  so a fresh install (the plugin has no `db/install.php`, so no rules are seeded there; only `db/upgrade.php` seeds
  them) sends the same e-mail as an upgraded one. UAT check still open: whether a fresh install has the learner's
  `course_completed` rule at all.
- Skipped: a hidden course, a suspended enrolment, a suspended or deleted user, a guest, a duplicate within five
  minutes; for the manager copy a supervisor who is suspended, deleted or in another tenant than the learner
  (ADR-031: a name and progress do not cross a tenant boundary).
- **The manager copy's delivery-log row never names the learner** (fix round 1): the e-mail does, the row (the manager's,
  `userid` = manager) logs 'A team member has completed <course>'. The privacy provider reaches a log row only by
  `userid` / `sender_userid`, so a name in the manager's row could never be removed by the learner's own erasure.
  `notification_sender::send()` takes an optional `log_subject` for this; no schema or provider change.
- **The path poller writes its marker once per run** (fix round 1), not once per row looked at. While its flag is OFF for
  every tenant it reads no row and moves the marker to the highest id in one write; otherwise it writes after each
  e-mail handed to the sender (an e-mail cannot be taken back, so a run that stops half way never sends twice) and once at
  the end. The values are strings: `set_config` compares strictly, an int is never equal to the stored string and would
  rewrite and purge the cache on every call. It stands in for 'the learning-path enrol event' until `path_manager` calls
  `parity_senders::learning_path_enrolled()` directly.
- **Turning a sender ON for Airpay at cutover is Nitin's call**, after he has seen them on UAT (Q11 of the owner
  decisions). Recommended flip: ON for Airpay. Nothing here flips a flag.
- New strings en + hi (`parity_*`, `task_send_path_enrolments`); the path template drops its deadline lines for a
  path with no end date; the editor and preview list the new template.

Other changes: `idx_sender_userid` on the log (F-64; install.xml and the guarded upgrade step `2026100701`, the privacy
provider looks senders up); imported bodies lose off-site images, CSS `url()` and `background` attributes before
`format_text` (`imported_history::without_external_resources()`), and CSV cells starting with `=` `+` `-` `@` are
prefixed with a quote (`delivery_log::csv_safe()`), both F-65, before `imported_body_detail.enabled` is ever flipped;
test hygiene (F-66: the optional-columns test drops the table it cut, `sql_compare_text` on `error_message`); the
`mask_pii_for_dev.php` fix is in the platform (F-60, F-87: the `to_email` UPDATE is gone, imported subjects are masked,
bodies removed). F-85 ledger for this plugin: ONE version, `2026100701`.

Accepted, recorded (no code):
- **COMMS-N4** a blank "Sent from" on an imported row means the BizLMS system (the support user, `from_userid` -20)
  sent it, exactly as BizLMS's own list showed (`BZ email_status_filters.php:43`). 11,099 of 14,202 April rows. Say so
  in the visual-evidence README so the blank column does not read as broken.
- **COMMS-N5** the `legacy_bridge` template-filter change ships WITHOUT a flag, accepted as a bug fix 2026-10-07 under
  the delegation (the Templates tab was empty for everyone on the production shape). Evidence owed: the Templates tab
  as a scoped (`/77`) admin.
- **COMMS-N6** `notifications.deleted_recipient_sent` applies only to a recipient already deleted when BizLMS ran the
  send; the wording is corrected in the decisions file, ADR-032 and the mapping doc.
- **COMMS-C1** the orphan skip reasons are NOT pre-accepted; the owner adds `notifications:orphan_user` after Stage B.
- **COMMS-C2** recommended flips for Airpay at cutover (none made): `sentientia.emails.imported_history.enabled` ON,
  `sentientia.emails.imported_body_detail.enabled` ON, after the screenshots.

Visual evidence still owed (desktop and mobile, `docs/visual-evidence/<date>/`): the Logs tab (Sent from, Sent on,
`not_sent` and other badges, the BizLMS badge), `email_detail.php` (with an imported mail that had a remote image), the
Templates tab as a `/77` admin, and the new `enrollment/manager_course_completed` template in the preview.

### 2026-10-07 - fix round 1 after the two reviews (same version `2026100701`, no schema change)

Branch `claude/owner-decisions-x`. Written, NOT run (the lead runs PHPUnit after merging). Both trees identical.

- **COMMS-N1 (both reviews, must-fix).** Removed the `notification_infoid > 0` condition in `log_step::credential_reason()`:
  every row whose template or type cannot be resolved loses its body, a row with no template reference (a custom mail, an ILT
  reminder, a `local_email_logs` row, NULL included) too. Preflight counts the unresolved rows that name a secret word for BOTH
  tables, with no reference condition. The decision text, the mapping doc s11 and the code agree now. April impact: none (0 rows
  with a reference of 0 or less in `local_emaillogs`, no `local_email_logs` table); the live backup may differ, which is why the
  count is in the preflight. Tests: a reference of 0 and a NULL reference lose their body, the seed's `local_email_logs` rows are
  unresolved (their warnings are counted), the preflight counts a row with no reference and the second table.
- **COMMS-N7 manager copy (second review, must-fix).** The delivery-log row no longer carries the learner's name (see the N7
  section above). Strings `parity_log_subject_manager_completion` (en, hi). Tests: the logged subject is neutral, no column of
  the row holds the learner's name, e-mail or username, and the `log_subject` option changes only what is logged.
- **COMMS-N7 poller (both reviews, should-fix).** One marker write per run, a jump to the highest id while the flag is OFF
  (see above). Tests: the OFF jump, and a mixed batch (an old row, a new one and a skipped one) ending on the last row.
- **F-11.** `log_step::root_is_registered()` asks `tenant_resolver::root_is_registered()`.
- **Left, on purpose.** (1) The manager-copy template `enrollment/manager_course_completed.mustache` is English only, like the
  shared partials `course_info_box` and `footer_note` and the rest of the template family, so a Hindi recipient gets a mixed
  e-mail for the subject only. A per-recipient-language render of the whole family (force the recipient's language while
  rendering, move the texts to `{{#str}}`) has to land before the COMMS-N7 flags are flipped for a Hindi-speaking audience; it
  is not a small change and it touches every template. (2) F-15: the providers of `email_overrides` and `email_rules` do not
  declare `usermodified` (listed in `UNDECLARED_ACTOR_TABLES`); another stream's commit `ac725ba2d` declares them, so run
  `privacy_coverage_test` after the lead's re-init and merge. (3) The visual evidence below is still owed.
- **UAT checks added to the runbook:** that no core enrol plugin's own welcome message double-sends once
  `send_course_enrolment` is ON; that the manager-copy log row names no learner.

Visual evidence owed in addition to the list above: the Logs tab with a manager-copy row ('A team member has completed ...'), and
the Templates tab (it lists 'Course Completed (Manager Copy)' whether or not the sender is on).

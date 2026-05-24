# NIGHT-RUN PLAYBOOK — Airpay / Sentientia LMS

**Owner:** autonomous Claude session (started by Nitin's "code all night" instruction, 2026-05-24)
**Scope:** Phase B.12 cutover-day mechanical fixes + plugin tests + Goal C user-guide drafts
**Branch:** `production` on `nitin-rajput-learning-tech/Airpay-Academy2.0`
**Resumption schedule:** scheduled-task `airpay-night-run-resume` fires hourly. See bottom of file for prompt.

---

## Operating rules (verbatim from `D:\Claude Local\airpay-ld-os\CLAUDE.md`)

- **NEVER deploy to production server** (live.airpay.academy). Local repo + GitHub `production` branch only.
- **NEVER POST to live Moodle / ElevenLabs / Gamma / Anthropic** without explicit `[CONFIRM]` (none of tonight's items need them — flag if you think they do).
- **NEVER skip pre-commit hooks.** If a hook fails, fix and re-stage; do not `--no-verify`.
- **NEVER bypass signing.**
- **Push after every commit** (CLAUDE.md §14 git protocol).
- **Update `moodle-enhancement/PROJECT-STATE.md`** with a 1-line entry per shipped item.
- **All file writes inside `D:\Claude Local\airpay-ld-os\` only.**
- **Feature flags + Hindi parity** non-negotiable for any new user-facing feature (these items don't ship user-facing features — they're internal tests + dual-target migrations + docs).
- **Co-author every commit** with `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>`.

---

## How to use this playbook

1. **Find the first item with status `[PENDING]`** below.
2. **Flip it to `[IN_PROGRESS]`** (edit this file).
3. **Execute the entire item per its spec.**
4. **On success:** flip to `[COMPLETED]` + record commit hash + 1-line completion note.
5. **On blocker:** flip to `[BLOCKED]` + record the reason + move to the next pending item.
6. **One commit per item** unless the spec says otherwise. Push after each commit.
7. **Time budget:** if a single item exceeds 60 minutes of wall time, ship partial progress + add a `[PAUSED-AT]` marker explaining where to resume.

When ALL items reach `[COMPLETED]` or `[BLOCKED]`, append the line `=== NIGHT-RUN DONE ===` at the very bottom of this file and let the scheduled task idle.

---

## Item list (priority order)

### A1 — Fix `quizaccess_airpay_proctoring/db/upgrade.php` table-existence bug

- **Status:** `[COMPLETED]`
- **Why:** The db/upgrade.php at v2026051300 inserts into `quizaccess_airpay_proctor` but on production (v2026051120) that table does not yet exist — install.xml only creates it on fresh install, not on upgrade. So upgrading from prod v2026051120 fails. (Documented in `docs/5.2-merge/PHASE-B12-HOTFIX-MISSED-OVERLAY-PLUGINS.md`.)
- **File:** `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/db/upgrade.php`
- **Change:** before the `foreach ($rows…)` block, check if the table exists; if not, create it from XMLDB via `$dbman->create_table()` using the same definition as `db/install.xml`. Then run the existing migration.
- **Verify:** `php -l <file>`; cross-check XMLDB definition matches install.xml `<TABLE NAME="quizaccess_airpay_proctor">`.
- **Bump:** version.php `2026051300 → 2026052401` + release string.
- **Lang:** none required (internal change).
- **Commit msg:** `fix(quizaccess_airpay_proctoring): create table inside upgrade.php if absent`
- **Completion log:** commit `114fed155` — defensive XMLDB table-create added to `< 2026051300` savepoint, no-op `< 2026052401` savepoint added for diagnostics, version bumped 2026051300→2026052401, release 1.1.0→1.1.1. PHP lint clean.

### A2 — `local/airpay_courses/amd/src/enrolledusers.js` — ModalFactory → core/modal dual-target

- **Status:** `[PENDING]`
- **Why:** `core/modal_factory` removed in Moodle 5.2 (MDL-79182). Replace with `core/modal` while keeping 5.1 compatibility via a runtime check.
- **File:** `moodle-enhancement/local/airpay_courses/amd/src/enrolledusers.js`
- **Pattern (drop-in shim — copy into the file's existing `define([…])` array):**
  ```js
  // Detect 5.2's core/modal vs 5.1's core/modal_factory at runtime.
  // 5.2: import * as Modal from 'core/modal'; Modal.create({type, ...})
  // 5.1: import ModalFactory from 'core/modal_factory'; ModalFactory.create({type, ...})
  // Both expose .create() returning a promise<Modal>.
  const ModalApi = await (async () => {
      try {
          return await import('core/modal');         // 5.2 path
      } catch (e) {
          const m = await import('core/modal_factory'); // 5.1 fallback
          return m.default || m;
      }
  })();
  // …existing code, but call ModalApi.create(…) instead of ModalFactory.create(…)
  ```
  If the file uses AMD `define([…], function (…) {})` (not ES modules), the equivalent is:
  ```js
  require(['core/modal'], function(Modal) {
      // 5.2 path
  }, function() {
      require(['core/modal_factory'], function(ModalFactory) {
          // 5.1 fallback
      });
  });
  ```
- **Build:** if a build pipeline exists for this plugin (`amd/build/*.min.js`), regenerate. Otherwise note in commit that minified bundle requires grunt rebuild on next infra-rebuild day.
- **Verify:** read the file post-edit and confirm no syntax errors.
- **Commit msg:** `feat(airpay_courses): dual-target ModalFactory→core/modal for 5.2 readiness`
- **Completion log:**

### A3 — `local/airpay_request/amd/src/request_button.js` — same dual-target as A2

- **Status:** `[PENDING]`
- **File:** `moodle-enhancement/local/airpay_request/amd/src/request_button.js`
- **Same pattern as A2.**
- **Commit msg:** `feat(airpay_request): dual-target ModalFactory→core/modal — request_button.js`
- **Completion log:**

### A4 — `local/airpay_request/amd/src/decide.js` — same dual-target as A2

- **Status:** `[PENDING]`
- **File:** `moodle-enhancement/local/airpay_request/amd/src/decide.js`
- **Same pattern as A2.**
- **Commit msg:** `feat(airpay_request): dual-target ModalFactory→core/modal — decide.js`
- **Completion log:**

### A5 — `local/airpay_cart/amd/src/admin_orders.js` — same dual-target as A2

- **Status:** `[PENDING]`
- **File:** `moodle-enhancement/local/airpay_cart/amd/src/admin_orders.js`
- **Same pattern as A2.**
- **Commit msg:** `feat(airpay_cart): dual-target ModalFactory→core/modal — admin_orders.js`
- **Completion log:**

### A6 — 3 AMD shims cleanup (`page_title.js`, `deprecated.js`, `announcement.js`)

- **Status:** `[PENDING]`
- **Why (per `docs/5.2-merge/PHASE-B.3.f-amd-shim-cleanup-plan.md`):**
  - `page_title.js` — borrowed for 5.2 readiness, zero callsites in airpayux. Delete.
  - `deprecated.js` — borrowed for 5.2 readiness, zero callsites in airpayux. Delete.
  - `announcement.js` — review for actual usage; if zero callsites, delete; if used, keep + add dual-target comment.
- **Files to inspect:** under `moodle-enhancement/theme/airpayux/amd/src/`.
- **Verify before delete:** `grep -r 'theme_airpayux/page_title\|theme_airpayux/deprecated\|theme_airpayux/announcement' moodle-enhancement/` — if zero hits, safe to delete.
- **Commit msg:** `chore(theme_airpayux): drop 3 AMD shims with zero callsites (B.3.f cleanup)`
- **Completion log:**

### A7 — `course.mustache:237` tertiary-nav swap (dual-target)

- **Status:** `[PENDING]`
- **Why (per `docs/5.2-merge/PHASE-B.3.c-top-templates-rebase.md`):** `core/url_select` partial removed in 5.2; replaced by `core/select_menu`. Need a dual-target Mustache pattern.
- **File:** `moodle-enhancement/theme/airpayux/templates/course.mustache` line ~237 (search for `{{> core/url_select }}` or similar).
- **Change pattern:**
  ```mustache
  {{#is_select_menu_context}}
      {{> core/select_menu }}
  {{/is_select_menu_context}}
  {{^is_select_menu_context}}
      {{> core/url_select }}
  {{/is_select_menu_context}}
  ```
  The `is_select_menu_context` flag is set in `theme_airpayux/classes/output/core_renderer.php` (added in Phase B.3.a). If not present, add: `'is_select_menu_context' => class_exists('\\core\\output\\select_menu')`.
- **Verify:** `php -l` on touched PHP, mustache-lint via static read.
- **Commit msg:** `feat(theme_airpayux): dual-target course.mustache tertiary-nav for 5.2`
- **Completion log:**

### A8 — `drawer/drawers/secure` mustache audit + selective backport

- **Status:** `[PENDING]`
- **Why (per B.3.c plan):** 5.2 reshaped these layout templates. Audit our overrides; selectively backport only changes that don't break 5.1 rendering.
- **Files:** `moodle-enhancement/theme/airpayux/templates/drawers.mustache`, `drawer.mustache`, `secure.mustache`.
- **Method:** diff our copy vs vanilla Boost 5.2 (under `C:\xampp\htdocs\moodle5.2\public\theme\boost\templates\` inside the container at `/var/www/moodle/public/theme/boost/templates/`). Cherry-pick non-breaking adds. If a template has nothing 5.2-specific to backport, document that and skip.
- **Commit msg:** `feat(theme_airpayux): backport non-breaking 5.2 drawer/secure template changes`
- **Completion log:**

### B1 — PHPUnit tests for `paygw_airpay`

- **Status:** `[PENDING]`
- **Why:** Plugin was just tracked in repo for the first time (commit `275f45c84`); zero test coverage. Risk: untested 31-file payment plugin.
- **Files to test:**
  - `moodle-enhancement/payment/gateway/airpay/classes/gateway.php` — gateway capabilities, currency support, fee calculation if any.
  - `moodle-enhancement/payment/gateway/airpay/classes/airpay_helper.php` — checksum / signing logic.
  - `moodle-enhancement/payment/gateway/airpay/classes/checksum.php` — checksum verification.
  - `moodle-enhancement/payment/gateway/airpay/classes/privacy/provider.php` — privacy metadata.
- **Output:** `moodle-enhancement/payment/gateway/airpay/tests/` directory with one PHPUnit class per target.
- **Conventions:** extend `\advanced_testcase`; `$this->resetAfterTest()`; mock external API endpoints.
- **Verify:** no actual execution required (Docker substrate slow); commit + leave run for CI.
- **Commit msg:** `test(paygw_airpay): initial PHPUnit coverage — gateway, helper, checksum, privacy provider`
- **Completion log:**

### B2 — PHPUnit tests for `quizaccess_airpay_proctoring`

- **Status:** `[PENDING]` (depends on A1)
- **Why:** Plugin shipped at v2026051300 with new relational schema + migration. Needs test for: rule loading, migration idempotence (A1's fix), table-creation-on-upgrade path.
- **Files to test:**
  - `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/rule.php` — rule loading + form validation.
  - `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/db/upgrade.php` — covered by integration test that simulates v2026051120 → v2026052401 upgrade path.
- **Output:** `moodle-enhancement/mod/quiz/accessrule/airpay_proctoring/tests/` directory.
- **Commit msg:** `test(quizaccess_airpay_proctoring): rule + migration coverage`
- **Completion log:**

### C1 — Goal C user guide: Site Admin

- **Status:** `[PENDING]`
- **Why:** Goal C documentation outline approved; needs 6 persona-specific guides.
- **Output:** `moodle-enhancement/docs/user-guides/site-admin.md`
- **Persona:** Site Admin (full superuser, all tenants).
- **Sections (mandatory):**
  1. Day-1 setup checklist (post-deploy)
  2. Tenant management (Switchboard + customer-scope flags)
  3. Plugin management (Sentientia plugins, BizLMS plugins)
  4. User import (HRMS sync cron, manual CSV path)
  5. SCORM upload + validation gates
  6. Audit log + reporting
  7. PWA + push notifications admin
  8. WhatsApp / SMS notifications admin
  9. Theme customization (per-customer branding via `local_airpay_core`)
  10. Emergency procedures (rollback, password reset CLI, cache purge)
- **Reference:** `docs/visual-evidence/2026-05-2*/` for surface coverage.
- **Commit msg:** `docs(user-guides): Site Admin guide`
- **Completion log:**

### C2 — Goal C user guide: Tenant Admin

- **Status:** `[PENDING]`
- **Output:** `moodle-enhancement/docs/user-guides/tenant-admin.md`
- **Persona:** Tenant Admin (BizLMS costcenter admin — scoped to Airpay id=1, Public id=77, ZEEA id=177).
- **Sections:**
  1. Scope (what they can/cannot see vs Site Admin)
  2. User management within their tenant
  3. Course/program/path creation + assignment
  4. Reporting dashboards (tenant-scoped)
  5. Compliance status overview
  6. Welcome-email templates (per tenant)
  7. WhatsApp opt-in management
  8. Tenant-specific branding (read-only — Site Admin manages)
- **Commit msg:** `docs(user-guides): Tenant Admin guide`
- **Completion log:**

### C3 — Goal C user guide: Course Author

- **Status:** `[PENDING]`
- **Output:** `moodle-enhancement/docs/user-guides/course-author.md`
- **Persona:** Course Author (can create + edit + publish their assigned courses across one or more tenants).
- **Sections:**
  1. Creating a course
  2. Adding activities (SCORM, quiz, feedback, evaluation)
  3. Target audiences + cohort filtering
  4. Setting completion criteria
  5. Deadline reminders (uses airpay_courses cron)
  6. Grade reports (overview + per-course)
  7. Publishing + tenant visibility
  8. Hindi content readiness checklist
- **Commit msg:** `docs(user-guides): Course Author guide`
- **Completion log:**

### C4 — Goal C user guide: Manager

- **Status:** `[PENDING]`
- **Output:** `moodle-enhancement/docs/user-guides/manager.md`
- **Persona:** Manager (line manager — sees team progress, approves requests, escalations).
- **Sections:**
  1. My Team dashboard
  2. Approval queue (airpay_request decisions)
  3. Compliance dashboard (mandatory training status per direct report)
  4. Overdue escalation emails (system-generated; how to action)
  5. Skill ratings + reviews (airpay_skills self-rate workflow)
  6. Reporting + CSV export
- **Commit msg:** `docs(user-guides): Manager guide`
- **Completion log:**

### C5 — Goal C user guide: Learner

- **Status:** `[PENDING]`
- **Output:** `moodle-enhancement/docs/user-guides/learner.md`
- **Persona:** Learner (most users — does courses, takes quizzes, earns badges).
- **Sections:**
  1. First login (PWA install hint, language selector)
  2. Catalogue + My Courses
  3. Taking a course (SCORM, quiz, evaluation)
  4. Badges + certificates
  5. Skill self-rating
  6. Calendar + deadlines
  7. Messaging (mod_message)
  8. Notifications preferences (email/push/WhatsApp)
  9. Mobile experience (590px responsive notes)
  10. Hindi UI toggle
- **Commit msg:** `docs(user-guides): Learner guide`
- **Completion log:**

### C6 — Goal C user guide: External Public Learner

- **Status:** `[PENDING]`
- **Output:** `moodle-enhancement/docs/user-guides/public-learner.md`
- **Persona:** External Public Learner (Public tenant id=77 — self-registered users on airpay.academy/learning, no Airpay employment).
- **Sections:**
  1. Self-registration (signup.php flow)
  2. Privacy policy + T&C consent
  3. Catalogue browsing (visible Public courses only)
  4. Paid course purchase flow (airpay_cart + paygw_airpay)
  5. Course access after purchase
  6. Certificate download
  7. Limitations (no employee directory, no skill tree, no internal compliance)
- **Commit msg:** `docs(user-guides): External Public Learner guide`
- **Completion log:**

---

## End-of-night sweep

When all 16 items are `[COMPLETED]` or `[BLOCKED]`:

1. Update `moodle-enhancement/PROJECT-STATE.md` with a "Night-run 2026-05-24" section listing shipped commits.
2. Append `=== NIGHT-RUN DONE ===` at the bottom of THIS file.
3. Don't disable the scheduled task — it will idle on subsequent fires (the prompt detects the `DONE` marker and exits cleanly).

---

## Scheduled-task prompt (for reference — the actual prompt is in `.claude/scheduled-tasks/airpay-night-run-resume/SKILL.md`)

> You are resuming the Airpay/Sentientia autonomous night-run. cwd: `D:\Claude Local\airpay-ld-os\`.
>
> 1. Read `D:\Claude Local\airpay-ld-os\NIGHT-RUN-PLAYBOOK.md`.
> 2. If the bottom of that file contains the line `=== NIGHT-RUN DONE ===`, exit immediately — work is complete.
> 3. Otherwise find the first item with `[PENDING]`, flip it to `[IN_PROGRESS]`, execute per spec, flip to `[COMPLETED]` + commit hash, push.
> 4. Hard rules (verbatim from `D:\Claude Local\airpay-ld-os\CLAUDE.md`): never deploy live, never POST to paid APIs, never skip pre-commit hooks, push after every commit, co-author every commit.
> 5. Time budget: 60 minutes per fire. If item exceeds budget, save partial work with `[PAUSED-AT]` marker and stop.
> 6. On any non-recoverable error, flip item to `[BLOCKED]` with the error message and move to next pending item.

---

## Item status legend

- `[PENDING]` — not started
- `[IN_PROGRESS]` — claimed by an active session (only one at a time)
- `[COMPLETED]` — shipped + pushed (record commit hash)
- `[BLOCKED]` — can't proceed (record reason)
- `[PAUSED-AT]` — partial progress saved (record where to resume)

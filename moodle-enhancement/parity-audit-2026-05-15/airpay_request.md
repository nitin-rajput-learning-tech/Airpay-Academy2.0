# airpay_request vs BizLMS local_request — Parity Audit
Generated: 2026-05-15 | Auditor: feature-parity cluster 4 | Stakes: HIGH

## Source paths + size

| | BizLMS `local_request` | Airpay `local_airpay_request` |
|---|---|---|
| Path | `C:\xampp\htdocs\moodle5\bizlms_disabled\request\` | `C:\xampp\htdocs\moodle5\public\local\airpay_request\` |
| PHP files | 35 | 16 |
| Total LOC | **6,630** | **1,607** |
| Tables | `local_request_records`, `local_request_comments`, `local_request_config`, `local_request_formfields` (4) | `local_airpay_request` (1) |
| Capabilities | `addrecord`, `viewrecord`, `approverecord`, `denyrecord`, `deleterecord`, `addcomment`, `viewconfig` (7, all `CONTEXT_COURSECAT`) | `request`, `approve`, `viewall`, `overrideroute` (4, all `CONTEXT_SYSTEM`) |
| Events | `request_added`, `request_approved`, `request_rejected`, `request_commented`, `request_created`, `request_deleted` (6) | NONE — only message-provider messages |
| Cron tasks | NONE (synchronous) | `escalate_overdue` (15 min) |
| Message providers | `request_add`, `request_approve`, `request_deny` (3, with popup+email+airnotifier) | `request_submitted`, `request_pending`, `request_decided`, `request_escalated` (4, popup+email) |
| Request-button on course/classroom/program/cert/LP pages | yes (5 component types) | yes (1 — course only) |
| Supported request targets | **elearning, classroom, program, certification, learningplan** | **course only** |

---

## Architecture summary

**BizLMS model — generic CRUD wrapper for ANY component:**
1. Any module type (course/classroom/program/certification/learningplan) calls `local_request\api\requestapi::create($component, $componentid)`.
2. Inserts `local_request_records` row with `compname` ('elearning'|'classroom'|'program'|'certification'|'learningplan'), `componentid`, status='PENDING'.
3. `get_users_by_capability($context, 'local/request:approverecord')` returns approvers at the appropriate context (CONTEXT_COURSECAT for course-category-scoped delegation; `local_classroom\accesslib::get_module_context($componentid)` for classroom-specific approvers, etc.).
4. Per-tenant + per-country filtering: only managers whose `open_path[1]==org` AND `open_path[2]==country` receive the email (line 122-126 of requestapi.php).
5. On approve: switch on `$component` → calls **the appropriate component-specific self-enrol method** (e.g. `(new classroom)->classroom_self_enrolment(...)`, `(new program)->program_self_enrolment(...)`, etc.).
6. Sends `request_add` notification email (via `local_notifications`) on submit; `request_approve` on approve; `request_deny` on reject.
7. Comments on requests stored in `local_request_comments` (admin / approver can attach a note to the discussion thread, not just a one-shot reject note).
8. Admin bulk-approve / bulk-deny pages (UI was: pick N pending rows → approve in batch).
9. Admin can configure form text labels via `local_request_config` (column titles, descriptions — admin-editable form-field labels per tenant).
10. Audit trail via Moodle Events API: every action emits an event row to `mdl_logstore_standard_log` with `local_request\event\request_{created,approved,rejected,commented,deleted}`.
11. Webservice `local_request_view_availiable_request` (datatable + paging).
12. Course-category-scoped delegation: a manager of one course category can approve only that category's requests.

**Airpay model — clean single-purpose course enrolment request:**
1. Only **course enrolment** is supported. No classroom, program, certification, or LP requests.
2. `request_manager::submit($userid, $courseid, $reason)`:
   - Reason must be ≥20 chars (anti-spam).
   - Resolves tenant snapshot via `open_path` split.
   - Routes approver via 3-tier fallback: (a) `open_managerid` if set, (b) course custom-field `course_owner_userid` if set, (c) admin `default_approver` setting.
   - Sets `timedue` = now + `sla_hours` (default 48).
   - Inserts row + sends `request_submitted` to requester + `request_pending` to approver.
3. `request_manager::decide($id, $deciderid, 'approved'|'rejected', $note)`:
   - Strict: only the assigned approver, a site admin, or holder of `local/airpay_request:overrideroute` AND tenant-equal can decide.
   - Reject requires non-empty note.
   - Wraps in `start_delegated_transaction` — on approve, enrols via manual enrol plugin.
4. `request_manager::cancel($id, $userid)` — requester withdraws a still-pending request.
5. `request_manager::escalate_overdue()` (15-min cron) — if pending past `timedue`, switch route → `admin` and re-arm `timedue`.
6. `request_manager::auto_expire()` (15-min cron) — pending older than `auto_expire_days` becomes `expired`.
7. Three UI pages: `index.php` (my requests), `approvals.php` (pending approvals for current user), `all.php` (admin tenant-wide).
8. Datatable-driven via 5 externals: `submit_request`, `decide`, `cancel_request`, `list_mine`, `list_pending`, `list_all`.

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| **SUPPORTED REQUEST TARGETS** | | | | | |
| 1 | Course (e-learning) enrolment request | yes — `compname='elearning'` | yes — only this | **PARITY** | — |
| 2 | Classroom (ILT) enrolment request | yes — `compname='classroom'` (self-enrol or waitlist) | NO | **LOST** | **P0** |
| 3 | Program enrolment request | yes — `compname='program'` | NO | **LOST** | **P0** |
| 4 | Certification enrolment request | yes — `compname='certification'` | NO | **LOST** | **P0** |
| 5 | Learning-plan enrolment request | yes — `compname='learningplan'` | NO | **LOST** | **P1** (LP itself is being replaced by airpay_learningpath) |
| **WORKFLOW** | | | | | |
| 6 | Manager-hierarchy approval routing | yes — `get_users_by_capability` returns ALL category managers; per-country filter via `open_path[1..2]` | yes — single approver via 3-tier fallback (open_managerid → course_owner → admin) | partial — Airpay is single-user not capability-based | **P1** |
| 7 | Multi-approver pool (any capable user can claim) | yes — anyone with `local/request:approverecord` in scope can approve | NO — exactly one assigned approver | **LOST** | **P1** |
| 8 | Course-category-scoped delegation | yes — capability at `CONTEXT_COURSECAT` lets you grant approval rights to L&D admins per category | NO — capabilities all `CONTEXT_SYSTEM` | **LOST** | **P1** |
| 9 | Per-tenant + per-country approver filtering | yes — line 122-126 filters approvers by org+country segments of open_path | partial — costcenterid snapshot stored but `notifier::request_pending` doesn't filter by tenant | weaker | P1 |
| 10 | Auto-enrolment on approve | yes — calls each component's self-enrol method | yes — manual enrol via `enrol_get_plugin('manual')` | **PARITY** (but for course only) | — |
| 11 | Auto-unenrol on retroactive reject | yes — `unenroll_to_component()` runs if status was previously APPROVED | NO — once approved, reject is impossible (state-machine rejects non-pending) | **LOST** | P2 (rare) |
| 12 | SLA / deadline / escalation | NO | yes — `timedue`, `escalate_overdue` cron, `timeescalated` flag | **NEW** | — |
| 13 | Auto-expire stale pending requests | NO | yes — `auto_expire` cron | **NEW** | — |
| 14 | Requester can cancel own pending | NO (only delete capability) | yes — `cancel_request` external | **NEW** | — |
| 15 | Override-route capability (admin steals decision from absent approver) | partial — siteadmin always | yes — `local/airpay_request:overrideroute` + tenant equality enforced | **BETTER** | — |
| **COMMENTS / DISCUSSION** | | | | | |
| 16 | Comment thread on a request (multi-message back-and-forth) | yes — `local_request_comments` table | NO — only single `decision_note` from approver | **LOST** | **P1** |
| 17 | Requester can attach extra info after initial submit | yes (via comments) | NO | **LOST** | **P1** |
| 18 | Approver can ask clarifying question before deciding | yes (via comments) | NO | **LOST** | **P1** |
| **BULK OPERATIONS** | | | | | |
| 19 | Bulk approve N pending requests | yes — `admin/bulk_approve.php` | NO | **LOST** | **P1** |
| 20 | Bulk deny N pending requests | yes — `admin/bulk_deny.php` | NO | **LOST** | **P1** |
| **AUDIT / EVENTS** | | | | | |
| 21 | Moodle Events API integration | yes — 6 event classes (`request_added/created/approved/rejected/commented/deleted`) | NO — events not emitted | **LOST** | **P1** for compliance audit logging |
| 22 | `logstore_standard_log` rows for each action | yes (automatic via event trigger) | NO | **LOST** | **P1** for SIEM/compliance |
| 23 | Datatable: filter by status (pending/approved/rejected) | yes — `requeststatus` filter form | yes — column-level via tabulator | **PARITY** | — |
| 24 | Datatable: filter by component (elearning/classroom/program) | yes — `request` filter form | N/A (only course) | n/a | — |
| **NOTIFICATIONS** | | | | | |
| 25 | Email on submit to approvers | yes — `request_add` template, BizLMS tokens | yes — `request_pending` event | **PARITY** | — |
| 26 | Email on approve to requester | yes — `request_approve` template | yes — `request_decided` event | **PARITY** | — |
| 27 | Email on reject to requester | yes — `request_deny` template | yes — `request_decided` event | **PARITY** | — |
| 28 | Email on escalation | NO | yes — `request_escalated` event | **NEW** | — |
| 29 | Manager CC on requester emails | yes — `notification.php` line 93-105 reads `open_supervisorid`, sends ALSO to supervisor with `adminbody` variant | NO | **LOST** | **P1** |
| 30 | Per-tenant template overrides (different wording per costcenter) | yes — via `local_notifications` machinery | NO — hardcoded `notifier::request_pending` body | **LOST** | **P1** |
| 31 | Mobile push via `airnotifier` channel | yes — declared in messages.php | NO — only popup+email | partial | P2 |
| **ADMINISTRATIVE UX** | | | | | |
| 32 | Admin can configure form field labels per tenant | yes — `local_request_config` table holds `page1_fieldname1`, `page1_fielddesc1`, etc. with tenant scoping | NO — labels are hardcoded | **LOST** | P2 |
| 33 | Admin can configure form field count + visibility (page1_field3status, etc.) | yes | NO | **LOST** | P2 |
| 34 | Admin settings page | NO — admin via config row inserts | yes — `settings.php` with `sla_hours`, `default_approver`, `auto_expire_days` | **NEW** | — |
| 35 | Settings expose retention / SLA via admin UI | NO | yes | **NEW** | — |
| **DATA / TENANT ISOLATION** | | | | | |
| 36 | Tenant scoping on view | implicit via category context | explicit `costcenterid` column + filter in `list_all` external | **BETTER** | — |
| 37 | Tenant snapshot at request time (immune to user moving tenants later) | NO | yes — `costcenterid` snapshot at submit | **NEW** | — |
| 38 | Status enum strict | weak — strings 'PENDING'/'APPROVED'/'REJECTED' uppercase | strict lowercase, default 'pending' DB-level | minor | — |
| **INTEGRATION** | | | | | |
| 39 | Request button on course catalog page | yes — `get_requestbutton()` returns HTML | yes — `amd/src/request_button.js` mounts on course card | **PARITY** | — |
| 40 | Request button on classroom view page | yes | NO | **LOST** | **P0** for ILT self-service |
| 41 | Request button on program view page | yes | NO | **LOST** | **P0** for programs |
| 42 | Request button on certification view page | yes | NO | **LOST** | **P0** for compliance certs |
| **TESTING / RELIABILITY** | | | | | |
| 43 | Race-safe decide (transaction) | weak | strong — `start_delegated_transaction` in `decide()` line 160 | **BETTER** | — |
| 44 | Unit tests | NO | NO formal phpunit (only `cli/smoke_request.php`) | partial | P2 |
| 45 | Privacy provider | NO | yes — `classes/privacy/provider.php` | **NEW** | — |
| 46 | i18n | en, es, plus a stray `de/block_cmanager.php` | en only | **less coverage** | P2 |
| **WEBSERVICES** | | | | | |
| 47 | Mobile-service support | yes — `view_availiable_request` listed for MOODLE_OFFICIAL_MOBILE_SERVICE | partial — externals registered but not flagged for mobile | partial | P2 |

---

## User flows (multi-step tasks)

### Flow 1: Learner self-requests a course they don't have access to
**BizLMS** (works for elearning/classroom/program/cert/LP):
1. User browses catalog or course page; sees "Request enrolment" button (`get_requestbutton`).
2. Clicks → modal asks for justification (optional).
3. `requestapi::create('elearning', $courseid)` runs → row in `local_request_records` (PENDING) → `request_created` event → notification email to all course-category managers in same tenant+country.
4. Manager opens `/local/request/index.php` → sees pending list with filter, clicks Approve → `requestapi::approve($id)` calls `self_enrolment` for the matching component → user is enrolled + 'request_approve' email goes back to requester.

**Airpay** (works for courses only):
1. User browses catalog → sees "Request enrolment" button via `amd/src/request_button.js`.
2. Clicks → in-page form → enters reason (must be ≥20 chars).
3. `submit_request` external → `request_manager::submit` → row in `local_airpay_request` (pending) → `request_pending` message to approver, `request_submitted` ack to user.
4. Approver opens `/local/airpay_request/approvals.php` → sees their pending list → clicks Approve → `decide` external → user enrolled via manual enrol plugin → `request_decided` to user.

For COURSES ONLY this is **parity + better (race-safe, SLA tracked).** For ANY OTHER COMPONENT (ILT/program/cert/LP) **this flow is gone**, the button doesn't render, the table doesn't accept a `compname`, no enrol logic exists.

### Flow 2: Learner needs to attend a Compliance ILT (classroom)
**BizLMS** (works):
1. Visits `/local/classroom/view.php?cid=X` → sees Request button.
2. Submits → `requestapi::create('classroom', $classroomid)`.
3. Approver gets email; approves.
4. `classroom::classroom_self_enrolment` runs → user is enrolled into classroom roster OR added to waitlist.
5. If waitlist, learner sees waitlist position in confirmation.

**Airpay** (BROKEN — no equivalent):
1. Classroom view page renders without request button.
2. User has no self-service path.
3. **Only fix**: L&D admin must manually enrol them via airpay_classroom roster page.

### Flow 3: Approver asks "What's your business case?" before deciding
**BizLMS** (works):
1. Approver sees the request row.
2. Clicks "Add comment" → posts a question to the requester via `admin/comment.php`.
3. Email goes via `request_commented` event.
4. Requester logs in, replies via the same comment thread.
5. Approver re-reads thread, then approves.

**Airpay** (BROKEN):
1. Approver sees only the request + the original reason. No reply mechanism.
2. Approver must reject (with note) OR approve "to keep the workflow moving". **No conversation possible.**

### Flow 4: Manager bulk-clears 30 pending Q4 onboarding requests
**BizLMS** (works):
1. Open admin pending list → tick 30 checkboxes → "Bulk approve" → `bulk_approve.php` iterates each, enrols user, sends email.

**Airpay** (BROKEN):
1. Manager must click each one individually. With 30 requests this is 30 modal clicks.

### Flow 5: SLA breach — Manager on leave, request stuck for 3 days
**BizLMS** (handles weakly):
1. Email reminders only if `local_notifications` rule `request_add` has a reminder configured. Otherwise: request sits forever.

**Airpay** (handles strongly):
1. After 48h (default `sla_hours`), `escalate_overdue` cron fires.
2. Route flips manager → admin; `timeescalated` set; new `request_escalated` notification to admin.
3. Admin can act, OR after 30 days `auto_expire` cron marks status='expired' and requester is notified to re-submit.

This is a **NEW capability** with no BizLMS equivalent — superior in this single dimension.

### Flow 6: Compliance officer pulls audit log for SOX evidence
**BizLMS** (works):
1. Open Moodle Reports → Logs → filter by component `local_request` → see every request_created, request_approved, request_rejected event with user, IP, timestamp.

**Airpay** (BROKEN):
1. `mdl_logstore_standard_log` has no rows from airpay_request — no event observers are wired.
2. Auditor falls back to querying `local_airpay_request` table directly via DB. Not SIEM-compatible.

---

## Severity legend
- **P0** = blocks enterprise use (classroom/program/cert/LP self-service requests are entirely missing)
- **P1** = important workflow degraded (comments, bulk ops, events for audit, manager CC, per-tenant template variants)
- **P2** = polish

---

## Recommended fixes (prioritised)

### P0 — Restore non-course request targets

1. **Add `component` and `componentid` columns to `local_airpay_request`.** Modify `db/install.xml` and add upgrade step:
   ```xml
   <FIELD NAME="component"   TYPE="char" LENGTH="50" NOTNULL="true" DEFAULT="course"/>
   <FIELD NAME="componentid" TYPE="int"  LENGTH="10" NOTNULL="true"/>
   ```
   Keep `courseid` as an alias for back-compat OR migrate to use `componentid` everywhere.
   **Start at:** `db/install.xml:6` (new fields) + `db/upgrade.php:4` (new versioned upgrade).

2. **Polymorphic enrolment dispatcher.** In `classes/request_manager.php:268` (`enrol_user`), switch on `component`:
   ```php
   private static function enrol_target(int $userid, string $component, int $componentid): void {
       match ($component) {
           'course'        => self::enrol_course($userid, $componentid),
           'classroom'     => self::enrol_classroom($userid, $componentid),  // airpay_classroom
           'program'       => self::enrol_program($userid, $componentid),     // airpay_programs
           'learningpath'  => self::enrol_learningpath($userid, $componentid),// airpay_learningpath
           default => throw new \moodle_exception('error_unknowncomponent'),
       };
   }
   ```
   Each branch calls the appropriate airpay_* plugin's roster API.
   **Start at:** new method `classes/request_manager.php:enrol_target()` replacing `enrol_user()`.

3. **Render request button on classroom/program/learningpath view pages.** Add an AMD trigger script load in the templates of:
   - `local/airpay_classroom/templates/classroom_view.mustache` — wire `amd/src/request_button.js` with `data-component="classroom"`.
   - `local/airpay_programs/templates/program_view.mustache` — same with `data-component="program"`.
   - `local/airpay_learningpath/templates/path_view.mustache` — same with `data-component="learningpath"`.

### P1 — Restore audit-grade event emission

4. **Create event classes** mirroring BizLMS:
   - `classes/event/request_submitted.php`
   - `classes/event/request_approved.php`
   - `classes/event/request_rejected.php`
   - `classes/event/request_escalated.php`
   - `classes/event/request_cancelled.php`

   In `request_manager::submit()` after the insert (line 84), fire:
   ```php
   \local_airpay_request\event\request_submitted::create([
       'context'  => \context_system::instance(),
       'objectid' => $rec->id,
       'other'    => ['courseid' => $courseid, 'route' => $route],
   ])->trigger();
   ```
   Apply same pattern in `decide()`, `cancel()`, `escalate_overdue()`. This restores SIEM-compatible audit trail.

### P1 — Restore comment thread

5. **Add `local_airpay_request_comments` table** and CRUD external `add_comment`. New files:
   - `db/install.xml` — table with FKs `requestid`, `userid`, `body`, `timecreated`.
   - `classes/external/add_comment.php` — external (capability: must be requester or assigned approver).
   - `classes/external/list_comments.php` — external returning thread.
   - Wire into `templates/my_requests.mustache` & `templates/pending.mustache` — expand-row to show thread.
   - Fire `request_commented` event + send `request_commented` message to the OTHER party.

### P1 — Restore bulk operations

6. **Add `bulk_decide` external** that accepts `requestids[]` + a single `decision`+`note`, iterates, returns success count + error list.
   **Start at:** `classes/external/bulk_decide.php` (NEW).
   Wire bulk-action UI in `templates/pending.mustache` — top toolbar with "Approve N selected" / "Reject N selected".

### P1 — Restore multi-approver pool

7. **Make approver capability-based, not single-user.** Change `local_airpay_request.approver_userid` semantics: instead of FK to user, store a JSON/CSV of capable user IDs at submit time OR move to capability check at decide time (`require_capability + tenant-equal`). At least allow ANY user with `:approve` cap in the tenant to claim a pending request.
   **Start at:** `request_manager.php:142` — replace `(int) $rec->approver_userid !== $deciderid` with a capability+tenant check.

### P1 — Manager CC on requester emails

8. In `classes/notifier.php:send()` — after primary `message_send`, look up `$user->open_managerid` and CC them when the message is `request_decided`. Mirrors BizLMS `notification.php:93-105`.

### P2 — Polish

9. **Add `airnotifier` to message-provider defaults** in `db/messages.php` for mobile push channel.
10. **Per-tenant override of subject/body** via admin settings (or via `local_airpay_emails` integration).
11. **Withdraw button on /index.php** so requesters can cancel their own pending — UI exists in `cancel_request.php` external but no button in my_requests.mustache template yet.
12. **Add formal phpunit tests** for state-machine transitions (escalate, expire, decide, cancel).

---

## Summary verdict for stakeholder

**Status: REGRESSION for multi-component request workflows; NET BETTER for course-only workflows.**

What Airpay GAINED:
- SLA tracking with timedue + escalate cron + auto-expire
- Tenant snapshot at submit (immune to user moving)
- Race-safe decide via DB transaction
- Settings UI for sla_hours, default_approver, auto_expire_days
- override-route capability with tenant equality enforcement
- Privacy provider

What Airpay LOST (blocks enterprise use today):
- **Cannot request enrolment into classrooms** (the biggest BizLMS request use case — ILT self-service)
- **Cannot request enrolment into programs** (mandatory training paths)
- **Cannot request enrolment into certifications** (compliance audit)
- **Cannot request enrolment into learning plans**
- **No comment thread** — every clarifying conversation must happen out-of-band over chat
- **No bulk approve/deny** — Q1 onboarding for 200 hires becomes 200 clicks
- **No Moodle Events emitted** — `logstore_standard_log` empty for requests, SIEM/SOX evidence broken
- **No course-category-scoped delegation** — every approver sees every tenant's pending list (only blocked by tenant equality at decide time, not list time)

Before production, fixes 1-3 are mandatory (restore non-course targets). Fixes 4-7 are required within first sprint (audit + comments + bulk + multi-approver). Without them, the request feature is a course-only convenience, not the multi-modality self-service workflow BizLMS provided.

# State Card — `local_sentientia_request`

**Component:** `local_sentientia_request` (was `local_airpay_request` until ADR-022/025; older sections below still use the old name)
**Version:** `2026093001` / `1.5.0`  (ADR-032 importer; the 2026-10-07 owner decisions below changed code only, so the version did not move)
**Maturity:** `MATURITY_STABLE`
**Status:** Learner-driven course request workflow (Sentientia is not live yet; the live system is BizLMS).
**Last refreshed:** 2026-10-07 (COMMS-R owner decisions)

---

## Mission

Learner-driven course request workflow — a learner sees an interesting
course (or category) they'd like to take, files a request; the request
is routed to their line manager (and optionally a course owner) for
approval. Approved requests trigger an enrolment.

Distinct from `local_airpay_courses_requests` (which is a
tenant-manager-to-Airpay-admin pull workflow for the cross-tenant
sharing feature in Sprint D). This plugin is the learner-to-manager
direction.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_airpay_request` | Request records (learner, course, requested-at, status, approver, decision_at, rationale) |

## Capabilities (4)

`local/airpay_request:` `request` (learner), `approve` (manager),
`viewall` (admin), `overrideroute` (admin — re-route to alternate
approver).

## Feature flags

| Flag | Default | What it does |
|---|---|---|
| `sentientia.request.imported_history` | OFF | Shows the requests imported from BizLMS (ADR-032) in My requests, Pending approvals and All requests, counts them in the approver nav badge and in the duplicate-request guard. OFF (registered in `db/feature_flags.php`, 2026-09-30): the lists look as they did before the import. The import never flips it; turning it on for Airpay is Nitin's call after the visual evidence. |

## Key files

```
local/airpay_request/
├── version.php                                   2026052201 / 1.2.2
├── README.md
├── lib.php
├── index.php                                      Learner: file a request
├── approvals.php                                  Approver inbox
├── all.php                                        Admin: all requests
├── cli/                                            Operations
├── classes/
│   ├── request_manager.php                       Request CRUD + state machine
│   ├── notifier.php                              Notification dispatcher
│   ├── event/                                     Audit events
│   ├── external/                                  WS endpoints
│   ├── task/                                      Scheduled escalation
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                1 table
│   ├── upgrade.php
│   └── access.php                                 4 capabilities
├── amd/
├── lang/
│   ├── en/local_airpay_request.php
│   └── hi/local_airpay_request.php
└── tests/                                         1 PHPUnit class / 5 methods
```

## Tests

1 PHPUnit class, 5 methods. Smoke on the request lifecycle.

## Open items

- [ ] Auto-escalate after N days without approver action
- [ ] Behat coverage of the request → approval flow
- [ ] Per-tenant approval matrix (today: direct manager only)
- [ ] WhatsApp approval inbox (Phase C.1 integration with
      `local_airpay_whatsapp`)
- [ ] Inline budget impact (link to `local_airpay_manager` allocations)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031 tenant scope (1.4.0, 2026092500)

Sweep hit 54 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25). `:viewall` keeps its manager default and
the `db/install.php` grant to 'administrator': all.php is meant to be the tenant-wide admin view.
`list_all` started from 1=1 and took the tenant from the client's filters JSON, so every holder
saw every tenant's requesters, emails, reasons and decision notes. It now starts from
`tenant::sql_filter('r')` (the caller's tenant; 1=0 when it does not resolve, so the
costcenterid 0 rows are not "every tenant"), and only a cross-tenant caller may pick a tenant with
`filters.tenant`. Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`).

## 2026-09-25 - ADR-031 follow-up (still 1.4.0, 2026092500)

Reviewer items on wave 1 (branch `claude/adr031-comms-ff`).

- `request_manager::decide()` had a fail-open that the sweep missed. On the `:overrideroute` path
  it called `tenant::require_access((int) $rec->costcenterid, $deciderid)`, and
  `viewer_can_access()` compares tenant roots with `===`. A decider whose `open_path` does not
  resolve has root 0, and 0 equals a costcenterid-0 request. `db/install.php` grants
  `:overrideroute` to the 'administrator' tenant-admin role, so such a holder could approve or
  reject every tenant-less request and enrol its requester. `decide()` now refuses a
  costcenterid <= 0 request unless `tenant::is_cross_tenant($deciderid)`. The assigned-approver
  path is unchanged. The same `0 === 0` pattern still lives in the platform helper
  `viewer_can_access()` for any other caller. That fix belongs to local_sentientia_platform.
- `list_all` clamps `perpage` to `1..list_all::MAX_PERPAGE` (100) and `page` to `>= 0` (hit 54
  side item).

Tests in `tests/tenant_scope_test.php`:
- A no-tenant router cannot decide a tenant-less request, or any other.
- A /1 router decides only /1 requests.
- A site admin still decides tenant-less requests.
- The page-size clamp holds.

## 2026-09-25 - ADR-031 follow-up 2 (still 1.4.0, 2026092500)

Reviewer item on `claude/adr031-comms-ff`, fixed on branch `claude/adr031-comms-ff2`. This is a
cosmetic, test-only change: no code, schema or capability changed, so there is no version bump.
In `tests/tenant_scope_test.php`, the class docblock paragraph about `decide()` sat after the
`@package` / `@category` tags, where PHPDoc reads it as part of the tag block. It now comes before
the tags. Both trees were changed identically. The test class is still `@group tenant_isolation`.

## 2026-09-25 - ADR-031 follow-up 3: the requested item is tenant-checked (still 1.4.0, 2026092500)

Reviewer item (P1, CONFIRMED) on the merged wave 1, fixed on branch `claude/adr031-comms3-ff`.
No schema, capability or service change, so the wave-1 version stands. Both trees identical.

The request flow never compared the COURSE or PATH with anyone's tenant. `submit()` took any
course id through `local_sentientia_request_submit` (`:request` goes to every authenticated user),
and `decide()` only checked the override-route decider against `costcenterid` (the requester's own
tenant). A /77 tenant admin (the 'administrator' role holds `:request`, `:approve` and
`:overrideroute`) could request a /1 course and approve it themselves; an in-tenant supervisor, as
the assigned approver, could approve a report into another tenant's course with no check at all.

- `submit()`: `require_course_requestable()` runs before `context_course::instance()`. A requester
  with no tenant (`scope_path()` null) is refused everything. A scoped requester needs a visible
  course in their tenant's tree, shared to their tenant, or a legacy course with no open_path
  (`course_manager::course_in_enrol_scope()`, with an inline tree-or-legacy fallback when
  local_sentientia_courses is absent). A missing id gets the same `error_outoftenant`.
- `submit_path()`: fetches `open_path` and calls `path_manager::assert_path_in_scope($path, $userid)`
  before the status check. A missing path is `error_outoftenant` (it was a dml exception), so a
  foreign path no longer reveals that it exists or is archived.
- `decide()`: for every decider who is not cross-tenant, the assigned approver included,
  `tenant::require_same_tenant_user($rec->userid, $deciderid)`; on approval,
  `require_item_requestable()` re-runs the course check (or `assert_path_in_scope`) against the
  REQUESTER. Both run before the status row changes, so a refusal leaves the request pending. A
  rejection skips the item check, so an out-of-scope request can still be turned down. Site admins
  and `:crosstenant` holders are not scoped.
- `cli/seed_qa_pending_request.php` and `cli/smoke_request.php` now pick a course inside the test
  user's tenant (`path_descendant_filter`, legacy rows allowed), since `submit()` refuses others.

Tests (`@group tenant_isolation`, `tests/tenant_scope_test.php`): a /77 learner is refused a /1
course, the /770 prefix trap, a hidden own-tenant course and a missing id, and nothing is inserted or
notified; own-tenant, legacy and shared courses still go through; a no-tenant requester requests
nothing; `submit_path` refuses /1, pathless and missing paths (an archived foreign one included);
the UAT repro (a /77 router approving their own /1 request) is refused and not enrolled; an assigned
supervisor cannot approve a report into a /1 course or path but can reject it; a /1 approver
decides nothing for a /77 learner; in-tenant submit -> supervisor approve -> enrolled still works,
and the site admin still approves across tenants. Updated: the `request()` helper gives its
requester the tenant the row claims (the scoped decider now checks the requester), and
`path_request_test` gives the duplicate and inactive cases an in-tenant requester and asserts the
exact error code.

**Behaviour to note (needs Nitin's awareness, not a code change):** a request routed to an approver
outside the requester's tenant can now only be decided by a cross-tenant user. That covers the
'courseowner' route when the owner of a /1 course shared to /77 decides a /77 learner's request, and
a `default_approver` set to a tenant-scoped user. Courseowner rows escalate to the default approver
after the SLA; 'admin' rows do not escalate. Keep `default_approver` a site admin or `:crosstenant`
holder (the default, user 2, is).


## 2026-09-30 - ADR-032 BizLMS import, request feature (1.5.0, 2026093001)

The request importer (mapping doc `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md` section 19), built on branch
`claude/bizlms-import-request`. `depends()` is classroom, program, learningplan, exactly as the map's run-order
table says. Atomic: no. Both trees.

**Importer** (`db/bizlms_import.php`, `classes/bizlms/`): three MAP load steps into the one target, no recompute step.

| Step | Source | Result |
|---|---|---|
| `request.records` | `local_request_records` | one request per row (duplicates stay separate). compname -> item_type, componentid -> itemid (course id kept; path, classroom and program resolved through their feature's map; certification keeps the legacy id). A decided row: route from decision `request.decided_route` (admin), approver and decider = responder, `timedecided` = respondeddate. A pending course or path row: routed like a new submission (`approver_routing`, full user row) and left pending. A pending classroom, program or certification row: no approver (history only). `costcenterid` = the requester's CURRENT tenant root (0 when none or unregistered). `reason` ''. Times from the source (COALESCE chains as the map says), `timedue` and `timeescalated` NULL. The comment thread of the request is built into `decision_note`. |
| `request.approvals` | `local_learningplan_approval` | a `path` request of its own (approvestatus 0/1/2, decider = approvedby else usermodified, `reject_msg` = note), or FOLDED (`dup_of_request`) into the latest imported `local_request_records` row for the same (user, plan). |
| `request.comments` | `local_request_comments` | each comment FOLDED (`folded_into_note`) into its request; orphans skipped (`orphan_request`). The note is built once from the source, so a re-run cannot double it. |

Nothing calls `request_manager`, sends, enrols, triggers an event or sets a deadline; the static scan passes.
Preflight blockers: an unknown `status`, `compname` or `approvestatus`; a non-NULL value in `compcode`, `compkey`,
`req_type`, `req_values`, `c1`, `c2`, `c3`; a row in `block_request_*`, `local_request_formfields`,
`local_request_form_data` or `local_crequest_*`. `local_request_config` is declined. Reasons: `orphan_user`,
`orphan_item`, `orphan_request` (retryable, need the owner), `no_tenant` (owner), `hidden_in_bizlms`,
`dup_of_request`, `folded_into_note`.

**Owner decisions read** (all `accepted` in `docs/cutover/bizlms-import-decisions.json`): `request.pending`
(actionable; readonly and expired are also implemented), `request.pending_classroom_program`, `request.certification`,
`request.decided_route` (admin; legacy implemented), `request.hidden_rows` (show; filter implemented: archives a
deleted item or a deleted or suspended requester), `request.tenant_basis`, `request.pending_approver`,
`request.comments`, `tenant.unresolved.request` (pathless; skip implemented). No cart key is declared.

**Schema:** `local_sentientia_request.legacy_source` CHAR(40) NULL (install.xml + upgrade step 2026093001 through
`db/upgradelib.php`, idempotent). No other column or table. `version.php` requires platform 2026093001.

**Reader and engine fixes** (map section 19, "Code fixes"): 1 item names for every type in the three lists (LEFT JOINs
to the path, classroom and program tables, the latter two only when the table exists; search reaches them), 2 the
"Item" column label through `get_string` (en + hi), 3 `decide()` refuses item types other than course and path and the
inbox shows no buttons for them, 4 `auto_expire()` and `escalate_overdue()` select `legacy_source IS NULL`, 5 the
placeholder reason for an imported row, 6 `list_all` declares `status_badge` and `status_badge_class`, 7 `list_pending`
returns `due_badge`, 8 the inbox sorts rows with no deadline last in either direction, 9 the privacy export carries
`item_type` and `item_id`, 10 the route in words (`route_label`, `route_legacy`). Also: the routing rules moved to
`approver_routing` (request_manager delegates; the import may not call the manager, and routing only reads), and the
duplicate-request guards of `submit()` and `submit_path()` ignore imported rows while the flag is off, so a learner is
not refused for a pending request the lists do not show them.

**Privacy:** `decided_by_userid`, `item_type` and `itemid` added to the provider's metadata; erasing a requester,
approver or decider redacts the `decision_note` of every imported row they are on, because the folded thread names people.

**Tests:** `tests/bizlms_import_test.php` (the importer contract plus item and status mapping, routing, tenant, derived
timestamps, folds, the comment note, every reason and blocker, `request.pending` / `decided_route` / `hidden_rows` /
`tenant.unresolved` variants, `verify()`, cron and `decide()` on imported rows; fixtures are checked-in copies of the
BizLMS `request` and `learningplan` install.xml under `tests/fixtures/bizlms/`, stand-ins for the three dependencies in
`tests/classes/bizlms/dependency_stub.php`), `tests/imported_history_test.php` (flag gating, tenant isolation, item
names, inbox order and badges, cron, `decide()`, duplicate guards, routing, privacy, the upgrade helper). NOT RUN: the
lead re-inits PHPUnit once for all version bumps.

**Open items** (owner, from the map): whether the `imported_history` flag is on for Airpay at cutover (framework
decision, Nitin after the visual evidence); the `reject_msg` of a learning-plan approval that folds into a request row is
not carried (it is usually NULL); certification requests stay unmapped until an owner entity exists (gap G3). Framework
need: the static scan bans `request_manager::` wholesale, so the map's "use `rm::route_approver`" became the shared
`approver_routing` class.

## 2026-10-07 - owner decisions of the comms cluster (code only, version unchanged)

Branch `claude/owner-decisions-x`. Decided under Nitin's delegation of 2026-10-07 ("self review and decide recommended
option") on top of the signed basis "do everything as recommended"; list: `docs/cutover/OWNER-DECISIONS-2026-10-07.md`.
PHPUnit was NOT run (the lead re-inits and runs the group). No flag flipped, nothing copied to XAMPP. No schema, no
capability, no new flag, so no version bump: the request plugin stays `2026093001`.

- **COMMS-R1, `request.pending_stale = history_only`** (new decisions-file key, declared by the importer): a legacy
  PENDING course or path request whose requester is deleted or suspended, or whose item no longer exists, imports as
  `pending`, route `admin`, NO approver, warning `pending_history_only`. The source status stays exact (nothing is
  closed as `expired`: BizLMS never had that status). It sits in nobody's inbox and admins still see it in All requests
  (`request.hidden_rows = show` still applies). Why: 1,452 of 2,187 non-deleted tenant-1 users are suspended on the
  April copy, so a stale pending request mostly belongs to somebody who has left, and Approve would enrol and message
  that account. Same treatment as classroom and program rows. `request_step::pending_plan()` takes the "item gone"
  flag from `records_step` and returns a fourth element, the warning code; `approvals_step` can only be stale through
  its requester (a plan missing from the map is skipped earlier). `verify()` fails
  `pending_rows_with_an_approver_whose_requester_or_item_is_gone:N` (a course that is not there, a path with no entry in
  the learning-plan map, a requester deleted or suspended). April: every request table is empty, so no output changes.
  `request.pending = actionable` still routes the rest.
- **COMMS-R2, itemid 0.** A request whose path, classroom or program is gone gets `itemid` 0, not its legacy id (the
  comment in `records_step` that said "nothing new will ever reuse" the id was false: those three features keep their
  ids and reset their sequence to `MAX(id)+1`, so the next new item could get it, and the old request would show its name,
  trip the `submit_path` duplicate guard and, on a cross-tenant approval, enrol the learner in an unrelated item). `0`
  renders as "(deleted item)" (`item_label`). The legacy id stays in `local_request_records.componentid` and, with the
  legacy map, is recoverable. A course keeps its id (core ids are never reused); a certification keeps its legacy id
  (no entity). Warning `item_deleted` unchanged.
- **COMMS-R3, reader fixes unflagged.** The Item header, the route in words, the status badges in All requests, the SLA
  column and real names on path requests ship as bug fixes to existing native screens (each was wrong without any
  import). The `sentientia.request.imported_history` description and the README now say the flag "leaves the imported
  rows out" (it used to say the lists "look exactly as they did before the import", which the fixes make untrue).
  Recorded: reader fixes accepted unflagged 2026-10-07. Visual evidence (desktop and mobile) of My requests, Pending
  approvals and All requests is owed in the UAT pass before any production deploy.
- **COMMS-R4, comments.** BizLMS has no writer for `local_request_comments` (its comments went to
  `block_request_comments`), so the table is expected to be empty (0 rows on April). If it holds rows, preflight blocks
  `needs_owner:request_comments_present=N`, because nobody knows who could see them and `list_mine` shows the folded
  note to the requester. The owner reads the rows and writes `request.comments = fold_reviewed` in the decisions file
  (the importer accepts `fold_into_decision_note`, the signed value, and `fold_reviewed`; the second is the owner's
  acknowledgement and is a re-approval event, so the hash is re-pinned). `request_manager::decide()` on an imported row
  now appends the decider's note below the folded thread (a newline, then the note) and never replaces it; an empty
  note leaves the thread; a native row is unchanged.
- **COMMS-R5, no code.** `approvals_step` folds a DECIDED learning-plan approval into a pending request row of the same
  user and plan and drops the approval's newer state. BizLMS has no insert into `local_learningplan_approval` (only
  updates), April holds 0 rows, and preflight reports the count (`learningplan_approvals`). Stage B trigger: if it is
  above 0, change the rule so a decided approval imports as its own row. The step docblock calls the outcome `fold`
  (`dup_of_request`), not `merge`.
- **COMMS-R6, no code.** Routing can pick a supervisor who lacks `local/sentientia_request:approve`; imported rows have no
  deadline, so they never escalate and stay stuck. Routing stays identical to a native submission. Runbook step, after
  the flag is ON: an L&D admin reviews the imported pending rows in All requests and decides them with `overrideroute`.
  Optional later: a Stage B count of imported pending rows whose approver lacks the capability.
- **COMMS-C1.** `request:orphan_user`, `request:orphan_item` and `request:orphan_request` are NOT pre-accepted (all 0 on
  April). After Stage B the owner adds the ones that occur to the top-level `accepted_reasons` list, with counts.
- **COMMS-C2.** Recommended future flip for Airpay at cutover, none made: `sentientia.request.imported_history` ON, only
  after COMMS-R1 (this change) has landed, because otherwise Approve could enrol and message a suspended or deleted account.

Follow-ups done: **F-77** `verify()` accepts `cancelled` (a requester can cancel an imported pending row after go-live;
`request_manager::cancel()`); **F-78** classroom, program and learningplan are merged, so
`test_the_real_registry_knows_the_three_features_request_depends_on` pins that the real registry resolves them before
`request`. The stand-ins stay for the other tests, because `legacy_schema_fixture` takes ONE fixture XML (F-79) and the
real importers' own legacy tables would need more; a full cross-importer run is a Stage B rehearsal item. **F-80** the dead
`request_manager::get_course_owner_userid()` is gone (approver_routing has the live copy), the CLI scripts
`smoke_request.php` and `seed_qa_pending_request.php` leave `legacy_source` rows alone, the upgrade test puts back the
column it drops (`try/finally`), and a comment with the MySQL zero date (`0000-00-00 00:00:00`) reads as an unknown date.

Tests added (`tests/bizlms_import_test.php`): stale requests history only, the `actionable` variant, itemid 0 for a gone
path, classroom or program, the comments blocker and the unsupported value, `decide()` appending, the note helper, the
zero date, `cancelled` in verify, the real registry. The seed's `request.comments` is `fold_reviewed` because it holds comment rows.

Visual evidence owed: My requests, Pending approvals, All requests (desktop and mobile) with the flag ON.
